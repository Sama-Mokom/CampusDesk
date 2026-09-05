# CampusDesk — Architecture Decision Record (ADR)

Each entry: Decision, Context, Options Considered, Decision Made, Reasoning, Consequences, Status.

---

## ADR-01: Bearer Token Auth over Sanctum SPA Cookie Mode

**Context:** Vue SPA needs to authenticate against Laravel API. Sanctum supports two modes: SPA cookie mode (session-based, requires CSRF handling and `withCredentials`) or Bearer token mode (stateless, token in `Authorization` header).

**Options considered:**
- A. Sanctum SPA cookie mode
- B. Sanctum Bearer token mode

**Decision:** Bearer token mode (B).

**Reasoning:** The backend was already built and tested end-to-end with Bearer tokens via Postman before frontend integration began. Login already returned `{ token, user }`. Switching to cookie mode would mean rebuilding what was already working. Token mode is also simpler for a single-frontend SPA.

**Consequences:** `EnsureFrontendRequestsAreStateful` middleware had to be REMOVED from `bootstrap/app.php` — it caused a redirect loop that broke CORS entirely when both were present. Frontend Axios instance uses a request interceptor to attach `Authorization: Bearer {token}` from localStorage.

**Status:** ✅ Implemented and stable.

---

## ADR-02: `status_history.changed_by` References `staff_profiles.id`, Not `users.id`

**Context:** Every status transition needs to be logged with who made the change.

**Options considered:**
- A. `changed_by` → `users.id`
- B. `changed_by` → `staff_profiles.id`

**Decision:** B — `staff_profiles.id`, with `nullOnDelete()`.

**Reasoning:** Preserves audit history semantically tied to a staff role rather than a raw user account. If a staff account is deleted, the history entry becomes null rather than cascading away.

**Consequences:** This created a recurring bug — `RequestStage.handled_by` stores `users.id`, but `StatusHistory.changed_by` expects `staff_profiles.id`. Every place that logs to `status_history` must explicitly resolve:
```php
$staffProfileId = StaffProfile::where('user_id', $stage->handled_by)->value('id');
```
This mismatch caused a foreign key constraint violation multiple times during development.

**Current implementation:** Both `claim()` in `RequestStageController` and `updated()` in `RequestStageObserver` correctly resolve `staff_profile_id` before inserting.

**Status:** ✅ Implemented. Active risk: any new code path writing to `status_history` must follow the same pattern. Open question: whether to simplify both foreign keys to `users.id` — see ROADMAP.md "Needs Decision".

---

## ADR-03: Staff Can Belong to Multiple Departments

**Context:** Modeling how staff relate to departments — one department per staff member, or many-to-many?

**Options considered:**
- A. One staff = one department (simple foreign key)
- B. Staff can belong to multiple departments (pivot table)

**Decision:** B — many-to-many via `department_staff` pivot table with an `is_primary` flag.

**Reasoning:** Real university staff often have responsibilities across multiple departments. The `is_primary` flag determines default queue view and scopes department-admin powers.

**Consequences:** Every query for "staff's departments" must go through the pivot: `$staffProfile->departments`. The seeder (`DepartmentStaffSeeder`) assigns each department one primary staff member and distributes remaining staff as secondaries.

**Status:** ✅ Implemented.

---

## ADR-04: Two-Tier Admin System (`dept_admin` / `super_admin`) via `staff_profiles.admin_level`

**Context:** Need to model administrative privilege levels.

**Options considered:**
- A. Separate `Admin` role entirely, disconnected from staff
- B. Staff can be elevated to admin (single flag)
- C. Multiple admin levels stored on `staff_profiles`

**Decision:** C — `admin_level` nullable enum column on `staff_profiles`: `null` (plain staff), `dept_admin`, `super_admin`.

**Reasoning:** Admins are always staff first. A nullable enum avoids restructuring the whole record when someone is elevated.

**Consequences:** All admin authorization checks must check both `role === 'staff'` AND `admin_level`. Gates encode this compound check. The seeder auto-elevates each department's primary staff to `dept_admin`.

**Status:** ✅ Data model implemented. ❌ Dept Admin and Super Admin backend routes are NOT yet implemented.

---

## ADR-05: Stage Claiming — "Any Staff Can Pick Up, But Then It Gets Locked to Them"

**Context:** How does stage assignment work when multiple staff can belong to the same department?

**Options considered:**
- A. Any staff can act on any stage at any time (no locking)
- B. Stage must be explicitly assigned by admin
- C. Any staff can pick up (claim) an unclaimed stage, which then locks it to them

**Decision:** C.

**Reasoning:** Mirrors a real physical queue — first to pick up a ticket owns it.

**Consequences:** Required careful concurrency handling. This led to the multi-claim concurrency bug where Stage 2 was claimable before Stage 1 was approved. Fixed with:
1. SQL-level `whereExists` predecessor check in the queue query
2. `DB::transaction()` + `lockForUpdate()` in `claim()`

PHPUnit tests in `SequentialRoutingPreservationTest` and `SequentialRoutingBugConditionTest` lock this behaviour in.

**Status:** ✅ Implemented, concurrency-safe. Tests written.

---

## ADR-06: Rejected Requests Are Closed But Reopenable (Not Editable In-Place)

**Context:** What happens when a request is rejected?

**Options considered:**
- A. Rejected requests are permanently closed
- B. Student can revise and resubmit the same request
- C. Request is closed but student can reopen it (fresh stage sequence spawned)

**Decision:** C.

**Reasoning:** Explicit decision from the developer: "It is closed completely but the student can reopen it." Editing in place would corrupt the original rejection's audit trail.

**Consequences:** Reopening sets `is_reopened = true` on the `requests` row and spawns an entirely new set of `request_stages` from the `default_department_sequence`, leaving the original rejection in `status_history` untouched.

**Status:** ❌ NOT IMPLEMENTED. No backend endpoint exists. `doReopen()` in `StudentDashboard.vue` is a stub. `StageGenerationService` exists and should be used when implementing this.

---

## ADR-07: Request Types Support Symbolic Department Tokens

**Context:** `default_department_sequence` needs to handle cases where the routing depends on the specific requesting student (e.g. "their own department").

**Options considered:**
- A. Always store raw department IDs
- B. Support symbolic tokens (`STUDENT_DEPARTMENT`, `FACULTY_RECORDS`) resolved per-student at request-creation time

**Decision:** B — hybrid sequences with symbolic tokens and literal IDs.

**Reasoning:** A request type like "Official Transcript" must route through "the student's own department" — this varies per student. Symbolic tokens let one `request_types` row serve all students.

**Consequences:** `resolveSequence()` in `RequestController` maps:
- `"STUDENT_DEPARTMENT"` → `$profile->department_id`
- `"FACULTY_RECORDS"` → `Department::where('faculty_id', ...)->where('type', 'records')->first()`
- Integer → used as-is

`StageGenerationService` provides the canonical, service-level implementation of the same logic. The `departments.type` column (confirmed present via migration) is what `FACULTY_RECORDS` resolution depends on.

**Current implementation:** `RequestController` has an inline `resolveSequence()` private method. `StageGenerationService` provides the same logic extracted into a service class. When building the reopen endpoint, use `StageGenerationService` directly and eventually replace the inline method.

**Status:** ✅ VERIFIED and implemented. `departments.type` column confirmed to exist.

---

## ADR-08: File Attachments Served Through a Protected Controller, Not Public Storage

**Context:** Uploaded documents need to be viewable client-side without being publicly accessible.

**Options considered:**
- A. Store in `storage/app/public/`, symlink, serve as static files
- B. Store in private `storage/app/attachments/`, serve through an authenticated controller

**Decision:** B.

**Reasoning:** Attachments contain sensitive academic records. A university document system should not expose them via guessable public URLs.

**Consequences:** `AttachmentController::show()` checks ownership or staff role before streaming via `Storage::response()`. Frontend cannot use plain `<img src>` or `<iframe src>` with a Bearer token — instead fetches as a blob via Axios and creates a temporary `URL.createObjectURL()` for display.

**Status:** ✅ Implemented and working. Unit tests for `DocumentViewer.vue` exist.

---

## ADR-09: Frontend State Management — No Pinia/Vuex, Composable Pattern Instead

**Context:** Vue SPA needs shared/global state for auth (current user, token).

**Options considered:**
- A. Pinia (Vue's recommended state library)
- B. Vuex (legacy)
- C. Custom composable with module-level refs

**Decision:** C — `useAuth.ts` composable with a module-level `ref<User | null>`.

**Reasoning:** The original mock data layer (`useMockData.ts`) already used this pattern. Rather than introducing a new dependency during integration, the same pattern was extended to real auth state, backed by `localStorage` for persistence.

**Consequences:** Simpler dependency footprint. Less scalable than Pinia if the admin dashboards introduce significant shared state.

**Status:** ✅ Implemented and working.

---

## ADR-10: API Resources Use `whenLoaded()` for Conditional Relationship Exposure

**Context:** API Resources need to expose relationships conditionally to avoid N+1 queries.

**Decision:** Use Laravel's `whenLoaded()` on all relationship fields, and have controllers explicitly eager-load only what's needed per endpoint.

**Consequences:** `RequestResource`, `RequestStageResource`, and `UserResource` all use `whenLoaded()`. Controllers call `->with([...])` with different relationship sets depending on the endpoint (list vs. detail). Frontend code assumes the fields it expects are present for a given call — this creates a soft coupling.

**Status:** ✅ Implemented.

---

## ADR-11: Automated Seeder Suite with Parsed University Data

**Context:** Initial development used manually-created Tinker commands to seed staff and test data. As the data model grew more complex (27 migrations, `departments.type`, programmes with `department_id`, matricule prefixes on faculties), manual seeding became impractical and fragile.

**Decision:** Build a comprehensive, fully-automated seeder suite driven by a parsed University of Buea faculty/department/programme data markdown file.

**Reasoning:** Reproducible, realistic test data that mirrors the actual target university's structure is more useful for testing the routing logic (which depends on faculty/department relationships) than toy data. Parsing from a structured document also catches structural assumptions in the code (e.g. that every faculty has a records department).

**Consequences:** `php artisan migrate:fresh --seed` now produces a complete dataset including ~80 staff, students in every department, and all department-staff assignments. Super admin accounts must still be created manually via Tinker (never seeded — by design).

**Implementation:** `FacultyMarkdownParser`, `FacultyMatriculeMapper`, `DepartmentTypeMapper`, `StudentDistributionHelper` in `database/seeders/support/`. `UserFactory` handles profile creation with proper matricule generation.

**Status:** ✅ Implemented and working.
