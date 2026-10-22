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

- 13 initial migrations created in dependency order: faculties → departments → programmes → users (+role) → student_profiles → staff_profiles → department_staff → request_types → requests → request_stages → status_history → attachments → notifications. (The total later grew to 33 through subsequent schema evolution — see Phase 10, Phase 14, and DATABASE.md.)
- Several migration defects were identified and fixed, including a missing `name` column on `departments`, duplicate faculty seeding, and hardcoded-ID fragility — see KNOWN_ISSUES.md.
- 12 Eloquent models were built with explicit relationships and corrected for mass-assignment boundaries, relationship naming, and foreign-key references.
- Table name overrides identified and applied: `StatusHistory` → `status_history`, `DepartmentStaff` → `department_staff`.

## Phase 4: Authentication & Roles

- Laravel Breeze installed in API mode (`php artisan breeze:install api`) — provides Sanctum token infrastructure without Blade views.
- `RegisteredUserController::store()` extended to also create a `StudentProfile` after the `User` — went through multiple debugging rounds (missing `user_id` reference, missing `$fillable` fields, validation rules for the new profile fields).
- Gates defined in `AppServiceProvider`: the intended names were `is-student`, `is-staff`, `is-dept-admin`, `is-super-admin` (all hyphens).
- Middleware classes created: `EnsureIsStudent`, `EnsureIsStaff`, `EnsureIsDeptAdmin`, `EnsureIsSuperAdmin` — each checking the corresponding Gate.
- Login (`AuthenticatedSessionController::store()`) was originally the default Breeze session-based implementation (`$request->session()->regenerate()`), which crashed with "Session store not set on request" in the API context. It was **rewritten to issue Bearer tokens** (`createToken()->plainTextToken`), aligning authentication with the separate Vue client and stateless API boundary.

## Phase 5: Request Lifecycle Implementation

- Request submission, stage generation, and initial history recording implemented in `RequestController::store()`.
- Department stage progression implemented via `RequestStageController::claim()` and `resolve()`.
- Concurrency locks added to prevent race conditions during simultaneous claims.

## Phase 6: Observer Pattern & Audit Trails

- Created `RequestStageObserver` to automatically capture status transitions into `status_history`.
- Parent request transitions captured explicitly to maintain a clear audit trail.

## Phase 7: Queues & Email Delivery

- Configured Mailtrap SMTP integration for development email testing.
- Added mailable classes dispatched asynchronously to the queue.

## Phase 8: Frontend Integration

- Initialized Vue 3 SPA with Vite, Vue Router, Tailwind CSS, and Lucide icons.
- Built composables (`useAuth`) and API services with Axios request/response interceptors.

## Phase 9: Automated University Seeder Suite

- Built parsers and mappers for actual University of Buea academic structures.
- Automated generation of faculties, departments, programmes, staff, students, and demo requests.

## Phase 10: Concurrency Hardening & Sequential Routing

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

## Phase 14: Account Security Hardening, Administrative Action Audit, and Real-Time Event Delivery

From 8 through 21 October 2026, CampusDesk added administrative account control, audit decoupling, and end-to-end real-time push delivery:

- **8–9 October 2026 (Account Security Hardening & Administrative Audit):**
  - Added `users.disabled_at` column, permanent account disablement, active-token rejection via `active_account` middleware, credential revocation, and last-active Super Admin safeguards.
  - Introduced the append-only `administrative_actions` table and Super Admin management endpoints (`PATCH /api/admin/users/{user}/disable`, `enable`, `GET /api/admin/administrative-actions`), keeping administrative mutations separate from request `status_history`.
  - Added staging credential remediation commands, rotating three retained personas and remediating 618 shared-password demo accounts.
- **14–21 October 2026 (Real-Time Invalidation & Push Delivery):**
  - Implemented the Transactional Outbox Pattern using the `request_events` table (UUID primary key, strictly increasing `aggregate_sequence`, delivery status tracking, JSON audience mapping, retry/failure audit columns).
  - Built `RequestEventRecorder` to record outbox events inside the primary database transactions and dispatch asynchronous `PublishRequestEvent` jobs to a dedicated `realtime` database queue upon commit.
  - Deployed Laravel Reverb WebSocket broadcasting over private channels (`private-users.{id}`, `private-departments.{id}`) authorized via Sanctum Bearer tokens.
  - Implemented client-side subscription and lifecycle management using Laravel Echo and Pusher JS (`realtime.ts`).
  - Followed the "invalidate and refetch" architecture: broadcast envelopes contain strictly metadata and identifiers (no student PII or internal staff notes), triggering scoped REST query refetches on client arrival.
  - Added the `realtime:reconcile` console command scheduled every 5 minutes to recover stranded or failed events, providing at-least-once delivery guarantees.
  - Expanded Docker Compose topology from 4 to 7 services (`frontend`, `backend`, `worker`, `db`, `reverb`, `realtime-worker`, `scheduler`).
  - Conducted a surgical code audit and optimization: removed triplicated aggregate sequence computation in `RequestEventRecorder`, unified error summary formatting in `RequestEventDelivery`, streamlined eager-loading queries in `RequestStatusNotificationService`, and resolved frontend TypeScript warnings.
  - Reached full verification baseline: 111 PHPUnit tests (592 assertions) across 23 suites and 155 Vitest unit tests across 18 files with zero lint/typecheck errors and clean production builds.
