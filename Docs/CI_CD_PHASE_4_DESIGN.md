# CampusDesk — Phase 4: Runtime Reverb Configuration

**Last reviewed:** 25 November 2026

**Status:** ✅ Verified 25 November 2026 — `8c8fed` deployed; key rotated to `campusdesk-461a0bb9080ea6190e64dbfba3dee31d`; WebSocket and same-image checks passed. Phase 5 is next.

**Scope:** [Frontend/Dockerfile](../Frontend/Dockerfile), [Frontend/docker/nginx.conf](../Frontend/docker/nginx.conf), the new frontend entrypoint, [Frontend/index.html](../Frontend/index.html), [Frontend/src/services/echo.ts](../Frontend/src/services/echo.ts), [Frontend/src/services/realtime.ts](../Frontend/src/services/realtime.ts), [compose.yaml](../compose.yaml), [compose.staging.yaml](../compose.staging.yaml), [.github/workflows/build-images.yml](../.github/workflows/build-images.yml), [ops/staging/campusdesk-deploy.sh](../ops/staging/campusdesk-deploy.sh), and the CI documentation.

**Related:** [CI/CD Hardening Plan](CI_CD_HARDENING_PLAN.md), [Phase 2 design](CI_CD_PHASE_2_DESIGN.md), [Phase 3 design](CI_CD_PHASE_3_DESIGN.md), [CI/CD implementation and operations guide](CI_CD_SESSION_2_HANDOFF.md), [CI-CD.md](../CI-CD.md).

## 1. Goal

The frontend image becomes environment-agnostic. The same image runs locally and in staging with different `REVERB_APP_KEY` values, and rotating the key requires a container recreate, not an image rebuild.

**Non-goals:** rotating `REVERB_APP_SECRET` (host-only), changing `VITE_API_URL`, changing the backend broadcasting configuration, or redesigning the realtime protocol.

## 2. Confirmed decisions

| Decision | Choice |
| --- | --- |
| Config generation | Nginx entrypoint uses `jq` for correct JSON escaping |
| Missing key | The frontend container fails; Compose requires the key |
| Missing `config.js` | The SPA boots; Echo initialization fails; realtime degrades gracefully |
| Caching | `config.js` and `index.html` are `no-store`; hashed assets are immutable |
| Host check | Post-start `config.js` assertion in `campusdesk-deploy.sh` (replaces the obsolete baked-key preflight) |
| Packaging | One PR; the host script is reinstalled after merge |
| Repository variable | `VITE_REVERB_APP_KEY` is deleted after the new image is verified |

## 3. Current state

The key is baked into:

- [`echo.ts:88-91`](../Frontend/src/services/echo.ts#L88-L91), which falls back to the literal `campusdesk-key`;
- [`Frontend/Dockerfile`](../Frontend/Dockerfile), via `ARG`/`ENV VITE_REVERB_APP_KEY`;
- [`build-images.yml`](../.github/workflows/build-images.yml), as a frontend build arg;
- [`compose.yaml:165`](../compose.yaml#L165), as a frontend build arg, while the backend's key at line 74 defaults to `campusdesk-key`.

The staging frontend service has no `REVERB_APP_KEY` environment. [`index.html`](../Frontend/index.html) has no runtime-config script, and [`nginx.conf`](../Frontend/docker/nginx.conf) only has the SPA fallback.

`RealtimeManager` creates the Echo instance lazily, so a missing key can be caught without preventing the SPA from booting.

## 4. Target flow

```text
/opt/campusdesk/.env.staging REVERB_APP_KEY
        |
        +-- backend / reverb / worker / realtime-worker
        +-- frontend container environment
                 |
        nginx entrypoint writes /usr/share/nginx/html/config.js
                 |
        index.html loads /config.js before the app bundle
                 |
        window.__APP_CONFIG__.reverbAppKey -> Echo config
```

The image contains no key. The host environment is the single source for both the backend and the frontend.

## 5. Runtime config generation

`Frontend/docker/40-generate-config.sh` is copied into `/docker-entrypoint.d/` so the stock nginx entrypoint runs it before nginx starts:

```sh
#!/bin/sh
set -eu

if [ -z "${REVERB_APP_KEY:-}" ]; then
  echo "REVERB_APP_KEY is required" >&2
  exit 1
fi

config="$(jq -cn --arg key "$REVERB_APP_KEY" '{reverbAppKey:$key}')"
printf 'window.__APP_CONFIG__ = %s;\n' "$config" \
  > /usr/share/nginx/html/config.js
chmod 0644 /usr/share/nginx/html/config.js
```

`jq` is installed in the nginx stage (`apk add --no-cache jq`). It handles quotes, backslashes, and control characters, so the key is not restricted to a hand-picked character set.

A tracked placeholder `Frontend/public/config.js` contains:

```js
window.__APP_CONFIG__ = {};
```

Vite copies it into `dist`; the entrypoint overwrites it at container start; the Vite dev server serves it locally. It contains no key.

## 6. Frontend consumption

[`index.html`](../Frontend/index.html) loads the config before the module:

```html
<script src="/config.js"></script>
...
<script type="module" src="/src/main.ts"></script>
```

The classic script is parser-blocking, so `window.__APP_CONFIG__` exists before the deferred module runs.

Add the type to the `echo.ts` global declaration:

```ts
interface Window {
  __APP_CONFIG__?: { reverbAppKey?: string }
}
```

Change `echo.ts`:

```ts
const runtimeKey =
  typeof window !== 'undefined'
    ? window.__APP_CONFIG__?.reverbAppKey?.trim()
    : undefined

const buildKey = import.meta.env.VITE_REVERB_APP_KEY?.trim()
const appKey = overrides?.appKey?.trim() || runtimeKey || buildKey || ''
```

Precedence: explicit override → runtime config → local `.env` build value. The hardcoded `campusdesk-key` fallback is removed.

`createEchoInstance` fails clearly when there is no key:

```ts
const config = getEchoConfig(overrides)
if (!config.key) {
  throw new Error('Reverb app key is not configured')
}
return new Echo(config)
```

`RealtimeManager.syncUser` catches that failure and degrades:

```ts
try {
  this.echo = this.echoFactory()
  this.bindReconnect()
} catch (error) {
  console.warn('Realtime unavailable:', error)
  this.echo = null
  return
}
```

This is what satisfies “the SPA still boots when `config.js` is missing”: the script 404s, the runtime config is absent, the Echo factory throws, and the manager disables realtime instead of breaking the app.

## 7. Nginx and caching

Add an exact location before the SPA fallback:

```nginx
location = /config.js {
    add_header Cache-Control "no-store" always;
    try_files $uri =404;
}
```

A missing config returns `404`, not `index.html` as JavaScript. `no-store` makes a key rotation visible on the next page load.

Companion rules:

```nginx
location = /index.html {
    add_header Cache-Control "no-store";
    try_files $uri =404;
}

location /assets/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    try_files $uri =404;
}
```

## 8. Dockerfile and build path

`Frontend/Dockerfile`:

- keep `ARG VITE_API_URL=/api`;
- remove `ARG VITE_REVERB_APP_KEY` and its `ENV`;
- install `jq` in the nginx stage;
- copy `docker/40-generate-config.sh` to `/docker-entrypoint.d/40-generate-config.sh` and make it executable;
- keep the nginx config copy and `EXPOSE 80`.

`.github/workflows/build-images.yml`:

- remove `VITE_REVERB_APP_KEY` from the frontend `build-args`;
- keep `VITE_API_URL=/api`.

The `VITE_REVERB_APP_KEY` repository variable becomes unused and is deleted after the new image is verified.

## 9. Compose and host environment

`compose.yaml`:

- change the backend default to `${REVERB_APP_KEY:?REVERB_APP_KEY must be set}`;
- remove the frontend `VITE_REVERB_APP_KEY` build arg;
- add the frontend environment:

```yaml
    environment:
      REVERB_APP_KEY: ${REVERB_APP_KEY:?REVERB_APP_KEY must be set}
```

`compose.staging.yaml`:

- add the same frontend environment entry;
- the top-level requirement already covers the backend, Reverb, and workers.

Local development:

- `Frontend/.env.example` keeps `VITE_REVERB_APP_KEY=campusdesk-key` for the Vite dev fallback;
- `.env.docker.example` keeps the local key for Compose;
- the real `.env`/`.env.docker` remain untracked.

The acceptance grep targets `.github/`, `Frontend/Dockerfile`, and `compose.yaml`; examples and docs may show the development key.

## 10. Rotation and host preflight

The old baked-key preflight is obsolete. Replace it with a post-start host assertion in `ops/staging/campusdesk-deploy.sh`:

- read `REVERB_APP_KEY` from the staging environment file;
- read `.reverbAppKey` from `/usr/share/nginx/html/config.js` in the frontend container;
- fail on mismatch or an empty config;
- check `curl -sI http://127.0.0.1:8080/config.js` for `200` and `Cache-Control: no-store`;
- fail the deploy before writing the release record.

The host script change requires the reviewed host reinstall. The SSM document is unchanged.

Rotation runbook:

1. Change `REVERB_APP_KEY` in `/opt/campusdesk/.env.staging`.
2. Recreate `reverb`, `backend`, `worker`, `realtime-worker`, and `frontend`.
3. Confirm the frontend entrypoint wrote the new `config.js`.
4. Reload the SPA; confirm a WebSocket connects with the new key.
5. Record the rotation in the staging operations log.

No image rebuild, no image tag change.

## 11. Acceptance tests

- Rotating `REVERB_APP_KEY` in `/opt/campusdesk/.env.staging` and recreating the containers keeps realtime working with no image rebuild.
- `curl -sI http://127.0.0.1:8080/config.js` returns `200` and `Cache-Control: no-store`.
- The same image runs locally and in staging with different keys.
- `grep -rn "campusdesk-key" .github/ Frontend/Dockerfile compose.yaml` returns no matches.
- The SPA still boots when `config.js` is missing; realtime is disabled with a warning.
- `config.js` contains the host key; the host script fails on mismatch.
- The frontend image contains no build-time key: `docker run --rm <image> grep -R "campusdesk-key" /usr/share/nginx/html` returns nothing.
- The Echo tests cover runtime precedence, build fallback, no-key failure, and graceful realtime degradation.

**Verification (25 November 2026):** `8c8fed` deployed as `normal` with the release manifest and record verified. The staging key was rotated to `campusdesk-461a0bb9080ea6190e64dbfba3dee31d` (public client key; the app secret is separate), all five services were recreated, and the frontend image stayed `473224a08684`. `config.js` served the new key with `200` and `no-store`; the browser WebSocket returned `101`, subscribed to `private-users.621`, received a `request.created` event, and exchanged ping/pong. The same image with `local-check-key` on port `8081` served that different key. The browser console's `runtime.lastError` messages are extension noise, not application errors.

## 12. Rollout and PR

One PR, `feat/runtime-reverb-key`:

- frontend entrypoint, nginx config, Dockerfile, `public/config.js`, `index.html`, `echo.ts`, `realtime.ts`, tests;
- `build-images.yml` build-arg removal;
- Compose frontend environment and required key;
- host script post-start assertion;
- rotation runbook and docs;
- this design document and plan updates.

After merge:

1. The new image is built without the key.
2. Install the reviewed `compose.staging.yaml` on the host; reinstall the host script; deploy the merge SHA. The workflow deploys images, not Compose, so the host Compose update is a required reviewed host change.
3. Rotate the key once to prove no rebuild is needed.
4. Delete `VITE_REVERB_APP_KEY` after the rollback window.

Rollback: restore the build-arg path and the old host key. Pre-Phase 4 images have the key baked, so an image rollback must be paired with the matching host key.

## 13. Risks

| Risk / decision | Position |
| --- | --- |
| Key escaping | `jq` in the nginx image; no restricted character set |
| Missing key | Container fails; Compose requires it |
| Missing `config.js` | SPA boots; Echo factory throws; realtime disabled gracefully |
| Caching | `config.js` and `index.html` no-store; hashed assets immutable |
| Load order | Classic `config.js` before the deferred module |
| Local development | `public/config.js` empty; `.env` build-key fallback |
| Baked key removal | Build args removed from Dockerfile, workflow, and Compose |
| Old image rollback | Old images have the baked key; roll back image and host key together |
| Preflight | Post-start host assertion on `config.js` and the no-store header |
| Repo variable | Delete after the new image is verified |
| Host script | Reviewed reinstall; SSM document unchanged |
