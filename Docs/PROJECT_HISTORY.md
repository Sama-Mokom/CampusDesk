# CampusDesk — Project History & Evolution

This timeline reconstructs the project's development chronologically based on the conversation record. Dates are approximate, inferred from message timestamps and file upload timestamps visible in the conversation (project appears to span roughly April–September 2026 based on artifact timestamps, though the developer's system clock in some Tinker outputs shows dates ranging from April to September 2026 — treat exact dates as approximate).

---

## Phase 0: Project Selection & Planning

- Developer (Sama Mokom) expressed a goal: improve Laravel/PHP backend skills through independent, hand-coded learning, using AI only as a Socratic mentor — not a code generator.
- Plan: vibe-code the frontend with v0/Vue, hand-code the backend against Laravel docs and Stack Overflow.
- Claude was asked to suggest a non-generic project idea reflecting the developer's real context (University of Buea student, GYGM Foundation connection, past internship experience).
- **Decision:** CampusDesk — a university document request and tracking system — was chosen over generic alternatives (todo app, blog) specifically because it naturally covers the full breadth of Laravel fundamentals and solves a problem the developer has personally experienced.
- A full Laravel learning roadmap was created covering 6 phases: project setup, migrations/Eloquent, auth/roles, core request lifecycle, queues/mail, and polish/hardening.
- A v0 prompt was drafted for initial frontend scaffolding (multi-role dashboards: Student, Staff, Admin).

## Phase 1: System Modeling

- Before any code was written, the full system was modeled: ERD, request lifecycle state machine, and role/permission matrix.
- Key modeling decisions made through Socratic questioning:
  - Only students initiate requests (not staff on their behalf)
  - Requests can involve multiple departments in sequence
  - Rejected requests are closed but reopenable (not editable in place) — **ADR-06**
  - Staff can belong to multiple departments — **ADR-03**
  - Stage claiming model: "any staff can pick up, but then it gets locked to them" — **ADR-05**
  - Two-tier admin system (dept_admin / super_admin) via `staff_profiles.admin_level` — **ADR-04**
  - Separate `faculties` table (not just a field on departments)
- A detailed user-model ERD was produced covering `faculties`, `departments`, `programmes`, `users`, `student_profiles`, `staff_profiles`, `department_staff` pivot.
- A Cursor Agent prompt was drafted to align the already-scaffolded v0 frontend UI with this backend model (mock data shapes, roles, navigation).

## Phase 2: Laravel Project Setup

- Environment: Windows + XAMPP (not Laragon, despite an initial mix-up in the conversation) + VS Code + MySQL via phpMyAdmin.
- `composer create-project laravel/laravel campusdesk` — Laravel project created (originally installed as Laravel 11, later upgraded to Laravel 12 per `composer.json`).
- `.env` configured for local MySQL, `php artisan key:generate`, initial `php artisan migrate` confirmed working.

## Phase 3: Migrations & Models

- 13 initial migrations created in dependency order: faculties → departments → programmes → users (+role) → student_profiles → staff_profiles → department_staff → request_types → requests → request_stages → status_history → attachments → notifications. (The total later grew to 27 through subsequent schema evolution — see Phase 10 and DATABASE.md.)
- The developer encountered and fixed several migration bugs independently (missing `name` column on `departments`, duplicate faculty seeding, hardcoded ID fragility) — see KNOWN_ISSUES.md.
- 12 Eloquent models built with relationships, reviewed one at a time by Claude with corrective feedback (e.g., removing `id` from `$fillable`, fixing relationship method casing, fixing foreign key references).
- Table name overrides identified and applied: `StatusHistory` → `status_history`, `DepartmentStaff` → `department_staff`.

## Phase 4: Authentication & Roles

- Laravel Breeze installed in API mode (`php artisan breeze:install api`) — provides Sanctum token infrastructure without Blade views.
- `RegisteredUserController::store()` extended to also create a `StudentProfile` after the `User` — went through multiple debugging rounds (missing `user_id` reference, missing `$fillable` fields, validation rules for the new profile fields).
- Gates defined in `AppServiceProvider`: the intended names were `is-student`, `is-staff`, `is-dept-admin`, `is-super-admin` (all hyphens). The actual current code has a naming inconsistency: `is_student` and `is_staff` use underscores, `is-super-admin` uses hyphens, and `is_dept-admin` uses mixed convention (underscore + hyphen) — see KNOWN_ISSUES.md.
- Middleware classes created: `EnsureIsStudent`, `EnsureIsStaff`, `EnsureIsDeptAdmin`, `EnsureIsSuperAdmin` — each checking the corresponding Gate.
- **Bug partially fixed:** Gate name inconsistency caused legitimate requests to be rejected. The `is_student`/`is_staff` inconsistency was resolved; the `is_dept-admin` mixed naming was introduced and remains unresolved — see KNOWN_ISSUES.md.
- Login (`AuthenticatedSessionController::store()`) was originally the default Breeze session-based implementation (`$request->session()->regenerate()`), which crashed with "Session store not set on request" once tested via Postman in the API context. **Rewritten to Bearer token issuance** (`createToken()->plainTextToken`) — this was an important learning moment, with Claude explaining the session-vs-token distinction in depth at the developer's request.

## Phase 5: Core Request Lifecycle

- `StoreRequestRequest` and `UpdateStageStatusRequest` Form Request classes created for validation.
- `RequestController` and `RequestStageController` built via `--resource` scaffolding, then customized.
- The stage-generation logic in `store()` — looping over `default_department_sequence` to create `request_stages` — went through several rounds of debugging (syntax errors, wrong field names, `$fillable` omissions).
- The staff pickup/lock mechanic (`claim()`) and resolution mechanic (`resolve()`) were designed and implemented by the developer with Claude reviewing for correctness rather than writing the code — notably the atomic conditional update pattern (`->where('status','pending')->whereNull('handled_by')->update(...)` then checking affected row count) was praised as "exactly the right pattern" for the pickup lock.
- Routes wired in `api.php`, with several rounds of debugging route-model-binding parameter name mismatches — see KNOWN_ISSUES.md.
- Full end-to-end Postman testing performed across all endpoints (register, login, submit request, claim, resolve).

## Phase 6: Automatic Status History via Observer

- Socratic discussion led the developer to identify Laravel Observers as the correct pattern (rather than a manual "helper" function) for guaranteeing status history is logged regardless of the call path (controller, Tinker, seeder, etc.).
- `RequestStageObserver` created, using `isDirty('status')` and `getOriginal('status')` to detect and log transitions on the `updated` event.
- Manual `statusHistories()->create()` calls removed from controllers once the observer was confirmed working — full end-to-end regression test performed and passed.

## Phase 7: Queues & Mail

- Discussion on why synchronous email sending is problematic (blocks HTTP response, risk of silent failure) led into Laravel's queue system.
- `RequestStatusUpdated` Mailable created (constructor takes User, DocumentRequest, string status; `loadMissing('requestType')` to ensure relationship availability).
- `SendRequestStatusNotification` Job created, dispatching the Mailable.
- Mailtrap configured for local dev email testing.
- Observer updated to dispatch the notification job alongside status history logging.
- Verified end-to-end: resolved a stage in Postman → job appeared in `jobs` table → queue worker processed it → email arrived in Mailtrap with correct dynamic content.

## Phase 8: API Hardening (Phase 6 of original roadmap)

- Rate limiting applied to routes, with the developer correctly reasoning through which endpoints needed the strictest limits (auth routes, `POST /requests`) versus looser limits (read routes, staff actions).
- API Resources (`RequestResource`, `RequestStageResource`, `UserResource`) built to replace raw Eloquent model responses, using `whenLoaded()` for conditional relationship exposure.
- Controllers updated to return Resources instead of raw models/collections.
- Full Postman re-verification of response shapes.

## Phase 9: Frontend/Backend Integration

This was the longest and most debugging-intensive phase.

### 9a. Pre-Integration Audit
- A comprehensive frontend integration spec (`frontend_integration_specs.md`) was produced, documenting the Vue SPA's state as **entirely mock-data-driven** with zero backend wiring — no HTTP client, no env config, no token persistence.
- 10 integration gaps identified and prioritized: CORS mismatch, missing HTTP client, no auth flow, login response shape mismatch, missing `password_confirmation`, missing reference-data endpoints, response shape misalignment (`request_type` as object vs string), missing endpoints (reopen/collect/notifications/reassign/admin CRUD), no 401 handling.
- **Decision:** Bearer token auth confirmed as the strategy (over Sanctum SPA cookie mode) since the backend was already built and tested this way.

### 9b. CORS & Environment Setup
- Multiple rounds of CORS debugging: initial mismatch (backend defaulted to port 3000, frontend runs on 5173), then a redirect loop caused by `EnsureFrontendRequestsAreStateful` being present alongside Bearer token auth — ultimately removed. See KNOWN_ISSUES.md.
- `HandleCors` middleware explicitly prepended in `bootstrap/app.php`.

### 9c. API Service Layer (Frontend)
- Axios installed, `api.ts` created with request interceptor (attach Bearer token from localStorage) and response interceptor (401 → clear token, redirect to login).
- TypeScript config updated to include `vite/client` types for `import.meta.env`.
- `auth.ts`, `requests.ts`, `stages.ts`, `reference.ts` service files built incrementally, each reviewed by Claude.
- Recurring naming collision bug identified and resolved: native browser `Request`/`Response` types colliding with custom domain types — resolved via type aliasing (`Request as DocumentRequest`) on both frontend TypeScript and backend PHP.

### 9d. Auth Flow Wiring
- `useAuth.ts` composable created (localStorage-backed, replacing the mock `useMockData` session ref).
- `router/index.ts` updated to use `useAuth` instead of `useMockData` for route guards.
- `LoginView.vue` and `RegisterView.vue` rewired to call real services, with async/await and try/catch error handling replacing the previous synchronous mock calls.
- **Backend addition during this phase:** `ReferenceDataController` created (faculties/departments/programmes/request-types public endpoints) — these did not exist before integration began and were needed to unblock the registration form's dropdowns.
- Login/register response shapes updated to include nested `student_profile`/`staff_profile` — required for role-based routing to function.

### 9e. Student Dashboard Wiring
- Extensive step-by-step guided rewrite of `StudentDashboard.vue`, replacing every `useMockData` reference with real service calls (`fetchRequests`, `fetchRequestTypes`, `fetchFaculties`, `fetchDepartments`, `createRequest`).
- Multiple response-shape mismatches debugged: Laravel Resource `{data: [...]}` wrapper not being unwrapped, `request_type` type mismatch (object vs string), file upload payload issues (wrong FormData field, Axios `Content-Type` override breaking multipart uploads).
- File upload flow (`fileList`) rewritten from metadata-only objects to real `File` objects.
- Detail modal "click to open" bug traced to missing eager-loading on the list endpoint (fixed by calling `fetchRequestById` on click instead of using list-item data directly) and a stale/incorrect route definition (`/request/{requests}` → `/requests/{request}`).
- Null-safety bug fixed: initial submission's `status_history.changed_by` is `null`, causing a template crash (`Cannot read properties of null (reading 'name')`) — fixed with optional chaining.

### 9f. Staff Dashboard Wiring
- Similar systematic rewrite of `StaffDashboard.vue`.
- **Backend addition:** discovered that `RequestStageController::index()` (used for the queue) doesn't accept a meaningful `{request}` parameter — a dedicated `GET /api/stages` staff-queue endpoint and `GET /api/stages/my-cases` (active claims) endpoint were added.
- `RequestStageResource` extended to include nested `request` object with student name/matricule/level and request type — needed for the staff queue cards to display student context.
- Resolve modal field-name mismatch bug (`resolution`/`action` vs `status`) traced and fixed.
- **Critical bug discovered and fixed:** the multi-claim concurrency bug (see KNOWN_ISSUES.md) — a request's Stage 2 was claimable before Stage 1 was approved. Claude proposed a two-part fix (query filter + claim guard); the developer identified this initial fix was insufficient (TOCTOU race condition remained) and independently designed and implemented a more robust fix using SQL `whereExists` + `lockForUpdate()` transactional locking.

### 9g. Document Viewing
- 403 bug on attachment viewing traced to files being stored in private storage but referenced via public-style URLs.
- `AttachmentController` built for authenticated, role-aware file streaming.
- `DocumentViewer.vue` rewired to fetch files as blobs and display via `URL.createObjectURL()`, since HTML `src` attributes can't carry Bearer tokens.
- "Open in new tab" button bug (accessing `window` directly in template) fixed by moving to a script-level function.

### 9h. Staff "View Details" Bug
- Empty stage timeline bug traced to the staff dashboard calling the wrong endpoint (the filtered queue endpoint, which excludes claimed/resolved stages).
- Resolved by reusing the existing student `show()` endpoint with a relaxed ownership check (`isOwner || isStaff`), avoiding a new duplicate endpoint.

## Phase 10: Seeder Suite & Testing

Following the frontend integration work, a comprehensive seeder suite was built to replace ad-hoc Tinker commands:

- `UserFactory` refactored to support both `staff()` and `student()` states with auto-generated profiles, proper matricule generation (format: `{PREFIX}{YEAR2}{SECTION}{SEQ3}`), and random but realistic department/programme assignment
- `FacultyMarkdownParser` + support helpers to parse a real University of Buea faculty/department/programme structure from a markdown document
- `FacultySeeder`, `DepartmentSeeder`, `ProgrammeSeeder` driven by the parsed data
- `StaffSeeder(80)`, `StudentSeeder(10/dept)`, `DepartmentStaffSeeder` for full user seeding
- `DepartmentStaffSeeder` auto-elevates each department's primary staff to `dept_admin`

Additional schema changes were made during this phase:
- Added `matricule_prefix` to `faculties`
- Added `department_id` to `programmes`; `Programme` model boot hook auto-syncs `faculty_id`
- Changed `level` enum from `L100`–`L600` to `100`–`600`
- Changed `degree_type` enum from `BSc/BEng/MEng/MSc/PhD` to `BACHELOR/CERTIFICATE/MASTER/PHD`
- Added `type` column to `departments` (`academic`/`records`/`admin`)
- Fixed `programmes.code` unique constraint through two further migrations (single → compound with dept_id → triple with degree_type)

`StageGenerationService` was extracted from `RequestController`'s inline `resolveSequence()` method, providing a cleaner, reusable service-layer implementation of symbolic token resolution.

PHPUnit feature tests were written to lock in the sequential routing concurrency fix:
- `SequentialRoutingBugConditionTest` — confirms the two defects
- `SequentialRoutingPreservationTest` — guards 18+ correct behaviours with property-based scenarios

Vitest frontend unit tests were written:
- `DocumentViewer.spec.ts` — file viewer behaviour
- `StaffDashboard.preserve.spec.ts` and `StaffDashboard.resolve.spec.ts` — resolve modal fix
- `RequestTimeline.spec.ts` — timeline component

## Current State (as of September 2026)

- Student Dashboard: fully wired and functional.
- Staff Dashboard: fully wired and functional, with concurrency-safe claim/queue logic.
- Automated seeder suite: fully operational (`php artisan migrate:fresh --seed` produces a complete dataset).
- PHPUnit tests: 2 feature test files covering sequential routing.
- Vitest tests: 4 test files covering key frontend components.
- Documentation audit performed against actual source files (September 2026).

## Known Open Issues (as of documentation audit)

1. `AuthenticatedSessionController::destroy()` crashes in API context — logout does not revoke tokens
2. `forRequest()` route-model binding mismatch — `GET /requests/{request}/stages` always returns empty
3. `AppServiceProvider` defines `'is_dept-admin'` (mixed naming) — `dept_admin` middleware always denies
4. Frontend `RegisterCredentials.level` type uses `L100`–`L600` but backend expects `100`–`600`
5. Frontend `DegreeType` uses `BSc/BEng/etc.` but database uses `BACHELOR/CERTIFICATE/etc.`

See KNOWN_ISSUES.md and HANDOFF.md for details and fixes.
