# CampusDesk — System Architecture

## Overall Architecture

CampusDesk is a **decoupled SPA + REST API** architecture:

```
┌─────────────────────────┐         ┌─────────────────────────────┐
│   Vue 3 SPA             │  HTTP   │   Laravel 12 API            │
│   Frontend/src/         │ ──────► │   campusdesk/               │
│   localhost:5173        │  JSON   │   127.0.0.1:8000            │
│                         │◄─────── │                             │
│   Axios + Bearer token  │         │   Sanctum token auth        │
└─────────────────────────┘         └──────────────┬──────────────┘
                                                   │
                                    ┌──────────────┼──────────────┐
                                    │              │              │
                               ┌────▼────┐   ┌────▼────┐   ┌────▼────┐
                               │  MySQL  │   │ Storage │   │ Queue   │
                               │  DB     │   │ (files) │   │ (jobs)  │
                               └─────────┘   └─────────┘   └─────────┘
                                                                │
                                                          ┌─────▼─────┐
                                                          │ Mailtrap  │
                                                          │ (dev mail)│
                                                          └───────────┘
```

## Frontend Architecture

- **Framework:** Vue 3 with Composition API and `<script setup>`
- **Language:** TypeScript (strict mode)
- **Build tool:** Vite 6
- **Styling:** Tailwind CSS 3.x + DaisyUI 5.x
- **Routing:** Vue Router 4 (HTML5 history mode)
- **HTTP client:** Axios 1.x with request/response interceptors
- **State management:** No Pinia/Vuex — singleton composable pattern (`useAuth`)
- **Auth token storage:** `localStorage` (token + user object)
- **Test runner:** Vitest 2.x with `@vue/test-utils`

### Frontend Directory Structure
```
Frontend/
├── src/                           ← THE REAL VUE SPA (all development happens here)
│   ├── main.ts                    ← app bootstrap
│   ├── App.vue                    ← root component, logout handler, notification bell
│   ├── style.css                  ← global styles
│   ├── types/
│   │   └── index.ts               ← ALL TypeScript interfaces (single source of truth)
│   ├── composables/
│   │   ├── useAuth.ts             ← token + user state management
│   │   └── useMockData.ts         ← mock data layer (still referenced by unfinished views)
│   ├── services/
│   │   ├── api.ts                 ← Axios instance + interceptors
│   │   ├── auth.ts                ← login/register/logout API calls
│   │   ├── requests.ts            ← student request API calls
│   │   ├── stages.ts              ← staff stage API calls
│   │   └── reference.ts           ← dropdown data (faculties, depts, etc.)
│   ├── router/
│   │   └── index.ts               ← routes + role-based guards
│   ├── views/
│   │   ├── LoginView.vue          ← login page
│   │   ├── RegisterView.vue       ← student registration page
│   │   ├── StudentView.vue        ← wrapper → StudentDashboard
│   │   ├── StaffView.vue          ← wrapper → StaffDashboard
│   │   ├── DeptAdminView.vue      ← dept admin (MOCK ONLY — uses useMockData)
│   │   └── SuperAdminView.vue     ← super admin (MOCK ONLY — uses useMockData)
│   └── components/
│       ├── StudentDashboard.vue   ← main student UI (WIRED to real API)
│       ├── StaffDashboard.vue     ← main staff UI (WIRED to real API)
│       ├── AdminDashboard.vue     ← super admin UI component (MOCK ONLY — uses useMockData)
│       ├── DocumentViewer.vue     ← blob URL file viewer (WIRED)
│       ├── RequestTimeline.vue    ← stage progression display
│       ├── NotificationBell.vue   ← notification bell (MOCK ONLY — uses useMockData)
│       ├── StatusBadge.vue        ← coloured status pill
│       └── LevelBadge.vue         ← student level display
├── app/                           ← ABANDONED Next.js scaffold (ignore — do not use)
├── components/                    ← shadcn/ui component stubs (unused by working app)
├── hooks/                         ← Next.js-style hooks (unused by working app)
├── package.json                   ← Vue/Vite project (correct — use this)
├── vite.config.js                 ← Vite config with @ alias → src/
└── tailwind.config.ts             ← Tailwind config
```

**Note:** The `Frontend/app/` subdirectory and several root-level files (`next.config.mjs`, `Frontend/components/`, `Frontend/hooks/`) are remnants of an abandoned Next.js scaffold. They are not part of the working application. All active development is in `Frontend/src/`.

## Backend Architecture

- **Framework:** Laravel 12
- **Language:** PHP 8.2
- **Auth:** Laravel Sanctum 4.x (Bearer token mode)
- **Queue:** Database driver (`jobs` table)
- **Mail:** SMTP via Mailtrap (development)
- **File storage:** Laravel Storage facade (local disk, `storage/app/attachments/`)
- **ORM:** Eloquent with relationships, observers, and model events

### Backend Structure
```
campusdesk/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── AuthenticatedSessionController.php  ← login (token response)
│   │   │   │   │                                         ⚠️ logout() has session bug
│   │   │   │   └── RegisteredUserController.php        ← registration + student profile
│   │   │   ├── RequestController.php                   ← student request CRUD
│   │   │   │                                              (contains inline resolveSequence())
│   │   │   ├── RequestStageController.php              ← staff queue + claim + resolve
│   │   │   ├── AttachmentController.php                ← protected file serving
│   │   │   └── ReferenceDataController.php             ← public dropdown data
│   │   ├── Middleware/
│   │   │   ├── EnsureIsStudent.php      ← checks is_student gate
│   │   │   ├── EnsureIsStaff.php        ← checks is_staff gate
│   │   │   ├── EnsureIsDeptAdmin.php    ← checks is-dept-admin gate
│   │   │   │                               ⚠️ AppServiceProvider defines is_dept-admin
│   │   │   │                               (mixed naming — gate never matches — see KNOWN_ISSUES)
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
│   │   └── Notification.php
│   ├── Observers/
│   │   └── RequestStageObserver.php ← auto status history + email dispatch
│   ├── Jobs/
│   │   └── SendRequestStatusNotification.php
│   ├── Mail/
│   │   └── RequestStatusUpdated.php
│   ├── Services/
│   │   └── StageGenerationService.php  ← resolves symbolic department tokens
│   │                                      (built but NOT YET USED by RequestController)
│   ├── Http/Resources/
│   │   ├── RequestResource.php
│   │   ├── RequestStageResource.php
│   │   └── UserResource.php
│   └── Providers/
│       └── AppServiceProvider.php     ← Gates + Observer registration
│                                         ⚠️ gate 'is_dept-admin' has mixed naming bug
├── database/
│   ├── migrations/                  ← 27 migration files total
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
│       └── support/
│           ├── FacultyMarkdownParser.php    ← parses university structure markdown
│           ├── FacultyMatriculeMapper.php   ← maps faculty codes to matricule prefixes
│           ├── DepartmentTypeMapper.php     ← maps dept codes to type enum values
│           ├── StudentDistributionHelper.php← distributes students across levels
│           └── university_programs_structure.md ← UB faculty/dept/programme data
├── routes/
│   ├── api.php                      ← all API routes
│   └── auth.php                     ← Breeze auth routes
├── config/
│   ├── cors.php                     ← CORS (allows localhost:5173)
│   └── sanctum.php                  ← stateful domains
├── bootstrap/
│   └── app.php                      ← HandleCors + middleware aliases
├── tests/
│   ├── Feature/
│   │   ├── SequentialRoutingBugConditionTest.php   ← confirms concurrency defects
│   │   └── SequentialRoutingPreservationTest.php   ← guards correct behaviour
│   └── Unit/                        ← empty (default Laravel scaffold only)
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

**⚠️ Known issue — logout:** `AuthenticatedSessionController::destroy()` currently calls `$request->session()->invalidate()` which will crash in the stateless API context. See KNOWN_ISSUES.md.

## Authorization Architecture

Three-layer authorization:

1. **Route middleware** — broad role gates (`student`, `staff`, `dept_admin`, `super_admin`)
2. **Form Request `authorize()`** — per-request role checks
3. **Controller-level checks** — fine-grained ownership and business rule enforcement

Gates defined in `AppServiceProvider::boot()`:
```php
Gate::define('is_student', fn(User $user) => $user->role === 'student');
Gate::define('is_staff',   fn(User $user) => $user->role === 'staff');
Gate::define('is_dept-admin', fn(User $user) =>          // ⚠️ BUG: mixed naming
    $user->role === 'staff' && $user->staffProfile?->admin_level === 'dept_admin');
Gate::define('is-super-admin', fn(User $user) =>
    $user->role === 'staff' && $user->staffProfile?->admin_level === 'super_admin');
```

**⚠️ Naming inconsistency:** `is_student` and `is_staff` use underscores. `is-super-admin` uses hyphens. `is_dept-admin` uses a mixed convention (underscore + hyphen). `EnsureIsDeptAdmin` checks `Gate::allows('is-dept-admin')` (all-hyphen) — which does NOT match the mixed-convention definition `'is_dept-admin'`. The `dept_admin` middleware will always deny access until this is fixed.

Middleware aliases in `bootstrap/app.php`:
- `student` → `EnsureIsStudent`
- `staff` → `EnsureIsStaff`
- `dept_admin` → `EnsureIsDeptAdmin`
- `super_admin` → `EnsureIsSuperAdmin`

## File Storage Architecture

Uploaded attachments are stored in `storage/app/attachments/` (private, NOT in `public/`).

Files are served through `AttachmentController::show()` which:
1. Validates the authenticated user is the request owner OR is staff
2. Streams the file using `Storage::response()`

Frontend receives a file ID, fetches it via Axios with `responseType: 'blob'`, creates a `URL.createObjectURL(blob)` for display in `<img>` and `<iframe>` tags. This sidesteps the Bearer token limitation on HTML src attributes.

## Queue / Async Architecture

Email notifications are dispatched as queued jobs:

```
Stage status changes
  ↓
RequestStageObserver::updated() fires
  ↓
SendRequestStatusNotification::dispatch($student, $request, $newStatus)
  ↓
Job pushed to `jobs` table (database queue driver)
  ↓
php artisan queue:work (or via composer run dev) picks up job
  ↓
Mail::to($student)->send(new RequestStatusUpdated(...))
  ↓
Mailtrap (dev) receives email
```

Queue must be running for notifications to send. Use `composer run dev` to start server + queue worker + log viewer concurrently, or run `php artisan queue:work` in a separate terminal.

## Stage Sequence Resolution Architecture

Request types store a `default_department_sequence` JSON array that can contain either literal department IDs or symbolic tokens. When a student submits a request, the sequence is resolved to concrete department IDs:

- `"STUDENT_DEPARTMENT"` → resolves to the student's own `department_id`
- `"FACULTY_RECORDS"` → resolves to the `records`-type department in the student's faculty
- Integer → used as-is

**Current implementation:** `RequestController` contains an inline private `resolveSequence()` method that performs this resolution. A refactored `StageGenerationService` class exists at `app/Services/StageGenerationService.php` and implements the same logic more cleanly, but `RequestController` has not yet been updated to use it. When adding the reopen endpoint, use `StageGenerationService` directly.

## Seeder Architecture

The seeder suite parses a real University of Buea faculty/department/programme structure from a markdown file (`database/seeders/support/university_programs_structure.md`) and seeds a complete, realistic dataset:

1. `FacultySeeder` — all UB faculties with matricule prefixes
2. `DepartmentSeeder` — all departments with type classification (`academic`/`records`/`admin`); also creates one `records` department per faculty
3. `ProgrammeSeeder` — all programmes linked to departments
4. `StaffSeeder(80)` — 80 staff users with auto-generated profiles
5. `StudentSeeder(10/dept)` — 10 students per academic department
6. `DepartmentStaffSeeder` — assigns all staff to departments; primary staff automatically elevated to `dept_admin`
7. `RequestTypeSeeder` — 4 request types (Transcript, Enrollment Attestation, Completion Attestation, Correction of Transcript)
