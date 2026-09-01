/**
 * Preservation Property Tests — Stage Resolve Status 422 Fix
 *
 * SPEC: .kiro/specs/stage-resolve-status-422-fix/bugfix.md
 * DESIGN: .kiro/specs/stage-resolve-status-422-fix/design.md
 *
 * PURPOSE
 * -------
 * These tests capture the existing CORRECT behaviour of the resolve modal on
 * UNFIXED code for all code paths where the bug condition does NOT hold (i.e.
 * where `resolveModal.status` is already a valid string).
 *
 *   FUNCTION isBugCondition(X)
 *     RETURN X.statusAtSubmit = undefined OR X.statusAtSubmit = null
 *   END FUNCTION
 *
 * The tests here cover NOT isBugCondition(X) paths:
 *
 *   Property 2 — Preservation:
 *   FOR ALL X WHERE NOT isBugCondition(X) DO
 *     ASSERT submitResolve_original(X) = submitResolve_fixed(X)
 *     // Same HTTP body, same UI outcome, same queue reload
 *   END FOR
 *
 * EXPECTED OUTCOME ON UNFIXED CODE
 * ---------------------------------
 * ALL tests PASS. This establishes the baseline behaviour that must not regress
 * after the fix is applied.
 *
 * EXPECTED OUTCOME AFTER THE FIX
 * --------------------------------
 * ALL tests STILL PASS (regression confirmation).
 *
 * Validates: Requirements 3.1, 3.2, 3.3 (bugfix.md)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { RequestStage } from '../../types'

// ── Service mocks ──────────────────────────────────────────────────────────────
// Hoisted before the component import so that StaffDashboard.vue resolves these.
vi.mock('../../services/stages', () => ({
  fetchStaffQueue:    vi.fn().mockResolvedValue([]),
  fetchMyCases:       vi.fn().mockResolvedValue([]),
  claimStage:         vi.fn().mockResolvedValue(undefined),
  resolveStage:       vi.fn().mockResolvedValue(undefined),
  fetchRequestStages: vi.fn().mockResolvedValue([]),
}))

// ── Auth composable mock ───────────────────────────────────────────────────────
vi.mock('../../composables/useAuth', () => ({
  useAuth: () => ({
    user: { value: {
      id: 1,
      name: 'Test Staff',
      email: 'staff@test.com',
      role: 'staff',
      created_at: '2024-01-01',
      staff_profile: {
        staff_id: 'S001',
        admin_level: null,
        departments: [{ id: 1, name: 'Computer Science', code: 'CS', is_primary: true }],
      },
    }},
    isAuthenticated: { value: true },
    setUser: vi.fn(),
    clearAuth: vi.fn(),
  }),
}))

// Lazy import after mocks are registered
import StaffDashboard from '../StaffDashboard.vue'
import { resolveStage, claimStage, fetchStaffQueue, fetchMyCases } from '../../services/stages'

// ── Mock stage fixture ─────────────────────────────────────────────────────────
const mockStage: RequestStage = {
  id: 42,
  request_id: 7,
  department_name: 'Computer Science',
  sequence_order: 1,
  status: 'in_review',
  handled_by: 'S001',
  staff_note: null,
  updated_at: null,
  request: {
    id: 7,
    description: 'Please issue my transcript',
    request_type: 'Transcript Request',
    created_at: '2024-01-15T10:00:00Z',
    student_name: 'Alice Student',
    student_matricule: 'FE/2021/001',
    student_level: '300',
  },
}

// An unclaimed stage for the "Pick up" flow
const unclaimedStage: RequestStage = {
  id: 99,
  request_id: 12,
  department_name: 'Computer Science',
  sequence_order: 1,
  status: 'pending',
  handled_by: null,
  staff_note: null,
  updated_at: null,
  request: {
    id: 12,
    description: 'Need degree certificate',
    request_type: 'Degree Certificate',
    created_at: '2024-02-01T08:00:00Z',
    student_name: 'Bob Student',
    student_matricule: 'FE/2020/002',
    student_level: '400',
  },
}

// ── Global stubs for child components ─────────────────────────────────────────
const globalStubs = {
  LevelBadge: true,
  RequestTimeline: true,
  DocumentViewer: true,
}

// ── Helper: mount and wait for onMounted to settle ────────────────────────────
async function mountDashboard() {
  const wrapper = mount(StaffDashboard, {
    global: { stubs: globalStubs },
  })
  await flushPromises()
  return wrapper
}

// =============================================================================
// Test Case A — Rejection Guard Preservation (Requirement 3.1)
// =============================================================================
/**
 * Property 2 — Preservation of client-side rejection guard:
 * FOR ALL inputs with status === 'rejected' AND empty note:
 *   ASSERT resolveStage is NOT called
 *   ASSERT resolveError === 'A staff note is required when rejecting.'
 *
 * This covers NOT isBugCondition(X) paths where resolveModal.status is the valid
 * string 'rejected' — the status is present and correct, but the guard fires before
 * the API call because the note is empty.
 *
 * Validates: Requirement 3.1 (bugfix.md)
 */
describe('StaffDashboard — Preservation: rejection guard (Req 3.1)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('blocks the API call and sets error when status is "rejected" with an empty note (empty string)', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    vm.openResolve(mockStage)
    await flushPromises()

    // Force the non-buggy path: status is a valid string, note is empty
    vm.resolveStatus = 'rejected'
    vm.resolveModal.note = ''
    await flushPromises()

    await vm.submitResolve()
    await flushPromises()

    // resolveStage must NOT have been called
    expect(resolveStage).not.toHaveBeenCalled()

    // Error message must be set
    expect(vm.resolveError).toBe('A staff note is required when rejecting.')
  })

  it('blocks the API call and sets error when status is "rejected" with a whitespace-only note', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    vm.openResolve(mockStage)
    await flushPromises()

    // Whitespace-only note should be treated as empty by .trim()
    vm.resolveStatus = 'rejected'
    vm.resolveModal.note = '   '
    await flushPromises()

    await vm.submitResolve()
    await flushPromises()

    expect(resolveStage).not.toHaveBeenCalled()
    expect(vm.resolveError).toBe('A staff note is required when rejecting.')
  })

  it('allows the API call when status is "rejected" and a non-empty note is provided', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    vm.openResolve(mockStage)
    await flushPromises()

    // Non-buggy path: status valid, note present — guard must NOT fire
    vm.resolveStatus = 'rejected'
    vm.resolveModal.note = 'Missing documents'
    await flushPromises()

    await vm.submitResolve()
    await flushPromises()

    // Guard must NOT block this
    expect(vm.resolveError).not.toBe('A staff note is required when rejecting.')

    // resolveStage must have been called with the correct payload
    expect(resolveStage).toHaveBeenCalledTimes(1)
    expect(resolveStage).toHaveBeenCalledWith(
      mockStage.request_id,
      mockStage.id,
      expect.objectContaining({
        status: 'rejected',
        staff_note: 'Missing documents',
      })
    )
  })

  it('does NOT show rejection error when status is "approved" with an empty note', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    vm.openResolve(mockStage)
    await flushPromises()

    // Approved + empty note: guard must NOT fire
    vm.resolveStatus = 'approved'
    vm.resolveModal.note = ''
    await flushPromises()

    await vm.submitResolve()
    await flushPromises()

    // No rejection-guard error
    expect(vm.resolveError).not.toBe('A staff note is required when rejecting.')

    // resolveStage must have been called
    expect(resolveStage).toHaveBeenCalledTimes(1)
  })
})

// =============================================================================
// Test Case B — Claim Flow Independence (Requirement 3.2)
// =============================================================================
/**
 * Property 2 — Preservation of claim flow:
 * FOR ALL stages in the queue:
 *   pickUp(stage) MUST call claimStage(stage.request_id, stage.id)
 *   pickUp is completely independent of resolveModal state
 *   After pickUp, the queue reloads (fetchStaffQueue + fetchMyCases called again)
 *
 * Validates: Requirement 3.2 (bugfix.md)
 */
describe('StaffDashboard — Preservation: claim flow independence (Req 3.2)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('calls claimStage with the correct request_id and stage id when pickUp is called', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    // Reset call counts from onMounted
    vi.clearAllMocks()

    await vm.pickUp(unclaimedStage)
    await flushPromises()

    expect(claimStage).toHaveBeenCalledTimes(1)
    expect(claimStage).toHaveBeenCalledWith(unclaimedStage.request_id, unclaimedStage.id)
  })

  it('reloads the queue (fetchStaffQueue + fetchMyCases) after a successful pickUp', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    // Reset call counts from onMounted
    vi.clearAllMocks()

    await vm.pickUp(unclaimedStage)
    await flushPromises()

    // Queue must be reloaded after claim
    expect(fetchStaffQueue).toHaveBeenCalledTimes(1)
    expect(fetchMyCases).toHaveBeenCalledTimes(1)
  })

  it('pickUp is independent of resolveModal state — works regardless of what resolveModal contains', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    // Put resolveModal in an open state with a stage selected
    vm.openResolve(mockStage)
    await flushPromises()
    vm.resolveStatus = 'rejected'
    vm.resolveModal.note = 'Some note'
    await flushPromises()

    vi.clearAllMocks()

    // pickUp should still work correctly even with resolveModal holding data
    await vm.pickUp(unclaimedStage)
    await flushPromises()

    expect(claimStage).toHaveBeenCalledTimes(1)
    expect(claimStage).toHaveBeenCalledWith(unclaimedStage.request_id, unclaimedStage.id)

    // resolveStage must NOT have been called during pickUp
    expect(resolveStage).not.toHaveBeenCalled()
  })

  it('does NOT interfere with resolveModal state after pickUp completes', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    vm.openResolve(mockStage)
    await flushPromises()

    vi.clearAllMocks()

    // Run pickUp — it must not clobber the resolve modal's open state or stage
    await vm.pickUp(unclaimedStage)
    await flushPromises()

    // resolveModal should be unaffected by pickUp
    expect(vm.resolveModal.stage).toEqual(mockStage)
    expect(vm.resolveModal.open).toBe(true)
  })
})

// =============================================================================
// Test Case C — Modal Reset on Close (Requirement 3.3)
// =============================================================================
/**
 * Property 2 — Preservation of modal reset behaviour:
 * FOR ANY sequence open → cancel → open:
 *   resolveModal.note  must be ''
 *   resolveModal.open  must be true
 *   resolveError       must be ''
 *
 * Validates: Requirement 3.3 (bugfix.md)
 */
describe('StaffDashboard — Preservation: modal reset on close (Req 3.3)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('resets note to empty string when the modal is reopened after a cancel', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    // First open — set a note
    vm.openResolve(mockStage)
    await flushPromises()
    vm.resolveModal.note = 'Some draft note'
    await flushPromises()

    // Cancel (simulate clicking the Cancel button)
    vm.resolveModal.open = false
    await flushPromises()

    // Second open — note must be reset
    vm.openResolve(mockStage)
    await flushPromises()

    expect(vm.resolveModal.note).toBe('')
  })

  it('sets modal open to true on the second open after a cancel', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    vm.openResolve(mockStage)
    await flushPromises()
    vm.resolveModal.open = false
    await flushPromises()

    vm.openResolve(mockStage)
    await flushPromises()

    expect(vm.resolveModal.open).toBe(true)
  })

  it('clears resolveError on the second open after a validation error was set', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    // First open — trigger the rejection guard to populate resolveError
    vm.openResolve(mockStage)
    await flushPromises()
    vm.resolveStatus = 'rejected'
    vm.resolveModal.note = ''
    await flushPromises()
    await vm.submitResolve()
    await flushPromises()

    // Confirm the error was set
    expect(vm.resolveError).toBe('A staff note is required when rejecting.')

    // Cancel
    vm.resolveModal.open = false
    await flushPromises()

    // Second open — resolveError must be cleared by openResolve
    vm.openResolve(mockStage)
    await flushPromises()

    expect(vm.resolveError).toBe('')
  })

  it('attaches the correct stage on the second open when a different stage is passed', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    // Open with first stage
    vm.openResolve(mockStage)
    await flushPromises()

    // Cancel
    vm.resolveModal.open = false
    await flushPromises()

    // Open with a different stage
    vm.openResolve(unclaimedStage)
    await flushPromises()

    expect(vm.resolveModal.stage).toEqual(unclaimedStage)
    expect(vm.resolveModal.note).toBe('')
    expect(vm.resolveModal.open).toBe(true)
  })

  it('resets note even when closed multiple times before reopening', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    // Open → set note → close → close again (idempotent) → open
    vm.openResolve(mockStage)
    await flushPromises()
    vm.resolveModal.note = 'draft'
    vm.resolveModal.open = false
    await flushPromises()
    vm.resolveModal.open = false
    await flushPromises()

    vm.openResolve(mockStage)
    await flushPromises()

    expect(vm.resolveModal.note).toBe('')
    expect(vm.resolveModal.open).toBe(true)
  })
})
