# CampusDesk — User Roles, Permissions & Flows

## User Roles

| Role | Storage | Description |
|------|---------|-------------|
| Student | `users.role = 'student'` + `student_profiles` row | Submits and tracks requests |
| Staff (plain) | `users.role = 'staff'` + `staff_profiles.admin_level = null` | Processes stages in their department(s) |
| Department Admin | `users.role = 'staff'` + `staff_profiles.admin_level = 'dept_admin'` | Oversees primary department, reassigns stages |
| Super Admin | `users.role = 'staff'` + `staff_profiles.admin_level = 'super_admin'` | Full system management |

**Seeder note:** `DepartmentStaffSeeder` automatically elevates each department's primary staff to `dept_admin`. Every department is guaranteed exactly one primary (dept_admin) staff member after seeding.

## Permission Matrix

| Action | Student | Staff | Dept Admin | Super Admin |
|---|---|---|---|---|
| Submit a request | ✅ | ❌ | ❌ | ✅ |
| View own requests | ✅ | ❌ | ❌ | ✅ |
| View all requests | ❌ | ❌ | ❌ | ✅ |
| View department queue | ❌ | ✅ | ✅ | ✅ |
| Claim a stage | ❌ | ✅ (own dept only) | ✅ | ✅ |
| Resolve a stage | ❌ | ✅ (own claim only) | ✅ | ✅ |
| Reassign a claimed stage | ❌ | ❌ | ✅ (own dept) | ✅ |
| Reopen rejected request | ✅ | ❌ | ❌ | ✅ |
| Upload attachments | ✅ | ❌ | ❌ | ✅ |
| View attachments | ✅ (own) | ✅ | ✅ | ✅ |
| Manage users | ❌ | ❌ | ❌ | ✅ |
| Manage departments/faculties | ❌ | ❌ | ❌ | ✅ |
| Manage request types | ❌ | ❌ | ❌ | ✅ |
| View audit log | ❌ | ❌ | 🟡 (own dept only — planned) | ✅ |
| Receive email notifications | ✅ | ❌ | ❌ | ❌ |

**Note:** This matrix reflects the INTENDED design. Admin-side permissions (reassign, manage, audit log) have no backend enforcement yet because the admin routes do not exist. The matrix is aspirational for those rows.

---

## Authentication Flow

```mermaid
sequenceDiagram
    participant U as User (browser)
    participant V as Vue SPA
    participant A as Axios (api.ts)
    participant L as Laravel API

    U->>V: Enter email + password
    V->>A: login(credentials)
    A->>L: POST /api/login
    L->>L: Validate credentials (LoginRequest::authenticate)
    L->>L: createToken() via Sanctum
    L-->>A: { token, user: {..., student_profile/staff_profile} }
    A-->>V: user object
    V->>V: useAuth().setUser(user)
    V->>V: localStorage.setItem('token', token)
    V->>V: localStorage.setItem('user', JSON)
    V->>V: router.replace(homePathForUser()) based on role
```

Every subsequent request:
```mermaid
sequenceDiagram
    participant V as Vue Component
    participant S as Service (e.g. requests.ts)
    participant AX as Axios instance
    participant L as Laravel API

    V->>S: fetchRequests()
    S->>AX: api.get('/requests')
    AX->>AX: Request interceptor reads localStorage token
    AX->>AX: Sets Authorization: Bearer {token}
    AX->>L: GET /api/requests (with header)
    L->>L: auth:sanctum middleware validates token
    L->>L: student middleware checks role
    L-->>AX: { data: [...] }
    AX-->>S: response
    S-->>V: unwrapped array
```

On 401 response:
```
Axios response interceptor → clears localStorage → window.location.href = '/login'
```

---

## Student Request Submission Flow

```mermaid
sequenceDiagram
    participant St as Student
    participant SD as StudentDashboard.vue
    participant RS as requests.ts
    participant RC as RequestController
    participant DB as Database

    St->>SD: Fill form, select type, attach files
    SD->>RS: createRequest(payload)
    RS->>RS: Build FormData (if files) or JSON
    RS->>RC: POST /api/requests
    RC->>RC: StoreRequestRequest validates
    RC->>DB: BEGIN TRANSACTION
    RC->>DB: INSERT requests (status=pending)
    RC->>DB: INSERT attachments (if any)
    RC->>RC: Load RequestType.default_department_sequence
    RC->>RC: resolveSequence() — resolve symbolic tokens
    loop for each department in sequence
        RC->>DB: INSERT request_stages (sequence_order=n, status=pending)
    end
    RC->>DB: INSERT status_history (old=null, new=pending, changed_by=null)
    RC->>DB: COMMIT
    RC-->>SD: RequestResource (with stages, attachments, status_history)
    SD->>SD: Show confirmation, add new request to list
```

---

## Staff Claim & Resolve Flow (with concurrency fix)

```mermaid
sequenceDiagram
    participant S1 as Staff Member A (Dept X)
    participant S2 as Staff Member B (Dept Y)
    participant RSC as RequestStageController
    participant DB as Database (locked rows)

    Note over S1,S2: Stage 1 (Dept X) is pending, unclaimed<br/>Stage 2 (Dept Y) exists but predecessor not approved

    S2->>RSC: GET /api/stages (queue for Dept Y)
    RSC->>DB: whereExists(predecessor approved OR seq=1)
    DB-->>RSC: Stage 2 EXCLUDED (predecessor not approved)
    RSC-->>S2: Empty/filtered queue — Stage 2 not visible

    S1->>RSC: POST .../stages/{stage}/claim
    RSC->>DB: BEGIN TRANSACTION + lockForUpdate(stage)
    RSC->>DB: lockForUpdate(predecessor stage, if seq > 1)
    RSC->>DB: Verify status=pending, handled_by=null
    RSC->>DB: UPDATE stage: status=in_review, handled_by=A
    RSC->>DB: INSERT status_history (changed_by=staff_profile_id_of_A)
    RSC->>DB: UPDATE request: status=in_review
    RSC->>DB: COMMIT
    RSC-->>S1: 200 "Stage claimed"
    Note over RSC: RequestStageObserver::updated() fires:<br/>creates another status_history entry,<br/>dispatches email notification job

    S1->>RSC: PATCH .../stages/{stage}/resolve {status: approved}
    RSC->>DB: Verify handled_by=A, status=in_review
    RSC->>DB: UPDATE stage: status=approved, staff_note=...
    RSC->>RSC: handleApproval() — more stages exist → request.status=forwarded
    RSC->>DB: UPDATE request: status=forwarded
    RSC->>DB: COMMIT
    RSC-->>S1: 200 "Stage resolved."
    Note over RSC: Observer fires: status_history logged,<br/>email job dispatched

    Note over S2: NOW Stage 2 becomes visible (predecessor approved)
    S2->>RSC: GET /api/stages
    RSC-->>S2: Stage 2 now included
    S2->>RSC: POST .../stages/{stage2}/claim
    RSC-->>S2: 200 "Stage claimed"
```

---

## Request Lifecycle State Machine

```mermaid
stateDiagram-v2
    [*] --> pending : student submits
    pending --> in_review : staff claims first stage
    in_review --> forwarded : stage approved,\nmore stages remain
    forwarded --> in_review : next staff claims
    in_review --> ready : final stage approved
    in_review --> rejected : stage rejected
    rejected --> pending : student reopens\n(is_reopened=true,\nfresh stages spawned)\n❌ NOT YET IMPLEMENTED
    ready --> collected : student marks collected\n❌ NOT YET IMPLEMENTED
    collected --> [*]
```

## Stage-Level State Machine

```mermaid
stateDiagram-v2
    [*] --> pending
    pending --> in_review : staff claims\n(predecessor must be\napproved if not first stage)
    in_review --> approved : staff approves
    in_review --> rejected : staff rejects
    approved --> [*]
    rejected --> [*]
```

---

## Role-Based Post-Login Routing (Frontend)

```mermaid
flowchart TD
    A[Login success] --> B{user.role?}
    B -->|student| C["/student"]
    B -->|staff| D{staff_profile.admin_level?}
    D -->|null| E["/staff"]
    D -->|dept_admin| F["/dept-admin"]
    D -->|super_admin| G["/admin"]
```

Implemented in `router/index.ts`'s `homePathForUser()` function, reading from `useAuth().user`.

Route guard logic:
- `/student` — requires `requiresAuth: true`, `roles: ['student']`
- `/staff` — requires `requiresAuth: true`, `staffLevel: 'plain'` (admin_level must be null)
- `/dept-admin` — requires `requiresAuth: true`, `staffLevel: 'dept_admin'`
- `/admin` — requires `requiresAuth: true`, `staffLevel: 'super_admin'`

---

## Document Viewing Flow (Secure Blob Pattern)

```mermaid
sequenceDiagram
    participant U as User (Student/Staff)
    participant DV as DocumentViewer.vue
    participant AX as Axios
    participant AC as AttachmentController

    U->>DV: Click attachment button
    DV->>AX: GET /api/attachments/{id} (responseType: blob)
    AX->>AX: Interceptor attaches Bearer token
    AX->>AC: Authenticated request
    AC->>AC: Check: isOwner (student_id = user.id) OR isStaff (role = 'staff')
    AC->>AC: Storage::exists(file_path)
    AC-->>AX: Binary file stream + Content-Type header
    AX-->>DV: Blob response
    DV->>DV: URL.createObjectURL(blob)
    DV->>U: Render in <img> or <iframe> using blob: URL
    U->>DV: Click same attachment again (or deselect)
    DV->>DV: URL.revokeObjectURL() — free memory
    DV->>U: Viewer collapsed
```

---

## Email Notification Flow

```mermaid
sequenceDiagram
    participant RSC as RequestStageController (resolve/claim)
    participant RO as RequestStageObserver
    participant JQ as jobs table (queue)
    participant QW as queue:work process
    participant M as Mailtrap (dev)
    participant ST as Student (email inbox)

    RSC->>RSC: stage.update({status: ...})
    Note over RO: Observer fires on stage 'updated' event
    RO->>RO: isDirty('status') check
    RO->>RO: Insert status_history (resolved staff_profile_id)
    RO->>JQ: SendRequestStatusNotification::dispatch(student, request, newStatus)
    Note over QW: php artisan queue:work
    QW->>JQ: Pick up job
    QW->>M: Mail::to(student)->send(RequestStatusUpdated)
    M->>ST: Email: "Update on your {request_type} request"
```

**Note:** The queue worker must be running. Use `composer run dev` to start it automatically alongside `php artisan serve`.
