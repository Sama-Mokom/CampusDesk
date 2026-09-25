# CampusDesk handoff

## Current state (September 2026)

CampusDesk is a Laravel 12 and Vue 3 university document request system. Students register, submit requests with private attachments, track stages, reopen rejected requests, and mark ready requests as collected. Staff claim and resolve stages. Department admins oversee their primary department and reassign claimed stages. Super Admins manage reference data and users, see system statistics and requests, and review request/stage status history. The notification bell uses the authenticated API. Email dispatch requires a running queue worker.

The local Docker foundation is complete and verified. Docker Compose runs Vue/Nginx, Laravel/Apache, a separate Laravel queue worker, and MySQL 8.4. Only the frontend publishes `localhost:8080`; MySQL data and private attachments persist outside replaceable containers. See [CI_CD_SESSION_1_DOCKER.md](CI_CD_SESSION_1_DOCKER.md).

| Area | Implementation |
|---|---|
| Student, staff, department admin, and Super Admin workflows | Wired to Laravel APIs |
| Sanctum Bearer login, registration, logout | Implemented; logout revokes the current token |
| Sequential routing, stage claim/resolve, reopen, collect | Implemented |
| Department admin oversight and claimed-stage reassignment | Implemented for the primary department |
| Super Admin CRUD, elevation, statistics, oversight, status audit | Implemented under `/api/admin` |
| Local Docker images and four-service Compose stack | Complete and durability-tested |
| CI, registry publishing, and AWS staging deployment | Not implemented; CI is next |
| Separate audit of administrative CRUD and elevation | Planned as roadmap task 9 |

## Where to work next

The next delivery task is to learn the GitHub Actions execution model and then add CI checks for the already-green backend and frontend suites. Do not add image publishing or deployment to the first workflow. See [CI-CD.md](../CI-CD.md) and [ROADMAP.md](ROADMAP.md).

Run the backend and frontend suites before extending the application. Attachments are intentionally loaded through the protected blob flow, so tests must not expect immediate public document URLs. `status_history` records request and stage transitions only. See [TESTING.md](TESTING.md).

## Key implementation files

- Backend routes and authorization: `campusdesk/routes/api.php`, `campusdesk/app/Providers/AppServiceProvider.php`, `campusdesk/app/Http/Middleware/`.
- Request lifecycle: `RequestController.php`, `RequestStageController.php`, `StageGenerationService.php`, and `RequestStageObserver.php` under `campusdesk/app/`.
- Admin API: `campusdesk/app/Http/Controllers/AdminReferenceController.php`, `AdminUserController.php`, and `AdminOverviewController.php`.
- Frontend: `Frontend/src/components/StudentDashboard.vue`, `StaffDashboard.vue`, `DeptAdminDashboard.vue`, `AdminDashboard.vue`, and `DocumentViewer.vue`; API calls live in `Frontend/src/services/`.
- Test fixtures: `campusdesk/database/seeders/` and `campusdesk/tests/Feature/`.
- Containers: `compose.yaml`, `campusdesk/Dockerfile`, `Frontend/Dockerfile`, and both `docker/` configuration directories. The real `.env.docker` is intentionally ignored.

## Constraints

- Keep Sanctum Bearer token auth; do not introduce session based API auth.
- Preserve the SQL eligibility check and transaction locks in stage claiming.
- A request type's symbolic routing tokens are resolved when a request is created. Editing a template does not change existing stages.
- Programme faculty is derived from its department. Staff department admin authority comes from the primary pivot assignment.
- Stream attachments through authenticated `/api/attachments/{id}`. Do not expose storage paths as public document URLs.
- Super Admin deletion returns 409 for referenced records; administrative activity needs its own audit table.
- Super Admin accounts are intentionally not seeded. Use [TINKER_STAFF_USERS.md](TINKER_STAFF_USERS.md) for local testing.

## Local commands

Verified Docker Compose environment:

```cmd
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f
docker compose --env-file .env.docker down
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
