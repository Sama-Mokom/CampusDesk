# CampusDesk — Testing

**Last reviewed:** 21 October 2026

## Current State

Automated tests cover authentication, account disablement and credential remediation, the request lifecycle, department administration, the Super Admin API, in-app notifications, push notifications and WebSocket invalidations, attachment storage/access, routed role pages, and shared frontend interactions.

Current test suite baseline:
- `php artisan test`: **111 tests passed (592 assertions)** across 23 test suites.
- `npm test`: **18 test files passed (155 tests passed)**.
- `npm run lint` (`eslint .`): **Passed** (0 errors, 0 warnings).
- `npm run typecheck` (`vue-tsc --noEmit`): **Passed** (0 errors).
- `npm run build` (`vite build`): **Passed** (production assets compiled cleanly).

Historical local baselines:
- 9 October 2026: 71 PHPUnit tests / 457 assertions; 14 Vitest files / 132 tests.
- 24 September 2026: 54 PHPUnit tests / 354 assertions; 37 Vitest tests.

---

## Backend Tests (PHPUnit)

**Location:** `campusdesk/tests/Feature/`

**Run command:**
```bash
cd campusdesk
php artisan test
# or
composer run test   # also clears config cache first
```

### Written Feature Tests

#### `RequestTypeSeederTest.php`
Validates that `RequestTypeSeeder` persists the four canonical names, descriptions, cast `default_department_sequence` arrays, and dynamically resolved final department IDs. Runs the seeder twice to lock in idempotency.

#### `DatabaseSeederIntegrationTest.php`
Runs the complete `DatabaseSeeder` against the test database and validates reference-data relationships, records departments, 80 staff users, primary department assignments, eligible students, all six seeded request states, stages/history, attachments, and in-app notification fixtures.

#### `SequentialRoutingBugConditionTest.php`
Tests that confirm sequential routing guards in `RequestStageController`:
- Downstream stage is not visible in queue while predecessor is in review.
- Second serial claim on the same stage returns 409.
- Claiming downstream stage before predecessor approval returns 422.

#### `SequentialRoutingPreservationTest.php`
Tests guarding correct sequential routing behavior:
- First-stage visibility and predecessor-dependent downstream queue visibility.
- Atomic stage claim, state transition, and history recording.
- Staff `myCases` and ordered `forRequest` timeline endpoints.
- Approval advancing to `forwarded` (non-final) or `ready` (final stage).
- Rejection setting request to `rejected`.

#### `SuperAdminDashboardTest.php`
Covers the Super Admin gate, reference CRUD and safe deletion, staff creation and elevation, token revocation, self/last-admin protection, profile consistency, routing-template validation, statistics, and paginated request/status-history reads.

#### `AccountDisablingTest.php`
Covers enabled and disabled login behavior, generic authentication failures, active-account middleware, immediate token/reset-token/remember-token invalidation, role authorization, self-disable protection, enabled-only Super Admin counting, idempotent disable/enable transitions, re-authentication after password rotation, safe audit values, and append-only audit-model enforcement.

#### `DemoCredentialRemediationCommandTest.php`
Covers staging-environment enforcement, dry-run immutability, known-password selection, Super Admin abort behavior, non-echoing retained-persona rotation, transactional apply behavior, random password replacement, account disablement, credential invalidation, audit creation, and second-run idempotency.

#### `AttachmentStorageTest.php`
Covers attachment-backed student request submission and storage integrity:
- Student upload creates a non-empty local-disk path and physical file.
- Owner, department-route staff, assigned handler, and Super Admin can download.
- Unrelated student and staff receive HTTP 403; missing file returns HTTP 404.
- Failed storage write rolls back request and attachment records; multi-file failure cleans up partial writes.

#### `CollectionAndNotificationTest.php`
Covers student marking ready requests as collected, owner-scoping of collection, user-scoped notification retrieval (`GET /api/notifications`), and marking notifications as read (`PATCH /api/notifications/{id}/read`).

#### `ReopenRequestTest.php`
Covers atomic reopening of rejected requests, clearing stage assignment, restoring `pending` status, setting `is_reopened = true`, and creating audit records.

#### `BroadcastChannelAuthorizationTest.php`
Validates private broadcast channel authorization callbacks in `routes/channels.php`:
- Student authorizes own private channel `private-users.{id}`; forbidden on other users' channels.
- Staff authorizes assigned department channel `private-departments.{id}`; forbidden on unassigned department channels.
- Super Admin authorizes any department channel.
- Students forbidden on all department channels; unauthenticated requests return 401.

#### `BroadcastingRequestEventTransportTest.php`
Validates that `BroadcastingRequestEventTransport` implements `RequestEventTransport`, binds in the service container, maps channel strings to `PrivateChannel` instances, and broadcasts synchronously with generic wire event `request.event`.

#### `RequestEventOutboxTest.php`
Validates durable event recording in `request_events`:
- Request creation records `request.created` with aggregate sequence 1 and initial department/student audience.
- Collecting a ready request records `request.status_changed` with incremented sequence.
- Claiming a stage records `request_stage.claimed` with stage ID and claimant audience.
- Resolving and reopening requests record `request.status_changed` with sequential numbering.

#### `PublishRequestEventTest.php`
Validates after-commit queue dispatching:
- Committed request creation, status changes, and stage claims push `PublishRequestEvent` to the isolated `realtime` queue on the `database` connection.
- Rolled-back transactions queue nothing.
- Job handler publishes through `RequestEventDelivery` and updates delivery status to `published`.
- Post-commit queue dispatch failure is reported to error logging without turning the committed database mutation into a false failure.

#### `PublishRequestEventReliabilityTest.php`
Validates queue reliability configuration:
- Job implements `ShouldBeUnique` keyed by event ID with 1-hour lock.
- Configures 6 tries and exponential backoff `[1, 5, 30, 120, 600]`.
- Exhausted failure marks outbox event `failed`, sets `failed_at`, and audits truncated error summary.
- Failure callback does not overwrite an already published event.

#### `RequestEventDeliveryTest.php`
Validates delivery logic:
- Pending event is published to targeted audiences and marked `published` with attempt timestamps.
- Transport failure records sanitized, byte-bounded `last_error` and rethrows for queue retry.
- Re-publishing an already published event is an idempotent no-op.

#### `ReconcileRequestEventsCommandTest.php`
Validates the one-minute durability reconciler `realtime:reconcile`:
- Dispatches delivery jobs for orphaned `pending` events only; ignores `published` and `failed` events.
- Verifies scheduled execution `everyMinute()->withoutOverlapping()`.

#### `RetryRequestEventsCommandTest.php`
Validates the manual recovery command `realtime:retry`:
- Retrying specific event resets delivery status to `pending` and dispatches job.
- `--all` option retries all failed events.
- Rejecting retry for non-failed events.

#### `RealtimeRuntimeConfigurationTest.php`
Validates feature-flag gating (`REALTIME_DELIVERY_ENABLED`), config definition, and delivery suppression when disabled.

---

## Frontend Tests (Vitest)

**Location:** `Frontend/src/**/__tests__/`

**Run command:**
```bash
cd Frontend
npm test           # vitest run --reporter=verbose
```

### Written Test Files

#### `echo.spec.ts`
Tests Laravel Echo configuration and Pusher setup:
- Creates default Reverb configuration with correct host, port, TLS settings, and authorizer.
- Bypasses `window.location.port` when running on Vite dev ports (5173-5179) and defaults to Reverb port 8085.
- Uses proxy port when on custom non-Vite reverse proxy.
- Sanctum authorizer dispatches `POST /broadcasting/auth` with Bearer credentials and handles errors.

#### `realtime.spec.ts`
Tests `RealtimeManager` lifecycle and invalidation handling:
- Subscribes students to `users.{id}` and staff to `users.{id}` plus assigned `departments.{id}`.
- Handles camelCase `staffProfile` property fallbacks.
- Unsubscribes and disconnects Echo on user logout.
- Dispatches typed invalidations on incoming `.request.event`.
- Deduplicates identical event IDs across multiple channels.
- Enforces per-request sequence high-watermark to discard stale or out-of-order events.
- Coalesces rapid invalidation bursts within a configurable debounce window.
- Binds Reverb reconnect handlers to trigger registered reconnect callbacks.

#### `useRealtime.spec.ts`
Tests the `useRealtime` composable:
- Registers invalidation and coalesced listeners.
- Automatically unbinds listeners when the surrounding Vue `effectScope` is disposed (component unmount).

#### `RealtimeIntegration.spec.ts`
Tests integration between real-time invalidations and UI components:
- `NotificationBell` refetches notifications upon receiving real-time invalidation.
- `useStudentRequests` refetches request collection upon receiving real-time invalidation.
- Reconnect triggers immediate refetch across loaded views.

#### `NotificationBell.spec.ts`
Ensures guests do not request protected notifications, authenticated users load them normally, loading errors provide retries, and read states update optimistically with rollback on failure.

#### `DocumentViewer.spec.ts` & `DocumentViewer.lifecycle.spec.ts`
Tests attachment viewer rendering (empty, images, PDFs, fallback) and lifecycle cleanup (canceling pending requests, revoking object URLs).

#### `StaffDashboard.preserve.spec.ts` & `StaffDashboard.resolve.spec.ts`
Tests staff resolution workflow, note validation, and claim independence.

#### `RequestTimeline.spec.ts`
Tests stage display, approved/rejected states, active stage detection, and sorting.

#### `AdminDashboard.spec.ts` & `DeptAdminDashboard.spec.ts`
Tests Super Admin and Department Admin collection loading, filtering, pagination, and stage reassignment.

#### `Registration.spec.ts` & `BaseModal.spec.ts` & `StatusBadge.spec.ts`
Tests registration cascading dropdowns, modal focus trapping/accessibility, and status badge styling.

---

## Test Infrastructure

| Tool | Installed | Used |
|------|-----------|------|
| PHPUnit 11.x | ✅ | ✅ Backend feature tests |
| Vitest 2.x | ✅ | ✅ Frontend unit tests |
| `@vue/test-utils` | ✅ | ✅ Component mounting in Vitest |
| jsdom | ✅ | ✅ Vitest `environment: 'jsdom'` |
| Pest | ❌ Not installed | — |
| Cypress | ❌ Not installed | — |
| Playwright | ❌ Not installed | — |

---

## Testing Coverage Summary

| Area | Coverage |
|------|----------|
| Sequential routing and serial claim conflict (backend) | ✅ Covered by feature tests; true parallel claim test remains open |
| Stage claim/resolve flow (backend) | ✅ Covered by preservation tests |
| DocumentViewer component (frontend) | ✅ Covered by unit and lifecycle tests |
| Staff resolution workspace (frontend) | ✅ Covered by unit tests |
| RequestTimeline component (frontend) | ✅ Covered by unit tests |
| Authentication flows | ✅ Covered across registration, login, logout, password reset, and active-account middleware |
| Attachment storage and authorization | ✅ Covered by `AttachmentStorageTest` |
| Student request submission & lifecycle | ✅ Covered across creation, reopen, and collection |
| In-app notification system | ✅ Covered by backend controller tests and `NotificationBell.spec.ts` |
| Real-time push & WebSocket delivery | ✅ Covered by 9 backend feature tests and 4 frontend unit/integration test suites |
| Admin endpoints & audit records | ✅ Covered across reference CRUD, user management, and administrative actions |
| Frontend E2E | ❌ Not covered (Playwright/Cypress not installed) |
