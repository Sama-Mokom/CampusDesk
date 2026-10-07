#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

readonly SCRIPT_DIRECTORY="$(
  cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
  pwd -P
)"

# shellcheck source=recovery-common.sh
source "$SCRIPT_DIRECTORY/recovery-common.sh"

capture_log=""
download_directory=""
backup_bucket=""
backup_prefix=""

recovery_artifacts=(
  database.sql.gz
  database-counts.txt
  attachments.tar.gz
  attachment-files.sha256
  attachment-db-paths.txt
  attachment-archive-paths.txt
  release-images.txt
  recovery-metadata.txt
  SHA256SUMS
)

notify_failure() {
  local exit_status="$1"

  if [[ -z "${CAMPUSDESK_BACKUP_ALERT_TOPIC_ARN:-}" ]] ||
     [[ -z "${CAMPUSDESK_AWS_REGION:-}" ]] ||
     ! command -v aws >/dev/null 2>&1
  then
    return 0
  fi

  aws --region "$CAMPUSDESK_AWS_REGION" sns publish \
    --topic-arn "$CAMPUSDESK_BACKUP_ALERT_TOPIC_ARN" \
    --subject "CampusDesk staging backup failed" \
    --message "CampusDesk staging backup failed on $(hostname) with exit status ${exit_status}. Inspect campusdesk-backup.service." \
    >/dev/null ||
    printf 'ERROR: Backup failure notification could not be published.\n' >&2
}

verify_offsite_object_metadata() {
  local object_key="$1"
  local artifact="$2"
  local metadata
  local encryption
  local kms_key_id
  local version_id

  metadata="$(
    aws --region "$CAMPUSDESK_AWS_REGION" s3api head-object \
      --bucket "$backup_bucket" \
      --key "$object_key" \
      --query '[ServerSideEncryption,SSEKMSKeyId,VersionId]' \
      --output text
  )" || recovery_fail "Could not read S3 metadata for $artifact."

  IFS=$'\t' read -r encryption kms_key_id version_id <<< "$metadata"

  [[ "$encryption" == "aws:kms" ]] ||
    recovery_fail "The S3 object does not use SSE-KMS: $artifact"

  [[ "$kms_key_id" == "$CAMPUSDESK_BACKUP_KMS_KEY_ID" ]] ||
    recovery_fail "The S3 object does not use the configured KMS key: $artifact"

  case "${version_id,,}" in
    ""|none|null)
      recovery_fail "The S3 object has no non-null version ID: $artifact"
      ;;
  esac
}

cleanup_backup() {
  local exit_status=$?
  local cleanup_failed=0

  trap - EXIT INT TERM
  set +e

  if [[ -n "$capture_log" ]] && [[ -f "$capture_log" ]]; then
    if ! rm -f -- "$capture_log"; then
      printf 'ERROR: Could not remove the capture log.\n' >&2
      cleanup_failed=1
    fi
  fi

  if [[ -n "$download_directory" ]] && [[ -e "$download_directory" ]]; then
    case "$download_directory" in
      /var/tmp/campusdesk-offsite-verify.*)
        if ! rm -rf -- "$download_directory"; then
          printf 'ERROR: Could not remove the downloaded verification copy.\n' >&2
          cleanup_failed=1
        fi
        ;;
      *)
        printf 'ERROR: Refusing to remove an unexpected verification path.\n' >&2
        exit_status=1
        ;;
    esac
  fi

  if ((cleanup_failed == 1 && exit_status == 0)); then
    exit_status=1
  fi

  if ((exit_status != 0)); then
    notify_failure "$exit_status"
  fi

  exit "$exit_status"
}

prune_local_recovery_sets() {
  local candidate
  local candidate_backup_id
  local marker_backup_id
  local pruned_sets=0

  while IFS= read -r -d '' candidate; do
    candidate_backup_id="${candidate##*/}"

    [[ "$candidate_backup_id" =~ ^[0-9]{8}T[0-9]{6}Z$ ]] ||
      recovery_fail "Refusing to prune a directory with an invalid backup ID."

    case "$candidate" in
      "$RECOVERY_BACKUP_ROOT"/"$candidate_backup_id")
        ;;
      *)
        recovery_fail "Refusing to prune an unexpected recovery-set path."
        ;;
    esac

    [[ -f "$candidate/OFFSITE_VERIFIED" ]] || continue
    [[ ! -L "$candidate/OFFSITE_VERIFIED" ]] || continue

    marker_backup_id="$(
      awk -F= '
        $1 == "backup_id" {
          print $2
        }
      ' "$candidate/OFFSITE_VERIFIED"
    )"

    [[ "$marker_backup_id" == "$candidate_backup_id" ]] ||
      recovery_fail "Refusing to prune a recovery set with a mismatched verification marker."

    rm -rf -- "$candidate"
    pruned_sets=$((pruned_sets + 1))
  done < <(
    find "$RECOVERY_BACKUP_ROOT" \
      -mindepth 1 \
      -maxdepth 1 \
      -type d \
      -name '????????T??????Z' \
      -mtime +"$CAMPUSDESK_LOCAL_RETENTION_DAYS" \
      -print0
  )

  printf 'local_recovery_sets_pruned=%d\n' "$pruned_sets"
}

trap cleanup_backup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

recovery_require_root

for required_command in \
  awk \
  aws \
  cmp \
  date \
  find \
  flock \
  gzip \
  hostname \
  install \
  mktemp \
  mv \
  python3 \
  rm \
  sha256sum \
  stat \
  tar \
  tee
do
  recovery_require_command "$required_command"
done

if (($# != 0)); then
  recovery_fail "Usage: $0"
fi

: "${CAMPUSDESK_BACKUP_S3_URI:?CAMPUSDESK_BACKUP_S3_URI must be set}"
: "${CAMPUSDESK_BACKUP_KMS_KEY_ID:?CAMPUSDESK_BACKUP_KMS_KEY_ID must be set}"
: "${CAMPUSDESK_BACKUP_ALERT_TOPIC_ARN:?CAMPUSDESK_BACKUP_ALERT_TOPIC_ARN must be set}"
: "${CAMPUSDESK_AWS_REGION:?CAMPUSDESK_AWS_REGION must be set}"
: "${CAMPUSDESK_LOCAL_RETENTION_DAYS:?CAMPUSDESK_LOCAL_RETENTION_DAYS must be set}"

[[ "$CAMPUSDESK_BACKUP_S3_URI" =~ ^s3://[a-z0-9][a-z0-9.-]*/[^/[:space:]]+(/[^/[:space:]]+)*$ ]] ||
  recovery_fail "CAMPUSDESK_BACKUP_S3_URI must include a bucket and prefix without a trailing slash."

s3_location="${CAMPUSDESK_BACKUP_S3_URI#s3://}"
backup_bucket="${s3_location%%/*}"
backup_prefix="${s3_location#*/}"

[[ "$CAMPUSDESK_AWS_REGION" =~ ^[a-z]{2}(-[a-z]+)+-[0-9]+$ ]] ||
  recovery_fail "CAMPUSDESK_AWS_REGION is invalid."

[[ "$CAMPUSDESK_LOCAL_RETENTION_DAYS" =~ ^[0-9]+$ ]] ||
  recovery_fail "CAMPUSDESK_LOCAL_RETENTION_DAYS must be numeric."

((CAMPUSDESK_LOCAL_RETENTION_DAYS >= 7)) ||
  recovery_fail "CAMPUSDESK_LOCAL_RETENTION_DAYS must be at least 7."

bucket_versioning_status="$(
  aws --region "$CAMPUSDESK_AWS_REGION" s3api get-bucket-versioning \
    --bucket "$backup_bucket" \
    --query Status \
    --output text
)" || recovery_fail "Could not read the backup bucket versioning status."

[[ "$bucket_versioning_status" == "Enabled" ]] ||
  recovery_fail "The backup bucket must have versioning enabled before capture."

printf 'bucket_versioning=Enabled\n'

capture_log="$(mktemp /var/tmp/campusdesk-capture.XXXXXX)"

if ! "$SCRIPT_DIRECTORY/capture-recovery-set.sh" |
  tee "$capture_log"
then
  recovery_fail "The local recovery capture failed."
fi

backup_id="$(
  awk -F= '
    $1 == "backup_id" {
      value = $2
    }

    END {
      print value
    }
  ' "$capture_log"
)"

backup_directory="$(recovery_backup_directory "$backup_id")"
recovery_validate_backup_set "$backup_directory"

remote_directory="${CAMPUSDESK_BACKUP_S3_URI}/${backup_id}"

aws --region "$CAMPUSDESK_AWS_REGION" s3 cp \
  "$backup_directory/" \
  "$remote_directory/" \
  --recursive \
  --only-show-errors \
  --sse aws:kms \
  --sse-kms-key-id "$CAMPUSDESK_BACKUP_KMS_KEY_ID"

for artifact in "${recovery_artifacts[@]}"; do
  verify_offsite_object_metadata \
    "${backup_prefix}/${backup_id}/${artifact}" \
    "$artifact"
done

printf 'offsite_artifact_metadata=OK\n'

download_directory="$(
  mktemp --directory /var/tmp/campusdesk-offsite-verify.XXXXXX
)"

aws --region "$CAMPUSDESK_AWS_REGION" s3 cp \
  "$remote_directory/" \
  "$download_directory/" \
  --recursive \
  --only-show-errors

recovery_validate_backup_set "$download_directory"

cmp --silent \
  "$backup_directory/SHA256SUMS" \
  "$download_directory/SHA256SUMS" ||
  recovery_fail "The downloaded off-host checksum manifest differs from local."

verification_time="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
verification_epoch="$(date +%s)"

{
  printf 'backup_id=%s\n' "$backup_id"
  printf 'verified_at_utc=%s\n' "$verification_time"
  printf 'verified_at_epoch=%s\n' "$verification_epoch"
} > "$download_directory/OFFSITE_VERIFIED"

aws --region "$CAMPUSDESK_AWS_REGION" s3 cp \
  "$download_directory/OFFSITE_VERIFIED" \
  "$remote_directory/OFFSITE_VERIFIED" \
  --only-show-errors \
  --sse aws:kms \
  --sse-kms-key-id "$CAMPUSDESK_BACKUP_KMS_KEY_ID"

verify_offsite_object_metadata \
  "${backup_prefix}/${backup_id}/OFFSITE_VERIFIED" \
  OFFSITE_VERIFIED

printf 'offsite_marker_metadata=OK\n'

install \
  --owner root \
  --group root \
  --mode 0600 \
  "$download_directory/OFFSITE_VERIFIED" \
  "$backup_directory/OFFSITE_VERIFIED"

install \
  --owner root \
  --group root \
  --mode 0600 \
  "$download_directory/OFFSITE_VERIFIED" \
  /var/backups/campusdesk/.last-offsite-success.new

mv -- \
  /var/backups/campusdesk/.last-offsite-success.new \
  /var/backups/campusdesk/last-offsite-success

rm -f -- /var/backups/campusdesk/backup-alert-active

exec 8>"$RECOVERY_LOCK_FILE"

if flock --nonblock 8; then
  prune_local_recovery_sets
else
  printf 'local_recovery_set_pruning=SKIPPED_LOCKED\n'
fi

printf 'offsite_upload=OK\n'
printf 'offsite_download_validation=OK\n'
printf 'offsite_backup_id=%s\n' "$backup_id"
printf 'offsite_verified_at_utc=%s\n' "$verification_time"
