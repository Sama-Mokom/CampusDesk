# CampusDesk — API Documentation

**Last reviewed:** 21 October 2026

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
Protected endpoints also pass through the `active_account` middleware, which ensures that accounts with a non-null `disabled_at` timestamp are blocked immediately with HTTP 403.

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

Frontend unwraps `response.data.data` for resource endpoints and `response.data` for plain endpoints.

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

**Level values:** The `level` field must be `"100"`, `"200"`, `"300"`, `"400"`, `"500"`, or `"600"` (no `L` prefix).

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

**Response 200:** Same shape as register response (token + user with nested profiles). Disabled accounts (`disabled_at !== null`) are denied authentication with generic credential failure credentials.

---

### POST /api/logout
Revoke the current token.

**Auth:** Bearer token required

Deletes the current Sanctum personal access token and returns HTTP 204.

**Response 204:** No content.

---

## Real-Time Broadcasting & WebSockets

CampusDesk uses Laravel Reverb and Laravel Echo for real-time invalidation and notification push delivery over private WebSocket channels.

### POST /api/broadcasting/auth
Authenticates private channel subscriptions for Laravel Echo / Pusher JS.

**Auth:** Bearer token required (`auth:sanctum` via `bootstrap/app.php` broadcasting configuration)

**Request body:**
```
socket_id: string
channel_name: string
```

**Authorized Channel Patterns (`routes/channels.php`):**
1. `private-users.{id}`:
   - Evaluates: `(int) $user->id === (int) $id`
   - Scopes: Allows students and staff to receive events specifically addressed to their user ID.
2. `private-departments.{id}`:
   - Evaluates: `$user->role === 'staff'` AND (`$user->staffProfile->admin_level === 'super_admin'` OR assigned to department `{id}`).
   - Scopes: Allows departmental staff to receive queue-invalidation events for requests entering or progressing in their department.

**Response 200:**
```json
{
  "auth": "campusdesk-key:signature..."
}
```

### Wire Event & Envelope Contract

- **Wire Event Name:** `request.event` (Listened to by Laravel Echo on the client with `.request.event` to bypass Laravel namespace prefixing).
- **Channels Broadcast To:** `private-users.{id}` and `private-departments.{id}` based on computed event audiences.
- **Envelope Payload Structure:**
```json
{
  "event_id": "9d41d1a7-6f81-4966-96a9-8397a61d1e44",
  "event_name": "request.status_updated",
  "schema_version": 1,
  "aggregate_sequence": 3,
  "occurred_at": "2026-10-21T14:30:00.000000Z",
  "request_id": 12,
  "student_id": 45,
  "current_status": "in_review",
  "current_stage_id": 3,
  "current_department_id": 2,
  "target_department_ids": [2],
  "target_user_ids": [45, 18],
  "reason": "Stage claimed by staff"
}
```

**Security & Privacy Contract:**
Broadcast payloads strictly avoid transmitting student Personally Identifiable Information (PII) or sensitive staff notes over WebSockets. The payload serves as a lightweight cache-invalidation signal. The frontend client (`realtime.ts`) catches the event and triggers scoped query refetches through standard authenticated API endpoints.

---

## Reference Data Endpoints (Public)

These endpoints require no authentication. Used to populate registration and form dropdowns.

### GET /api/faculties
Returns all faculties. Plain JSON array (no `data` wrapper).

### GET /api/departments
Returns all departments with `id`, `faculty_id`, `name`, `code`, `created_at`.

### GET /api/programmes
Returns all programmes with `id`, `faculty_id`, `department_id`, `name`, `code`, `degree_type`.

### GET /api/request-types
Returns all request types with their `default_department_sequence`.

---

## Student Endpoints

All require: `auth:sanctum` + `active_account` + `student` middleware.

### GET /api/requests
Returns all requests for the authenticated student.

**Rate limit:** 60/minute
**Eager loads:** `requestType`, `attachments`, `requestStages.department`, `statusHistories`

### GET /api/requests/{request}
Returns a single request with full stage timeline, attachments, and status history.

**Auth:** `auth:sanctum` + `active_account`. Accessible by the owning student or any staff member.
**Rate limit:** 60/minute
**Eager loads:** `requestStages`, `attachments`, `statusHistories`

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

**Side effects:**
1. Creates `requests` row
2. Resolves symbolic tokens in sequence via `StageGenerationService::resolveSequence()`
3. Creates one `request_stages` row per department in resolved sequence
4. Creates initial `status_history` entry
5. Stores uploaded files privately under `storage/app/private/attachments/`
6. Records transactional domain event in `request_events` outbox and dispatches `PublishRequestEvent` to `realtime` queue after transaction commits.

### POST /api/requests/{request}/reopen
Reopen a rejected request.

**Auth:** `auth:sanctum` + `active_account`. Available to owning student or Super Admin.
**Guards:** Parent request must have `status = rejected`. Resets rejected stage to `pending`, clears `handled_by`, retains `staff_note`, and dispatches outbox event.

### PATCH /api/requests/{request}/collect
Mark a ready request as collected by the student.

**Auth:** `auth:sanctum` + `active_account` + `student`.
**Guards:** Request status must be `ready` and student must be the owner. Transitions status to `collected` and dispatches outbox event.

---

## Staff Endpoints

All require: `auth:sanctum` + `active_account` + `staff` middleware + 60/minute rate limit.

### GET /api/stages
Returns the unclaimed stage queue for the authenticated staff member's departments.
Uses a SQL-level `whereExists` correlated subquery to ensure predecessor stage approval.

### GET /api/stages/my-cases
Returns stages currently claimed by the authenticated staff member (`status = in_review`, `handled_by = auth user id`).

### GET /api/requests/{docRequest}/stages
Returns all stages for a specific request in sequence order.

### POST /api/requests/{docRequest}/stages/{stage}/claim
Claim an unclaimed stage.
Uses `DB::transaction()` with `lockForUpdate()` to prevent race conditions. Sets stage `status = in_review`, `handled_by = auth user id`, records audit history, and dispatches outbox domain event.

### PATCH /api/requests/{docRequest}/stages/{stage}/resolve
Resolve a claimed stage (`approved` or `rejected`).
If approved and more stages remain, request transitions to `forwarded`. If final stage, transitions to `ready`. If rejected, transitions to `rejected`. Records stage and parent status history, queues email notification, and records outbox domain event for real-time delivery.

---

## Department Admin Endpoints

All require: `auth:sanctum` + `active_account` + `dept_admin` middleware.

### GET /api/dept-admin/requests
Returns all stages, staff members, and summary statistics scoped to the department admin's primary department.

### PATCH /api/dept-admin/stages/{stage}/reassign
Reassign an active claimed stage from one department staff member to another.
Writes an immutable audit record in `stage_reassignments`, creates an in-app notification for the receiving staff member, and emits a real-time outbox event.

---

## Attachment Endpoint

### GET /api/attachments/{attachment}
Stream a protected attachment file.

**Auth:** `auth:sanctum` + `active_account`.
**Authorization:** Requester must be owning student, Super Admin, stage handler, or staff in a department on the request route. Files are streamed from private storage.

---

## Super Admin API

All `/api/admin/*` routes require `auth:sanctum`, `active_account`, `super_admin` middleware, and throttling (60/minute). Lists accept `page` (default 1) and `per_page` (default 20, maximum 100), and return `data`, pagination `meta`, and `links`.

| Routes | Inputs / Description |
|---|---|
| `GET/POST /api/admin/faculties`, `PATCH/DELETE /api/admin/faculties/{id}` | Reference CRUD for faculties (`name`, `code`, `matricule_prefix`). |
| `GET/POST /api/admin/departments`, `PATCH/DELETE /api/admin/departments/{id}` | Reference CRUD for departments (`faculty_id`, `name`, `code`, `type`). |
| `GET/POST /api/admin/programmes`, `PATCH/DELETE /api/admin/programmes/{id}` | Reference CRUD for programmes (`department_id`, `name`, `code`, `degree_type`). |
| `GET/POST /api/admin/request-types`, `PATCH/DELETE /api/admin/request-types/{id}` | Reference CRUD for request types (`name`, `description`, `default_department_sequence`). |
| `GET/POST /api/admin/users`, `GET/PATCH/DELETE /api/admin/users/{user}` | User and profile CRUD. Staff memberships configure `department_ids` and `primary_department_id`. |
| `PATCH /api/admin/users/{user}/admin-level` | Updates `admin_level`: `null`, `dept_admin`, or `super_admin`. Guarded against removing the last active Super Admin. |
| `PATCH /api/admin/users/{user}/disable` | Deactivates user account (`disabled_at = now()`), revokes all active Sanctum tokens, guards the last Super Admin, and logs `user.disabled` administrative action. Optional body: `{"reason": "string"}`. |
| `PATCH /api/admin/users/{user}/enable` | Idempotently re-enables user account (`disabled_at = null`) and logs `user.enabled` administrative action. |
| `GET /api/admin/administrative-actions` | Super Admin audit log for administrative mutations (user enable/disable, credential rotation). Filters: `action`, `page`, `per_page`. |
| `GET /api/admin/stats` | System statistics (total, today, counts by status, average resolution hours, recent transitions). |
| `GET /api/admin/requests`, `GET /api/admin/requests/{request}` | System-wide request oversight with comprehensive filters (`search`, `status`, `reopened`, date range). |
| `GET /api/admin/audit-log` | Request and stage `status_history` only. Filters: `request_id`, `actor_id`, `new_status`, date range. |

---

## Route Summary (57 registered routes from `api.php`)

```
GET|POST|HEAD api/broadcasting/auth                          auth:sanctum
GET|HEAD      api/user                                       auth:sanctum, active_account
GET|HEAD      api/attachments/{attachment}                   auth:sanctum, active_account
GET|HEAD      api/requests/{request}                         auth:sanctum, active_account, throttle:60,1
POST          api/requests/{request}/reopen                  auth:sanctum, active_account, throttle:60,1
GET|HEAD      api/notifications                              auth:sanctum, active_account, throttle:60,1
PATCH         api/notifications/{notification}/read          auth:sanctum, active_account, throttle:60,1
GET|HEAD      api/requests                                   auth:sanctum, active_account, student, throttle:60,1
POST          api/requests                                   auth:sanctum, active_account, student, throttle:10,1
PATCH         api/requests/{request}/collect                 auth:sanctum, active_account, student, throttle:10,1
GET|HEAD      api/requests/{docRequest}/stages               auth:sanctum, active_account, staff, throttle:60,1
GET|HEAD      api/stages                                     auth:sanctum, active_account, staff, throttle:60,1
GET|HEAD      api/stages/my-cases                            auth:sanctum, active_account, staff, throttle:60,1
POST          api/requests/{docRequest}/stages/{stage}/claim auth:sanctum, active_account, staff
PATCH         api/requests/{docRequest}/stages/{stage}/resolve auth:sanctum, active_account, staff
GET|HEAD      api/dept-admin/requests                        auth:sanctum, active_account, dept_admin, throttle:60,1
PATCH         api/dept-admin/stages/{stage}/reassign         auth:sanctum, active_account, dept_admin, throttle:60,1
GET/POST/PATCH/DELETE api/admin/{faculties|departments|programmes|request-types} auth:sanctum, active_account, super_admin, throttle:60,1
GET/POST/PATCH/DELETE api/admin/users                        auth:sanctum, active_account, super_admin, throttle:60,1
PATCH         api/admin/users/{user}/admin-level             auth:sanctum, active_account, super_admin, throttle:60,1
PATCH         api/admin/users/{user}/disable                 auth:sanctum, active_account, super_admin, throttle:60,1
PATCH         api/admin/users/{user}/enable                  auth:sanctum, active_account, super_admin, throttle:60,1
GET|HEAD      api/admin/stats                                auth:sanctum, active_account, super_admin, throttle:60,1
GET|HEAD      api/admin/requests[/{request}]                 auth:sanctum, active_account, super_admin, throttle:60,1
GET|HEAD      api/admin/audit-log                            auth:sanctum, active_account, super_admin, throttle:60,1
GET|HEAD      api/admin/administrative-actions               auth:sanctum, active_account, super_admin, throttle:60,1
GET|HEAD      api/faculties                                  (public)
GET|HEAD      api/departments                                (public)
GET|HEAD      api/programmes                                 (public)
GET|HEAD      api/request-types                              (public)

--- from auth.php ---
POST          api/register                                   guest, throttle:5,1
POST          api/login                                      guest, throttle:5,1
POST          api/forgot-password                            guest
POST          api/reset-password                             guest
GET|HEAD      api/verify-email/{id}/{hash}                   auth, signed, throttle:6,1
POST          api/email/verification-notification            auth, throttle:6,1
POST          api/logout                                     auth
```
