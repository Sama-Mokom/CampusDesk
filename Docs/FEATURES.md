# CampusDesk — Features & Implementation Status

Status legend: ✅ IMPLEMENTED · 🟡 PARTIALLY IMPLEMENTED · ❌ PLANNED/TODO

---

## Feature 1: Student Registration ✅ IMPLEMENTED

**Purpose:** Allow a new student to create an account tied to their academic identity.

**Actors:** Prospective student (unauthenticated)

**Main flow:**
1. Student visits `/register`
2. Frontend loads faculties, departments, programmes via `GET /api/faculties`, `/departments`, `/programmes` on mount
3. Student fills form: name, email, password, matricule, faculty (dropdown), department (filtered by faculty), programme (filtered by faculty), level
4. Frontend submits `POST /api/register` with `password_confirmation` duplicated from `password`
5. Backend validates all fields, creates `User` (role=student) + `StudentProfile`
6. Backend returns `{ token, user }` with nested `student_profile`
7. Frontend stores token + user in localStorage via `useAuth().setUser()`
8. Frontend redirects to `/student`

**Validation:**
- Email: unique, lowercase
- Matricule: unique in `student_profiles`
- Faculty/department/programme IDs: must exist
- Level: one of `100`, `200`, `300`, `400`, `500`, `600` (no `L` prefix)
- Password: confirmed

**⚠️ Known type mismatch:** The frontend `RegisterCredentials` TypeScript type defines `level` as `'L100' | 'L200' | ...`. The backend expects `'100' | '200' | ...`. Verify what `RegisterView.vue` actually submits.

**Database interactions:** INSERT into `users`, INSERT into `student_profiles`.

---

## Feature 2: Login (Student & Staff) ✅ IMPLEMENTED

**Purpose:** Authenticate any user and issue a Bearer token.

**Actors:** Student, Staff (plain), Dept Admin, Super Admin (all use the same login form/endpoint)

**Main flow:**
1. User submits email + password to `LoginView.vue`
2. `POST /api/login`
3. Backend validates credentials, issues Sanctum token via `createToken()`
4. Backend returns `{ token, user }` — `user` includes nested `student_profile` OR `staff_profile` depending on role
5. Frontend stores token + user
6. Frontend redirects based on role:
   - `role === 'student'` → `/student`
   - `role === 'staff'`, `admin_level === null` → `/staff`
   - `admin_level === 'dept_admin'` → `/dept-admin`
   - `admin_level === 'super_admin'` → `/admin`

**Database interactions:** SELECT on `users`, INSERT into `personal_access_tokens` (Sanctum).

---

## Feature 3: Submit Document Request ✅ IMPLEMENTED

**Purpose:** Student initiates a document request that will flow through one or more departments.

**Actors:** Student

**Main flow:**
1. Student selects a request type from dropdown (populated from `GET /api/request-types`)
2. Student writes description, optionally attaches files (PDF/DOCX/JPG/PNG, max 5MB each)
3. Frontend submits `POST /api/requests` as `multipart/form-data` (if files) or JSON
4. Backend, in a DB transaction:
   - Creates `requests` row (status: pending)
   - Stores uploaded files, creates `attachments` rows
   - Loads `request_type.default_department_sequence`, resolves symbolic tokens via `resolveSequence()`
   - Creates one `request_stages` row per department in the resolved sequence, in order
   - Creates initial `status_history` entry (old_status: null, new_status: pending, changed_by: null)
5. Backend returns the full created request with nested `stages`, `attachments`, `status_history`

**Seeded request types:**
- Transcript Request (`STUDENT_DEPARTMENT` → `FACULTY_RECORDS` → TRD dept)
- Attestation of Enrollment (`STUDENT_DEPARTMENT` → `FACULTY_RECORDS` → AOE dept)
- Attestation of Completion of Degree (`STUDENT_DEPARTMENT` → `FACULTY_RECORDS` → AOC dept)
- Correction of Transcript (`STUDENT_DEPARTMENT` → `FACULTY_RECORDS` → TRD dept)

**Database interactions:** INSERT `requests`, INSERT `attachments` (0+), INSERT `request_stages` (1+), INSERT `status_history` (1).

---

## Feature 4: Track Request Status (Student Dashboard) ✅ IMPLEMENTED

**Purpose:** Student views all their requests and drills into detail for any one of them.

**Actors:** Student

**Main flow:**
1. Dashboard loads on mount: `GET /api/requests`
2. Dashboard shows: profile header (name, matricule, faculty/department names, level badge), stats strip (total / pending / ready for collection), list of requests
3. Clicking a request card calls `GET /api/requests/{id}` for full detail
4. Detail modal shows: description, status badge, reopened flag, attachments (via `DocumentViewer`), stage timeline (department name, sequence order, stage status, staff note, handler name), full status history log
5. If status is `rejected`, a "Reopen Request" button is shown — ❌ NOT WIRED (stub)
6. If status is `ready`, a "Mark as Collected" button is shown — ❌ NOT WIRED (stub)

**Database interactions:** SELECT `requests` with eager-loaded `requestType`, `attachments`, `requestStages`, `statusHistories`.

---

## Feature 5: Staff Queue & Pickup ✅ IMPLEMENTED

**Purpose:** Staff see requests waiting for action in their department(s) and claim them.

**Actors:** Staff (any admin_level)

**Main flow:**
1. Staff dashboard loads: `GET /api/stages` (unclaimed queue) and `GET /api/stages/my-cases` (active claims) in parallel
2. Queue shows only actionable stages — a stage only appears if it's the first in its sequence OR its predecessor has `status = approved` (SQL-level `whereExists` filter)
3. Staff clicks "Pick Up" → `POST /api/requests/{docRequest}/stages/{stage}/claim`
4. Backend verifies department membership, predecessor approval, and atomically claims inside a locked transaction
5. Stage moves from queue to "My Active Cases"

**Concurrency safety:** Both the queue filter and the claim endpoint enforce sequential ordering. PHPUnit tests in `tests/Feature/` verify and protect this behaviour.

**Database interactions:** SELECT `request_stages` (filtered), UPDATE `request_stages` (claim), INSERT `status_history`.

---

## Feature 6: Staff Resolve Stage (Approve/Reject) ✅ IMPLEMENTED

**Purpose:** Staff approve or reject a stage they have claimed.

**Actors:** Staff who currently holds the claim (`handled_by = auth user id`)

**Main flow:**
1. Staff opens resolve modal, selects `approved` or `rejected`, optionally/required enters a staff note
2. Frontend submits `PATCH /api/requests/{docRequest}/stages/{stage}/resolve`
3. Backend verifies: stage belongs to request, `handled_by` matches auth user, stage status is `in_review`
4. On approval: parent request → `forwarded` (if more stages remain) or `ready` (if final stage)
5. On rejection: parent request → `rejected`
6. `RequestStageObserver::updated()` fires: logs `status_history`, dispatches email notification

**Validation:** `status` required (`approved|rejected`), `staff_note` required if rejecting.

**Database interactions:** UPDATE `request_stages`, UPDATE `requests` (status), INSERT `status_history`.

---

## Feature 7: View Request Details (Staff) ✅ IMPLEMENTED

**Purpose:** Staff review a request's full description, attachments, and stage progression.

**Main flow:**
1. Staff clicks "View Details" on a queued or active stage
2. Frontend fetches `GET /api/requests/{id}` (reused from student endpoint — ownership check includes `role === 'staff'`)
3. Modal shows: description, full stage timeline (ALL stages regardless of status), attachments, status history

**Note:** The `GET /requests/{request}/stages` endpoint (`forRequest()`) is NOT used for this — it always returns empty due to a route-model binding bug. The student `show()` endpoint with relaxed ownership check is used instead.

**Database interactions:** SELECT `requests` with `requestStages`, `attachments`, `statusHistories`.

---

## Feature 8: View Document Attachments (Both Roles) ✅ IMPLEMENTED

**Purpose:** Securely preview uploaded documents inline.

**Actors:** Student (own requests) and Staff (any request)

**Main flow:**
1. User clicks attachment in `DocumentViewer.vue`
2. Frontend calls `GET /api/attachments/{attachment}` via Axios with `responseType: 'blob'`
3. Backend verifies ownership (student) or role (staff), streams the file
4. Frontend creates `URL.createObjectURL(blob)` and displays in `<img>` (images) or `<iframe>` (PDFs)
5. "Open in new tab" button opens blob URL in a new window
6. Deselecting calls `URL.revokeObjectURL()` to free memory

**Unit tests:** `DocumentViewer.spec.ts` covers empty state, image display, PDF display, unsupported type fallback, and toggle behaviour.

---

## Feature 9: Email Notifications on Status Change ✅ IMPLEMENTED

**Purpose:** Notify the student by email whenever any stage of their request changes status.

**Main flow:**
1. Any `RequestStage` model update where `status` is dirty triggers `RequestStageObserver::updated()`
2. Observer creates the `status_history` entry (resolving `staff_profile_id` from `user_id`)
3. Observer dispatches `SendRequestStatusNotification::dispatch($student, $request, $stage->status)` for transitions to `in_review`, `approved`, or `rejected`
4. Job pushed to `jobs` table — requires `php artisan queue:work` (or `composer run dev`)
5. Job sends `RequestStatusUpdated` mailable → Mailtrap in dev

**Why queued (not synchronous):** Decouples email from the HTTP request cycle; allows automatic retry on mail server failure.

**Database interactions:** INSERT `status_history`, INSERT `jobs` table. The `notifications` table is NOT written by this flow (in-app notifications are a separate unimplemented feature).

---

## Feature 10: Reopen a Rejected Request ❌ NOT IMPLEMENTED

**Purpose:** Allow a student to resubmit a rejected request.

**Design decision (ADR-06):** Spawn fresh `request_stages` from the `default_department_sequence`, leaving original rejection in `status_history` untouched.

**What needs to be built:**
- Backend: `POST /api/requests/{request}/reopen`
  - Guard: request must belong to student, `status` must be `rejected`
  - Set `is_reopened = true`, `status = pending`
  - Use `StageGenerationService` to resolve the sequence (do not duplicate `resolveSequence()`)
  - Log `status_history` entry
- Frontend: wire `doReopen()` in `StudentDashboard.vue`

**Status:** ❌ NOT IMPLEMENTED. Backend stub: none. Frontend stub: `doReopen()` logs to console.

---

## Feature 11: Mark Request as Collected ❌ NOT IMPLEMENTED

**Purpose:** Student confirms physical collection, closing the request lifecycle.

**What needs to be built:**
- Backend: `PATCH /api/requests/{request}/collect`
  - Guard: request must belong to student, `status` must be `ready`
  - Set `status = collected`
  - Log `status_history` entry
- Frontend: wire `doCollected()` in `StudentDashboard.vue`

**Status:** ❌ NOT IMPLEMENTED. Frontend stub: `doCollected()` logs to console.

---

## Feature 12: In-App Notifications (Bell/Dropdown) ❌ NOT IMPLEMENTED

**Purpose:** Show unread notification count and list in the app UI (separate from email).

**Current state:** `notifications` table exists. `Notification` model exists but has no `$fillable`. `NotificationBell.vue` exists but uses `useMockData`. The email-notification observer does NOT write to the `notifications` table.

**What needs to be built:**
- Add `$fillable` to `Notification` model
- Backend: `GET /api/notifications`, `PATCH /api/notifications/{id}/read`
- Decide: should the email observer also create `notifications` rows for in-app display?
- Frontend: wire `NotificationBell.vue`

**Status:** ❌ NOT IMPLEMENTED.

---

## Feature 13: Department Admin Dashboard ❌ NOT IMPLEMENTED (mock UI only)

**Purpose:** Dept admins see all requests through their primary department, can reassign stages, view department-level stats.

**Current state:** `DeptAdminView.vue` exists with fully mock-data-driven UI. No backend routes exist. The `dept_admin` middleware group in `api.php` is empty. Additionally, the `is_dept-admin` gate has a naming bug that would prevent the middleware from working even if routes were added.

**What needs to be built:**
- Fix `is_dept-admin` gate name in `AppServiceProvider` first
- Backend: `GET /api/dept-admin/requests`, `PATCH /api/dept-admin/stages/{stage}/reassign`
- Frontend: replace `useMockData` in `DeptAdminView.vue`

**Status:** ❌ NOT IMPLEMENTED.

---

## Feature 14: Super Admin Dashboard ❌ NOT IMPLEMENTED (mock UI only)

**Purpose:** Full system administration.

**Current state:** `AdminDashboard.vue` and `SuperAdminView.vue` exist with fully mock-driven UI (the mock UI is quite detailed). No backend routes exist.

**What needs to be built:**
- CRUD endpoints for faculties, departments, programmes, request types, users
- Staff elevation endpoint
- System-wide stats, audit log endpoints
- Frontend wiring of `AdminDashboard.vue`

**Status:** ❌ NOT IMPLEMENTED.

---

## Feature Status Summary

| Feature | Backend | Frontend | Overall |
|---|---|---|---|
| Student registration | ✅ | ✅ | ✅ |
| Login (all roles) | ✅ | ✅ | ✅ |
| Submit request | ✅ | ✅ | ✅ |
| Track requests (student) | ✅ | ✅ | ✅ |
| Staff queue & pickup | ✅ | ✅ | ✅ |
| Staff resolve stage | ✅ | ✅ | ✅ |
| View request details (staff) | ✅ | ✅ | ✅ |
| View attachments (both roles) | ✅ | ✅ | ✅ |
| Email notifications | ✅ | N/A | ✅ |
| Reopen request | ❌ | 🟡 (stub) | ❌ |
| Mark collected | ❌ | 🟡 (stub) | ❌ |
| In-app notifications | ❌ | 🟡 (mock UI) | ❌ |
| Dept Admin dashboard | ❌ | 🟡 (mock UI) | ❌ |
| Super Admin dashboard | ❌ | 🟡 (mock UI) | ❌ |
| Logout (token revocation) | ⚠️ BUG | ✅ (clears localStorage) | ⚠️ |
