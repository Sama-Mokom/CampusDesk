# CampusDesk — Phase 5: End-to-End Release Verification

**Last reviewed:** 25 November 2026

**Status:** PR A implemented on `feat/release-identity`; PR B (release scan) pending.

**Scope:** [campusdesk/Dockerfile](../campusdesk/Dockerfile), [campusdesk/config/app.php](../campusdesk/config/app.php), [campusdesk/routes/api.php](../campusdesk/routes/api.php), [Frontend/Dockerfile](../Frontend/Dockerfile), [Frontend/index.html](../Frontend/index.html), [Frontend/docker/40-generate-config.sh](../Frontend/docker/40-generate-config.sh), [.github/workflows/build-images.yml](../.github/workflows/build-images.yml), [.github/workflows/deploy-staging.yml](../.github/workflows/deploy-staging.yml), [ops/staging/campusdesk-deploy.sh](../ops/staging/campusdesk-deploy.sh), and the CI documentation.

**Related:** [CI/CD Hardening Plan](CI_CD_HARDENING_PLAN.md), [Phase 3 design](CI_CD_PHASE_3_DESIGN.md), [Phase 4 design](CI_CD_PHASE_4_DESIGN.md), [CI/CD implementation and operations guide](CI_CD_SESSION_2_HANDOFF.md), [CI-CD.md](../CI-CD.md).

## 1. Goal

Prove, on every deploy, that the running backend and frontend are the requested release commit; record the verified runtime identity and digests; and scan the published release images before the release manifest is written.

**Non-goals:** dashboards, alerting, canary or blue/green deployment, and fixing vulnerabilities found by the scan. Remediation is a normal fix commit.

## 2. Confirmed decisions

| Decision | Choice |
| --- | --- |
| Build time | Commit timestamp, not wall-clock build time |
| Scan tool | Trivy |
| Scan gate | CRITICAL/HIGH, engine `ignore-unfixed`; full report retained |
| Scan placement | Separate `scan-release` job before `release-manifest` |
| Mismatch test | Test image plus host verification logic |
| PR split | PR A identity/verification; PR B release scan |

## 3. Build identity

### Backend

`campusdesk/Dockerfile`:

```dockerfile
ARG APP_BUILD_SHA=unknown
ARG APP_BUILD_TIME=unknown
ENV APP_BUILD_SHA=${APP_BUILD_SHA}
ENV APP_BUILD_TIME=${APP_BUILD_TIME}
```

`config/app.php` adds:

```php
'build_sha' => env('APP_BUILD_SHA', 'unknown'),
'build_time' => env('APP_BUILD_TIME', 'unknown'),
```

`routes/api.php` adds a public route before the authenticated groups:

```php
Route::get('/version', function () {
    return response()
        ->json([
            'build_sha' => config('app.build_sha'),
            'build_time' => config('app.build_time'),
        ])
        ->header('Cache-Control', 'no-store');
});
```

The endpoint has no auth and no secrets. A feature test sets the config values, requests `/api/version`, and asserts the JSON and `no-store`.

### Frontend build SHA

Dockerfile:

```dockerfile
# build stage
ARG VITE_API_URL=/api
ARG VITE_APP_BUILD_SHA=unknown
ENV VITE_API_URL=${VITE_API_URL}
ENV VITE_APP_BUILD_SHA=${VITE_APP_BUILD_SHA}

# nginx stage
ARG APP_BUILD_SHA=unknown
ENV APP_BUILD_SHA=${APP_BUILD_SHA}
```

`index.html` adds:

```html
<meta name="app-build" content="%VITE_APP_BUILD_SHA%" />
```

`40-generate-config.sh` adds the runtime build SHA:

```sh
config="$(jq -cn \
  --arg key "$REVERB_APP_KEY" \
  --arg sha "${APP_BUILD_SHA:-unknown}" \
  '{reverbAppKey:$key, buildSha:$sha}')"
```

`echo.ts` extends the `__APP_CONFIG__` type with `buildSha?: string`. The frontend remains key-free; the build SHA is image identity, not environment configuration.

### Build workflow

The `Compute image reference` step adds:

```bash
echo "commit_time=$(git show -s --format=%cI "$IMAGE_TAG")" >> "$GITHUB_OUTPUT"
```

The backend build args:

```yaml
build-args: |
  APP_BUILD_SHA=${{ inputs.image_tag }}
  APP_BUILD_TIME=${{ steps.meta.outputs.commit_time }}
```

The frontend build args:

```yaml
build-args: |
  VITE_API_URL=/api
  VITE_APP_BUILD_SHA=${{ inputs.image_tag }}
  APP_BUILD_SHA=${{ inputs.image_tag }}
```

The `created` OCI label remains the wall-clock build time; the application build time is the commit timestamp, so the Phase 3 reproducibility check remains meaningful.

## 4. Host verification

`ops/staging/campusdesk-deploy.sh` adds `verify_runtime_sha`, called after `verify_frontend_config` in both the no-op and recreate paths:

1. `GET http://127.0.0.1:8080/api/version`; parse `.build_sha` and `.build_time`.
2. Require `.build_sha == release_sha`.
3. Read `config.js`; require `.buildSha == release_sha`.
4. Fetch `/`; require `<meta name="app-build" content="<release_sha>">`.
5. Log `runtime_sha=... runtime_build_time=... frontend_build_sha=... frontend_meta_sha=...`.

`release.json` gains:

- `build_sha`;
- `build_time`;
- `frontend_build_sha`;
- `frontend_meta_sha`.

The failure message identifies which identity source disagreed.

### Negative test

A throwaway backend image built with `APP_BUILD_SHA=deadbeef` runs `/api/version`. The host verification logic is run against that endpoint with the real `release_sha`; it must fail with the expected message. The real release must pass. No mismatched image is put into staging.

## 5. Deployment summary

The `Wait for deployment` step parses the host output:

```bash
runtime_sha=...
runtime_build_time=...
frontend_build_sha=...
frontend_meta_sha=...
```

and exposes them as step outputs. The summary replaces static claims with:

- `Release manifest` status from the `Resolve release manifest` step (`manifest_status=verified`);
- `SSM deployment` status from the wait step (`ssm_status=Success`);
- runtime `/api/version` SHA and commit time;
- frontend build SHA and meta SHA;
- deployment record status;
- the existing release, latest, previous, reason, and digests;
- the host record path.

The summary prints only values from successful steps or the host output.

## 6. Release scan — PR B

A separate `scan-release` job in `ci.yml`:

- `needs: publish-images`;
- push to `development` only;
- publisher role + ECR login;
- Trivy on both pushed index digests;
- gate on `CRITICAL`/`HIGH` with `ignore-unfixed`;
- upload `release-scan-<sha>` with table and SARIF reports;
- output `scan_status=passed`;
- `release-manifest` changes to `needs: [publish-images, scan-release]`.

The release manifest records:

```json
"scan": {
  "status": "passed",
  "report_artifact": "release-scan-<sha>"
}
```

The manifest and `release.json` record the status. A failed scan leaves the pushed tag inert and skips the manifest, matching the Phase 3 quarantine rule. The full report still lists unfixed findings.

## 7. Runbooks and docs

Update the handoff, CI-CD, and KNOWN_ISSUES:

- `/api/version`, build identity, and the verified summary;
- the release scan and report artifact;
- remove or fold the stale Vite dev-server entry into the resolved history;
- exercise the rollback and key-rotation runbooks once end to end and record the results.

## 8. Acceptance tests

- `curl -fsS http://127.0.0.1:8080/api/version` on the host returns the deployed SHA and commit timestamp.
- The SPA's `meta[name="app-build"]` and `window.__APP_CONFIG__.buildSha` match the same SHA.
- A deploy whose `/api/version` build SHA differs from `RELEASE_SHA` fails with a clear host-script error.
- The release scan runs on both images; the report artifact exists; the manifest records `scan.status`.
- The deployment summary contains only verified step outputs; no static “verified” lines.
- The rollback and key-rotation runbooks are executed and recorded.
- The Phase 3 reproducibility check still passes; the build SHA is deterministic and the build time is the commit timestamp.

## 9. Rollout and PR split

**PR A — release identity and verification**

- backend build args, `/api/version`, config, test;
- frontend meta, `config.js` buildSha, Dockerfile/build args, type;
- host `verify_runtime_sha`, `release.json` fields;
- deploy summary from step outputs;
- tests and docs.

**PR B — release scan**

- `scan-release`, Trivy, artifact, manifest scan status, summary;
- docs.

The runbook exercises and KNOWN_ISSUES cleanup can land with PR B or a small follow-up.

## 10. Risks and decisions

| Risk / decision | Position |
| --- | --- |
| Build time | Commit timestamp; preserves Phase 3 reproducibility |
| Frontend build identity | Meta and runtime `buildSha` from the baked `APP_BUILD_SHA` |
| `/api/version` | Public, no secrets, `no-store` |
| Mismatch test | Test image plus host logic; no bad staging release |
| Summary | Only verified step/host outputs; remove static claims |
| Scan gate | Trivy CRITICAL/HIGH with `ignore-unfixed`; report all severities |
| Scan ordering | Before the manifest; failure leaves an inert tag and no manifest |
| Manifest schema | Add `scan` status; keep existing SHA/digest fields |
| `release.json` | Add runtime build identity and scan status |
| Runbooks | Exercise rollback and key rotation once |
| Stale dev-server entry | Fold into resolved history |
