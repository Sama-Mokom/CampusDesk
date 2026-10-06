#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

readonly SCRIPT_DIRECTORY="$(
  cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
  pwd -P
)"

# shellcheck source=recovery-common.sh
source "$SCRIPT_DIRECTORY/recovery-common.sh"

database_container=""
backend_container=""
database_volume=""
attachment_volume=""
test_network=""
temporary_directory=""

cleanup_restore_test() {
  local exit_status=$?
  local cleanup_failed=0

  trap - EXIT INT TERM
  set +e

  if [[ -n "$backend_container" ]] &&
     ! docker rm --force "$backend_container" >/dev/null 2>&1
  then
    printf 'ERROR: Could not remove the restore-test backend container.\n' >&2
    cleanup_failed=1
  fi

  if [[ -n "$database_container" ]] &&
     ! docker rm --force "$database_container" >/dev/null 2>&1
  then
    printf 'ERROR: Could not remove the restore-test database container.\n' >&2
    cleanup_failed=1
  fi

  if [[ -n "$database_volume" ]] &&
     ! docker volume rm "$database_volume" >/dev/null 2>&1
  then
    printf 'ERROR: Could not remove the restore-test database volume.\n' >&2
    cleanup_failed=1
  fi

  if [[ -n "$attachment_volume" ]] &&
     ! docker volume rm "$attachment_volume" >/dev/null 2>&1
  then
    printf 'ERROR: Could not remove the restore-test attachment volume.\n' >&2
    cleanup_failed=1
  fi

  if [[ -n "$test_network" ]] &&
     ! docker network rm "$test_network" >/dev/null 2>&1
  then
    printf 'ERROR: Could not remove the restore-test network.\n' >&2
    cleanup_failed=1
  fi

  if [[ -n "$temporary_directory" ]] &&
     [[ -e "$temporary_directory" ]]
  then
    case "$temporary_directory" in
      /var/tmp/campusdesk-restore-test.*)
        if ! rm -rf -- "$temporary_directory"; then
          printf 'ERROR: Could not remove the restore-test directory.\n' >&2
          cleanup_failed=1
        fi
        ;;
      *)
        printf 'ERROR: Refusing to remove an unexpected temporary path.\n' >&2
        cleanup_failed=1
        ;;
    esac
  fi

  if ((cleanup_failed == 1 && exit_status == 0)); then
    exit_status=1
  fi

  exit "$exit_status"
}

trap cleanup_restore_test EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

recovery_require_root

for required_command in \
  awk \
  cmp \
  date \
  docker \
  find \
  flock \
  gzip \
  mktemp \
  python3 \
  rm \
  seq \
  sha256sum \
  sleep \
  sort \
  stat \
  tail \
  tar \
  wc
do
  recovery_require_command "$required_command"
done

if (($# > 1)); then
  recovery_fail "Usage: $0 [backup-id]"
fi

exec 9>"$RECOVERY_LOCK_FILE"

if ! flock --nonblock 9; then
  recovery_fail "Another recovery operation is already running."
fi

backup_id="${1:-}"

if [[ -z "$backup_id" ]]; then
  backup_id="$(recovery_latest_backup_id)"
fi

backup_directory="$(recovery_backup_directory "$backup_id")"
recovery_validate_backup_set "$backup_directory"

metadata_backup_id="$(
  recovery_metadata_value "$backup_directory" backup_id
)"

[[ "$metadata_backup_id" == "$backup_id" ]] ||
  recovery_fail "The recovery metadata does not match the directory name."

format_version="$(
  recovery_metadata_value "$backup_directory" format_version
)"

[[ "$format_version" == "2" ]] ||
  recovery_fail "The recovery-set format is not supported by this restore test."

count_names=(
  attachment_rows
  invalid_attachment_paths
  physical_attachment_files
  migrations
  users
  requests
  request_stages
  jobs
  failed_jobs
)
captured_counts=()

for count_name in "${count_names[@]}"; do
  count_value="$(
    recovery_metadata_value "$backup_directory" "$count_name"
  )"

  [[ "$count_value" =~ ^[0-9]+$ ]] ||
    recovery_fail "Recovery metadata contains a non-numeric count: $count_name"

  captured_counts+=("$count_value")
done

attachment_rows="${captured_counts[0]}"
invalid_attachment_paths="${captured_counts[1]}"
physical_attachment_files="${captured_counts[2]}"

backend_image="$(
  recovery_release_image "$backup_directory" backend
)"

mysql_image="$(
  recovery_release_image "$backup_directory" mysql
)"

docker image inspect "$backend_image" >/dev/null ||
  recovery_fail "The captured backend image is unavailable locally."

docker image inspect "$mysql_image" >/dev/null ||
  recovery_fail "The captured MySQL image is unavailable locally."

temporary_directory="$(
  mktemp --directory /var/tmp/campusdesk-restore-test.XXXXXX
)"
chmod 0700 "$temporary_directory"
chown root:root "$temporary_directory"

test_suffix="$(date -u +%Y%m%d%H%M%S)-$$"
database_container_name="campusdesk-restore-db-${test_suffix}"
backend_container_name="campusdesk-restore-backend-${test_suffix}"
test_network_name="campusdesk-restore-${test_suffix}"
restore_password="CampusDesk-restore-test-${test_suffix}"
restore_app_password="CampusDesk-restore-app-${test_suffix}"

test_network="$(
  docker network create --internal "$test_network_name"
)"

database_volume="$(
  docker volume create \
    --label "campusdesk.restore-test=${test_suffix}"
)"

attachment_volume="$(
  docker volume create \
    --label "campusdesk.restore-test=${test_suffix}"
)"

database_container="$(
  docker create \
    --pull never \
    --name "$database_container_name" \
    --network "$test_network" \
    --network-alias db \
    --mount "type=volume,source=${database_volume},target=/var/lib/mysql" \
    --env "MYSQL_ROOT_PASSWORD=${restore_password}" \
    "$mysql_image" \
    --skip-log-bin
)"

docker start "$database_container" >/dev/null

database_ready=0

for _ in $(seq 1 60); do
  if docker exec \
    --env "MYSQL_PWD=${restore_password}" \
    "$database_container" \
    mysqladmin \
      --user=root \
      --silent \
      ping \
      >/dev/null 2>&1
  then
    database_ready=1
    break
  fi

  sleep 2
done

((database_ready == 1)) ||
  recovery_fail "The isolated MySQL container did not become ready."

if ! gzip --decompress --stdout "$backup_directory/database.sql.gz" |
  docker exec \
    --interactive \
    --env "MYSQL_PWD=${restore_password}" \
    "$database_container" \
    mysql \
      --user=root
then
  recovery_fail "The database restore failed."
fi

restored_databases="$(
  docker exec \
    --env "MYSQL_PWD=${restore_password}" \
    "$database_container" \
    mysql \
      --user=root \
      --batch \
      --skip-column-names \
      --execute="
        SELECT SCHEMA_NAME
        FROM information_schema.schemata
        WHERE SCHEMA_NAME NOT IN (
          'information_schema',
          'mysql',
          'performance_schema',
          'sys'
        )
        ORDER BY SCHEMA_NAME;
      "
)"

restored_database_count="$(
  printf '%s\n' "$restored_databases" |
    awk 'NF { count++ } END { print count + 0 }'
)"

[[ "$restored_database_count" == "1" ]] ||
  recovery_fail "The dump did not restore exactly one application database."

restored_database="$restored_databases"

[[ "$restored_database" =~ ^[A-Za-z0-9_]+$ ]] ||
  recovery_fail "The restored database name is unsafe."

database_summary="$(
  docker exec \
    --env "MYSQL_PWD=${restore_password}" \
    "$database_container" \
    mysql \
      --user=root \
      --database="$restored_database" \
      --batch \
      --skip-column-names \
      --execute="
        SELECT
          (SELECT COUNT(*) FROM attachments),
          (
            SELECT COALESCE(
              SUM(
                OCTET_LENGTH(file_path) = 0
                OR file_path = 0x30
              ),
              0
            )
            FROM attachments
          ),
          (SELECT COUNT(*) FROM migrations),
          (SELECT COUNT(*) FROM users),
          (SELECT COUNT(*) FROM requests),
          (SELECT COUNT(*) FROM request_stages),
          (SELECT COUNT(*) FROM jobs),
          (SELECT COUNT(*) FROM failed_jobs);
      "
)"

read -r \
  restored_attachment_rows \
  restored_invalid_paths \
  restored_migrations \
  restored_users \
  restored_requests \
  restored_request_stages \
  restored_jobs \
  restored_failed_jobs \
  <<< "$database_summary"

restored_counts=(
  "$restored_attachment_rows"
  "$restored_invalid_paths"
  "$physical_attachment_files"
  "$restored_migrations"
  "$restored_users"
  "$restored_requests"
  "$restored_request_stages"
  "$restored_jobs"
  "$restored_failed_jobs"
)

for count_index in "${!count_names[@]}"; do
  [[ "${restored_counts[$count_index]}" =~ ^[0-9]+$ ]] ||
    recovery_fail "A restored database count is invalid: ${count_names[$count_index]}"

  [[ "${restored_counts[$count_index]}" == "${captured_counts[$count_index]}" ]] ||
    recovery_fail "A restored count does not match capture metadata: ${count_names[$count_index]}"
done

application_table_count="$(
  docker exec \
    --env "MYSQL_PWD=${restore_password}" \
    "$database_container" \
    mysql \
      --user=root \
      --database="$restored_database" \
      --batch \
      --skip-column-names \
      --execute="
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_type = 'BASE TABLE';
      "
)"

[[ "$application_table_count" =~ ^[0-9]+$ ]] ||
  recovery_fail "The application base-table count is invalid."

((application_table_count > 0)) ||
  recovery_fail "The restored database contains no application base tables."

check_table_statements="$(
  docker exec \
    --env "MYSQL_PWD=${restore_password}" \
    "$database_container" \
    mysql \
      --user=root \
      --database="$restored_database" \
      --batch \
      --skip-column-names \
      --execute="
        SELECT CONCAT(
          'CHECK TABLE \`',
          REPLACE(table_name, '\`', '\`\`'),
          '\`;'
        )
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_type = 'BASE TABLE'
        ORDER BY BINARY table_name;
      "
)"

generated_check_count="$(
  printf '%s\n' "$check_table_statements" |
    awk 'NF { count++ } END { print count + 0 }'
)"

[[ "$generated_check_count" == "$application_table_count" ]] ||
  recovery_fail "A CHECK TABLE statement was not generated for every base table."

check_table_output="$(
  printf '%s\n' "$check_table_statements" |
    docker exec \
      --interactive \
      --env "MYSQL_PWD=${restore_password}" \
      "$database_container" \
      mysql \
        --user=root \
        --database="$restored_database" \
        --batch \
        --skip-column-names
)"

printf '%s\n' "$check_table_output" |
  awk -F '\t' '
    NF < 4 || $3 != "status" || $4 != "OK" {
      failed = 1
    }

    END {
      exit failed
    }
  ' || recovery_fail "SQL CHECK TABLE reported a problem."

successful_check_count="$(
  printf '%s\n' "$check_table_output" |
    awk -F '\t' '
      $3 == "status" && $4 == "OK" {
        count++
      }

      END {
        print count + 0
      }
    '
)"

[[ "$successful_check_count" == "$application_table_count" ]] ||
  recovery_fail "SQL CHECK TABLE did not return one success for every base table."

if ! docker exec \
  --env "MYSQL_PWD=${restore_password}" \
  "$database_container" \
  mysql \
    --user=root \
    --database="$restored_database" \
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
    " |
  recovery_normalize_attachment_paths \
    > "$temporary_directory/restored-db-paths.txt"
then
  recovery_fail "The restored database contains invalid attachment paths."
fi

cmp --silent \
  "$backup_directory/attachment-db-paths.txt" \
  "$temporary_directory/restored-db-paths.txt" ||
  recovery_fail "Restored database attachment paths differ from the capture."

docker run \
  --rm \
  --pull never \
  --network none \
  --read-only \
  --entrypoint /bin/sh \
  --mount "type=volume,source=${attachment_volume},target=/restore" \
  --mount "type=bind,source=${backup_directory},target=/backup,readonly" \
  "$backend_image" \
  -ec '
    set -eu
    cd /restore
    tar --extract --gzip --file=/backup/attachments.tar.gz

    unexpected_entry="$(
      find . \
        -mindepth 1 \
        ! -type d \
        ! -type f \
        -print \
        -quit
    )"

    if [ -n "$unexpected_entry" ]; then
      printf "ERROR: Restored attachment storage contains a non-regular entry.\n" >&2
      exit 1
    fi

    sha256sum --check --status /backup/attachment-files.sha256
  '

docker run \
  --rm \
  --pull never \
  --network none \
  --read-only \
  --entrypoint /bin/sh \
  --mount "type=volume,source=${attachment_volume},target=/restore,readonly" \
  "$backend_image" \
  -ec 'cd /restore; find . -type f -printf "%P\n" | LC_ALL=C sort' \
  > "$temporary_directory/restored-archive-paths.txt"

cmp --silent \
  "$backup_directory/attachment-archive-paths.txt" \
  "$temporary_directory/restored-archive-paths.txt" ||
  recovery_fail "Restored attachment paths differ from the captured archive."

cmp --silent \
  "$temporary_directory/restored-db-paths.txt" \
  "$temporary_directory/restored-archive-paths.txt" ||
  recovery_fail "Restored database and physical attachment paths do not match."

restored_attachment_files="$(
  awk 'END { print NR }' \
    "$temporary_directory/restored-archive-paths.txt"
)"

[[ "$restored_attachment_files" == "$physical_attachment_files" ]] ||
  recovery_fail "The restored attachment-file count does not match metadata."

docker exec \
  --env "MYSQL_PWD=${restore_password}" \
  "$database_container" \
  mysql \
    --user=root \
    --database="$restored_database" \
    --execute="
      CREATE USER 'campusdesk_restore'@'%'
        IDENTIFIED BY '${restore_app_password}';
      GRANT ALL PRIVILEGES
        ON \`${restored_database}\`.*
        TO 'campusdesk_restore'@'%';
      FLUSH PRIVILEGES;
    "

{
  printf 'APP_NAME=CampusDesk Restore Test\n'
  printf 'APP_ENV=testing\n'
  printf 'APP_KEY=base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=\n'
  printf 'APP_DEBUG=false\n'
  printf 'APP_URL=http://127.0.0.1\n'
  printf 'FRONTEND_URL=http://127.0.0.1\n'
  printf 'LOG_CHANNEL=stderr\n'
  printf 'LOG_LEVEL=warning\n'
  printf 'DB_CONNECTION=mysql\n'
  printf 'DB_HOST=db\n'
  printf 'DB_PORT=3306\n'
  printf 'DB_DATABASE=%s\n' "$restored_database"
  printf 'DB_USERNAME=campusdesk_restore\n'
  printf 'DB_PASSWORD=%s\n' "$restore_app_password"
  printf 'SESSION_DRIVER=database\n'
  printf 'CACHE_STORE=database\n'
  printf 'QUEUE_CONNECTION=database\n'
  printf 'FILESYSTEM_DISK=local\n'
  printf 'MAIL_MAILER=log\n'
  printf 'MAIL_FROM_ADDRESS=restore-test@example.invalid\n'
  printf 'MAIL_FROM_NAME=CampusDesk Restore Test\n'
} > "$temporary_directory/backend.env"

chmod 0600 "$temporary_directory/backend.env"
chown root:root "$temporary_directory/backend.env"

backend_container="$(
  docker create \
    --pull never \
    --name "$backend_container_name" \
    --network "$test_network" \
    --network-alias backend \
    --env-file "$temporary_directory/backend.env" \
    --mount "type=volume,source=${attachment_volume},target=/var/www/html/storage/app/private/attachments" \
    "$backend_image"
)"

docker start "$backend_container" >/dev/null

backend_ready=0

for _ in $(seq 1 60); do
  if docker exec "$backend_container" php -r \
    "exit(@file_get_contents('http://127.0.0.1/up') === false ? 1 : 0);" \
    >/dev/null 2>&1
  then
    backend_ready=1
    break
  fi

  sleep 2
done

((backend_ready == 1)) ||
  recovery_fail "The backend did not become healthy against the restored data."

docker exec "$backend_container" \
  php artisan migrate:status --no-interaction \
  >/dev/null

printf 'database_restore=OK\n'
printf 'database_counts=OK\n'
printf 'database_check_tables=OK\n'
printf 'attachment_path_comparison=OK\n'
printf 'backend_start=OK\n'
printf 'restore_test=OK\n'
printf 'backup_id=%s\n' "$backup_id"
printf 'restored_database=%s\n' "$restored_database"
printf 'attachment_rows=%s\n' "$restored_attachment_rows"
printf 'invalid_attachment_paths=%s\n' "$restored_invalid_paths"
printf 'physical_attachment_files=%s\n' "$restored_attachment_files"
