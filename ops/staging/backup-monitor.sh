#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

readonly SUCCESS_FILE="/var/backups/campusdesk/last-offsite-success"
readonly ALERT_FILE="/var/backups/campusdesk/backup-alert-active"
readonly MAXIMUM_AGE_SECONDS=45000

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

for required_command in awk aws date hostname install rm; do
  command -v "$required_command" >/dev/null 2>&1 ||
    fail "Required command is unavailable: $required_command"
done

((EUID == 0)) || fail "Run this script as root."

: "${CAMPUSDESK_BACKUP_ALERT_TOPIC_ARN:?CAMPUSDESK_BACKUP_ALERT_TOPIC_ARN must be set}"
: "${CAMPUSDESK_AWS_REGION:?CAMPUSDESK_AWS_REGION must be set}"

[[ "$CAMPUSDESK_AWS_REGION" =~ ^[a-z]{2}(-[a-z]+)+-[0-9]+$ ]] ||
  fail "CAMPUSDESK_AWS_REGION is invalid."

current_epoch="$(date +%s)"
verified_epoch=""

if [[ -f "$SUCCESS_FILE" ]] && [[ ! -L "$SUCCESS_FILE" ]]; then
  verified_epoch="$(
    awk -F= '
      $1 == "verified_at_epoch" {
        print $2
      }
    ' "$SUCCESS_FILE"
  )"
fi

backup_is_stale=1

if [[ "$verified_epoch" =~ ^[0-9]+$ ]] &&
   ((current_epoch >= verified_epoch)) &&
   ((current_epoch - verified_epoch <= MAXIMUM_AGE_SECONDS))
then
  backup_is_stale=0
fi

if ((backup_is_stale == 0)); then
  aws --region "$CAMPUSDESK_AWS_REGION" cloudwatch put-metric-data \
    --namespace CampusDesk/Backup \
    --metric-data 'MetricName=OffsiteBackupFresh,Dimensions=[{Name=Environment,Value=staging}],Value=1,Unit=Count'

  rm -f -- "$ALERT_FILE"
  printf 'offsite_backup_freshness=OK\n'
  printf 'aws_backup_heartbeat=OK\n'
  exit 0
fi

if [[ ! -e "$ALERT_FILE" ]]; then
  aws --region "$CAMPUSDESK_AWS_REGION" sns publish \
    --topic-arn "$CAMPUSDESK_BACKUP_ALERT_TOPIC_ARN" \
    --subject "CampusDesk staging backup is stale" \
    --message "CampusDesk staging has no download-verified off-host backup newer than 12.5 hours on $(hostname)." \
    >/dev/null || fail "The stale-backup notification could not be published."

  install \
    --owner root \
    --group root \
    --mode 0600 \
    /dev/null \
    "$ALERT_FILE"
fi

fail "The latest download-verified off-host backup is stale or missing."
