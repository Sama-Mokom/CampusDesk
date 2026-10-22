# CampusDesk handoff

## Current handoff (updated 21 October 2026)

The real-time push notification feature and event invalidation delivery pipeline are fully implemented, verified, and surgically refactored end to end. CampusDesk now leverages Laravel Reverb (WebSockets), Laravel Echo, Pusher JS, a dedicated `realtime` database queue worker, and the Transactional Outbox Pattern (`request_events`). In addition, administrative action auditing (`administrative_actions`), permanent account disablement (`disabled_at`), and last-active Super Admin protections are live. The test suite stands at **111 PHPUnit feature tests (592 assertions)** across 23 suites and **155 Vitest unit tests** across 18 test files, passing with 0 ESLint errors/warnings and 0 TypeScript errors.

## Current state (21 October 2026)

CampusDesk is a Laravel 12 and Vue 3 university document request system. Students register, submit requests with private attachments, track stages, reopen rejected requests, and mark ready requests as collected. Staff claim and resolve stages. Department admins oversee their primary department and reassign claimed stages. Super Admins manage reference data and users, see system statistics and requests, disable/enable accounts, and review both request lifecycle history and administrative mutation audits. The notification bell and dashboards update in real time via private WebSocket channels (`private-users.{id}` and `private-departments.{id}`). Email dispatch continues via the default background queue worker.

The local Docker Compose environment has been expanded to a 7-service topology: `frontend`, `backend`, `worker`, `db`, `reverb`, `realtime-worker`, and `scheduler`. Staging runs on an Ubuntu 24.04 AMD64 EC2 instance reachable via SSH tunnel, backed by off-host encrypted S3 recovery with automated twice-daily captures and 15-minute freshness monitoring.

| Area | Implementation |
|---|---|
| Student, staff, department admin, and Super Admin workflows | Wired to Laravel APIs with real-time UI invalidation |
| Sanctum Bearer login, registration, logout | Implemented; logout revokes the current token |
| Account disablement & security | `users.disabled_at`, `active_account` middleware, credential revocation, last-active Super Admin safeguard |
| Sequential routing, stage claim/resolve, reopen, collect | Implemented with concurrency locking |
| Department admin oversight and claimed-stage reassignment | Implemented for the primary department with audit logging |
| Super Admin CRUD, elevation, statistics, oversight, status audit | Implemented under `/api/admin` |
| Administrative mutation audit | Append-only `administrative_actions` table and Super Admin read API |
| Real-time push delivery & invalidation | Laravel Reverb WebSockets, Laravel Echo client, `request_events` outbox, dedicated `realtime` worker, and `realtime:reconcile` scheduler |
| Local Docker images and 7-service Compose stack | Complete, integrated, and durability-tested |
| GitHub Actions quality gates and image build validation | Implemented and passing (PHPUnit, Vitest, lint, typecheck, build) |
| GitHub OIDC and immutable Amazon ECR publication | Implemented for pushes to `development` |
| AWS EC2 staging deployment | Manual GitHub dispatch, protected-environment approval, exact ECR digests, restricted SSM command, and fixed root-owned host script |
| Staging database initialization | Migrations (33 total) and one-time full demonstration seed completed |
| Staging credential remediation | Staging commands executed; 618 demo accounts remediated and disabled |
| Attachment durability and access | One-shot volume initializer, fail-fast write handling, cleanup on rollback, scoped download authorization, regression tests complete |
| Off-host recovery | Private versioned S3, SSE-KMS, least-privilege EC2 access, twice-daily coherent captures, download verification, isolated restore test, 15-minute monitoring verified |
| Public DNS and HTTPS | Not implemented; staging access remains through an SSH tunnel |

## Where to work next

The next operational and development tasks are:
1. Roll out the expanded 7-service stack (`compose.staging.yaml` and deployment script) to AWS staging.
2. Establish stable public staging addressing, DNS, and TLS termination (including WSS WebSocket termination for Reverb on port 8085/443).
3. Continue periodic isolated restore drills and backup freshness verification.
4. Expand administrative action auditing and consider frontend browser E2E test automation (Playwright/Cypress).

See [CI_CD_SESSION_2_HANDOFF.md](CI_CD_SESSION_2_HANDOFF.md), [STAGING_RECOVERY.md](STAGING_RECOVERY.md), [TECH_STACK.md](TECH_STACK.md), and [ROADMAP.md](ROADMAP.md).

## Key implementation files

- Backend routes and authorization: `campusdesk/routes/api.php`, `campusdesk/routes/channels.php`, `campusdesk/app/Providers/AppServiceProvider.php`, `campusdesk/app/Http/Middleware/`.
- Real-time pipeline: `campusdesk/app/Services/RequestEventRecorder.php`, `campusdesk/app/Services/RequestEventDelivery.php`, `campusdesk/app/Jobs/PublishRequestEvent.php`, `campusdesk/app/Console/Commands/ReconcileRequestEventOutbox.php`.
- Frontend real-time: `Frontend/src/services/echo.ts`, `Frontend/src/services/realtime.ts`.
- Request lifecycle: `RequestController.php`, `RequestStageController.php`, `StageGenerationService.php`, and `RequestStageObserver.php` under `campusdesk/app/`.
- Admin API: `campusdesk/app/Http/Controllers/AdminReferenceController.php`, `AdminUserController.php`, `AdministrativeActionController.php`, and `AdminOverviewController.php`.
- Frontend: `Frontend/src/router/index.ts`, `App.vue`, role page directories under `src/views/`, composables under `src/composables/`, and shared workflow components under `src/components/`.
- Test fixtures: `campusdesk/database/seeders/`, `campusdesk/tests/Feature/`, and `Frontend/tests/`.
- Local containers: `compose.yaml`.
- Staging containers: `compose.staging.yaml` and `ops/staging/`.

## Constraints

- Keep Sanctum Bearer token auth; do not introduce session-based API auth.
- Preserve the SQL eligibility check and transaction locks in stage claiming.
- Broadcast envelopes must strictly contain event metadata and identifiers; do NOT transmit student PII or staff notes over WebSockets.
- Real-time events are cache invalidation triggers; clients must refetch authoritative state from existing authenticated REST endpoints.
- Preserve `disabled_at` checks on protected routes via `active_account` middleware.
- Stream attachments through authenticated `/api/attachments/{id}`. Do not expose storage paths as public URLs.
- Super Admin accounts are intentionally not seeded. Use [TINKER_STAFF_USERS.md](TINKER_STAFF_USERS.md) for local testing.

## Local commands

Verified Docker Compose environment (7 services):

```cmd
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f
docker compose --env-file .env.docker down
```

Bare development environment:

```bash
cd campusdesk

# Start complete real-time dev environment (server, queues, reverb, scheduler, vite):
composer run dev-realtime

# Or run tests:
php artisan test

cd ../Frontend
npm test
npm run lint
npm run typecheck
npm run build
```
