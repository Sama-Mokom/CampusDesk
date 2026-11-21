# CampusDesk — Phase 1: Release Selection Guard

**Last reviewed:** 21 November 2026

**Status:** PR A (release manifest publication) implemented on `ci/release-manifest`; PRs B and C pending.

**Scope:** [.github/workflows/ci.yml](../.github/workflows/ci.yml), [.github/workflows/deploy-staging.yml](../.github/workflows/deploy-staging.yml), [ops/staging/campusdesk-deploy.sh](../ops/staging/campusdesk-deploy.sh), IAM for the publisher, deploy, and EC2 runtime roles, and the operator documentation.

**Related:** [CI/CD Hardening Plan](CI_CD_HARDENING_PLAN.md), [CI/CD implementation and operations guide](CI_CD_SESSION_2_HANDOFF.md), [CI-CD.md](../CI-CD.md).

## 1. Objective

Make a stale or unrelated release impossible to deploy silently, while keeping the deliberate rollback path and the manual approval gate.

After Phase 1:

1. A deploy is accepted only if a CI-produced release manifest exists for that exact SHA.
2. In normal mode the SHA must be the current `development` tip **and** the latest published release.
3. Going backwards requires `allow_previous_release=true` plus a reason, and the target must be older than what is currently deployed.
4. The manifest digests must match ECR, and the pulled images must carry `org.opencontainers.image.revision == SHA`.
5. The host records the deployed SHA in SSM after a successful deploy; the workflow verifies it.

## 2. Non-goals

- Changing the build recipe (Phase 2).
- Runtime configuration (Phase 4).
- Zero-downtime, canary, or blue/green deployment.
- Automatic deployment on publish.

## 3. Release records

Three SSM `String` parameters in `eu-north-1`, all free of secrets and account identifiers:

| Parameter | Written by | Read by | Value |
| --- | --- | --- | --- |
| `/campusdesk/releases/<sha>` | publisher role (CI manifest job) | deploy role | JSON release manifest |
| `/campusdesk/staging/latest` | publisher role, only when the published SHA is the current `development` tip | deploy role | the SHA of the newest published release |
| `/campusdesk/staging/last-deployed-sha` | **EC2 instance role** (host script) | deploy role | the SHA of the last successful deployment |

`PutParameter --overwrite` creates each parameter if absent. No KMS permission is required for `String` parameters under the default SSM key. CloudTrail records every write.

### Manifest schema

`release-manifest.json`, also uploaded as the workflow artefact `release-<sha>` (90 days):

```json
{
  "schema": 1,
  "sha": "<40-hex>",
  "ref": "refs/heads/development",
  "run_id": "1234567890",
  "run_attempt": "1",
  "created_at": "2026-11-21T15:00:00Z",
  "backend": {
    "repository": "campusdesk_backend",
    "tag": "<40-hex>",
    "digest": "sha256:…"
  },
  "frontend": {
    "repository": "campusdesk_frontend",
    "tag": "<40-hex>",
    "digest": "sha256:…"
  }
}
```

The manifest contains no account ID; the deploy builds the registry reference from `vars.AWS_ACCOUNT_ID` as it does today.

### The `latest` tip guard

The manifest job writes `/campusdesk/staging/latest` only when the release SHA is the current `origin/development` tip at write time. A CI re-run of an older push still writes its per-SHA manifest but does not move `latest` backwards. If a newer push lands while the job runs, the newer push's manifest job owns `latest`.

## 4. Selection rules

| Mode | Entered when | Conditions |
| --- | --- | --- |
| **normal** | default | `sha == origin/development` tip, `sha == /staging/latest`, and `last-deployed` is an ancestor of `sha` (or unknown) |
| **idempotent** | default | `sha == last-deployed` (re-run or health check even if the tip moved) |
| **rollback** | `allow_previous_release=true` + non-empty `reason` | `sha` is a published ancestor of `development` and an ancestor of `last-deployed` (or `last-deployed` is unknown during bootstrap) |

Failure messages name the values, for example:

- `4f5c02e is not the current development tip (tip is ff258aa); use allow_previous_release for a rollback`
- `no published release manifest for <sha>; wait for the publish job to finish`
- `<sha> is not the latest published release (latest is <sha>)`
- `<sha> is not older than the deployed release <sha>; rollback targets must be ancestors of the deployed release`
- `allow_previous_release requires a non-empty reason`

## 5. PR A — release manifest publication (implemented)

**Goal:** every successful push publication produces an immutable per-release manifest and updates `latest` safely. Deployment still behaves exactly as before.

**CI changes:**

- `backend-publish` and `frontend-publish` gain job output `digest`.
- A `Resolve published digest` step runs after the build/push step, whether the image was just pushed or already existed, and validates `^sha256:[0-9a-f]{64}$`.
- A new `release-manifest` job runs on a push to `development` after both publish jobs:
  1. checks out the repository with full history;
  2. assumes the publisher role;
  3. validates the SHA and both digests;
  4. writes `release-manifest.json` with `jq`;
  5. writes `/campusdesk/releases/<sha>`;
  6. writes `/campusdesk/staging/latest` only if `<sha>` is the current `origin/development` tip;
  7. uploads the artefact `release-<sha>`;
  8. writes a job summary including the manifest and `latest_updated`.

**IAM prerequisite (publisher role only):** `ssm:PutParameter` on `/campusdesk/releases/*` and `/campusdesk/staging/latest`. This is applied manually before the first push after PR A merges.

**Acceptance:**

- Both publish jobs green; `release-manifest` green on a `development` push.
- `/campusdesk/releases/<sha>` contains the manifest with both digests.
- `/campusdesk/staging/latest` equals the SHA when it was the tip.
- Artefact `release-<sha>` exists.
- A re-run of an older CI run does not move `latest`.
- No deploy behaviour changes.

## 6. PR B — deploy selection

**New inputs:**

```yaml
allow_previous_release:
  description: Deploy a published release older than the current deployment (rollback)
  required: false
  default: false
  type: boolean
reason:
  description: Required when allow_previous_release is true
  required: false
  default: ""
  type: string
```

**New step `Resolve release manifest`** (replaces `Verify release images`):

- read `/campusdesk/releases/<sha>`; missing → fail with "no published release manifest";
- validate the manifest SHA and both digests;
- cross-check both ECR tags resolve to the same digests;
- read `/campusdesk/staging/latest`;
- read `/campusdesk/staging/last-deployed-sha` tolerantly (missing = `unknown`);
- output `backend_digest`, `frontend_digest`, `latest_sha`, `last_deployed_sha`.

**New step `Apply release selection`:**

```bash
git fetch --no-tags origin +refs/heads/development:refs/remotes/origin/development
tip="$(git rev-parse origin/development)"

if [[ "$RELEASE_SHA" == "$tip" && "$RELEASE_SHA" == "$LATEST_SHA" ]]; then
    mode="normal"
elif [[ "$RELEASE_SHA" == "$LAST_DEPLOYED_SHA" ]]; then
    mode="idempotent"
elif [[ "$ALLOW_PREVIOUS" == "true" ]]; then
    [[ -n "$REASON" ]] || fail "allow_previous_release requires a non-empty reason"
    [[ "$LAST_DEPLOYED_SHA" == "unknown" ]] ||
        git merge-base --is-ancestor "$RELEASE_SHA" "$LAST_DEPLOYED_SHA" ||
        fail "rollback targets must be ancestors of the deployed release"
    mode="rollback"
else
    fail "not the tip/latest; use allow_previous_release for a rollback"
fi
```

**New step `Verify deployment record`** after the SSM wait:

```bash
recorded="$(aws ssm get-parameter --name /campusdesk/staging/last-deployed-sha \
  --query Parameter.Value --output text)"
[[ "$recorded" == "$RELEASE_SHA" ]] || fail "deployment record does not match the release"
```

**Summary additions:** selection mode, tip, latest, previous deployed, rollback reason, digests.

**IAM prerequisite (deploy role):** `ssm:GetParameter` on the three parameter paths.

## 7. PR C — host record and label verification

**Host script changes:**

1. **Verify image labels after pull, before downtime:**

   ```bash
   verify_image_revision() {
       local image="$1" revision
       revision="$(docker image inspect "$image" \
           --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')"
       [[ "$revision" == "$release_sha" ]] ||
           die "Image ${image} declares revision '${revision}', expected ${release_sha}"
   }
   ```

2. **Record the deployed SHA** after `write_state` and `write_release_record` on both the no-op and recreate success paths:

   ```bash
   readonly LAST_DEPLOYED_PARAMETER="/campusdesk/staging/last-deployed-sha"

   record_last_deployed() {
       aws ssm put-parameter --region "$AWS_REGION" \
           --name "$LAST_DEPLOYED_PARAMETER" --type String \
           --value "$release_sha" --overwrite >/dev/null
   }
   ```

   On failure, log `application is deployed but the release record could not be updated` and exit 1. The app stays healthy; the SSM command fails so the workflow surfaces the problem.

3. **No-op verifies the running containers, not only `.env.staging`.** If the recorded refs match but the running `Config.Image` values do not, fall through to a recreate instead of no-op'ing over a stale stack.

**IAM prerequisite (EC2 instance role):** `ssm:PutParameter` on `/campusdesk/staging/last-deployed-sha` only.

**Rollout:** install the updated host script, then deploy the current tip normally; the host writes `last-deployed-sha`. Until then the workflow treats it as `unknown`.

## 8. IAM changes

All are additive statements on existing roles. `<ACCOUNT_ID>` is substituted at apply time and never committed.

**Publisher role** (`CampusDeskGitHubEcrPublisher`) — required before PR A's first push:

```json
{
  "Sid": "WriteReleaseManifests",
  "Effect": "Allow",
  "Action": "ssm:PutParameter",
  "Resource": [
    "arn:aws:ssm:eu-north-1:<ACCOUNT_ID>:parameter/campusdesk/releases/*",
    "arn:aws:ssm:eu-north-1:<ACCOUNT_ID>:parameter/campusdesk/staging/latest"
  ]
}
```

**Deploy role** (`CampusDeskGitHubStagingDeployer`) — PR B:

```json
{
  "Sid": "ReadReleaseRecords",
  "Effect": "Allow",
  "Action": "ssm:GetParameter",
  "Resource": [
    "arn:aws:ssm:eu-north-1:<ACCOUNT_ID>:parameter/campusdesk/releases/*",
    "arn:aws:ssm:eu-north-1:<ACCOUNT_ID>:parameter/campusdesk/staging/latest",
    "arn:aws:ssm:eu-north-1:<ACCOUNT_ID>:parameter/campusdesk/staging/last-deployed-sha"
  ]
}
```

**EC2 instance role** (`CampusDeskEc2Runtime`) — PR C:

```json
{
  "Sid": "WriteLastDeployedRelease",
  "Effect": "Allow",
  "Action": "ssm:PutParameter",
  "Resource": "arn:aws:ssm:eu-north-1:<ACCOUNT_ID>:parameter/campusdesk/staging/last-deployed-sha"
}
```

`AmazonSSMManagedInstanceCore` grants `GetParameter*` but, as far as known, not `PutParameter`; confirm before relying on the scoped statement. If it does grant `PutParameter`, the statement is additive rather than restrictive and needs a different control (permissions boundary or a dedicated role).

## 9. Failure modes

| Situation | Behaviour |
| --- | --- |
| Publish run failed / no manifest | Deploy fails before SSM |
| Manifest exists but ECR digest differs | Deploy fails before SSM |
| Tip moved after publish | Normal deploy of the older SHA fails; use `allow_previous_release` or deploy the new tip |
| Unpublished feature commit | Fails at the manifest lookup, not at ECR |
| Rollback without reason / to a newer release | Fails with a specific message |
| Host `PutParameter` fails | App stays up; SSM command fails; workflow reports the record failure |
| Host image label mismatch | Fails after pull, before downtime |
| No-op while containers are stale | Falls through to a recreate |
| Missing `last-deployed-sha` | Treated as `unknown`; bootstrap allowed |

## 10. Rollout and test plan

1. Apply the publisher IAM statement.
2. Merge PR A; confirm the manifest for the merge commit and `latest`.
3. Merge PR B and the deploy-role IAM statement.
4. Merge PR C and the EC2 IAM statement; install the host script.
5. Deploy the current tip: normal mode, success, `last-deployed-sha` written.
6. Repeat the same SHA: idempotent, host no-op.
7. Negative tests: `d096046` without the flag fails; with the flag and a reason succeeds as a rollback; a feature commit with no manifest fails.

| Test | Expected |
| --- | --- |
| Normal deploy of the tip | Success; record updated |
| Repeat the same SHA | `idempotent`; no-op |
| `d096046` without flag | Fail: not the tip |
| `d096046` + flag + reason | Rollback success; record = `d096046`; redeploy tip to restore |
| Feature commit with no manifest | Fail: no manifest |
| Manifest/ECR mismatch | Fail before SSM |
| Host label mismatch | Fail after pull, before downtime |
| Manual container drift | Recreate, not no-op |

## 11. Decisions

| Decision | Choice |
| --- | --- |
| Idempotent re-deploy | Allow `sha == last-deployed` even when it is no longer the tip |
| Rollback target | Must be an ancestor of `last-deployed` |
| Manifest artefact | `release-manifest.json`, artifact `release-<sha>` |
| `latest` guard | Update only when the SHA is the current tip |
| Publisher IAM | Manual console/CLI change, documented with `<ACCOUNT_ID>` |
| Deployed record writer | EC2 instance role, host script after success |
| Deploy role | Read-only on release records |
| Label verification | Host-side after pull, before downtime |
| No-op verification | Running containers must match the recorded references |
| IaC | No IAM IaC in the repository; changes are applied manually |

## 12. PR split

| PR | Branch | Contents |
| --- | --- | --- |
| A | `ci/release-manifest` | CI manifest job, publish digest outputs, publisher IAM statement, design doc |
| B | `ci/deploy-selection` | Deploy inputs/rules/manifest resolution/record verification, deploy-role IAM |
| C | `ops/last-deployed-record` | Host label check, `last-deployed` write, running-container check, EC2 IAM, host reinstall |
| D | docs | Operator procedure, rollback runbook, plan update |

## 13. References

- [CI/CD Hardening Plan](CI_CD_HARDENING_PLAN.md) — defect catalogue and phase plan.
- [CI/CD implementation and operations guide](CI_CD_SESSION_2_HANDOFF.md) — current pipeline, IAM, EC2, and deploy procedure.
- [.github/workflows/ci.yml](../.github/workflows/ci.yml) — publish and manifest jobs.
- [.github/workflows/deploy-staging.yml](../.github/workflows/deploy-staging.yml) — deploy selection (PR B).
- [ops/staging/campusdesk-deploy.sh](../ops/staging/campusdesk-deploy.sh) — host record and label checks (PR C).
