# 🚨 HANDOFF — START HERE

> **Read this document first.** It tells you exactly what the project is, what has been built, what still needs to be built, and where to start.

---

## What is CampusDesk?

CampusDesk is a **university document request and tracking system** built by Nkeng Sama Mokom (matricule FE23A118, Level 400 Computer Engineering, University of Buea, Cameroon) as a deliberate learning project to master Laravel backend development and fullstack PHP development.

Students submit document requests (transcripts, attestations, etc.). Each request flows through one or more university departments in sequence. Staff in each department pick up and process their stage. Students track status in real time and receive email notifications on every change.

---

## Current Project Status (as of September 2026)

| Area | Status |
|------|--------|
| Laravel backend — Phases 1–6 | ✅ COMPLETE |
| Vue 3 frontend — built by v0/Cursor | ✅ COMPLETE (mock data) |
| CORS configuration | ✅ COMPLETE |
| Authentication wiring (login, register, logout) | ✅ COMPLETE |
| Student dashboard wiring | ✅ COMPLETE |
| Staff dashboard wiring | ✅ COMPLETE |
| Multi-claim concurrency bug | ✅ FIXED |
| Sequential routing PHPUnit tests | ✅ WRITTEN (2 feature test files) |
| Frontend Vitest unit tests | ✅ WRITTEN (4 test files) |
| Protected file serving (attachments) | ✅ COMPLETE |
| Request timeline for staff | ✅ COMPLETE |
| Comprehensive seeder suite (factories + seeders) | ✅ COMPLETE |
| Dept Admin dashboard wiring | ❌ NOT STARTED |
| Super Admin dashboard wiring | ❌ NOT STARTED |
| Notifications bell/dropdown wiring | ❌ NOT STARTED |
| Reopen rejected request endpoint | ❌ NOT IMPLEMENTED |
| Mark request as collected endpoint | ❌ NOT IMPLEMENTED |
| Reassign stage endpoint (dept admin) | ❌ NOT IMPLEMENTED |
| Admin CRUD endpoints | ❌ NOT IMPLEMENTED |

---

## Last completed action

A comprehensive seeder suite was built to replace the previous manual Tinker setup:
- `UserFactory` — produces both student and staff users with auto-generated profiles and properly formatted matricules
- `StaffSeeder` — seeds 80 staff users
- `StudentSeeder` — seeds 10 students per academic department
- `DepartmentStaffSeeder` — assigns staff to departments, auto-elevates primary staff to `dept_admin`
- `FacultyMarkdownParser` + support files — parse a University of Buea programme structure markdown file to seed real faculty/department/programme data

Additionally, PHPUnit feature tests and Vitest unit tests were written to lock in the concurrency fix and core resolve behaviours.

Before this seeder work, the multi-claim concurrency bug was identified and fixed by the developer. The fix involved:
1. A SQL-level `whereExists` correlated subquery in `index()` — a stage only appears in the queue if `sequence_order = 1` OR its predecessor has `status = approved`
2. Wrapping the claim check-then-act in `DB::transaction()` with `lockForUpdate()` on both the target stage and the predecessor row to close the TOCTOU window

---

## What to work on next (in order)

### 1. Fix the logout endpoint (⚠️ active bug)
- **Issue:** `AuthenticatedSessionController::destroy()` calls `$request->session()->invalidate()` and `$request->session()->regenerateToken()` — these crash in a stateless API context because no session driver is active on the API route.
- **Fix:** Replace with `$request->user()->currentAccessToken()->delete()` to revoke the Sanctum token, then return `response()->noContent()`.

### 2. Fix the `forRequest()` route-model binding mismatch (⚠️ active bug)
- **Issue:** Route is `GET /requests/{request}/stages` but controller method is `forRequest(DocumentRequest $docRequest)`. The parameter name `{request}` does not match `$docRequest`, so Laravel never resolves the model — the method returns an empty collection.
- **Evidence:** `SequentialRoutingPreservationTest::test_for_request_endpoint_returns_200_for_staff()` explicitly documents this as a known pre-existing bug.
- **Fix:** Either rename the route parameter to `{docRequest}` or rename the controller parameter to `$request` (but the latter conflicts with the injected `Illuminate\Http\Request` — use `DocumentRequest $docRequest` and fix the route parameter to match).

### 3. Fix the `is_dept-admin` gate name inconsistency (⚠️ active bug)
- **Issue:** `AppServiceProvider` defines the gate as `'is_dept-admin'` (underscore prefix, hyphen separator). `EnsureIsDeptAdmin` middleware checks `Gate::allows('is-dept-admin')` (all hyphens). These do NOT match — the `dept_admin` middleware will always deny access.
- **Fix:** Standardize to all-hyphen: rename the gate definition in `AppServiceProvider` from `'is_dept-admin'` to `'is-dept-admin'`.

### 4. Reopen rejected request
- **Backend:** Add `POST /api/requests/{request}/reopen` endpoint in `RequestController`
- **Logic:** Set `is_reopened = true`, spawn fresh `request_stages` from `default_department_sequence` (use `StageGenerationService` which already exists at `app/Services/StageGenerationService.php`), create status history entry, set parent request status back to `pending`
- **Frontend:** Wire `doReopen()` in `StudentDashboard.vue` (currently stubbed with a TODO comment)

### 5. Mark as collected
- **Backend:** Add `PATCH /api/requests/{request}/collect` endpoint
- **Logic:** Verify `status === 'ready'`, set status to `collected`, log status history
- **Frontend:** Wire `doCollected()` in `StudentDashboard.vue` (currently stubbed)

### 6. Notifications bell
- **Backend:** `GET /api/notifications` — return user's notifications
- **Backend:** `PATCH /api/notifications/{id}/read` — mark as read
- **Frontend:** Wire `NotificationBell.vue` (currently uses `useMockData`)

### 7. Dept Admin dashboard wiring
- Backend routes in `dept_admin` middleware group are empty (fix gate name first — item 3 above)
- `DeptAdminView.vue` component exists but is mock-only

### 8. Super Admin dashboard wiring
- Backend routes in `super_admin` middleware group are empty
- `AdminDashboard.vue` and `SuperAdminView.vue` exist but are mock-only
- Requires CRUD endpoints for faculties, departments, programmes, users, request types

---

## Critical files to inspect first

### Backend (Laravel)
```
campusdesk/app/Http/Controllers/RequestController.php       ← student request CRUD + resolveSequence()
campusdesk/app/Http/Controllers/RequestStageController.php  ← staff claim/resolve + FIXED queue
campusdesk/app/Http/Controllers/AttachmentController.php    ← protected file serving
campusdesk/app/Http/Controllers/ReferenceDataController.php ← public dropdown data
campusdesk/app/Services/StageGenerationService.php          ← stage sequence resolver (built, not yet used)
campusdesk/app/Observers/RequestStageObserver.php           ← auto status history + email dispatch
campusdesk/routes/api.php                                   ← all API routes
campusdesk/routes/auth.php                                  ← login/register/logout routes
campusdesk/app/Models/Request.php                           ← aliased as DocumentRequest/UserRequest
campusdesk/bootstrap/app.php                                ← CORS + middleware registration
campusdesk/app/Providers/AppServiceProvider.php             ← Gate definitions (contains naming bug — see above)
campusdesk/database/seeders/DatabaseSeeder.php              ← orchestrates full seeder suite
```

### Frontend (Vue 3)
```
Frontend/src/composables/useAuth.ts      ← token + user localStorage management
Frontend/src/services/api.ts             ← Axios instance + interceptors
Frontend/src/services/auth.ts            ← login/register/logout service functions
Frontend/src/services/requests.ts        ← student request API calls
Frontend/src/services/stages.ts          ← staff stage API calls
Frontend/src/services/reference.ts       ← dropdown data (faculties, departments etc.)
Frontend/src/router/index.ts             ← route guards + role-based routing
Frontend/src/components/StudentDashboard.vue ← primary student view
Frontend/src/components/StaffDashboard.vue   ← primary staff view
Frontend/src/components/NotificationBell.vue ← notification bell (uses useMockData — not wired)
Frontend/src/components/AdminDashboard.vue   ← super admin UI (uses useMockData — not wired)
Frontend/src/types/index.ts              ← all TypeScript interfaces
```

---

## Important constraints — do NOT change these

1. **Bearer token auth** — the project uses Sanctum token auth. Do NOT switch to cookie/session auth. `EnsureFrontendRequestsAreStateful` was intentionally removed from `bootstrap/app.php`.

2. **`Request` model is aliased** — Laravel's `Illuminate\Http\Request` conflicts with the custom `Request` model. The model is always imported as `use App\Models\Request as DocumentRequest` or `use App\Models\Request as UserRequest`.

3. **Gate naming convention** — after the bug fix in item 3 above, all gate names should use hyphens: `is-student`, `is-staff`, `is-dept-admin`, `is-super-admin`. Middleware aliases use underscores (`student`, `staff`, `dept_admin`, `super_admin`). Do not mix these up.

4. **`status_history.changed_by` references `staff_profiles.id`** — NOT `users.id`. This is intentional (audit trail preserved if user account deleted). When inserting status history, always resolve the staff_profile ID from the user ID first.

5. **`$fillable` must be explicitly set** — every model in this project has had silent data loss bugs due to missing `$fillable` entries. Always check `$fillable` when a field is not being saved.

6. **Route model binding parameter names must match controller variable names exactly** — `{docRequest}` in the route must match `DocumentRequest $docRequest` in the controller. The `forRequest()` method has a known mismatch (see item 2 above).

7. **The multi-claim concurrency fix must not be reverted** — the `index()` queue method uses a `whereExists` correlated subquery and `claim()` uses `lockForUpdate()` inside a DB transaction. These are critical for correctness.

8. **`level` enum values are `100`–`600`** (no `L` prefix) — a migration changed them from `L100`–`L600` to `100`–`600`. The registration endpoint validates `in:100,200,300,400,500,600`. The frontend `RegisterCredentials` TypeScript type still uses the old `L100` format (a known type mismatch — see KNOWN_ISSUES.md).

9. **`degree_type` enum values are `BACHELOR`, `CERTIFICATE`, `MASTER`, `PHD`** — changed from `BSc`, `BEng`, etc. The frontend `DegreeType` TypeScript type still uses the old values.

10. **`StageGenerationService` exists but is not yet used** — `app/Services/StageGenerationService.php` was written to replace the inline `resolveSequence()` method in `RequestController`. The controller has not been updated to use it yet. Use the service when adding the reopen endpoint.

---

## Known issues / things to be aware of

1. **`doReopen()` and `doCollected()` are stubbed in `StudentDashboard.vue`** — they log to console only. The backend endpoints do not exist yet.

2. **Dept Admin and Super Admin dashboards are still mock-only** — `DeptAdminView.vue`, `AdminDashboard.vue`, and `SuperAdminView.vue` exist but all data is sourced from `useMockData`. No backend routes exist for these views.

3. **`AuthenticatedSessionController::destroy()` calls session methods** — this will crash in the API context. The logout flow may silently fail or 500. Fix before shipping (see item 1 in "What to work on next").

4. **`forRequest()` always returns an empty collection** — the route-model binding mismatch means `GET /requests/{request}/stages` never returns the actual stages for that request. The staff view works around this by using `GET /requests/{id}` instead.

5. **`AppServiceProvider` defines `'is_dept-admin'`** — one gate name uses a mixed naming convention. The `dept_admin` middleware always returns 403 as a result. Fix before building dept admin features.

6. **`RequestStageController::index()` has a dead code path** — when a `$docRequest` parameter is passed, the method runs an old PHP-level `filter()` approach rather than the SQL-based concurrency-safe fix. This path is not currently reachable (no route passes `$docRequest`), but if reactivated it would expose the original bug.

7. **Queue worker must be running** for email notifications — `php artisan queue:work`. Notifications are sent via Mailtrap in development.

8. **Frontend `RegisterCredentials` type uses `L100`–`L600`** — mismatches the backend enum (`100`–`600`). This may cause a 422 validation error at registration depending on what the frontend sends. Verify the actual value submitted by `RegisterView.vue`.

9. **Frontend `DegreeType` uses old values (`BSc`, `BEng`, etc.)** — mismatches the database enum (`BACHELOR`, `CERTIFICATE`, etc.). Affects any dropdown that exposes degree types.

10. **`NotificationBell.vue` uses `useMockData`** — the bell is visible in the app header but displays mock notifications only.

11. **`Frontend/app/` directory is an abandoned Next.js scaffold** — this directory at `Frontend/app/` (not `Frontend/src/`) contains remnants of a Next.js project and should be ignored. The real frontend is `Frontend/src/`.

---

## Environment quick reference

```bash
# Backend — one-command startup (runs server + queue worker + pail log viewer together)
cd campusdesk
composer run dev

# OR manually in separate terminals:
php artisan serve          # runs on http://127.0.0.1:8000
php artisan queue:work     # must run in separate terminal for email notifications

# Reset + reseed (fully automated — no Tinker required)
php artisan migrate:fresh --seed
```

Backend `.env` key settings:
```env
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_DATABASE=campusdesk
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
QUEUE_CONNECTION=database
MAIL_MAILER=smtp           # Mailtrap credentials for dev
```

Frontend `.env`:
```env
VITE_API_URL=http://127.0.0.1:8000/api
```

```bash
# Frontend
cd Frontend
npm install
npm run dev                # runs on http://localhost:5173
npm test                   # runs Vitest unit tests (vitest run)
```

```bash
# Run PHPUnit tests (backend)
cd campusdesk
php artisan test
```
