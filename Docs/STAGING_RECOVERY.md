# Staging recovery

**Last reviewed:** 7 October 2026

**Verified state:** CloudFormation stack `campusdesk-staging-recovery` is complete; manual capture `20261006T122804Z`, isolated restore, scheduled monitor execution, and unattended timer capture `20261007T000005Z` passed. This runbook remains the operating procedure; live restoration is incident-controlled and destructive switching has not been performed.

CampusDesk staging recovery sets contain a transactionally consistent MySQL
dump, the complete private-attachment volume, checksums, record counts, and the
exact container image references in use when the set was captured. The capture
briefly stops the frontend, queue worker, and backend while leaving MySQL
running. It restarts the application and verifies health before publishing the
new set atomically.

The scripts never read secrets from this repository. On the staging host they
use the root-readable `/opt/campusdesk/.env.staging` file and the fixed
`/opt/campusdesk/compose.staging.yaml` file. Recovery sets are stored under
`/var/backups/campusdesk/backup-sets`, owned by root and not intended for web or
application access. Before capture, the backup requires the S3 bucket versioning
status to be `Enabled`. A scheduled backup is successful only after the complete
set has been uploaded to S3 with the configured SSE-KMS key, every object has a
non-null version ID, and the set has been downloaded again and checksum-validated.

## Recovery-set contents

Each timestamped directory contains:

- `database.sql.gz`: logical MySQL dump, including routines, events, and triggers;
- `database-counts.txt`: capture-time database version and important row counts;
- `attachments.tar.gz`: private attachment-volume archive;
- `attachment-files.sha256`: per-file attachment checksums;
- `attachment-db-paths.txt`: normalized attachment paths read from MySQL;
- `attachment-archive-paths.txt`: normalized paths read from the volume;
- `release-images.txt`: backend, frontend, and MySQL image references;
- `recovery-metadata.txt`: capture timestamps, maintenance duration, and counts;
- `SHA256SUMS`: checksums for every artifact above.

A recovery set is published only after the application has restarted, both
archives have been checked, the sorted database and physical path inventories
match byte-for-byte, all artifact checksums pass, and the attachment tree and
archive contain only directories and regular files. Symlinks, devices, FIFOs,
sockets, and other special entries abort capture and restore testing.

## AWS recovery infrastructure

The reviewed CloudFormation template at
`ops/staging/aws/backup-infrastructure.yaml` creates the KMS key, versioned and
private S3 bucket, encrypted SNS topic, least-privilege inline policy for the
existing staging instance role, and AWS-side CloudWatch heartbeat alarm. Validate
and deploy it before installing the host timers:

```bash
release_root=/tmp/campusdesk-recovery-release

aws cloudformation validate-template \
  --template-body \
  "file://$release_root/ops/staging/aws/backup-infrastructure.yaml"

aws cloudformation deploy \
  --template-file \
  "$release_root/ops/staging/aws/backup-infrastructure.yaml" \
  --stack-name campusdesk-staging-recovery \
  --capabilities CAPABILITY_NAMED_IAM \
  --parameter-overrides \
    StagingInstanceRoleName=REPLACE_WITH_STAGING_INSTANCE_ROLE_NAME \
    AlertEmail=REPLACE_WITH_ALERT_EMAIL

aws cloudformation describe-stacks \
  --stack-name campusdesk-staging-recovery \
  --query 'Stacks[0].Outputs'
```

The template intentionally creates a new CloudFormation-managed recovery bucket.
During Milestone 1, leave the manually created recovery bucket and all of its
objects untouched. Point the automated backup configuration only at the new
stack output. Decide whether to migrate, retain, archive, or remove the original
bucket only after a complete automated restore proof succeeds from the managed
bucket.

Confirm the optional email subscription before relying on it. Copy the four
stack outputs into `/etc/campusdesk/backup.env`; the S3 URI ends in
`staging/backup-sets`, matching the resource-scoped IAM policy. The instance role
can list only that prefix, read and write its objects, abort failed multipart
uploads within that prefix, and read the bucket versioning status through a
separate bucket-scoped permission without a prefix condition. It can use only the
backup KMS key, publish only to the backup SNS topic, and publish metrics only in
the `CampusDesk/Backup` namespace. It is deliberately not granted
`s3:DeleteObject`, bucket administration, KMS administration, SNS administration,
or CloudWatch alarm administration.

## Host installation

Review the scripts and units before installing them. Stage a reviewed release in
a separate source directory; never use `/opt/campusdesk` as both the source and
destination. The example below assumes `/tmp/campusdesk-recovery-release`:

```bash
release_root=/tmp/campusdesk-recovery-release

sudo install -o root -g root -m 0700 -d \
  /var/backups/campusdesk/backup-sets \
  /etc/campusdesk

sudo install -o root -g root -m 0750 -d \
  /opt/campusdesk/ops/staging \
  /opt/campusdesk/Docs

sudo install -o root -g root -m 0750 \
  "$release_root/ops/staging/backup.sh" \
  "$release_root/ops/staging/backup-monitor.sh" \
  "$release_root/ops/staging/capture-recovery-set.sh" \
  "$release_root/ops/staging/recovery-common.sh" \
  "$release_root/ops/staging/restore-test.sh" \
  /opt/campusdesk/ops/staging/

sudo install -o root -g root -m 0644 \
  "$release_root/Docs/STAGING_RECOVERY.md" \
  /opt/campusdesk/Docs/STAGING_RECOVERY.md

sudo install -o root -g root -m 0644 \
  "$release_root/ops/staging/systemd/campusdesk-backup.service" \
  "$release_root/ops/staging/systemd/campusdesk-backup.timer" \
  "$release_root/ops/staging/systemd/campusdesk-backup-monitor.service" \
  "$release_root/ops/staging/systemd/campusdesk-backup-monitor.timer" \
  /etc/systemd/system/

sudo install -o root -g root -m 0600 \
  "$release_root/ops/staging/backup.env.example" \
  /etc/campusdesk/backup.env

sudoedit /etc/campusdesk/backup.env

sudo systemctl daemon-reload
sudo systemctl start campusdesk-backup.service
sudo systemctl enable --now \
  campusdesk-backup.timer \
  campusdesk-backup-monitor.timer
```

Set the S3 prefix, KMS key ARN, SNS topic ARN, AWS region, and local retention in
the root-only environment file. They are infrastructure identifiers, not
application credentials. The supplied template enables S3 versioning and
SSE-KMS and creates the resource-scoped permissions described above. An approved
S3 retention or object-lock policy remains a separate operational decision.

The backup timer runs at 00:00 and 12:00 UTC, corresponding to 01:00 and 13:00 in
Africa/Douala. This provides the accepted 12-hour RPO without a random delay. A
persistent timer runs a missed capture after the host next starts. The capture
and restore test share a non-blocking lock, so they cannot overlap. The backup
service is bounded to 45 minutes and allows another five minutes for its signal
and restart traps to finish.

The monitor runs every 15 minutes. While the latest download-verified off-host
backup is fresh, it publishes a `CampusDesk/Backup` heartbeat. The AWS-side alarm
treats two missing periods as breaching and notifies SNS even if EC2 has stopped
or lost networking. The host also attempts an immediate SNS alert when no fully
verified backup is newer than 12.5 hours and clears its local alert state after
the next successful backup.

After upload, `backup.sh` calls `head-object` for every recovery artifact and
requires `ServerSideEncryption=aws:kms`, an `SSEKMSKeyId` exactly matching
`CAMPUSDESK_BACKUP_KMS_KEY_ID`, and a present, non-null `VersionId`. It repeats
the same check after uploading `OFFSITE_VERIFIED`. Only then does it publish the
root-only local marker and atomically update `last-offsite-success`.

Local retention defaults to 14 days and only removes timestamped sets older than
the configured minimum that contain a matching verification marker. Unverified
sets are never pruned, and S3 objects are never deleted by the instance role.

Check the installed schedule and unit hardening:

```bash
systemctl list-timers \
  campusdesk-backup.timer \
  campusdesk-backup-monitor.timer
systemd-analyze security campusdesk-backup.service
```

## Manual capture

Schedule a maintenance window before running a capture because application
services are stopped briefly:

```bash
sudo systemctl start campusdesk-backup.service
sudo journalctl -u campusdesk-backup.service -n 200 --no-pager
```

The service must emit `bucket_versioning=Enabled`,
`coherent_recovery_capture=OK`, `offsite_artifact_metadata=OK`,
`offsite_marker_metadata=OK`, `offsite_upload=OK`, and
`offsite_download_validation=OK`. Confirm the application health and save the
emitted backup ID with the change or operations record:

```bash
sudo systemctl status campusdesk-backup.service
sudo journalctl -u campusdesk-backup.service --since today
sudo docker compose \
  --env-file /opt/campusdesk/.env.staging \
  --file /opt/campusdesk/compose.staging.yaml \
  ps
```

A failed capture attempts to restart any application services it stopped and
removes only its own temporary directory. Investigate every nonzero exit before
retrying. Do not delete the prior good recovery set.

## Isolated restore test

The restore test does not modify the live Compose project. It creates temporary
Docker volumes, an internal Docker network, and a standalone MySQL container.
It verifies the set checksum, restores the database, generates and runs SQL
`CHECK TABLE` for every application base table, compares all captured database
counts, extracts the attachments into a temporary volume, rejects special
filesystem entries, verifies every attachment checksum and exact path, starts
the captured backend image with an explicit isolated environment against the
restored database, runs `migrate:status`, and removes the temporary resources on
exit.
The captured backend and MySQL images must still exist in the local Docker image
store; the test never pulls a replacement image.

Test the newest set:

```bash
sudo /opt/campusdesk/ops/staging/restore-test.sh
```

Test a specific set:

```bash
sudo /opt/campusdesk/ops/staging/restore-test.sh 20261005T021500Z
```

A successful test ends with `backend_start=OK` and `restore_test=OK`. Record the
backup ID, output, date, operator, and host in the operations log. Scheduled
captures are already copied to encrypted S3 and download-verified; the restore
test additionally proves that the database, files, and application image work
together.

## Disaster recovery boundary

These scripts deliberately do not overwrite the live database or attachment
volume. A live restore is destructive and must use an approved incident plan.
Before restoring, stop frontend, worker, and backend services; preserve the
current volumes; validate `SHA256SUMS`; confirm the intended backup ID and image
references; restore into new volumes; run the isolated test; and obtain the
required approval before switching the staging stack to the restored data.

Do not import a dump into the live database in place, extract over the live
attachment volume, substitute newer images for the recorded images, or treat a
successful archive listing as a complete restore test.
