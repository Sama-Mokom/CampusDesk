# Staging Credential Remediation Runbook

**Last reviewed:** 9 October 2026

**Scope:** One-time remediation of CampusDesk staging accounts that still match the seeded demonstration password.

**Execution status:** Completed on staging on 9 October 2026. The public-safe aggregate result and post-remediation recovery evidence are recorded in [CI_CD_SESSION_2_HANDOFF.md](CI_CD_SESSION_2_HANDOFF.md). Repeat this runbook after any future full demonstration seed or restoration of a pre-remediation recovery set.

**Security classification:** Safe to store in the repository. This document intentionally contains no real account IDs, names, email addresses, passwords, tokens, hostnames, image digests, AWS identifiers, or environment values. Put operational evidence and retained credentials only in the approved private operations record and password manager.

## Required outcome

Retain one student, one ordinary staff member, one department administrator, and optionally a secondary Super Admin with separate unique passwords. Disable every other account that still matches the known factory password, replace that password with an unrecorded random value, revoke all existing credentials, and preserve seeded request history.

Do not expose staging publicly until every verification in this runbook passes.

## Preconditions

1. Schedule a maintenance window and identify the operator responsible for the change.
2. Confirm the account-security release has passed review and CI, has been published, and is the release selected for staging deployment.
3. Confirm the latest scheduled recovery capture is successful, download-verified, and within the accepted recovery-point window.
4. Keep the application private behind the existing SSH tunnel. Do not change DNS, TLS, or security-group exposure during this operation.
5. In the Super Admin UI, select the retained persona user IDs. Record the IDs only in the approved private operations record, not in Git, chat, issues, screenshots, or shell history.
6. Open an interactive EC2 shell suitable for non-echoing password prompts.

Define the established Compose helper in that shell:

```bash
cdc() {
  docker compose \
    --env-file /opt/campusdesk/.env.staging \
    --file /opt/campusdesk/compose.staging.yaml \
    "$@"
}
```

## 1. Deploy and verify the account-security release

Deploy through the protected, manually approved staging workflow. After it completes, verify the running services and migrations:

```bash
cdc config --quiet
cdc ps
cdc run --rm backend php artisan migrate --force
cdc exec backend php artisan migrate:status
cdc exec backend php artisan list --raw | grep '^staging:'
```

Both commands must be listed:

```text
staging:remediate-demo-credentials
staging:rotate-persona-password
```

Stop if migration status is incomplete, either command is absent, a service is unhealthy, or the command reports that `APP_ENV` is not `staging`.

## 2. Capture a pre-change recovery point

Run a coherent off-host recovery capture and verify its result:

```bash
sudo systemctl start campusdesk-backup.service
sudo systemctl status campusdesk-backup.service --no-pager
sudo journalctl -u campusdesk-backup.service -n 200 --no-pager
```

Require a successful service result plus the documented checksum, encrypted upload, version-ID, download-verification, and `OFFSITE_VERIFIED` evidence. Record only the backup ID and approval reference in the private operations record.

This backup contains the pre-remediation credential state. If it is ever restored, staging must remain private and this remediation must be run again before access is widened.

## 3. Rotate every retained persona

For each privately recorded retained user ID, read the numeric ID into a temporary shell variable so it is not written into shell history, then run the command:

```bash
read -r -p "Retained user ID: " campusdesk_retained_user_id
cdc exec backend php artisan staging:rotate-persona-password "$campusdesk_retained_user_id"
unset campusdesk_retained_user_id
```

At the two hidden prompts, enter the persona's new unique password and its confirmation. Requirements:

- at least 16 characters;
- unique to that persona and not used by another CampusDesk account;
- not the known factory password;
- generated and stored directly in the approved password manager; and
- never pasted into a command argument, environment variable, transcript, issue, commit, or this runbook.

The command must finish with:

```text
Persona password rotated and existing credentials revoked.
```

It leaves the account enabled, revokes Sanctum tokens, deletes pending reset tokens, rotates the remember token, and records `user.password_rotated` with source `staging.retained_persona`.

After each rotation, use a private browser session to verify that the retained persona can sign in with the new password and that the old known password fails with the generic authentication message. Confirm the expected role landing page. Sign out before testing the next persona.

Stop if any retained persona cannot authenticate correctly. Do not proceed to the bulk operation until every required persona passes.

## 4. Run the mandatory dry run

The command defaults to dry-run behavior, but use the explicit option in the operations record:

```bash
cdc exec backend php artisan staging:remediate-demo-credentials --dry-run
```

Record only the Student, Staff, Department Administrator, Super Admin, and Total counts. Do not query or copy names, emails, hashes, or credentials into the record.

Required checks:

- `Super Admin` must be `0`. If it is not, the command aborts; rotate that Super Admin through step 3 and repeat the dry run.
- The retained personas must not be included because their unique passwords no longer match the known password.
- The total must agree with the expected number of non-retained seeded accounts. Investigate discrepancies before continuing.
- The dry run must not change `disabled_at`, passwords, tokens, reset tokens, remember tokens, or audit records.

## 5. Apply the remediation

Run the apply operation interactively:

```bash
cdc exec backend php artisan staging:remediate-demo-credentials --apply
```

Review the count-only summary again. At this prompt:

```text
Disable and rotate credentials for all affected accounts? (yes/no) [no]:
```

enter `yes` only if the counts match the approved dry run.

The command performs one database transaction. For each still-matching non-retained account it generates an unprinted 64-character password, sets `disabled_at`, revokes all Sanctum tokens, deletes pending reset tokens, rotates the remember token, and appends `demo_credentials.remediated`. A failure rolls back the transaction.

Require the final changed count to equal the approved dry-run total. Do not capture verbose database output or account-level data.

## 6. Verify idempotency and access behavior

Immediately repeat the dry run:

```bash
cdc exec backend php artisan staging:remediate-demo-credentials --dry-run
```

Required result:

```text
Total: 0
```

Then verify all of the following:

1. Each retained persona still signs in with its unique password and reaches the correct role area.
2. The known factory password fails for each retained persona tested.
3. A sampled remediated account receives the same generic login failure as an incorrect password; do not expose that account identifier in shared evidence.
4. Any bearer token captured before disablement receives HTTP 401. Do not paste the token into logs, chat, or documentation.
5. The Super Admin user list shows retained personas as Enabled and remediated accounts as Disabled; enabled/disabled filters work.
6. `GET /api/admin/administrative-actions` shows the expected `user.password_rotated` and `demo_credentials.remediated` events with no passwords, hashes, reset tokens, or bearer tokens.
7. Re-running disable/enable transitions does not create duplicate events for an unchanged state.
8. Backend, worker, frontend, and database services remain healthy.

Record only counts, pass/fail results, timestamps, the deployed release identifier, and the operator/approval reference in the private operations record.

## 7. Capture the post-remediation recovery point

After verification, create and verify a new coherent recovery set:

```bash
sudo systemctl start campusdesk-backup.service
sudo systemctl status campusdesk-backup.service --no-pager
sudo journalctl -u campusdesk-backup.service -n 200 --no-pager
```

Record the new backup ID privately and identify it as the first verified post-remediation recovery point. Keep older encrypted/versioned sets according to the approved retention policy, but treat restoration of any pre-remediation set as reintroduction of the known-password risk.

## Abort and recovery rules

- If the dry run reports an affected Super Admin, do not use `--apply`; rotate that account through step 3 first.
- If counts are unexpected, stop and investigate without printing account-level credentials or hashes.
- If apply is cancelled, no changes are made.
- If apply fails, keep staging private, preserve logs that do not contain secrets, confirm transaction rollback, and rerun the dry run.
- Do not re-enable bulk-remediated accounts merely to recover access. Re-enable only an explicitly approved account, then issue a unique password through the secure persona command or another approved administrative process.
- Do not perform a destructive live restore solely to undo this operation. Follow the incident-controlled recovery procedure in [STAGING_RECOVERY.md](STAGING_RECOVERY.md). After any pre-remediation restore, repeat this entire runbook before widening access.

## Completion record

The operation is complete only when the private record contains:

- approved change and operator references;
- deployed release identifier;
- pre-change and post-change verified backup IDs;
- retained persona roles and user IDs, without passwords;
- password-manager record references, without secret values;
- approved dry-run role/count summary;
- apply changed count;
- second dry-run `Total: 0` evidence;
- retained login and old-password rejection results;
- disabled-account/token rejection and audit-safety results; and
- final service-health result.

Do not copy the private completion record back into this repository.
