#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

readonly SCRIPT_DIRECTORY="$(
  cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
  pwd -P
)"

# shellcheck source=recovery-common.sh
source "$SCRIPT_DIRECTORY/recovery-common.sh"

readonly ENV_FILE="/opt/campusdesk/.env.staging"
readonly COMPOSE_FILE="/opt/campusdesk/compose.staging.yaml"
readonly BACKUP_ROOT="/var/backups/campusdesk/backup-sets"
readonly LOCK_FILE="/run/lock/campusdesk-recovery.lock"
readonly ATTACHMENT_VOLUME="campusdesk-staging_attachments_data"
readonly MINIMUM_FREE_KB=1048576
readonly APPLICATION_WAIT_SECONDS=180

maintenance_started=0
backup_id=""
backup_dir=""
temporary_dir=""
maintenance_start_epoch=0
maintenance_start_utc=""
maintenance_end_utc=""
maintenance_duration_seconds=0

compose() {
  docker compose \
    --env-file "$ENV_FILE" \
    --file "$COMPOSE_FILE" \
    "$@"
}

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 ||
    fail "Required command is unavailable: $1"
}

service_id() {
  local service="$1"
  local id

  id="$(compose ps -q "$service" 2>/dev/null || true)"

  if [[ -z "$id" ]]; then
    return 1
  fi

  printf '%s\n' "$id"
}

container_is_running() {
  local container_id="$1"

  [[ "$(
    docker inspect \
      --format '{{.State.Running}}' \
      "$container_id"
  )" == "true" ]]
}

container_health() {
  local container_id="$1"

  docker inspect \
    --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' \
    "$container_id"
}

validated_container_release_image() {
  local component="$1"
  local container_id="$2"
  local image_reference
  local container_image_id
  local resolved_image_id

  image_reference="$(
    docker inspect \
      --format '{{.Config.Image}}' \
      "$container_id"
  )" || fail "Could not read the $component container image reference."

  recovery_validate_release_image_reference \
    "$image_reference" \
    "$component"

  container_image_id="$(
    docker inspect \
      --format '{{.Image}}' \
      "$container_id"
  )" || fail "Could not read the $component container image ID."

  [[ "$container_image_id" =~ ^sha256:[0-9a-f]{64}$ ]] ||
    fail "The $component container image ID is invalid."

  resolved_image_id="$(
    docker image inspect \
      --format '{{.Id}}' \
      "$image_reference"
  )" || fail "The digest-qualified $component image is unavailable locally."

  [[ "$resolved_image_id" =~ ^sha256:[0-9a-f]{64}$ ]] ||
    fail "The locally resolved $component image ID is invalid."

  [[ "$resolved_image_id" == "$container_image_id" ]] ||
    fail "The digest-qualified $component image does not match the running container image ID."

  printf '%s\n' "$image_reference"
}

wait_for_application() {
  local deadline
  local db_id
  local backend_id
  local worker_id
  local frontend_id

  deadline=$((SECONDS + APPLICATION_WAIT_SECONDS))

  while ((SECONDS < deadline)); do
    db_id="$(service_id db 2>/dev/null || true)"
    backend_id="$(service_id backend 2>/dev/null || true)"
    worker_id="$(service_id worker 2>/dev/null || true)"
    frontend_id="$(service_id frontend 2>/dev/null || true)"

    if [[ -n "$db_id" ]] &&
       [[ -n "$backend_id" ]] &&
       [[ -n "$worker_id" ]] &&
       [[ -n "$frontend_id" ]] &&
       container_is_running "$db_id" &&
       container_is_running "$backend_id" &&
       container_is_running "$worker_id" &&
       container_is_running "$frontend_id" &&
       [[ "$(container_health "$db_id")" == "healthy" ]] &&
       [[ "$(container_health "$backend_id")" == "healthy" ]]
    then
      return 0
    fi

    sleep 5
  done

  return 1
}

restart_application() {
  if ((maintenance_started == 0)); then
    return 0
  fi

  printf 'application_restart=STARTED\n'

  if ! compose up \
    --detach \
    attachments-init \
    backend \
    worker \
    frontend \
    >/dev/null
  then
    printf 'application_restart=FAILED\n' >&2
    return 1
  fi

  if ! wait_for_application; then
    printf 'application_health_after_restart=FAILED\n' >&2
    return 1
  fi

  maintenance_started=0

  printf 'application_restart=OK\n'
  printf 'application_health_after_restart=OK\n'
}

cleanup_temporary_directory() {
  if [[ -z "$temporary_dir" ]] || [[ ! -e "$temporary_dir" ]]; then
    return 0
  fi

  case "$temporary_dir" in
    "$BACKUP_ROOT"/.tmp."$backup_id".*)
      rm -rf -- "$temporary_dir"
      ;;
    *)
      printf 'ERROR: Refusing to remove unexpected temporary path.\n' >&2
      return 1
      ;;
  esac
}

on_exit() {
  local exit_status=$?

  trap - EXIT INT TERM
  set +e

  if ((maintenance_started == 1)); then
    if ! restart_application; then
      exit_status=1
    fi
  fi

  if ! cleanup_temporary_directory; then
    exit_status=1
  fi

  exit "$exit_status"
}

trap on_exit EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if ((EUID != 0)); then
  fail "Run this script as root."
fi

for required_command in \
  awk \
  cmp \
  date \
  df \
  docker \
  find \
  flock \
  gzip \
  install \
  mktemp \
  mv \
  python3 \
  rm \
  sha256sum \
  stat \
  tar \
  wc
do
  require_command "$required_command"
done

[[ -r "$ENV_FILE" ]] ||
  fail "The staging environment file is unavailable."

[[ -r "$COMPOSE_FILE" ]] ||
  fail "The staging Compose file is unavailable."

exec 9>"$LOCK_FILE"

if ! flock --nonblock 9; then
  fail "Another recovery capture is already running."
fi

install \
  --directory \
  --owner root \
  --group root \
  --mode 0700 \
  "$BACKUP_ROOT"

compose config --quiet
printf 'compose_config=OK\n'

db_id="$(service_id db)" ||
  fail "The live database container is not running."

backend_id="$(service_id backend)" ||
  fail "The live backend container is not running."

worker_id="$(service_id worker)" ||
  fail "The live worker container is not running."

frontend_id="$(service_id frontend)" ||
  fail "The live frontend container is not running."

for running_container in \
  "$db_id" \
  "$backend_id" \
  "$worker_id" \
  "$frontend_id"
do
  container_is_running "$running_container" ||
    fail "A required live container is not running."
done

[[ "$(container_health "$db_id")" == "healthy" ]] ||
  fail "The live database is not healthy."

[[ "$(container_health "$backend_id")" == "healthy" ]] ||
  fail "The live backend is not healthy."

docker volume inspect "$ATTACHMENT_VOLUME" >/dev/null

mysql_image="$(
  validated_container_release_image mysql "$db_id"
)"

backend_image="$(
  validated_container_release_image backend "$backend_id"
)"

worker_image="$(
  validated_container_release_image worker "$worker_id"
)"

frontend_image="$(
  validated_container_release_image frontend "$frontend_id"
)"

[[ "$worker_image" == "$backend_image" ]] ||
  fail "The worker and backend containers do not use the same digest-qualified image reference."

printf 'release_image_validation=OK\n'

compose exec -T backend sh -ec '
  for required_tool in tar find sort xargs sha256sum; do
    command -v "$required_tool" >/dev/null
  done
'

printf 'container_tools=OK\n'

available_kb="$(
  df -Pk "$BACKUP_ROOT" |
    awk 'NR == 2 { print $4 }'
)"

[[ "$available_kb" =~ ^[0-9]+$ ]] ||
  fail "Could not determine available disk space."

if ((available_kb < MINIMUM_FREE_KB)); then
  fail "Less than 1 GiB is available for the recovery capture."
fi

printf 'available_disk_mib=%d\n' "$((available_kb / 1024))"

backup_id="$(date -u +%Y%m%dT%H%M%SZ)"
backup_dir="${BACKUP_ROOT}/${backup_id}"

[[ ! -e "$backup_dir" ]] ||
  fail "The generated backup directory already exists."

temporary_dir="$(
  mktemp \
    --directory \
    "${BACKUP_ROOT}/.tmp.${backup_id}.XXXXXX"
)"

chmod 0700 "$temporary_dir"
chown root:root "$temporary_dir"

{
  printf 'backend=%s\n' "$backend_image"
  printf 'frontend=%s\n' "$frontend_image"
  printf 'mysql=%s\n' "$mysql_image"
} > "$temporary_dir/release-images.txt"

maintenance_start_epoch="$(date +%s)"
maintenance_start_utc="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
maintenance_started=1

printf 'maintenance_started_utc=%s\n' "$maintenance_start_utc"

compose stop frontend >/dev/null
printf 'frontend_stop=OK\n'

compose stop worker >/dev/null
printf 'worker_stop=OK\n'

compose stop backend >/dev/null
printf 'backend_stop=OK\n'

db_id="$(service_id db)" ||
  fail "The database stopped during the maintenance window."

container_is_running "$db_id" ||
  fail "The database stopped during the maintenance window."

[[ "$(container_health "$db_id")" == "healthy" ]] ||
  fail "The database became unhealthy during the maintenance window."

printf 'database_during_maintenance=healthy\n'

if ! compose exec -T db sh -ec '
  export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"

  exec mysqldump \
    --user=root \
    --single-transaction \
    --quick \
    --routines \
    --events \
    --triggers \
    --hex-blob \
    --no-tablespaces \
    --set-gtid-purged=OFF \
    --default-character-set=utf8mb4 \
    --databases "$MYSQL_DATABASE"
' | gzip -9 > "$temporary_dir/database.sql.gz"
then
  fail "The MySQL dump failed."
fi

printf 'database_dump=OK\n'

compose exec -T db sh -ec '
  export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"

  exec mysql \
    --user=root \
    --database="$MYSQL_DATABASE" \
    --batch \
    --table \
    --execute="
      SELECT VERSION() AS mysql_version;

      SELECT COUNT(*) AS non_innodb_tables
      FROM information_schema.tables
      WHERE table_schema = DATABASE()
        AND engine IS NOT NULL
        AND engine <> 0x496E6E6F4442;

      SELECT COUNT(*) AS migrations FROM migrations;
      SELECT COUNT(*) AS users FROM users;
      SELECT COUNT(*) AS requests FROM requests;
      SELECT COUNT(*) AS request_stages FROM request_stages;
      SELECT COUNT(*) AS attachments FROM attachments;

      SELECT
        COALESCE(
          SUM(
            OCTET_LENGTH(file_path) = 0
            OR file_path = 0x30
          ),
          0
        ) AS invalid_attachment_paths
      FROM attachments;

      SELECT COUNT(*) AS jobs FROM jobs;
      SELECT COUNT(*) AS failed_jobs FROM failed_jobs;
    "
' > "$temporary_dir/database-counts.txt"

attachment_summary="$(
  compose exec -T db sh -ec '
    export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"

    exec mysql \
      --user=root \
      --database="$MYSQL_DATABASE" \
      --batch \
      --skip-column-names \
      --execute="
        SELECT
          COUNT(*),
          COALESCE(
            SUM(
              OCTET_LENGTH(file_path) = 0
              OR file_path = 0x30
            ),
            0
          )
        FROM attachments;
      "
  '
)"

read -r attachment_rows invalid_attachment_paths \
  <<< "$attachment_summary"

database_summary="$(
  compose exec -T db sh -ec '
    export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"

    exec mysql \
      --user=root \
      --database="$MYSQL_DATABASE" \
      --batch \
      --skip-column-names \
      --execute="
        SELECT
          (SELECT COUNT(*) FROM migrations),
          (SELECT COUNT(*) FROM users),
          (SELECT COUNT(*) FROM requests),
          (SELECT COUNT(*) FROM request_stages),
          (SELECT COUNT(*) FROM jobs),
          (SELECT COUNT(*) FROM failed_jobs);
      "
  '
)"

read -r migrations users requests request_stages jobs failed_jobs \
  <<< "$database_summary"

[[ "$attachment_rows" =~ ^[0-9]+$ ]] ||
  fail "The attachment-row count is invalid."

[[ "$invalid_attachment_paths" =~ ^[0-9]+$ ]] ||
  fail "The invalid attachment-path count is invalid."

for database_count in \
  "$migrations" \
  "$users" \
  "$requests" \
  "$request_stages" \
  "$jobs" \
  "$failed_jobs"
do
  [[ "$database_count" =~ ^[0-9]+$ ]] ||
    fail "A captured database count is invalid."
done

valid_attachment_paths="$((
  attachment_rows - invalid_attachment_paths
))"

if ((valid_attachment_paths < 0)); then
  fail "The valid attachment-path count is invalid."
fi

if ! compose exec -T db sh -ec '
  export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"

  exec mysql \
    --user=root \
    --database="$MYSQL_DATABASE" \
    --batch \
    --skip-column-names \
    --raw \
    --execute="
      SELECT file_path
      FROM attachments
      WHERE NOT (
        OCTET_LENGTH(file_path) = 0
        OR file_path = 0x30
      )
      ORDER BY BINARY file_path;
    "
' | recovery_normalize_attachment_paths \
  > "$temporary_dir/attachment-db-paths.txt"
then
  fail "Attachment database paths are invalid."
fi

database_attachment_paths="$(
  awk 'END { print NR }' \
    "$temporary_dir/attachment-db-paths.txt"
)"

[[ "$database_attachment_paths" =~ ^[0-9]+$ ]] ||
  fail "The normalized database attachment-path count is invalid."

if ((database_attachment_paths != valid_attachment_paths)); then
  fail "The normalized attachment-path count does not match database metadata."
fi

docker run \
  --rm \
  --network none \
  --read-only \
  --entrypoint /bin/sh \
  --mount "type=volume,source=${ATTACHMENT_VOLUME},target=/source,readonly" \
  --mount "type=bind,source=${temporary_dir},target=/backup" \
  "$backend_image" \
  -ec '
    set -eu
    umask 077
    cd /source

    unexpected_entry="$(
      find . \
        -mindepth 1 \
        ! -type d \
        ! -type f \
        -print \
        -quit
    )"

    if [ -n "$unexpected_entry" ]; then
      printf "ERROR: Attachment storage contains a non-regular entry.\n" >&2
      exit 1
    fi

    find . -type f -printf "%P\n" |
      LC_ALL=C sort \
      > /backup/attachment-archive-paths.txt

    find . -type f -print0 |
      sort -z |
      xargs -0 -r sha256sum \
      > /backup/attachment-files.sha256

    sha256sum \
      --check \
      --status \
      /backup/attachment-files.sha256

    tar \
      --format=pax \
      --create \
      --gzip \
      --file=/backup/attachments.tar.gz \
      .
  '

printf 'attachment_archive=OK\n'

physical_attachment_files="$(
  awk 'END { print NR }' \
    "$temporary_dir/attachment-archive-paths.txt"
)"

[[ "$physical_attachment_files" =~ ^[0-9]+$ ]] ||
  fail "The physical attachment-file count is invalid."

cmp --silent \
  "$temporary_dir/attachment-db-paths.txt" \
  "$temporary_dir/attachment-archive-paths.txt" ||
  fail "Attachment database and archive paths do not match exactly."

printf 'attachment_metadata_comparison=OK\n'

if ! restart_application; then
  fail "The application could not be restarted."
fi

maintenance_end_epoch="$(date +%s)"
maintenance_end_utc="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
maintenance_duration_seconds="$((
  maintenance_end_epoch - maintenance_start_epoch
))"

gzip --test "$temporary_dir/database.sql.gz"
gzip --test "$temporary_dir/attachments.tar.gz"

recovery_validate_attachment_archive_members \
  "$temporary_dir/attachments.tar.gz"

printf 'archive_validation=OK\n'

{
  printf 'format_version=2\n'
  printf 'backup_id=%s\n' "$backup_id"
  printf 'created_at_utc=%s\n' "$maintenance_end_utc"
  printf 'maintenance_started_utc=%s\n' "$maintenance_start_utc"
  printf 'maintenance_ended_utc=%s\n' "$maintenance_end_utc"
  printf 'maintenance_duration_seconds=%d\n' \
    "$maintenance_duration_seconds"
  printf 'attachment_rows=%d\n' "$attachment_rows"
  printf 'valid_attachment_paths=%d\n' "$valid_attachment_paths"
  printf 'invalid_attachment_paths=%d\n' "$invalid_attachment_paths"
  printf 'physical_attachment_files=%d\n' \
    "$physical_attachment_files"
  printf 'migrations=%d\n' "$migrations"
  printf 'users=%d\n' "$users"
  printf 'requests=%d\n' "$requests"
  printf 'request_stages=%d\n' "$request_stages"
  printf 'jobs=%d\n' "$jobs"
  printf 'failed_jobs=%d\n' "$failed_jobs"
} > "$temporary_dir/recovery-metadata.txt"

while IFS= read -r -d '' artifact; do
  chown root:root "$artifact"
  chmod 0600 "$artifact"
done < <(
  find \
    "$temporary_dir" \
    -maxdepth 1 \
    -type f \
    -print0
)

(
  cd "$temporary_dir"

  sha256sum \
    database.sql.gz \
    database-counts.txt \
    attachments.tar.gz \
    attachment-files.sha256 \
    attachment-db-paths.txt \
    attachment-archive-paths.txt \
    release-images.txt \
    recovery-metadata.txt \
    > SHA256SUMS

  chown root:root SHA256SUMS
  chmod 0600 SHA256SUMS

  sha256sum \
    --check \
    --status \
    SHA256SUMS
)

for expected_artifact in \
  database.sql.gz \
  database-counts.txt \
  attachments.tar.gz \
  attachment-files.sha256 \
  attachment-db-paths.txt \
  attachment-archive-paths.txt \
  release-images.txt \
  recovery-metadata.txt \
  SHA256SUMS
do
  artifact_path="${temporary_dir}/${expected_artifact}"

  [[ -f "$artifact_path" ]] ||
    fail "A required recovery artifact is missing."

  [[ "$(stat --format '%U:%G' "$artifact_path")" == "root:root" ]] ||
    fail "A recovery artifact has incorrect ownership."

  [[ "$(stat --format '%a' "$artifact_path")" == "600" ]] ||
    fail "A recovery artifact has incorrect permissions."
done

mv -- "$temporary_dir" "$backup_dir"
temporary_dir=""

printf 'capture_validation=OK\n'
printf 'backup_id=%s\n' "$backup_id"
printf 'maintenance_started_utc=%s\n' "$maintenance_start_utc"
printf 'maintenance_ended_utc=%s\n' "$maintenance_end_utc"
printf 'maintenance_duration_seconds=%d\n' \
  "$maintenance_duration_seconds"
printf 'attachment_rows=%d\n' "$attachment_rows"
printf 'valid_attachment_paths=%d\n' "$valid_attachment_paths"
printf 'invalid_attachment_paths=%d\n' "$invalid_attachment_paths"
printf 'physical_attachment_files=%d\n' \
  "$physical_attachment_files"

printf 'database_archive_bytes=%s\n' "$(
  stat \
    --format '%s' \
    "$backup_dir/database.sql.gz"
)"

printf 'attachment_archive_bytes=%s\n' "$(
  stat \
    --format '%s' \
    "$backup_dir/attachments.tar.gz"
)"

db_id="$(service_id db)" ||
  fail "The database is missing after capture."

backend_id="$(service_id backend)" ||
  fail "The backend is missing after capture."

worker_id="$(service_id worker)" ||
  fail "The worker is missing after capture."

frontend_id="$(service_id frontend)" ||
  fail "The frontend is missing after capture."

printf 'database_health=%s\n' "$(container_health "$db_id")"
printf 'backend_health=%s\n' "$(container_health "$backend_id")"

if container_is_running "$worker_id"; then
  printf 'worker_state=running\n'
else
  fail "The worker is not running after capture."
fi

if container_is_running "$frontend_id"; then
  printf 'frontend_state=running\n'
else
  fail "The frontend is not running after capture."
fi

printf 'coherent_recovery_capture=OK\n'
