# CampusDesk — API Documentation

## Base URL

```
http://127.0.0.1:8000/api
```

## Authentication

All protected endpoints require:
```
Authorization: Bearer {token}
Accept: application/json
```

Token is obtained from `POST /api/login` or `POST /api/register`.

## Response Shape

Laravel API Resources wrap responses in a `data` key for collections and single resources:

```json
// Collection
{ "data": [ {...}, {...} ] }

// Single resource
{ "data": { "id": 1, ... } }

// Plain JSON (reference data endpoints — no wrapper)
[ {...}, {...} ]

// Simple message responses
{ "message": "Stage claimed" }
```

Frontend must unwrap `response.data.data` for resource endpoints and `response.data` for plain endpoints.

---

## Authentication Endpoints

### POST /api/register
Register a new student account.

**Auth:** None (public)
**Rate limit:** 5/minute (via `throttle:5,1` on the route)

**Request body:**
```json
{
  "name": "Nkeng Sama Mokom",
  "email": "sama@ub.cm",
  "password": "password",
  "password_confirmation": "password",
  "matricule": "FE24A001",
  "faculty_id": 1,
  "department_id": 1,
  "programme_id": 1,
  "level": "400"
}
```

**⚠️ Level values:** The `level` field must be `"100"`, `"200"`, `"300"`, `"400"`, `"500"`, or `"600"` (no `L` prefix). The database enum was changed in migration `2026_07_21_111603`. The frontend `RegisterCredentials` TypeScript type still uses `L100`–`L600` — this is a known type mismatch. Verify what `RegisterView.vue` actually sends.

**Validation:**
- `matricule`: required, unique in `student_profiles`
- `faculty_id`, `department_id`, `programme_id`: must exist in respective tables
- `level`: one of `100`, `200`, `300`, `400`, `500`, `600`
- `password`: confirmed (matches `password_confirmation`)

**Response 200:**
```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Nkeng Sama Mokom",
    "email": "sama@ub.cm",
    "role": "student",
    "student_profile": {
      "id": 1,
      "matricule": "FE24A001",
      "level": "400",
      "status": "active",
      "faculty_id": 1,
      "department_id": 1,
      "programme_id": 1
    },
    "staff_profile": null
  }
}
```

**Side effects:** Creates `users` row + `student_profiles` row. Dispatches `Registered` event.

---

### POST /api/login
Authenticate and receive a Bearer token.

**Auth:** None (public)
**Rate limit:** 5/minute

**Request body:**
```json
{ "email": "sama@ub.cm", "password": "password" }
```

**Response 200:** Same shape as register response (token + user with nested profiles).

---

### POST /api/logout
Revoke the current token.

**Auth:** Bearer token required

**⚠️ Known bug:** `AuthenticatedSessionController::destroy()` currently calls `$request->session()->invalidate()` and `$request->session()->regenerateToken()` — these will crash in the stateless API context because no session is active. The token is NOT being properly revoked. This must be fixed (see KNOWN_ISSUES.md and HANDOFF.md).

**Response 204:** No content (when it works).

---

## Reference Data Endpoints (Public)

These endpoints require no authentication. Used to populate registration and form dropdowns.

### GET /api/faculties
Returns all faculties.

**Response:** Plain JSON array (no `data` wrapper):
```json
[
  {
    "id": 1,
    "name": "Faculty of Engineering & Technology",
    "code": "FET",
    "created_at": "..."
  }
]
```

### GET /api/departments
Returns all departments with `faculty_id`, `code`, and `type`.

**Response:** Plain JSON array:
```json
[
  {
    "id": 1,
    "faculty_id": 1,
    "name": "Computer Engineering",
    "code": "CE",
    "created_at": "..."
  }
]
```

**Note:** The `type` field (`academic`/`records`/`admin`) is NOT included in the response — `ReferenceDataController::departments()` only selects `id`, `faculty_id`, `name`, `code`, `created_at`.

### GET /api/programmes
Returns all programmes with `faculty_id` and `degree_type`.

**Note on degree_type values:** The enum is now `BACHELOR`, `CERTIFICATE`, `MASTER`, `PHD`. The frontend `DegreeType` TypeScript type still uses the old values (`BSc`, `BEng`, etc.).

### GET /api/request-types
Returns all request types with their `default_department_sequence` (JSON array of literal dept IDs and/or symbolic tokens).

---

## Student Endpoints

All require: `auth:sanctum` + `student` middleware.

### GET /api/requests
Returns all requests for the authenticated student.

**Rate limit:** 60/minute
**Eager loads:** `requestType`, `attachments`, `requestStages.department`, `statusHistories`

**Note:** Despite `requestStages` being eager-loaded, the `RequestResource` only exposes `stages` via `whenLoaded('requestStages')`, so they are included in the list response. The full data is available; however the primary usage pattern is to call `GET /api/requests/{id}` for the detail view since the list is used for cards only.

**Response `data` array item:**
```json
{
  "id": 1,
  "request_type": "Official Transcript",
  "description": "Needed for MSc application.",
  "status": "pending",
  "is_reopened": false,
  "created_at": "2026-05-01T17:53:12.000000Z",
  "stages": [...],
  "status_history": [...],
  "attachments": [...]
}
```

---

### GET /api/requests/{request}
Returns a single request with full stage timeline, attachments, and status history.

**Auth:** Also accessible by staff (ownership check bypassed for staff role)
**Rate limit:** 60/minute (placed in a broader `auth:sanctum` + `throttle:60,1` group — no `student` middleware required for this route)
**Eager loads:** `requestStages`, `attachments`, `statusHistories`

**Authorization:** Student must own the request OR user must be staff (`role === 'staff'`). Returns 403 otherwise.

**Response `data`:**
```json
{
  "id": 1,
  "request_type": "Official Transcript",
  "description": "...",
  "status": "forwarded",
  "is_reopened": false,
  "created_at": "...",
  "attachments": [
    {
      "id": 1,
      "original_name": "cert.pdf",
      "file_path": "http://127.0.0.1:8000/storage/attachments/...",
      "mime_type": "application/pdf"
    }
  ],
  "stages": [
    {
      "id": 1,
      "request_id": 1,
      "department_name": "Computer Engineering",
      "sequence_order": 1,
      "status": "approved",
      "handled_by": "Dr. Mbah John",
      "staff_note": "Verified records.",
      "updated_at": "..."
    }
  ],
  "status_history": [...]
}
```

**Note on `stages`:** The `RequestStageResource` exposes `handled_by` as the staff member's NAME (string), not their ID. It is drawn from the `handled_by` relationship loaded via `RequestStage::handled_by()` → `User`.

**Note on `file_path`:** `RequestResource` maps attachments using `asset('storage/' . $file->file_path)`. This produces a public-storage URL. To actually serve the file securely, use the `GET /api/attachments/{id}` endpoint with Bearer token — not the `file_path` URL directly.

---

### POST /api/requests
Submit a new document request.

**Rate limit:** 10/minute
**Content-Type:** `multipart/form-data` if attachments, `application/json` otherwise

**Request body:**
```
request_type_id: 1
description: "Needed for MSc application abroad."
attachments[]: (file, optional, pdf/docx/jpg/png, max 5MB each)
```

**Validation:**
- `request_type_id`: required, must exist
- `description`: nullable, string, max 1000
- `attachments`: nullable array of files
- `attachments.*`: file, `mimes:pdf,docx,jpg,png`, `max:5120` (5MB)

**Authorization:** `StoreRequestRequest::authorize()` uses `$this->user()->role === 'student'` (direct role check — NOT `Gate::allows()`, which is unreliable in stateless API context).

**Response 201:** Full request resource with stages and status history.

**Side effects:**
1. Creates `requests` row
2. Loads `request_type.default_department_sequence`
3. Resolves symbolic tokens in sequence via inline `resolveSequence()` in `RequestController`
4. Creates one `request_stages` row per department in resolved sequence
5. Creates initial `status_history` entry (`changed_by: null`, note: "Request submitted by student.")
6. Stores uploaded files in `storage/app/attachments/`
7. Creates `attachments` rows

---

## Staff Endpoints

All require: `auth:sanctum` + `staff` middleware + 60/minute rate limit.

### GET /api/stages
Returns the unclaimed stage queue for the authenticated staff member's departments.

**Filter logic (concurrency-safe):**
- `status = pending`
- `handled_by IS NULL`
- `department_id IN (staff's department IDs)`
- AND (`sequence_order = 1` OR previous stage for same request with `sequence_order - 1` has `status = approved`)

The last condition uses a SQL-level `whereExists` correlated subquery. This is the production-safe implementation.

**Eager loads:** `request.requestType`, `request.student.studentProfile`, `request.attachments`, `department`, `handled_by`

**Response `data` array item:**
```json
{
  "id": 2,
  "request_id": 1,
  "department_name": "Computer Engineering",
  "sequence_order": 1,
  "status": "pending",
  "handled_by": null,
  "staff_note": null,
  "updated_at": "...",
  "request": {
    "id": 1,
    "description": "...",
    "request_type": "Transcript Request",
    "student_name": "Nkeng Sama Mokom",
    "student_matricule": "FE24A001",
    "student_level": "400",
    "created_at": "...",
    "attachments": [...]
  }
}
```

---

### GET /api/stages/my-cases
Returns stages currently claimed by the authenticated staff member (`status = in_review`, `handled_by = auth user id`).

**Response:** Plain JSON array (NOT wrapped in `data` key) — `myCases()` returns `response()->json(ResourceCollection)`.

**Response shape:** Same as queue endpoint items.

---

### GET /api/requests/{request}/stages
Returns all stages for a specific request in sequence order.

**⚠️ Known bug (route-model binding mismatch):** The route uses `{request}` as the parameter name but the controller method `forRequest(DocumentRequest $docRequest)` uses `$docRequest`. Laravel's implicit binding requires the parameter name to match the variable name, so the model is never resolved — the method always returns an empty collection. This is documented in `SequentialRoutingPreservationTest` as a pre-existing bug. See KNOWN_ISSUES.md.

---

### POST /api/requests/{docRequest}/stages/{stage}/claim
Claim an unclaimed stage.

**Route model binding:** `{docRequest}` → `DocumentRequest`, `{stage}` → `RequestStage`

**Guards:**
1. Stage must belong to the specified request (404 if not)
2. Staff must belong to the stage's department (403 if not)
3. If `sequence_order > 1`, previous stage must have `status = approved` (422 if not)
4. Stage must still be `pending` with `handled_by = null` (409 if already claimed)

**Concurrency fix:** Steps 3 and 4 are wrapped in `DB::transaction()` with `lockForUpdate()` to prevent TOCTOU race conditions.

**Response 200:**
```json
{ "message": "Stage claimed" }
```

**Side effects:**
- Sets stage `status = in_review`, `handled_by = auth user id`
- Updates parent request `status = in_review`
- A `status_history` entry is manually inserted (NOT via observer — the claim() method writes to status_history directly, then the observer also fires on the stage update, which would create a duplicate — see KNOWN_ISSUES.md)

---

### PATCH /api/requests/{docRequest}/stages/{stage}/resolve
Resolve a claimed stage (approve or reject).

**Request body:**
```json
{
  "status": "approved",
  "staff_note": "Verified and approved."
}
```

**Validation (`UpdateStageStatusRequest`):**
- `status`: required, one of `approved | rejected`
- `staff_note`: nullable, string, max 1000, `required_if:status,rejected`

**Authorization:** `UpdateStageStatusRequest::authorize()` uses `Gate::allows('is_staff')`. The gate is defined as `'is_staff'` (underscore) in `AppServiceProvider` — this matches and works correctly.

**Guards (controller):**
1. Stage must belong to specified request (404)
2. `handled_by` must equal authenticated user's ID (403)
3. Stage `status` must be `in_review` (422)

**Side effects on approval:**
- If more stages remain → parent request `status = forwarded`
- If final stage → parent request `status = ready`
- `RequestStageObserver::updated()` fires → creates status history + dispatches email notification

**Side effects on rejection:**
- Parent request `status = rejected`
- Observer fires → status history + email notification

**Response 200:**
```json
{ "message": "Stage resolved. " }
```

---

## Attachment Endpoint

### GET /api/attachments/{attachment}
Stream a protected attachment file.

**Auth:** Bearer token required (both student and staff — placed in a `auth:sanctum` only group, no role middleware)

**Authorization (controller):**
- Authenticated user is the request owner (student), OR
- Authenticated user is staff (`role === 'staff'`)

**Response:** Binary file stream via `Storage::response()` with correct `Content-Type` header.

**Frontend usage:** Fetched via Axios with `responseType: 'blob'`, displayed via `URL.createObjectURL()` in `DocumentViewer.vue`.

---

## Endpoints Not Yet Implemented

| Feature | Suggested Endpoint | Status |
|---------|-------------------|--------|
| Reopen request | `POST /api/requests/{request}/reopen` | ❌ TODO |
| Mark collected | `PATCH /api/requests/{request}/collect` | ❌ TODO |
| Get notifications | `GET /api/notifications` | ❌ TODO |
| Mark notification read | `PATCH /api/notifications/{id}/read` | ❌ TODO |
| Reassign stage (dept admin) | `PATCH /api/stages/{stage}/reassign` | ❌ TODO |
| Dept admin: list department requests | `GET /api/dept-admin/requests` | ❌ TODO |
| Admin: list all requests | `GET /api/admin/requests` | ❌ TODO |
| Admin: CRUD faculties | `* /api/admin/faculties` | ❌ TODO |
| Admin: CRUD departments | `* /api/admin/departments` | ❌ TODO |
| Admin: CRUD programmes | `* /api/admin/programmes` | ❌ TODO |
| Admin: CRUD users | `* /api/admin/users` | ❌ TODO |
| Admin: CRUD request types | `* /api/admin/request-types` | ❌ TODO |
| System stats | `GET /api/admin/stats` | ❌ TODO |
| Audit log | `GET /api/admin/audit-log` | ❌ TODO |

---

## Route Summary (actual routes from `api.php`)

```
GET  /api/user                              auth:sanctum
GET  /api/attachments/{attachment}          auth:sanctum
GET  /api/requests/{request}               auth:sanctum, throttle:60,1
GET  /api/requests                         auth:sanctum, student, throttle:60,1
POST /api/requests                         auth:sanctum, student, throttle:10,1
GET  /api/requests/{request}/stages        auth:sanctum, staff, throttle:60,1  ⚠️ binding bug
GET  /api/stages                           auth:sanctum, staff, throttle:60,1
GET  /api/stages/my-cases                  auth:sanctum, staff, throttle:60,1
POST /api/requests/{docRequest}/stages/{stage}/claim    auth:sanctum, staff
PATCH /api/requests/{docRequest}/stages/{stage}/resolve auth:sanctum, staff
GET  /api/faculties                        (public)
GET  /api/departments                      (public)
GET  /api/programmes                       (public)
GET  /api/request-types                    (public)

--- from auth.php ---
POST /api/register                         guest, throttle:5,1
POST /api/login                            guest, throttle:5,1
POST /api/forgot-password                  guest
POST /api/reset-password                   guest
GET  /api/verify-email/{id}/{hash}         auth, signed, throttle:6,1
POST /api/email/verification-notification  auth, throttle:6,1
POST /api/logout                           auth
```
