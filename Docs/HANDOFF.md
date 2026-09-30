# CampusDesk handoff

## Current state (September 2026)

CampusDesk is a Laravel 12 and Vue 3 university document request system. Students register, submit requests with private attachments, track stages, reopen rejected requests, and mark ready requests as collected. Staff claim and resolve stages. Department admins oversee their primary department and reassign claimed stages. Super Admins manage reference data and users, see system statistics and requests, and review request/stage status history. The notification bell uses the authenticated API. Email dispatch requires a running queue worker.

The local Docker foundation is complete and verified. The CI pipeline, immutable ECR publication, and the first AWS staging deployment are also complete. Staging runs Vue/Nginx, Laravel/Apache, a separate Laravel queue worker, and MySQL 8.4 on one Ubuntu 24.04 AMD64 EC2 instance. The frontend binds only to EC2 loopback and is currently reached through an SSH tunnel at local port 18080. See [CI_CD_SESSION_1_DOCKER.md](CI_CD_SESSION_1_DOCKER.md) and [CI_CD_SESSION_2_HANDOFF.md](CI_CD_SESSION_2_HANDOFF.md).

| Area | Implementation |
|---|---|
| Student, staff, department admin, and Super Admin workflows | Wired to Laravel APIs |
| Sanctum Bearer login, registration, logout | Implemented; logout revokes the current token |
| Sequential routing, stage claim/resolve, reopen, collect | Implemented |
| Department admin oversight and claimed-stage reassignment | Implemented for the primary department |
| Super Admin CRUD, elevation, statistics, oversight, status audit | Implemented under `/api/admin` |
| Local Docker images and four-service Compose stack | Complete and durability-tested |
| GitHub Actions quality gates and image build validation | Implemented and passing |
| GitHub OIDC and immutable Amazon ECR publication | Implemented for pushes to `development` |
| AWS EC2 staging deployment | Running manually with digest-pinned images and pull-only instance IAM |
| Staging database initialization | Migrations and one-time full demonstration seed completed |
| Staging users | Student and manually created Super Admin access verified |
| Continuous deployment from ECR to EC2 | Not implemented; deployment is currently manual |
| Public DNS and HTTPS | Not implemented; access remains through an SSH tunnel |
| Separate audit of administrative CRUD and elevation | Planned as roadmap task 9 |

## Where to work next

The next infrastructure task is to establish restore-tested database and attachment backups, then design a manually approved deployment workflow using AWS control-plane access such as Systems Manager. Do not store an SSH private key in GitHub or expose the seeded environment publicly. See [CI-CD.md](../CI-CD.md), [CI_CD_SESSION_2_HANDOFF.md](CI_CD_SESSION_2_HANDOFF.md), and [ROADMAP.md](ROADMAP.md).

Run the backend and frontend suites before extending the application. Attachments are intentionally loaded through the protected blob flow, so tests must not expect immediate public document URLs. `status_history` records request and stage transitions only. See [TESTING.md](TESTING.md).

## Key implementation files

- Backend routes and authorization: `campusdesk/routes/api.php`, `campusdesk/app/Providers/AppServiceProvider.php`, `campusdesk/app/Http/Middleware/`.
- Request lifecycle: `RequestController.php`, `RequestStageController.php`, `StageGenerationService.php`, and `RequestStageObserver.php` under `campusdesk/app/`.
- Admin API: `campusdesk/app/Http/Controllers/AdminReferenceController.php`, `AdminUserController.php`, and `AdminOverviewController.php`.
- Frontend: `Frontend/src/components/StudentDashboard.vue`, `StaffDashboard.vue`, `DeptAdminDashboard.vue`, `AdminDashboard.vue`, and `DocumentViewer.vue`; API calls live in `Frontend/src/services/`.
- Test fixtures: `campusdesk/database/seeders/` and `campusdesk/tests/Feature/`.
- CI: `.github/workflows/ci.yml`.
- Local containers: `compose.yaml`; the real `.env.docker` is intentionally ignored.
- Staging containers: `compose.staging.yaml` and `.env.staging.example`; the real EC2 `.env.staging` is intentionally ignored and must remain secret.
- Images: `campusdesk/Dockerfile`, `Frontend/Dockerfile`, and both `docker/` configuration directories.

## Constraints

- Keep Sanctum Bearer token auth; do not introduce session based API auth.
- Preserve the SQL eligibility check and transaction locks in stage claiming.
- A request type's symbolic routing tokens are resolved when a request is created. Editing a template does not change existing stages.
- Programme faculty is derived from its department. Staff department admin authority comes from the primary pivot assignment.
- Stream attachments through authenticated `/api/attachments/{id}`. Do not expose storage paths as public document URLs.
- Super Admin deletion returns 409 for referenced records; administrative activity needs its own audit table.
- Super Admin accounts are intentionally not seeded. Use [TINKER_STAFF_USERS.md](TINKER_STAFF_USERS.md) for local testing.
- The full staging seed creates demo accounts with the known factory password `password`; keep staging private until those accounts are remediated.
- Staging migrations and seeds are explicit operations and must not be placed in container startup commands.
- EC2 deploys image digests, not mutable tags; ECR publication tags images with the source commit SHA for traceability.
- The current single-host Compose deployment is not highly available or zero-downtime.

## Local commands

Verified Docker Compose environment:

```cmd
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f
docker compose --env-file .env.docker down
```

EC2 staging helper, defined in an SSH session:

```bash
cdc() {
  docker compose \
    --env-file /opt/campusdesk/.env.staging \
    --file /opt/campusdesk/compose.staging.yaml \
    "$@"
}

cdc config --quiet
cdc ps
cdc logs --tail=100 backend
```

Bare development environment:

```bash
cd campusdesk
composer run dev
php artisan test

cd ../Frontend
npm run dev
npm test
npx vue-tsc --noEmit
npm run build
```
