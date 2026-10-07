# CampusDesk — Testing

**Last reviewed:** 7 October 2026

## Current State

Automated tests cover authentication, the request lifecycle, department administration, the Super Admin API, notifications, attachment storage/access, routed role pages, and shared frontend interactions. On 7 October 2026 the full local suites passed:

- `php artisan test`: **58 tests, 373 assertions**.
- `npm test -- --maxWorkers=2 --minWorkers=1`: **14 files, 132 tests**.

The pull request and merged `development` workflows also passed their applicable quality gates. The two pull-request image-publication jobs were correctly skipped; the merge push published the backend and frontend images.

Historical local baseline on 24 September 2026, before the later attachment regression tests were added:

- `php artisan test`: 54 tests passed with 354 assertions.
- `npm test`: 37 tests passed.
- `npm run lint`: passed.
- `npx vue-tsc --noEmit`: passed.
- `npm run build`: passed.

These historical numbers explain the earlier milestone but are not the current suite totals. Test counts will evolve; rely on the latest successful run and update this page when coverage changes.

Focused verification on 2 October 2026:

- `php artisan test --filter AttachmentStorageTest`: 4 tests passed with 19 assertions.

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

Validates that `RequestTypeSeeder` persists the four canonical names, descriptions, cast `default_department_sequence` arrays, and dynamically resolved final department IDs. It runs the seeder twice to lock in idempotency.

#### `DatabaseSeederIntegrationTest.php`

Runs the complete `DatabaseSeeder` against the test database and validates reference-data relationships, records departments, 80 staff users, primary department assignments, eligible students, all six seeded request states, stages/history, attachments, and in-app notification fixtures.

#### `SequentialRoutingBugConditionTest.php`
Tests that confirm the two defects in the original (pre-fix) `RequestStageController`:

| Test | Purpose |
|------|---------|
| `test_downstream_stage_is_not_visible_in_queue_while_predecessor_is_in_review` | Defect 1: downstream stage must NOT appear in queue when predecessor is in_review (not approved) |
| `test_second_serial_claim_on_same_stage_returns_409` | Defect 2: serial double-claim returns 409 |
| `test_claiming_downstream_stage_before_predecessor_approved_returns_422` | Guard: direct claim on out-of-order stage returns 422 |

#### `SequentialRoutingPreservationTest.php`
Tests that guard correct behaviour that must not regress:

| Test | Requirement |
|------|-------------|
| `test_first_stage_pending_stage_appears_in_general_queue` | P-3.1: first stage always visible |
| `test_first_stage_appears_when_second_stage_is_also_pending` | P-3.1 (N=2) |
| `test_second_stage_appears_in_queue_when_predecessor_is_approved` | P-3.2: stage N visible when predecessor approved |
| `test_third_stage_appears_in_queue_when_second_is_approved` | P-3.2 (N=3) |
| `test_valid_claim_returns_200_and_transitions_stage_correctly` | P-3.3: claim transitions, history created |
| `test_valid_claim_on_second_stage_with_approved_predecessor` | P-3.3 (N=2) |
| `test_my_cases_returns_in_review_stages_for_authenticated_staff` | P-3.4: myCases endpoint |
| `test_my_cases_returns_empty_when_no_in_review_stages` | P-3.4 (empty case) |
| `test_for_request_endpoint_returns_timeline_in_sequence_order_for_staff` | P-3.5: ordered timeline for the bound request |
| `test_for_request_returns_not_found_for_unknown_request` | P-3.5: unknown request handling |
| `test_approving_non_final_stage_advances_request_to_forwarded` | P-3.6a |
| `test_approving_final_stage_advances_request_to_ready` | P-3.6b |
| `test_approving_final_stage_in_three_stage_chain_sets_request_to_ready` | P-3.6b (N=3) |
| `test_rejecting_a_stage_sets_request_to_rejected` | P-3.6c |
| `test_rejecting_non_final_stage_still_sets_request_to_rejected` | P-3.6c (non-final) |
| `test_property_n1_only_eligible_stages_in_queue` | Property N=1 |
| `test_property_n2_only_eligible_stages_in_queue` | Property N=2 |
| `test_property_n3_only_eligible_stages_in_queue` | Property N=3 |

**Route binding:** `GET /requests/{docRequest}/stages` now matches the controller parameter; additional timeline tests cover the returned stages.

#### `SuperAdminDashboardTest.php`

Covers the Super Admin gate, reference CRUD and safe deletion, staff creation and elevation, token revocation, self/last-admin protection, profile consistency, routing-template validation, statistics, and paginated request/status-history reads.

#### `AttachmentStorageTest.php`

Covers the attachment-backed student request path and the staging defect that previously persisted `file_path = 0` after an unwritable-volume failure:

- a student upload creates a non-empty local-disk path and physical file;
- the owner, a department-route staff member, an assigned handler, and a Super Admin can download it;
- an unrelated student and unrelated staff member receive HTTP 403;
- a missing physical file returns HTTP 404;
- a failed storage write rolls back both the request and attachment record; and
- a later failure in a multi-file upload removes files already written by that request.

### Existing Default Tests

| File | Status |
|------|--------|
| `tests/Feature/ExampleTest.php` | Default Laravel scaffold — minimal, not project-specific |
| `tests/Feature/Auth/` | Breeze auth tests — scaffolded by Breeze install |
| `tests/Unit/` | Empty (default Laravel scaffold only) |

---

## Frontend Tests (Vitest)

### Issue #7 redesign regression coverage

The redesign extends the Vitest/jsdom suite with `StudentPages`, `StaffDashboard.workspace`, `DeptAdminDashboard`, `AdminDashboard`, `AuthAndShell`, `Registration`, `BaseModal`, `StatusBadge`, and `DocumentViewer.lifecycle` tests. The historical dashboard-named test files now exercise routed pages or extracted workflow components. Super Admin tests also cover delete conflicts and preserving the user ID when the student profile ID differs. All API mocks are confined to tests.

Native dialogs use a jsdom-compatible open-state fallback; tests verify accessible naming, focus wrapping/return, dismissal and pending guards. This is not a replacement for real-browser keyboard or visual checks. See [FRONTEND_REDESIGN.md](FRONTEND_REDESIGN.md) for executed commands and the outstanding 375/768/1024/1440px manual acceptance checks. There is no configured browser E2E runner.

**Location:** `Frontend/src/components/__tests__/`

**Run command:**
```bash
cd Frontend
npm test           # vitest run --reporter=verbose
```

### Written Test Files

#### `DocumentViewer.spec.ts`
Unit tests for `DocumentViewer.vue`:

| Test | Purpose |
|------|---------|
| Empty-state message when attachments is empty | Req 1.2, 3.6 |
| Image attachment shows `<img>` | Req 3.4 |
| PDF attachment shows `<iframe>` | Req 3.4 |
| Unsupported file type shows download fallback | Req 3.5 |
| Clicking active file collapses viewer (toggle) | Req 3.7 |

#### `StaffDashboard.preserve.spec.ts`
Preservation property tests for the extracted `staff/StaffCaseWorkspace.vue` resolve workflow:

| Test Group | Purpose |
|------------|---------|
| Rejection guard preservation | Rejected + empty note blocks API call; rejected + note allows it; approved + empty note is fine |
| Claim flow independence | `pickUp()` calls `claimStage()` correctly; does not interfere with resolve modal state |
| Modal reset on close | `openResolve()` clears note and error on each open; attaches correct stage |

#### `StaffDashboard.resolve.spec.ts`
Bug condition tests for the resolve status flow:

Tests cover the bug condition where `resolveModal.status` was `undefined` or `null` at submit time, causing the backend to receive an invalid payload. All service calls are mocked via `vi.mock`.

#### `RequestTimeline.spec.ts`
Unit tests for `RequestTimeline.vue` stage display.

#### `NotificationBell.spec.ts`

Ensures guests do not request protected notifications and authenticated users load them normally. This prevents a 401-triggered login reload loop.

#### `AdminDashboard.spec.ts`

Covers server-backed collection loading and pagination, rejected-request reopen, protected document viewing, request filtering, and the searchable faculty-grouped staff membership form.

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

## Test Coverage Gaps

### High Priority (concurrency-sensitive, previously buggy)

- [ ] **Concurrent claim test** — Two simultaneous claim requests on same stage; assert only one succeeds (200), the other gets 409. Requires multi-connection or goroutine-style execution — difficult in PHPUnit, but could simulate with `pcntl_fork` or a dedicated concurrency testing harness.
- [x] **Queue filtering integration test** — Preservation tests verify a downstream stage stays hidden until its predecessor is approved.
- [x] **Status-history transition coverage** — Feature tests verify one stage-level observer entry plus a parent-level entry, both attributed through `users.id`, for claim, resolve, and reopen.

### Medium Priority (core business logic)

- [ ] Student cannot view another student's request (403)
- [ ] Staff from wrong department cannot claim a stage (403)
- [ ] File upload validation — invalid types/oversized files return 422
- [x] Attachment access control — owner and related staff roles are allowed; unrelated students and staff are denied
- [x] Attachment-backed request submission — successful persistence, failed-write rollback, and partial-write cleanup
- [ ] Broader request submission cases create the correct number of stages for varied `default_department_sequence` templates

### Lower Priority (auth/validation)

- [ ] Registration with duplicate email/matricule → 422
- [ ] Login with wrong password → 401/422
- [ ] Rate limiting: exceed 5/minute login limit → 429
- [ ] `StoreRequestRequest` and `UpdateStageStatusRequest` field-by-field validation

### Frontend (gaps)

- [x] Routed student pages — request list, direct detail URL, protected attachment metadata, submission, reopen, and collection
- [x] Auth flow — login, persisted auth restoration, backend logout, and duplicate-logout prevention
- [x] Route guards — guest redirect retains the requested destination
- [x] Role routing — every nested role route rejects incompatible roles

---

## Testing Coverage Summary

| Area | Coverage |
|------|----------|
| Sequential routing and serial claim conflict (backend) | ✅ Covered by feature tests; true parallel claim test remains open |
| Stage claim/resolve flow (backend) | ✅ Covered by preservation tests |
| DocumentViewer component (frontend) | ✅ Five tests cover empty, image, PDF, fallback, and collapse behavior using the authenticated blob flow |
| Staff resolution workspace (frontend) | ✅ Covered by unit tests |
| RequestTimeline component (frontend) | ✅ Covered by unit tests |
| Authentication flows | ✅ Registration, verification, password reset, login, logout, and token behavior pass against the current API and fixtures |
| Attachment storage and authorization | ✅ Covered by `AttachmentStorageTest`; invalid/oversized validation cases remain open |
| Student request submission | ✅ Attachment-backed creation path covered; broader routing-template cases remain open |
| Notification system | ✅ Backend and bell tests |
| Admin endpoints | ✅ Focused department and Super Admin feature tests |
| Frontend E2E | ❌ Not covered |

## Current verification limits

- True parallel stage-claim behavior still lacks a multi-connection concurrency test.
- Invalid and oversized upload validation still needs dedicated feature coverage.
- Broader request-submission routing-template cases still need focused backend integration coverage.
- Browser-level E2E coverage is not installed.
- GitHub Actions runs the backend and frontend quality gates on disposable Ubuntu 24.04 runners.

## Staging Functional Verification — 2 October 2026

Manual end-to-end verification on the private staging deployment confirmed:

- a student can submit a request with a PDF attachment and receives a real `attachments/<generated-name>.pdf` path rather than `/storage/0`;
- an authenticated and authorized user can retrieve that attachment through `GET /api/attachments/{id}` with HTTP 200, the expected `application/pdf` content type, filename, and content length;
- staff can claim and approve each sequential department stage;
- the request reaches `ready`, the student receives the lifecycle notification, and the student can mark it `collected`; and
- the request, stages, history, notification, and attachment remain visible through their authenticated API flows.

This is functional staging evidence, not an installed browser automation suite. The screenshots and operator observations confirm the tested path; Playwright/Cypress coverage remains open.
