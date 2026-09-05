# CampusDesk — Known Issues, Bugs & Lessons Learned

This document is a knowledge base of every significant bug encountered during development, its root cause, and its resolution. Future agents should read this before touching related code to avoid repeating the same mistakes.

---

## 🔴 ACTIVE BUGS (not yet fixed)

---

### 🔴 ACTIVE — Logout Endpoint Crashes in API Context

**Symptom:** `POST /api/logout` crashes with a session-related error because `AuthenticatedSessionController::destroy()` calls `$request->session()->invalidate()` and `$request->session()->regenerateToken()`. The session driver is not active on API routes.

**Root cause:** The default Breeze-generated `destroy()` method is designed for cookie/session-based auth. This project uses stateless Bearer token auth. The session methods have no valid session to operate on.

**Impact:** Logout does not revoke the Sanctum token. The user's token remains valid even after "logout". The frontend clears `localStorage` on its side (via `clearAuth()`), but the token itself is not invalidated on the server.

**Fix needed:**
```php
public function destroy(Request $request): Response
{
    $request->user()->currentAccessToken()->delete();
    return response()->noContent();
}
```

**Status:** ❌ NOT FIXED — active bug.

---

### 🔴 ACTIVE — `forRequest()` Route-Model Binding Mismatch (Empty Stage Timeline)

**Symptom:** `GET /api/requests/{request}/stages` always returns an empty `data` array, regardless of which request ID is in the URL.

**Root cause:** The route defines `{request}` as the parameter name, but the controller method signature is `forRequest(DocumentRequest $docRequest)`. Laravel's implicit route-model binding resolves by matching the `{parameter}` name to the `$variable` name in the controller. Since `{request}` ≠ `$docRequest`, the model is never resolved and the method receives an empty/default model.

**Evidence:** Explicitly documented in `SequentialRoutingPreservationTest::test_for_request_endpoint_returns_200_for_staff()` as a pre-existing known bug.

**Fix needed:** Change the route parameter to match:
```php
Route::get('/requests/{docRequest}/stages', [RequestStageController::class, 'forRequest']);
```

**Status:** ❌ NOT FIXED — active bug. The Staff Dashboard works around this by using `GET /api/requests/{id}` (the `show()` endpoint) for viewing full stage timelines instead of this endpoint.

---

### 🔴 ACTIVE — `is_dept-admin` Gate Name Inconsistency

**Symptom:** The `dept_admin` middleware always returns 403, even for users with `admin_level = 'dept_admin'`.

**Root cause:** `AppServiceProvider` defines the gate as `'is_dept-admin'` (underscore at start, hyphen separator). `EnsureIsDeptAdmin::handle()` checks `Gate::allows('is-dept-admin')` (all hyphens). The strings do not match, so the gate check always fails.

```php
// AppServiceProvider (BUG):
Gate::define('is_dept-admin', fn(User $user) => ...);

// EnsureIsDeptAdmin (expects all-hyphen):
if (! Gate::allows('is-dept-admin')) { ... }
```

**Fix needed:** In `AppServiceProvider`, change `'is_dept-admin'` to `'is-dept-admin'`.

**Status:** ❌ NOT FIXED — active bug. Has no user-visible impact yet because no `dept_admin` routes exist.

---

### 🔴 ACTIVE — Frontend `RegisterCredentials` Type Mismatch on `level`

**Symptom:** The TypeScript type `RegisterCredentials.level` in `Frontend/src/types/index.ts` is typed as `'L100' | 'L200' | 'L300' | 'L400' | 'L500' | 'L600'`. The backend validation rule is `in:100,200,300,400,500,600` (no `L` prefix). If the frontend sends `'L400'`, the backend returns 422 validation error.

**Root cause:** The `level` enum on `student_profiles` was changed from `L100`-`L600` to `100`-`600` in migration `2026_07_21_111603`, but the frontend TypeScript types were not updated to match.

**Impact:** May cause registration failures. Needs verification of what `RegisterView.vue` actually sends.

**Fix needed:**
1. Update `RegisterCredentials.level` in `types/index.ts` to `'100' | '200' | '300' | '400' | '500' | '600'`
2. Update `StudentLevel` type to match
3. Verify and update any dropdown options in `RegisterView.vue`

**Status:** ❌ NOT FIXED — active type mismatch.

---

### 🔴 ACTIVE — Frontend `DegreeType` Type Uses Old Enum Values

**Symptom:** The TypeScript type `DegreeType` in `Frontend/src/types/index.ts` is `'BSc' | 'BEng' | 'MEng' | 'MSc' | 'PhD'`. The database enum is now `BACHELOR | CERTIFICATE | MASTER | PHD`.

**Root cause:** The `degree_type` enum was changed in migration `2026_07_27_000002` but the frontend type was not updated.

**Impact:** Any dropdown or display component that shows degree types will use incorrect values. The `Programme.degree_type` field in API responses will now return `BACHELOR`, etc.

**Fix needed:** Update `DegreeType` in `types/index.ts`:
```typescript
export type DegreeType = 'BACHELOR' | 'CERTIFICATE' | 'MASTER' | 'PHD'
```

**Status:** ❌ NOT FIXED — active type mismatch.

---

## 🟡 RESOLVED BUGS (historical — read before touching related code)

---

### ✅ RESOLVED — Multi-Claim Concurrency Bug

**Symptom:** A request with stage sequence `[dept A, dept B]` had its Stage 2 (dept B) claimed by a staff member BEFORE Stage 1 (dept A) was approved. Both stages ended up `in_review` simultaneously.

**Root cause (two-part):**
1. The staff queue (`index()`) filtered only by `status = pending` and `handled_by = null` — it never checked whether the previous stage in the sequence had been approved.
2. The `claim()` endpoint had a TOCTOU race condition: it checked predecessor approval as a separate query, then performed the claim as a second, unrelated query.

**Fix:**
1. `index()` — added SQL-level `whereExists` correlated subquery: a stage is included only if `sequence_order = 1` OR a sibling stage with `sequence_order - 1` on the same `request_id` has `status = 'approved'`.
2. `claim()` — wrapped the check-then-act in `DB::transaction()` with `->lockForUpdate()` on both the target stage and the predecessor row.

**Regression tests:** `SequentialRoutingBugConditionTest` and `SequentialRoutingPreservationTest` in `tests/Feature/` lock this fix in.

**Status:** ✅ RESOLVED.

---

### ✅ RESOLVED — Foreign Key Violation: `status_history.changed_by`

**Symptom:** Repeated `SQLSTATE[23000]: Cannot add or update a child row... FOREIGN KEY (changed_by) REFERENCES staff_profiles (id)` errors when claiming or resolving stages.

**Root cause:** `status_history.changed_by` has a foreign key to `staff_profiles.id`, but code was passing `Auth::id()` or `$stage->handled_by`, both of which are `users.id` values.

**Fix:** Every insertion into `status_history.changed_by` must first resolve the staff profile ID:
```php
$staffProfileId = \App\Models\StaffProfile::where('user_id', $userId)->value('id');
```

**Current implementation:** Both `claim()` in `RequestStageController` and `updated()` in `RequestStageObserver` correctly resolve `staff_profile_id` before inserting.

**Status:** ✅ RESOLVED. **⚠️ Reintroduction risk is high** — any new code path that writes to `status_history` must follow this pattern.

---

### ✅ RESOLVED — Recurring Pattern: Silent Data Loss via Missing `$fillable`

**Symptom:** Multiple instances where a field was correctly present in the request payload but silently discarded by `Model::create()`, resulting in `null` values.

**Occurrences:**
- `StudentProfile` — `faculty_id`, `department_id`, `programme_id`, `matricule`, `level`
- `RequestStage` — `sequence_order`
- `Attachment` — various fields
- `StatusHistory` — `request_stage_id`

**Root cause:** Laravel's mass-assignment protection (`$fillable`) silently ignores keys not listed.

**Lesson:** **Whenever a field is null after create/update despite being in the input, check `$fillable` first.** Always update `$fillable` immediately when adding a migration column.

**Current status:** All actively-used fields confirmed in `$fillable`. `Notification` model has no `$fillable` — this will matter when notification endpoints are built.

**Status:** ✅ Resolved for all currently-used fields. Live risk for new fields.

---

### ✅ RESOLVED — Gate Name Inconsistency (Partial)

**Symptom:** `EnsureIsStudent` and `EnsureIsStaff` were calling `Gate::allows('is_student')` / `Gate::allows('is_staff')` with mismatched names.

**Current state:** `is_student` and `is_staff` (underscores) are consistent between `AppServiceProvider` and their respective middleware. The `is-super-admin` (all hyphens) is also consistent. However, `is_dept-admin` remains inconsistent (see active bugs above).

**Status:** ✅ Partially resolved. `is_student`/`is_staff`/`is-super-admin` work. `is_dept-admin` is still broken.

---

### ✅ RESOLVED — Form Request `authorize()` Using `Gate::allows()` Without Explicit User (API Context)

**Symptom:** 403 on `StoreRequestRequest` for a correctly authenticated student.

**Root cause:** `Gate::allows('is-student')` in a Form Request's `authorize()` method tries to resolve the user through the default web guard/session — which doesn't exist in a stateless API context.

**Fix:** `StoreRequestRequest::authorize()` now uses `$this->user()->role === 'student'` directly.

**Note:** `UpdateStageStatusRequest::authorize()` uses `Gate::allows('is_staff')`. Since `is_staff` is defined and works correctly (it uses underscore naming consistently), this works.

**Status:** ✅ RESOLVED.

---

### ✅ RESOLVED — `EnsureFrontendRequestsAreStateful` Broke CORS

**Symptom:** Browser fetch to `/api/login` failed with "No 'Access-Control-Allow-Origin' header".

**Root cause:** Sanctum's SPA-cookie-mode middleware was present alongside Bearer token auth and issued a redirect before CORS headers could be attached.

**Fix:** Removed `EnsureFrontendRequestsAreStateful` from `bootstrap/app.php`. Added `HandleCors` explicitly prepended.

**Status:** ✅ RESOLVED.

---

### ✅ RESOLVED — Axios `Content-Type` Default Broke `FormData` File Uploads

**Symptom:** File uploads arrived as `{}` instead of binary data.

**Root cause:** Axios instance had a default `Content-Type: application/json` that overrode automatic multipart detection for `FormData`.

**Fix:** Removed `Content-Type` from Axios instance defaults. Axios now sets it per-request automatically.

**Status:** ✅ RESOLVED.

---

### ✅ RESOLVED — Native Browser `Request` Type Shadowing Custom TypeScript Interface

**Symptom:** TypeScript errors like `Property 'data' does not exist on type 'Request[]'`.

**Root cause:** `lib.dom.d.ts` defines a built-in `Request` (Fetch API) type that collides with the custom `Request` interface.

**Fix:** Always alias on import: `import type { Request as DocumentRequest } from '../types'`. Same alias pattern used on the PHP side (`use App\Models\Request as DocumentRequest`).

**Status:** ✅ RESOLVED — convention applied consistently.

---

### ✅ RESOLVED — Route Model Binding Parameter Name Mismatches

**Symptom:** `Argument #3 ($stage) must be of type App\Models\RequestStage, string given`.

**Root cause:** Route `{parameter}` names must exactly match controller method variable names for implicit model binding.

**Current routes:** `claim` and `resolve` use `{docRequest}` and `{stage}` matching `DocumentRequest $docRequest` and `RequestStage $stage`. These work correctly.

**Outstanding:** `forRequest()` still has the mismatch (see active bugs above).

**Status:** ✅ Resolved for `claim`/`resolve`. ❌ Still broken for `forRequest`.

---

### ✅ RESOLVED — Attachments Returning 403 (Public Storage Bug)

**Symptom:** Clicking to view an uploaded file returned 403.

**Root cause:** Files stored in `storage/app/attachments/` (private) were treated as public URLs.

**Fix:** `AttachmentController::show()` authenticates and streams files. Frontend uses Axios blob fetch + `URL.createObjectURL()`.

**Status:** ✅ RESOLVED.

---

### ✅ RESOLVED — Wrong Endpoint Used for Staff "View Request Details" (Empty Timeline)

**Symptom:** "View details" on a stage in Staff Dashboard showed empty stage timeline.

**Root cause:** Frontend was calling `fetchRequestStages(requestId)` which hits the staff queue endpoint — that endpoint deliberately filters to only actionable/unclaimed stages, so claimed/resolved stages disappeared.

**Fix:** Staff view now uses `GET /api/requests/{id}` (the `show()` endpoint) with relaxed ownership check (`isOwner || isStaff`).

**Status:** ✅ RESOLVED.

---

### ✅ RESOLVED — Hardcoded IDs in Seeders

**Symptom:** "duplicate entry" and "base table not found" errors when re-running seeders.

**Fix:** Seeders now use `updateOrCreate()` with stable unique fields, and `Faculty::where('code', ...)->value('id')` patterns instead of hardcoded IDs.

**Status:** ✅ RESOLVED — fully automated seeder suite with no hardcoded IDs.

---

## ⚪ PREVIOUSLY UNVERIFIED ITEMS — NOW RESOLVED

These items were marked UNVERIFIED in the original documentation. They have since been confirmed against actual source files:

1. ✅ **`departments.type` column** — CONFIRMED EXISTS. Migration `2026_07_27_000001_add_type_to_departments_table`. Enum: `academic|records|admin`. `Department.$fillable` includes `type`.

2. ✅ **Symbolic department sequence tokens** — CONFIRMED INTENTIONAL. `StageGenerationService.php` provides the canonical implementation. `RequestController` still has an inline `resolveSequence()` copy — these are functionally equivalent.

3. ✅ **Current live `RequestStageController::index()` implementation** — CONFIRMED uses SQL `whereExists` correlated subquery for the main staff queue path. There is a dead code branch at the top of `index()` (the `$docRequest` branch) that uses the old PHP-level filter — this is unreachable from any current route.

4. ✅ **`StatusHistory.$fillable` includes `request_stage_id`** — CONFIRMED. Current `$fillable`: `['new_status', 'old_status', 'changed_by', 'request_id', 'request_stage_id', 'note']`.

5. ✅ **`RequestStageObserver::created()` logging behaviour** — CONFIRMED that `created()` is empty (no status history logged on stage creation). Only `updated()` logs transitions.

6. ✅ **`.env` gitignore status** — CONFIRMED. `campusdesk/.gitignore` lists `.env`. The `.env` file is not committed.
