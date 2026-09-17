# Super Admin dashboard — implementation plan

**Status:** Implementation shipped in September 2026; verification gaps remain. This is the original task 7 plan, retained as design history; use [API.md](API.md), [FEATURES.md](FEATURES.md), and the source code for the shipped contract. Remaining test and manual-review work is recorded in [TESTING.md](TESTING.md).

## Scope and settled decisions

Deliver protected CRUD for faculties, departments, programmes, request types, and users; a separate staff `admin_level` endpoint; system statistics; a paginated system-wide request list; a paginated `status_history` audit log; and a live `AdminDashboard.vue`. The existing Super Admin route and Sanctum gate remain the authorization foundation.

1. Hard delete only unused records. A referenced record returns `409 Conflict` with a useful message. Do not rely on database cascades to decide whether deletion is safe. No archive/deactivation feature is included here.
2. Remove the mock arbitrary request-status override. The dashboard may expose the existing `POST /api/requests/{request}/reopen` action only when a request is rejected. Normal claim, resolve, and collect transitions keep their current owners and rules.
3. Create users as either students or staff and edit within the same role. No role conversion. Elevate or demote staff through a dedicated endpoint. Reject deletion or demotion of the signed-in administrator and of the last Super Admin.
4. The audit log in this task is the request/stage status history in `status_history`. A separate table for Admin CRUD and elevation activity is deferred as roadmap task 9. Do not imply the status log covers those actions.
5. All list endpoints use server-side pagination and filtering; all writes use validated allow-listed input. Passwords and token values never appear in admin responses.

## Architecture at the time of planning

- `routes/api.php` has an empty `auth:sanctum` + `super_admin` group. `EnsureIsSuperAdmin` uses the `is-super-admin` gate, which checks `role = staff` and `staff_profiles.admin_level = super_admin`.
- `Frontend/src/views/SuperAdminView.vue` renders `AdminDashboard.vue`. The dashboard currently uses `useMockData`; new calls should go through the shared Axios instance and a dedicated `Frontend/src/services/admin.ts` module.
- `StageGenerationService` resolves request-type sequence entries `STUDENT_DEPARTMENT`, `FACULTY_RECORDS`, and literal department IDs at request creation. Editing a template must not rewrite already generated stages.
- `Programme` derives `faculty_id` from `department_id` on create and department change. Do not accept an independent programme faculty ID as a trusted write value.
- Staff can belong to multiple departments through `department_staff`; exactly one assignment per staff profile is primary for this feature. Department-admin powers follow that primary assignment.
- Stage and parent transitions already use an observer/controller audit split. Admin reads must not mutate these records.

## API contract to implement first

Use JSON under `/api/admin`, guarded by `auth:sanctum`, `super_admin`, and a suitable throttle. Use `GET` for reads, `POST` for creation, `PATCH` for edits, and `DELETE` for safe deletion. Return `data` for single resources and `data` plus Laravel pagination `meta`/`links` for paginated collections. Keep public reference endpoints' response shapes stable; extend their selected columns only where existing consumers require them.

| Endpoint | Purpose and key inputs |
| --- | --- |
| `GET/POST /faculties`, `PATCH/DELETE /faculties/{faculty}` | List and manage name, unique code, unique matricule prefix. |
| `GET/POST /departments`, `PATCH/DELETE /departments/{department}` | List and manage faculty, name, unique code, and `academic\|records\|admin` type. |
| `GET/POST /programmes`, `PATCH/DELETE /programmes/{programme}` | List and manage department, name, code, and degree type; derive faculty. Enforce `(code, department_id, degree_type)` uniqueness. |
| `GET/POST /request-types`, `PATCH/DELETE /request-types/{requestType}` | Manage name, optional description, and ordered nonempty sequence of supported tokens or existing department IDs. |
| `GET/POST /users`, `GET/PATCH/DELETE /users/{user}` | Paginated role/search list; create and edit account plus the matching profile and staff memberships. Password required on create, optional on edit. |
| `PATCH /users/{user}/admin-level` | Set `admin_level` to `null`, `dept_admin`, or `super_admin` for a staff user only. |
| `GET /stats` | Total requests, requests today, count for every request status, average resolution hours, and recent status activity. |
| `GET /requests`, `GET /requests/{request}` | Paginated oversight list with search, faculty, department, request type, status, reopened, and date filters; complete authorized detail with student, stages, attachments, and history. |
| `GET /audit-log` | Paginated `status_history` rows with request ID, optional stage ID, old/new status, nullable actor summary, note, and timestamp. Filter by request ID, actor ID, new status, and date range. |

Read/write list shape can be finalized in API tests before frontend wiring. Accept bounded `per_page` values and document defaults. Sort by newest timestamp then descending ID so pagination is deterministic. Validate date ranges and use one configured application timezone for `today` and date filters.

**Average resolution definition:** Include requests whose current status is `ready`, `collected`, or `rejected`. Measure each current lifecycle from its latest reopen event (or creation if never reopened) to its first subsequent transition to `ready` or `rejected`; collection does not extend the interval. `forwarded` is not terminal. Return `null` if no qualifying request exists, and document the unit as hours. Implement the calculation from history in a bounded aggregate/query, not by loading every request into PHP.

## Validation and integrity rules

### Reference data

- Faculty creation requires `matricule_prefix`, which the mock form lacks. Keep prefix uniqueness; reject prefix changes after students are attached so existing matricules retain their institutional meaning.
- Department creation requires `type`, which the mock form lacks. Prevent a change or deletion that would remove the only `records` department needed by a faculty with students and `FACULTY_RECORDS` request templates. Ensure records-department assumptions are covered by tests.
- Programme forms must select a department. Ensure student faculty, department, and programme relationships agree whenever a student profile is created or edited. Prevent deleting referenced programmes.
- Request-type sequences preserve order and symbolic tokens. Validate each literal department ID and token; reject empty sequences and unresolved `FACULTY_RECORDS` configurations for eligible faculties. Existing request stages are unchanged after template edits.
- Replace broad reference-model mass assignment with explicit allow-lists, or pass only fully validated and named fields to model writes. Follow `Model::preventSilentlyDiscardingAttributes()`.

### Users and staff elevation

- In a transaction, create the `users` row and exactly one matching student/staff profile. On staff create or edit, validate unique department IDs, nonempty membership, and a primary ID contained in the set; sync the pivot with exactly one `is_primary = true`.
- Normalize and uniquely validate email, matricule, and staff ID. Enforce valid level and degree enum values. Store passwords using the existing `User` hashed cast; do not serialize or prefill password fields in edit forms.
- Keep role immutable in edit requests. Staff elevation updates only `staff_profiles.admin_level`; `dept_admin` requires a valid primary department. Lock/check the current Super Admin count before a demotion or deletion that could remove the final administrator. Block self-demotion and self-deletion; revoke the affected user's Sanctum tokens when their privilege changes.
- Before any deletion, explicitly check dependent profiles, requests, handled stages, status history actor references, stage reassignment references, and other records whose removal would erase business data. Use `409` for referenced users and reference data, including dependencies hidden behind cascading foreign keys. Delete truly unused users with their token/profile/pivot rows in one transaction.

## Backend implementation order

1. Add focused admin request validators and response resources. Resolve any response-shape mismatch before writing frontend code. Add a test helper for creating a Super Admin because one is intentionally not seeded.
2. Add reference-data controllers/routes and model write allow-lists. Implement preflight dependency checks and transaction boundaries. Add authorization, validation, and safe-delete feature tests alongside them.
3. Add user administration and elevation. Use separate logic for student profiles and staff profiles, with transactionally synced department pivots. Test rollback, cross-faculty student choices, duplicate identifiers, self/last-admin guards, token revocation, and denied access for students, ordinary staff, and department admins.
4. Add stats, request oversight, and status audit read endpoints. Eager-load selected relationships, keep filtered queries in SQL, and verify counts and pagination against seeded or purpose-built fixtures. Reuse existing protected attachment streaming; do not expose a new public document URL.

## Frontend implementation order

1. Add `Frontend/src/services/admin.ts` and admin-specific TypeScript response types. Do not reuse mock `User` with its `password` property for server data. Handle nullable `changed_by` as “System”.
2. Replace the mock data source in `AdminDashboard.vue` section by section: stats/recent activity, requests/detail, users/elevation, reference management, then audit log. Preserve the current design language while adding loading, empty, mutation-pending, and error states.
3. Send request/user/audit filters to the server and reset page number on filter changes. Use returned pagination metadata rather than slicing an in-memory collection. Refresh affected lists and stats after successful writes.
4. Expand forms for faculty prefix, department type, programme department, and symbolic request-routing tokens. Keep request-type reorder changes in a local draft until Save succeeds; restore the previous order on failure.
5. Remove the mock override panel and `adminOverrideRequestStatus` call. If a rejected request is shown, use the existing reopen service/action and refresh the detail, list, stats, and audit views after success.

## Verification and finish criteria

- Backend feature tests: route permissions; every CRUD validation and relationship rule; referenced delete conflicts; request-type token resolution; account transactions and staff pivots; elevation and token revocation; last-admin protection; request filters, statistics, null audit actor, and pagination stability.
- Frontend tests: API loading/errors, a persisted create/edit/delete flow, elevation, request/audit server pagination, safe request detail/reopen behavior, and lack of mock data or arbitrary override in the Super Admin view.
- Run `php artisan test`, `npm test`, `npx vue-tsc --noEmit`, and `npm run build`. Review the dashboard manually with a Tinker-created Super Admin and seeded requests.
- Update `Docs/API.md`, `Docs/DATABASE.md`, `Docs/FEATURES.md`, `Docs/SECURITY.md`, `Docs/USER_FLOWS.md`, and `Docs/ROADMAP.md` to reflect what is actually shipped. Correct stale statements encountered in those sections. Keep roadmap task 9 open for the separate administrative action audit table.

The feature is complete when all Super Admin UI data and actions persist through the API, a refresh shows the same result, other roles receive `403`, normal request routing remains intact, referenced records cannot be erased, and the status audit log clearly represents only request/stage transitions.
