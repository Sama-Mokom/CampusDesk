# Staging host operations

Reviewed source copies of the staging host tooling. The repository is the source of truth; the host copies are installed and updated manually through a separately reviewed host change.

| File | Installed as | Purpose |
| --- | --- | --- |
| [`campusdesk-deploy.sh`](campusdesk-deploy.sh) | `/usr/local/sbin/campusdesk-deploy` (root:root, mode `0750`) | The only executable invoked by the restricted SSM document. Validates the release, pulls digest-pinned images, runs migrations, recreates services, smoke-tests, and records the release. |
| [`CampusDesk-DeployStaging.template.json`](CampusDesk-DeployStaging.template.json) | SSM document `CampusDesk-DeployStaging` (version `1`) | The reviewed document template; `__AWS_ACCOUNT_ID__` is injected at registration time. The contract is [CampusDesk-DeployStaging.md](CampusDesk-DeployStaging.md). |
| [`register-ssm-doc.sh`](register-ssm-doc.sh) | run from the reviewed checkout | Renders the template with the account from STS, then creates or updates version `1` and sets it as the default. The rendered file is temporary. |
| `backup*.sh`, `capture-recovery-set.sh`, `restore-test.sh`, `recovery-common.sh`, `systemd/`, `aws/` | see [STAGING_RECOVERY.md](../../Docs/STAGING_RECOVERY.md) | Backup, restore, and freshness tooling. |

## Installing the deploy script

The host copy is replaced only through a reviewed host update. The reviewed procedure is:

```bash
# From the repository root (PowerShell workstation):
scp -i <key> ops/staging/campusdesk-deploy.sh ubuntu@<staging-host>:/tmp/campusdesk-deploy.sh

# On the host:
sudo bash -n /tmp/campusdesk-deploy.sh
sudo cp -a /usr/local/sbin/campusdesk-deploy /root/campusdesk-deploy.before-$(date -u +%Y%m%dT%H%M%SZ)
sudo install -o root -g root -m 0750 /tmp/campusdesk-deploy.sh /usr/local/sbin/campusdesk-deploy
sudo bash -n /usr/local/sbin/campusdesk-deploy
```

`compose.staging.yaml` is installed the same way (see the handoff guide). Neither file is synchronized automatically.

## Registering the SSM document

The document is registered from the template; the account ID is never committed.

```bash
./ops/staging/register-ssm-doc.sh
```

The script resolves the account from `aws sts get-caller-identity`, renders the template to a temporary file, updates the existing document (or creates it), and sets the resulting version as the default. The rendered file is removed on exit. Re-run it after any change to `CampusDesk-DeployStaging.template.json`.

The deploy workflow pins `--document-version "1"`, so a newly registered version is not used until that pin is updated in the same reviewed change.

## Release identity

The script now records what it deployed:

- `/var/lib/campusdesk-deploy/current-release.env` — the existing root-only state file (`RELEASE_SHA`, image references).
- `/opt/campusdesk/release.json` — root-owned, mode `0644`, written atomically on success:

  ```json
  {
    "release_sha": "…",
    "backend_digest": "sha256:…",
    "frontend_digest": "sha256:…",
    "previous_release_sha": "…",
    "action": "recreate",
    "migration_step_completed": true,
    "recorded_at": "…"
  }
  ```

  It records digests rather than full image references, so the world-readable record does not expose the registry account ID; the root-only `current-release.env` keeps the full references.
- SSM standard output now starts with `release_sha=… backend_digest=… frontend_digest=… previous_release_sha=…` and reports `action=recreate|noop migration_step_completed=true|false` on success. `migration_step_completed` means the one-time migrate command completed successfully; it does not assert that migrations were pending or applied.
- The published images carry `org.opencontainers.image.revision` equal to the release SHA.

## Verification

```bash
# What is recorded on the host?
sudo cat /opt/campusdesk/release.json

# What is the running frontend image?
cid=$(sudo docker compose --env-file /opt/campusdesk/.env.staging \
  --file /opt/campusdesk/compose.staging.yaml ps -q frontend)
sudo docker inspect --format '{{.Config.Image}}' "$cid"

# What did the image declare about itself?
backend_ref=$(sudo grep -E '^BACKEND_IMAGE=' /opt/campusdesk/.env.staging | cut -d= -f2-)
frontend_ref=$(sudo grep -E '^FRONTEND_IMAGE=' /opt/campusdesk/.env.staging | cut -d= -f2-)

sudo docker image inspect "$backend_ref" \
  --format '{{index .Config.Labels "org.opencontainers.image.revision"}}'
sudo docker image inspect "$frontend_ref" \
  --format '{{index .Config.Labels "org.opencontainers.image.revision"}}'
```

The deploy-time acceptance tests live in [Docs/CI_CD_HARDENING_PLAN.md](../../Docs/CI_CD_HARDENING_PLAN.md), Phase 0.
