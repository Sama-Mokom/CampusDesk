# CampusDesk handoff

## Current state (September 2026)

CampusDesk is a Laravel 12 and Vue 3 university document request system. Students register, submit requests with private attachments, track stages, reopen rejected requests, and mark ready requests as collected. Staff claim and resolve stages. Department admins oversee their primary department and reassign claimed stages. Super Admins manage reference data and users, see system statistics and requests, and review request/stage status history. The notification bell uses the authenticated API. Email dispatch requires a running queue worker.

| Area | Implementation |
|---|---|
| Student, staff, department admin, and Super Admin workflows | Wired to Laravel APIs |
| Sanctum Bearer login, registration, logout | Implemented; logout revokes the current token |
| Sequential routing, stage claim/resolve, reopen, collect | Implemented |
| Department admin oversight and claimed-stage reassignment | Implemented for the primary department |
| Super Admin CRUD, elevation, statistics, oversight, status audit | Implemented under `/api/admin` |
| Separate audit of administrative CRUD and elevation | Planned as roadmap task 9 |

## Where to work next

See [ROADMAP.md](ROADMAP.md). The main remaining work is test coverage, an administrative action audit table, and future initiatives. `status_history` records request and stage transitions only.

The full test suites are not green: five legacy Laravel auth tests have fixture or API expectation mismatches; four `DocumentViewer` tests still expect immediate public URLs instead of the protected blob-loading flow. `vue-tsc` also reports errors in existing mock data and other components. Focused Super Admin backend and frontend tests pass, and the frontend production build succeeds. See [TESTING.md](TESTING.md).

## Key implementation files

- Backend routes and authorization: `campusdesk/routes/api.php`, `campusdesk/app/Providers/AppServiceProvider.php`, `campusdesk/app/Http/Middleware/`.
- Request lifecycle: `RequestController.php`, `RequestStageController.php`, `StageGenerationService.php`, and `RequestStageObserver.php` under `campusdesk/app/`.
- Admin API: `campusdesk/app/Http/Controllers/AdminReferenceController.php`, `AdminUserController.php`, and `AdminOverviewController.php`.
- Frontend: `Frontend/src/components/StudentDashboard.vue`, `StaffDashboard.vue`, `DeptAdminDashboard.vue`, `AdminDashboard.vue`, and `DocumentViewer.vue`; API calls live in `Frontend/src/services/`.
- Test fixtures: `campusdesk/database/seeders/` and `campusdesk/tests/Feature/`.

## Constraints

- Keep Sanctum Bearer token auth; do not introduce session based API auth.
- Preserve the SQL eligibility check and transaction locks in stage claiming.
- A request type's symbolic routing tokens are resolved when a request is created. Editing a template does not change existing stages.
- Programme faculty is derived from its department. Staff department admin authority comes from the primary pivot assignment.
- Stream attachments through authenticated `/api/attachments/{id}`. Do not expose storage paths as public document URLs.
- Super Admin deletion returns 409 for referenced records; administrative activity needs its own audit table.
- Super Admin accounts are intentionally not seeded. Use [TINKER_STAFF_USERS.md](TINKER_STAFF_USERS.md) for local testing.

## Local commands

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
