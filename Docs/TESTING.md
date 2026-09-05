# CampusDesk — Testing

## Current State

Automated tests exist for both backend and frontend. They were written to lock in the sequential routing concurrency fix and the stage-resolve flow.

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
| `test_for_request_endpoint_returns_200_for_staff` | P-3.5: forRequest returns 200 (even though response is empty due to binding bug) |
| `test_for_request_response_is_wrapped_in_data_key` | P-3.5: response format |
| `test_approving_non_final_stage_advances_request_to_forwarded` | P-3.6a |
| `test_approving_final_stage_advances_request_to_ready` | P-3.6b |
| `test_approving_final_stage_in_three_stage_chain_sets_request_to_ready` | P-3.6b (N=3) |
| `test_rejecting_a_stage_sets_request_to_rejected` | P-3.6c |
| `test_rejecting_non_final_stage_still_sets_request_to_rejected` | P-3.6c (non-final) |
| `test_property_n1_only_eligible_stages_in_queue` | Property N=1 |
| `test_property_n2_only_eligible_stages_in_queue` | Property N=2 |
| `test_property_n3_only_eligible_stages_in_queue` | Property N=3 |

**Note on `forRequest` tests:** `SequentialRoutingPreservationTest` explicitly documents that `GET /requests/{request}/stages` always returns empty due to the route-model binding mismatch. These tests verify the **current (buggy) behaviour** as a regression anchor — the tests would need updating when the binding bug is fixed.

### Existing Default Tests

| File | Status |
|------|--------|
| `tests/Feature/ExampleTest.php` | Default Laravel scaffold — minimal, not project-specific |
| `tests/Feature/Auth/` | Breeze auth tests — scaffolded by Breeze install |
| `tests/Unit/` | Empty (default Laravel scaffold only) |

---

## Frontend Tests (Vitest)

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
Preservation property tests for `StaffDashboard.vue` resolve modal:

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
- [ ] **Queue filtering integration test** — Seed a request, have one staff member claim Stage 1 but not approve, assert Stage 2 does NOT appear in the Stage 2 dept's queue. *(This is now covered by preservation tests P-3.1/P-3.2)*
- [ ] **Status history FK correctness test** — Assert `status_history.changed_by` resolves to a `staff_profiles.id` (not `users.id`) after claim and resolve.

### Medium Priority (core business logic)

- [ ] Student cannot view another student's request (403)
- [ ] Staff from wrong department cannot claim a stage (403)
- [ ] File upload validation — invalid types/oversized files return 422
- [ ] Attachment access control — student can view own attachment; different student cannot; any staff can
- [ ] Request submission creates correct number of stages matching `default_department_sequence`

### Lower Priority (auth/validation)

- [ ] Registration with duplicate email/matricule → 422
- [ ] Login with wrong password → 401/422
- [ ] Rate limiting: exceed 5/minute login limit → 429
- [ ] `StoreRequestRequest` and `UpdateStageStatusRequest` field-by-field validation

### Frontend (gaps)

- [ ] `StudentDashboard.vue` — request list, detail modal, attachment loading
- [ ] Auth flow — login → token stored → protected route accessible
- [ ] Route guards — unauthenticated user redirected from `/student`
- [ ] Role routing — student cannot access `/staff`

---

## Testing Coverage Summary

| Area | Coverage |
|------|----------|
| Sequential routing concurrency (backend) | ✅ Covered by feature tests |
| Stage claim/resolve flow (backend) | ✅ Covered by preservation tests |
| DocumentViewer component (frontend) | ✅ Covered by unit tests |
| StaffDashboard resolve modal (frontend) | ✅ Covered by unit tests |
| RequestTimeline component (frontend) | ✅ Covered by unit tests |
| Authentication flows | ⚠️ Breeze scaffold only; no project-specific tests |
| Attachment security | ❌ Not covered |
| Student request submission | ❌ Not covered |
| Notification system | ❌ Not covered (feature not built) |
| Admin endpoints | ❌ Not covered (endpoints not built) |
| Frontend E2E | ❌ Not covered |
