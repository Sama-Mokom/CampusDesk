# CampusDesk — Project History & Evolution

This timeline records how CampusDesk progressed from an observed university-service problem to a working, staged system. Early dates are approximate because some surviving artifacts contain inconsistent local timestamps; later delivery and operational milestones use the recorded repository and staging evidence.

---

## Phase 0: Problem Definition & Product Planning

- Sama Mokom identified a recurring problem from his experience at the University of Buea: students could submit requests for official documents but had little visibility into their progress, delays, or required corrections.
- The impact extended beyond inconvenience because transcripts and attestations are often inputs to scholarship, university-application, internship, and employment deadlines.
- **Product decision:** build CampusDesk as a university document-request and tracking system that makes each request's current stage, outcome, and next action visible.
- The initial delivery plan covered system modeling, data and identity foundations, the request lifecycle, notifications, security hardening, and operational readiness.
- Initial frontend scaffolding established separate Student, Staff, and Admin experiences around the same request workflow.

## Phase 1: System Modeling

- Before any code was written, the full system was modeled: ERD, request lifecycle state machine, and role/permission matrix.
- Key modeling decisions made during product and architecture analysis:
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

- Initial environment: Windows, XAMPP, VS Code, and MySQL via phpMyAdmin.
- `composer create-project laravel/laravel campusdesk` — Laravel project created (originally installed as Laravel 11, later upgraded to Laravel 12 per `composer.json`).
- `.env` configured for local MySQL, `php artisan key:generate`, initial `php artisan migrate` confirmed working.

## Phase 3: Migrations & Models

- 13 initial migrations created in dependency order: faculties → departments → programmes → users (+role) → student_profiles → staff_profiles → department_staff → request_types → requests → request_stages → status_history → attachments → notifications. (The total later grew to 27 through subsequent schema evolution — see Phase 10 and DATABASE.md.)
- Several migration defects were identified and fixed, including a missing `name` column on `departments`, duplicate faculty seeding, and hardcoded-ID fragility — see KNOWN_ISSUES.md.
- 12 Eloquent models were built with explicit relationships and corrected for mass-assignment boundaries, relationship naming, and foreign-key references.
- Table name overrides identified and applied: `StatusHistory` → `status_history`, `DepartmentStaff` → `department_staff`.

## Phase 4: Authentication & Roles

- Laravel Breeze installed in API mode (`php artisan breeze:install api`) — provides Sanctum token infrastructure without Blade views.
- `RegisteredUserController::store()` extended to also create a `StudentProfile` after the `User` — went through multiple debugging rounds (missing `user_id` reference, missing `$fillable` fields, validation rules for the new profile fields).
- Gates defined in `AppServiceProvider`: the intended names were `is-student`, `is-staff`, `is-dept-admin`, `is-super-admin` (all hyphens). The actual current code has a naming inconsistency: `is_student` and `is_staff` use underscores, `is-super-admin` uses hyphens, and `is_dept-admin` uses mixed convention (underscore + hyphen) — see KNOWN_ISSUES.md.
- Middleware classes created: `EnsureIsStudent`, `EnsureIsStaff`, `EnsureIsDeptAdmin`, `EnsureIsSuperAdmin` — each checking the corresponding Gate.
- **Bug partially fixed:** Gate name inconsistency caused legitimate requests to be rejected. The `is_student`/`is_staff` inconsistency was resolved; the `is_dept-admin` mixed naming was introduced and remains unresolved — see KNOWN_ISSUES.md.
- Login (`AuthenticatedSessionController::store()`) was originally the default Breeze session-based implementation (`$request->session()->regenerate()`), which crashed with "Session store not set on request" in the API context. It was **rewritten to issue Bearer tokens** (`createToken()->plainTextToken`), aligning authentication with the separate Vue client and stateless API boundary.

## Phase 5: Core Request Lifecycle

- `StoreRequestRequest` and `UpdateStageStatusRequest` Form Request classes created for validation.
- `RequestController` and `RequestStageController` built via `--resource` scaffolding, then customized.
- The stage-generation logic in `store()` — looping over `default_department_sequence` to create `request_stages` — went through several rounds of debugging (syntax errors, wrong field names, `$fillable` omissions).
- The staff pickup/lock mechanic (`claim()`) and resolution mechanic (`resolve()`) used an atomic conditional update (`->where('status','pending')->whereNull('handled_by')->update(...)`, followed by an affected-row check) to prevent two staff members from taking the same stage.
- Routes wired in `api.php`, with several rounds of debugging route-model-binding parameter name mismatches — see KNOWN_ISSUES.md.
- Full end-to-end Postman testing performed across all endpoints (register, login, submit request, claim, resolve).

## Phase 6: Automatic Status History via Observer

- Laravel Observers were selected over controller-level helper calls so status history would be recorded consistently regardless of the mutation path (controller, Tinker, seeder, or future service code).
- `RequestStageObserver` created, using `isDirty('status')` and `getOriginal('status')` to detect and log transitions on the `updated` event.
- Manual `statusHistories()->create()` calls removed from controllers once the observer was confirmed working — full end-to-end regression test performed and passed.

## Phase 7: Queues & Mail

- Email delivery was moved off the request path to avoid blocking HTTP responses and to make delivery failures independently retryable.
- `RequestStatusUpdated` Mailable created (constructor takes User, DocumentRequest, string status; `loadMissing('requestType')` to ensure relationship availability).
- `SendRequestStatusNotification` Job created, dispatching the Mailable.
- Mailtrap configured for local dev email testing.
- Observer updated to dispatch the notification job alongside status history logging.
- Verified end-to-end: resolved a stage in Postman → job appeared in `jobs` table → queue worker processed it → email arrived in Mailtrap with correct dynamic content.

## Phase 8: API Hardening (Phase 6 of original roadmap)

- Rate limiting was applied by risk and workload: stricter limits for authentication and `POST /requests`, with looser limits for read routes and staff actions.
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
- `auth.ts`, `requests.ts`, `stages.ts`, and `reference.ts` established the frontend API service boundary.
- Recurring naming collision bug identified and resolved: native browser `Request`/`Response` types colliding with custom domain types — resolved via type aliasing (`Request as DocumentRequest`) on both frontend TypeScript and backend PHP.

### 9d. Auth Flow Wiring
- `useAuth.ts` composable created (localStorage-backed, replacing the earlier mock session state).
- `router/index.ts` updated to use `useAuth` for route guards.
- `LoginView.vue` and `RegisterView.vue` rewired to call real services, with async/await and try/catch error handling replacing the previous synchronous mock calls.
- **Backend addition during this phase:** `ReferenceDataController` created (faculties/departments/programmes/request-types public endpoints) — these did not exist before integration began and were needed to unblock the registration form's dropdowns.
- Login/register response shapes updated to include nested `student_profile`/`staff_profile` — required for role-based routing to function.

### 9e. Student Dashboard Wiring
- `StudentDashboard.vue` was rewritten to replace the earlier mock layer with real service calls (`fetchRequests`, `fetchRequestTypes`, `fetchFaculties`, `fetchDepartments`, `createRequest`).
- Multiple response-shape mismatches debugged: Laravel Resource `{data: [...]}` wrapper not being unwrapped, `request_type` type mismatch (object vs string), file upload payload issues (wrong FormData field, Axios `Content-Type` override breaking multipart uploads).
- File upload flow (`fileList`) rewritten from metadata-only objects to real `File` objects.
- Detail modal "click to open" bug traced to missing eager-loading on the list endpoint (fixed by calling `fetchRequestById` on click instead of using list-item data directly) and a stale/incorrect route definition (`/request/{requests}` → `/requests/{request}`).
- Null-safety bug fixed: initial submission's `status_history.changed_by` is `null`, causing a template crash (`Cannot read properties of null (reading 'name')`) — fixed with optional chaining.

### 9f. Staff Dashboard Wiring
- Similar systematic rewrite of `StaffDashboard.vue`.
- **Backend addition:** discovered that `RequestStageController::index()` (used for the queue) doesn't accept a meaningful `{request}` parameter — a dedicated `GET /api/stages` staff-queue endpoint and `GET /api/stages/my-cases` (active claims) endpoint were added.
- `RequestStageResource` extended to include nested `request` object with student name/matricule/level and request type — needed for the staff queue cards to display student context.
- Resolve modal field-name mismatch bug (`resolution`/`action` vs `status`) traced and fixed.
- **Critical bug discovered and fixed:** a request's Stage 2 could be claimed before Stage 1 was approved. An initial query filter and claim guard still left a time-of-check/time-of-use race. The final design closed that race with SQL `whereExists` eligibility checks and transactional `lockForUpdate()` locking — see KNOWN_ISSUES.md.

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

## State at the September 2026 documentation audit

- Student Dashboard: fully wired and functional.
- Staff Dashboard: fully wired and functional, with concurrency-safe claim/queue logic.
- Automated seeder suite: fully operational (`php artisan migrate:fresh --seed` produces a complete dataset).
- PHPUnit tests: 2 feature test files covering sequential routing.
- Vitest tests: 4 test files covering key frontend components.
- Documentation audit performed against actual source files (September 2026).

## Known issues at the earlier documentation audit (historical)

The five items below were subsequently resolved. Current verification limits are listed in [TESTING.md](TESTING.md).

1. `AuthenticatedSessionController::destroy()` crashes in API context — logout does not revoke tokens
2. `forRequest()` route-model binding mismatch — `GET /requests/{request}/stages` always returns empty
3. `AppServiceProvider` defines `'is_dept-admin'` (mixed naming) — `dept_admin` middleware always denies
4. Frontend `RegisterCredentials.level` type uses `L100`–`L600` but backend expects `100`–`600`
5. Frontend `DegreeType` uses `BSc/BEng/etc.` but database uses `BACHELOR/CERTIFICATE/etc.`

See KNOWN_ISSUES.md and HANDOFF.md for details and fixes.

## Phase 11: CI/CD Session 1 - Local Docker Foundation

On 25 September 2026, CampusDesk gained its first reproducible container environment. This phase deliberately stopped before GitHub Actions, registry publishing, or AWS deployment.

- Repaired and verified the full local quality baseline: 54 PHPUnit tests with 354 assertions, 37 Vitest tests, ESLint, `vue-tsc`, and the Vite production build.
- Standardized the frontend on npm, removed the pnpm lock file, and stopped tracking generated `dist/` output.
- Added a PHP 8.3/Apache backend image with production Composer dependencies, `pdo_mysql`, PCNTL, Laravel's public document root, and aligned upload limits.
- Added a Node 22 build stage and Nginx runtime image for the Vue SPA.
- Added Docker Compose services for MySQL 8.4, Laravel, the queue worker, and the frontend.
- Added database and application health checks, same-origin `/api` proxying, a persistent MySQL named volume, and a private attachment bind mount.
- Verified real database migrations, queued mail processing through the log driver, attachment survival, database survival after `docker compose down`, and reconstruction of the entire stack.
- Fixed an Apache-image stop-signal mismatch by overriding the worker with `SIGTERM`; idle worker shutdown fell from approximately 70 seconds to 0.2 seconds.
- Confirmed that only Nginx publishes a host port and that the backend, worker, and database remain internal to the Compose network.

The complete local design, commands, evidence, limitations, and subsequent AWS work are recorded in the consolidated [CI/CD implementation and operations guide](CI_CD_SESSION_2_HANDOFF.md).

## Phase 12: CI, AWS staging, controlled deployment, and attachment hardening

From 30 September through 2 October 2026, CampusDesk progressed from local containers to a verified private AWS staging deployment.

- Added Ubuntu 24.04 GitHub Actions quality gates, Compose validation, and backend/frontend image builds.
- Configured GitHub OIDC with least-privilege roles and immutable Amazon ECR repositories; pushes to `development` publish commit-SHA-tagged images.
- Deployed a digest-pinned single-host Compose stack to Ubuntu 24.04 AMD64 EC2 with pull-only runtime ECR access, a persistent MySQL volume, a private attachment volume, health checks, log rotation, and an SSH-tunnel-only frontend.
- Ran migrations and the full demonstration seed explicitly and verified student and manually created Super Admin access.
- Added a protected `staging` GitHub environment, environment-scoped deployment role, restricted custom SSM document, and fixed root-owned host deployment script.
- Verified exact digest resolution, deployment locking, pre-downtime pulls, a single migration run, application recreation, health/API smoke tests, explicit failure boundaries, and the repeated-release no-op path.
- Diagnosed attachment 404s to a root-owned named-volume directory that caused Laravel's upload operation to return `false` and persist `file_path = 0`.
- Added `attachments-init`, fail-fast explicit local-disk writes, partial-write cleanup, scoped attachment authorization, and `AttachmentStorageTest` regression coverage.
- Functionally verified a complete request lifecycle and successful authenticated PDF upload/retrieval in staging on 2 October 2026.

## Phase 13: Routed role pages and staging recovery

From 3 through 7 October 2026, the frontend redesign and recoverability work were merged into `development`.

- Split the four role dashboards into guarded nested overview/task pages while preserving the existing API and authorization contracts; PR #18 merged the routed-page work.
- Provisioned a private versioned S3 recovery bucket, customer-managed KMS key, encrypted SNS topic, scoped EC2 runtime policy, and missing-heartbeat CloudWatch alarm through CloudFormation.
- Added root-owned recovery scripts and systemd units for coherent twice-daily database/attachment capture, artifact checksums, immutable image references, S3 upload metadata validation, download verification, local retention, and 15-minute freshness monitoring.
- Verified manual capture `20261006T122804Z`, isolated database/attachment/backend-image restoration, alarm recovery from `ALARM` to `OK`, and unattended timer capture `20261007T000005Z`.
- Merged PR #20 as `989ca54`; the recovery feature branch was then removed locally and remotely.

The current authoritative operational record is [CI_CD_SESSION_2_HANDOFF.md](CI_CD_SESSION_2_HANDOFF.md). Credential remediation, DNS, HTTPS, periodic restore drills, and the separate administrative-action audit remain future work. Automatic failover and destructive live restoration are not implemented.
