# CampusDesk — System Architecture

**Last reviewed:** 21 October 2026

## Overall Architecture

CampusDesk is a **decoupled SPA + REST API** architecture with real-time push invalidations:

```
┌─────────────────────────┐         ┌─────────────────────────────┐
│   Vue 3 SPA             │  HTTP   │   Laravel 12 API            │
│   Frontend/src/         │ ──────► │   campusdesk/               │
│   localhost:5173        │  JSON   │   127.0.0.1:8000            │
│                         │◄─────── │                             │
│   Axios + Bearer token  │         │   Sanctum token auth        │
└────────────┬────────────┘         └──────────────┬──────────────┘
             │                                     │
   WebSocket │ (Pusher protocol)                   │ HTTP broadcast
             ▼                                     ▼
      ┌─────────────┐                    ┌──────────────────┐
      │   Laravel   │◄───────────────────│ Broadcasting     │
      │   Reverb    │                    │ Transport        │
      └─────────────┘                    └──────────────────┘
                                                   │
                                    ┌──────────────┼──────────────┐
                                    │              │              │
                               ┌────▼────┐   ┌────▼────┐   ┌────▼────┐
                               │  MySQL  │   │ Storage │   │ Queue   │
                               │  DB     │   │ (files) │   │ (jobs)  │
                               └─────────┘   └─────────┘   └─────────┘
                                                                 │
                                                     ┌───────────┴───────────┐
                                                     │                       │
                                               ┌─────▼─────┐           ┌─────▼─────┐
                                               │ Mailtrap  │           │ Realtime  │
                                               │ (dev mail)│           │ (outbox)  │
                                               └───────────┘           └───────────┘
```

### Verified local container topology

The bare development ports above remain available. The reproducible Docker Compose topology uses one public entry point and an internal WebSocket network:

```mermaid
flowchart LR
    Browser["Browser<br/>localhost:8080"] --> Frontend["Nginx + compiled Vue"]
    Frontend -->|"/api/*"| Backend["Apache + Laravel"]
    Frontend -.->|"ws /app/*"| Reverb["Laravel Reverb (8085)"]
    Backend --> DB["MySQL 8.4"]
    Backend --> Reverb
    Worker["Default queue worker"] --> DB
    RealtimeWorker["Realtime queue worker"] --> DB
    Scheduler["Console scheduler"] --> DB
    Backend --> Files["Private attachment bind mount"]
    Worker --> Files
    RealtimeWorker --> Files
    DB --> Volume["Named database volume"]
```

Only Nginx publishes a host port (`8080`). Compose DNS provides internal hostnames (`backend`, `reverb`, `db`). The backend, workers, and scheduler share the same base backend image but execute separate long-lived processes. See the consolidated [CI/CD implementation and operations guide](CI_CD_SESSION_2_HANDOFF.md) for the complete runtime design and verification record.

### Verified AWS staging delivery topology

```mermaid
flowchart LR
    Dev[Push to development] --> CI[GitHub Actions quality gates]
    CI --> ECR[Immutable backend and frontend ECR images]
    Operator[Operator] -->|manual dispatch and approval| Deploy[Protected staging environment]
    Deploy --> OIDC[Environment-scoped GitHub OIDC role]
    OIDC -->|resolve exact digests| ECR
    OIDC -->|restricted command| SSM[CampusDesk-DeployStaging]
    SSM --> Script["/usr/local/sbin/campusdesk-deploy"]
    Script --> Stack[Single-host staging Compose stack]
    Stack --> Init[One-shot attachments-init]
    Init --> Volume[Private attachments_data volume]
    Stack --> DBData[db_data volume]
```

The deployment is automated after a manual workflow dispatch and protected-environment approval. It validates the full release SHA, resolves exact ECR digests, serializes deployments, verifies images before downtime, runs migrations once, recreates application services, and performs health/API smoke tests. Re-dispatching the active release follows a verified no-op path. The workflow does not copy `compose.staging.yaml` or replace the root-owned host script; those host changes require a separate reviewed rollout. See [CI_CD_SESSION_2_HANDOFF.md](CI_CD_SESSION_2_HANDOFF.md).

## Frontend Architecture

The presentation architecture features a shared `components/ui` layer, `components/layout/AuthLayout.vue`, domain components, and guarded nested pages for each role. The role views are layout hosts containing `RouterView`; page-scoped composables load only the data needed by the selected URL. Existing service modules and singleton `useAuth` remain in place. Real-time updates are coordinated via `useRealtime` composable and `realtimeManager`.

- **Framework:** Vue 3 with Composition API and `<script setup>`
- **Language:** TypeScript (strict mode)
- **Build tool:** Vite 6
- **Styling:** Tailwind CSS 3.x + DaisyUI 5.x
- **Routing:** Vue Router 4 (HTML5 history mode)
- **HTTP client:** Axios 1.x with request/response interceptors
- **WebSocket client:** Laravel Echo 2.x + Pusher JS 8.x
- **State management:** No Pinia/Vuex — singleton composable pattern (`useAuth`, `useRealtime`)
- **Auth token storage:** `localStorage` (token + user object)
- **Test runner:** Vitest 2.x with `@vue/test-utils`

### Frontend Directory Structure
```
Frontend/
├── src/                           ← THE REAL VUE SPA (all development happens here)
│   ├── main.ts                    ← app bootstrap
│   ├── App.vue                    ← root component, logout handler, user sync, notification bell
│   ├── style.css                  ← global styles
│   ├── types/
│   │   └── index.ts               ← ALL TypeScript interfaces (single source of truth)
│   ├── composables/
│   │   ├── useAuth.ts             ← token + user state management
│   │   ├── useRealtime.ts         ← scope-bound WebSocket invalidation & reconnect listeners
│   │   ├── useStudentRequests.ts  ← student list/detail state
│   │   └── admin/                 ← page-scoped admin state
│   ├── services/
│   │   ├── api.ts                 ← Axios instance + interceptors
│   │   ├── auth.ts                ← login/register/logout API calls
│   │   ├── echo.ts                ← Laravel Echo factory & Sanctum custom authorizer
│   │   ├── realtime.ts            ← RealtimeManager singleton, deduplication & watermark tracker
│   │   ├── requests.ts            ← student request API calls
│   │   ├── stages.ts              ← staff stage API calls
│   │   ├── notifications.ts       ← in-app notification API calls
│   │   ├── deptAdmin.ts           ← department oversight/reassignment
│   │   ├── admin.ts               ← Super Admin APIs
│   │   └── reference.ts           ← dropdown data (faculties, depts, etc.)
│   ├── router/
│   │   └── index.ts               ← routes + role-based guards
│   ├── views/
│   │   ├── LoginView.vue          ← login page
│   │   ├── RegisterView.vue       ← student registration page
│   │   ├── StudentView.vue        ← student layout + nested RouterView
│   │   ├── StaffView.vue          ← staff layout + nested RouterView
│   │   ├── DeptAdminView.vue      ← department-admin layout + RouterView
│   │   ├── SuperAdminView.vue     ← Super Admin layout + RouterView
│   │   ├── student/               ← overview, list, create, detail pages
│   │   ├── staff/                 ← overview, queue, active-case pages
│   │   ├── dept-admin/            ← overview and department-work pages
│   │   └── admin/                 ← overview, requests, users, references, history
│   └── components/
│       ├── student/               ← request form and request card
│       ├── staff/                 ← case card and resolution workspace
│       ├── admin/                 ← shared admin fields, pagination, audit, delete dialog
│       ├── ui/                    ← modal, icons, headers, skeletons, empty states
│       ├── DocumentViewer.vue     ← blob URL file viewer (WIRED)
│       ├── RequestTimeline.vue    ← stage progression display
│       ├── NotificationBell.vue   ← notification bell (wired to API + real-time invalidation)
│       ├── StatusBadge.vue        ← coloured status pill
│       └── LevelBadge.vue         ← student level display
├── package.json                   ← Vue/Vite project (correct — use this)
├── vite.config.js                 ← Vite config with @ alias → src/
└── tailwind.config.ts             ← Tailwind config
```

All frontend application code is contained in `Frontend/src/`; the project is a Vue/Vite SPA.

## Backend Architecture

- **Framework:** Laravel 12
- **Language:** PHP `^8.2`; verified container runtime PHP 8.3
- **Auth:** Laravel Sanctum 4.x (Bearer token mode)
- **WebSockets / Broadcasting:** Laravel Reverb + private channel authorization
- **Queue:** Database driver (`jobs` table) with dedicated `default` and `realtime` queues
- **Mail:** SMTP via Mailtrap (development)
- **File storage:** Laravel Storage facade (explicit local disk, `storage/app/private/attachments/`)
- **ORM:** Eloquent with relationships, observers, and model events

### Backend Structure
```
campusdesk/
├── app/
│   ├── Console/Commands/
│   │   ├── ReconcileRequestEvents.php   ← scheduled durability backstop (realtime:reconcile)
│   │   └── RetryFailedRequestEvents.php ← manual retry command for failed events (realtime:retry)
│   ├── Contracts/
│   │   └── RequestEventTransport.php    ← seam for broadcasting request event invalidations
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── AuthenticatedSessionController.php  ← login (token response)
│   │   │   │   │                                         logout revokes current Sanctum token
│   │   │   │   └── RegisteredUserController.php        ← registration + student profile
│   │   │   ├── RequestController.php                   ← student request CRUD + reopen + collect
│   │   │   ├── RequestStageController.php              ← staff queue + claim + resolve
│   │   │   ├── NotificationController.php               ← list + mark-read notifications
│   │   │   ├── AttachmentController.php                ← protected file serving
│   │   │   ├── ReferenceDataController.php             ← public dropdown data
│   │   │   ├── DeptAdminController.php                 ← primary-department oversight/reassignment
│   │   │   ├── AdminReferenceController.php            ← protected reference CRUD
│   │   │   ├── AdminUserController.php                 ← protected user/admin-level CRUD
│   │   │   ├── AdminOverviewController.php             ← stats, requests, status history
│   │   │   └── AdministrativeActionController.php      ← audit log for admin operations
│   │   ├── Middleware/
│   │   │   ├── EnsureIsStudent.php      ← checks is_student gate
│   │   │   ├── EnsureIsStaff.php        ← checks is_staff gate
│   │   │   ├── EnsureIsDeptAdmin.php    ← checks is-dept-admin gate
│   │   │   └── EnsureIsSuperAdmin.php   ← checks is-super-admin gate
│   │   └── Requests/
│   │       ├── StoreRequestRequest.php      ← student request validation
│   │       └── UpdateStageStatusRequest.php ← stage resolve validation
│   ├── Models/
│   │   ├── User.php
│   │   ├── Faculty.php              ← includes matricule_prefix field
│   │   ├── Department.php           ← includes type field (academic|records|admin)
│   │   ├── Programme.php            ← includes department_id field; auto-syncs faculty_id
│   │   ├── StudentProfile.php
│   │   ├── StaffProfile.php
│   │   ├── DepartmentStaff.php      ← pivot model
│   │   ├── RequestType.php
│   │   ├── Request.php              ← aliased as DocumentRequest/UserRequest
│   │   ├── RequestStage.php
│   │   ├── StatusHistory.php
│   │   ├── Attachment.php
│   │   ├── Notification.php
│   │   ├── RequestEvent.php         ← durable outbox event tracking
│   │   ├── AdministrativeAction.php ← administrative CRUD audit records
│   │   └── StageReassignment.php    ← immutable handoff audit records
│   ├── Observers/
│   │   └── RequestStageObserver.php ← auto stage status history
│   ├── Jobs/
│   │   ├── PublishRequestEvent.php           ← dedicated realtime queue publisher (tries=6, backoff)
│   │   └── SendRequestStatusNotification.php ← email notification job
│   ├── Mail/
│   │   └── RequestStatusUpdated.php
│   ├── Services/
│   │   ├── StageGenerationService.php          ← resolves symbolic department tokens
│   │   ├── RequestCreationService.php          ← creates request/stage/history/event atomically
│   │   ├── SeedRequestProgressionService.php   ← advances deterministic seed fixtures
│   │   ├── RequestStatusNotificationService.php← in-app notification & queued email dispatch
│   │   ├── RequestEventRecorder.php            ← outbox event persistence & afterCommit dispatch
│   │   ├── RequestEventDelivery.php            ← synchronous delivery, deduplication, error audit
│   │   └── BroadcastingRequestEventTransport.php ← adapter publishing to Reverb private channels
│   ├── Http/Resources/
│   │   ├── RequestResource.php
│   │   ├── RequestStageResource.php
│   │   ├── NotificationResource.php
│   │   └── UserResource.php
│   └── Providers/
│       └── AppServiceProvider.php     ← Gates + Observer + RequestEventTransport binding
├── database/
│   ├── migrations/                  ← 33 migration files total
│   ├── factories/
│   │   ├── UserFactory.php          ← creates staff + student users with auto profiles
│   │   └── DepartmentFactory.php
│   └── seeders/
│       ├── DatabaseSeeder.php       ← orchestrates all seeders in dependency order
│       ├── FacultySeeder.php        ← reads from university_programs_structure.md
│       ├── DepartmentSeeder.php     ← reads parsed faculties; seeds academic + records depts
│       ├── ProgrammeSeeder.php      ← seeds programmes per department
│       ├── StaffSeeder.php          ← seeds 80 staff users via UserFactory
│       ├── StudentSeeder.php        ← seeds 10 students per academic department
│       ├── DepartmentStaffSeeder.php← assigns staff to depts; primary→dept_admin
│       ├── RequestTypeSeeder.php    ← seeds 4 request types with symbolic sequences
│       └── Support/
│           ├── FacultyMarkdownParser.php    ← parses university structure markdown
│           ├── FacultyMatriculeMapper.php   ← maps faculty codes to matricule prefixes
│           ├── DepartmentTypeMapper.php     ← maps dept codes to type enum values
│           ├── StudentDistributionHelper.php← distributes students across levels
│           └── university_programs_structure.md ← UB faculty/dept/programme data
├── routes/
│   ├── api.php                      ← all API routes
│   ├── auth.php                     ← Breeze auth routes
│   ├── channels.php                 ← private broadcast channel authorization rules
│   └── console.php                  ← scheduler registrations (realtime:reconcile)
├── config/
│   ├── broadcasting.php             ← Reverb, Pusher, and fallback broadcast connections
│   ├── cors.php                     ← CORS (allows localhost:5173)
│   ├── realtime.php                 ← delivery feature flag and backlog replay settings
│   ├── reverb.php                   ← Reverb server host, port, origins, and app credentials
│   └── sanctum.php                  ← stateful domains
├── bootstrap/
│   └── app.php                      ← HandleCors + middleware aliases
├── tests/
│   ├── Feature/                     ← auth, routing, lifecycle, admin, realtime, attachment tests
│   └── Unit/                        ← default scaffold example only
└── resources/views/
    └── emails/
        └── request-status-update.blade.php ← notification email template
```

## Authentication Architecture

CampusDesk uses **Sanctum Bearer token authentication** (not cookie/SPA mode).

```
POST /api/login
  ↓
AuthenticatedSessionController::store()
  ↓
$user->createToken('auth_token')->plainTextToken
  ↓
Response: { token: "...", user: { id, name, email, role, student_profile, staff_profile } }
  ↓
Frontend stores token in localStorage
  ↓
Axios request interceptor reads token → adds "Authorization: Bearer {token}" to every request
  ↓
auth:sanctum middleware validates token on every protected request
```

**Important:** `EnsureFrontendRequestsAreStateful` was intentionally REMOVED from `bootstrap/app.php`. It caused redirects that broke token-based auth. Only `HandleCors` is prepended to the middleware stack.

`AuthenticatedSessionController::destroy()` revokes the current Sanctum token. The historical session-method issue is resolved.

## Authorization Architecture

Three-layer authorization:

1. **Route middleware** — broad role gates (`student`, `staff`, `dept_admin`, `super_admin`)
2. **Form Request `authorize()`** — per-request role checks
3. **Controller-level checks** — fine-grained ownership and business rule enforcement

Gates defined in `AppServiceProvider::boot()`:
```php
Gate::define('is_student', fn(User $user) => $user->role === 'student');
Gate::define('is_staff',   fn(User $user) => $user->role === 'staff');
Gate::define('is-dept-admin', fn(User $user) =>
    $user->role === 'staff' && $user->staffProfile?->admin_level === 'dept_admin');
Gate::define('is-super-admin', fn(User $user) =>
    $user->role === 'staff' && $user->staffProfile?->admin_level === 'super_admin');
```

`is_student` and `is_staff` use underscores; the two admin gates use hyphens. Each middleware checks the gate name actually defined in `AppServiceProvider`.

Middleware aliases in `bootstrap/app.php`:
- `student` → `EnsureIsStudent`
- `staff` → `EnsureIsStaff`
- `dept_admin` → `EnsureIsDeptAdmin`
- `super_admin` → `EnsureIsSuperAdmin`

## File Storage Architecture

Uploaded attachments are stored through Laravel's explicit `local` disk in `storage/app/private/attachments/` (private, not under `public/`). In staging, backend and worker mount the shared `attachments_data` volume at that path.

Files are served through `AttachmentController::show()` which:
1. Validates that the authenticated user is the request owner, a Super Admin, an assigned handler, or staff in a department on the request route
2. Returns 404 if the physical local-disk object is missing
3. Streams the file using `Storage::disk('local')->response()` with its original filename and MIME type

`RequestController::store()` treats a false or empty storage result as an error, rolls back the request metadata, and removes files written earlier in a failed multi-file transaction. In staging, the network-isolated one-shot `attachments-init` service creates the mounted directory as `www-data:www-data` mode `0750` and must succeed before backend or worker startup.

Frontend receives a file ID, fetches it via Axios with `responseType: 'blob'`, creates a `URL.createObjectURL(blob)` for display in `<img>` and `<iframe>` tags. This sidesteps the Bearer token limitation on HTML src attributes.

## Real-Time Push Notification & Invalidation Architecture

CampusDesk implements an asynchronous, at-least-once outbox pipeline coupled with Laravel Reverb WebSockets and Laravel Echo:

```
Request Lifecycle Action (Create / Status Change / Stage Claim)
  ↓
RequestEventRecorder (persists row in `request_events` inside active DB transaction)
  ↓
DB::afterCommit() registers best-effort fast path dispatch
  ↓
PublishRequestEvent dispatched to isolated `realtime` queue on `database` connection
  ↓
Dedicated realtime queue worker processes job:
  PublishRequestEvent::handle() → RequestEventDelivery::publish()
  ↓
BroadcastingRequestEventTransport::publish() broadcasts to Reverb via PrivateChannel
  ↓
Laravel Reverb pushes wire event `request.event` to subscribed clients
  ↓
Frontend RealtimeManager receives envelope:
  1. Deduplicates by event_id
  2. Ignores stale sequences (aggregate_sequence <= high-watermark)
  3. Coalesces rapid bursts (debounce window)
  4. Triggers component invalidation callbacks
  ↓
NotificationBell / StudentRequestDetailPage / StaffCaseWorkspace / DeptAdminRequestsPage
  refetch authoritative data from REST API
```

### Durability & Reconciliation
- **Outbox Persistence:** Events are persisted in `request_events` with status `pending`, UUID `id`, strict per-request `aggregate_sequence`, and targeted audience arrays (`users` and `departments`).
- **Reliability & Backoff:** `PublishRequestEvent` implements `ShouldQueue` and `ShouldBeUnique` (keyed by event ID with 1-hour uniqueness lock). It executes with `$tries = 6` and backoff `[1, 5, 30, 120, 600]`.
- **Durability Backstop:** A scheduled command `realtime:reconcile` runs `everyMinute()->withoutOverlapping()` to find any orphaned `pending` events and re-enqueue delivery jobs.
- **Manual Retry:** `php artisan realtime:retry {eventId?|--all}` allows operators to reset `failed` events to `pending` and re-enqueue them.
- **Privacy & Security:** Broadcast payloads contain only minimal invalidation identifiers (`event_id`, `event_name`, `schema_version`, `request_id`, `aggregate_sequence`, `occurred_at`). Sensitive request notes, descriptions, attachments, and student PII are never sent over WebSockets. Authoritative REST endpoints handle all data hydration.
- **Channel Authorization:** `routes/channels.php` enforces that `users.{id}` channels require `(int) $user->id === (int) $id`, and `departments.{id}` channels require the authenticated user to be staff assigned to that department or a Super Admin.

## Queue / Async Architecture

Asynchronous execution is partitioned across two dedicated queues on the `database` connection:

1. **`default` queue:** Consumes general asynchronous jobs such as email notification dispatch (`SendRequestStatusNotification`).
2. **`realtime` queue:** Consumes latency-sensitive event publication jobs (`PublishRequestEvent`).

In production and Docker Compose environments, these run as separate long-lived worker processes:
- `php artisan queue:work database --queue=default`
- `php artisan queue:work database --queue=realtime --sleep=1 --timeout=30`

## Deferred Integration Architecture

Two future integration initiatives remain scoped for future releases:

- **AI support:** An AI gateway must be isolated from request mutation paths. It may receive curated support knowledge and redacted, read-only context only. Escalation requires persisted support conversations/messages and a human handoff ticket associated with a department or super administrator.
- **Payments:** Mobile Money support must sit behind a provider interface. A `payments` aggregate and verified provider webhook will control an `awaiting_payment` request state; successful-payment handling must lock the payment and request records before releasing the request into the existing stage queue.

## Stage Sequence Resolution Architecture

Request types store a `default_department_sequence` JSON array that can contain either literal department IDs or symbolic tokens. When a student submits a request, the sequence is resolved to concrete department IDs:

- `"STUDENT_DEPARTMENT"` → resolves to the student's own `department_id`
- `"FACULTY_RECORDS"` → resolves to the `records`-type department in the student's faculty
- Integer → used as-is

**Current implementation:** `StageGenerationService::resolveSequence()` maps the complete template through `resolveDepartmentId()`. `RequestCreationService` injects and uses the stage service to create the request, stages, and initial history atomically; `RequestController::store()` uses the creation service before adding attachments in its outer transaction. Reopening does not regenerate stages.

## Seeder Architecture

The seeder suite parses a real University of Buea faculty/department/programme structure from a markdown file (`database/seeders/Support/university_programs_structure.md`) and seeds a complete, realistic dataset:

1. `FacultySeeder` — all UB faculties with matricule prefixes
2. `DepartmentSeeder` — all departments with type classification (`academic`/`records`/`admin`); also creates one `records` department per faculty
3. `ProgrammeSeeder` — all programmes linked to departments
4. `StaffSeeder(80)` — 80 staff users with auto-generated profiles
5. `StudentSeeder(10/dept)` — 10 students per academic department
6. `DepartmentStaffSeeder` — assigns all staff to departments; primary staff automatically elevated to `dept_admin`
7. `RequestTypeSeeder` — 4 request types (Transcript, Enrollment Attestation, Completion Attestation, Correction of Transcript)
8. `RequestSeeder(24)` — deterministic requests distributed across pending, in-review, forwarded, ready, rejected, and collected states
9. `AttachmentSeeder` — private-storage attachment fixtures
10. `NotificationSeeder` — in-app demonstration notifications
