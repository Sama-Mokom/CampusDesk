/**
 * Bug Condition Exploration Test — Stage Resolve Status 422 Fix
 *
 * SPEC: .kiro/specs/stage-resolve-status-422-fix/bugfix.md
 *
 * PURPOSE
 * -------
 * This test surfaces the bug condition described in the spec:
 *
 *   FUNCTION isBugCondition(X)
 *     RETURN X.statusAtSubmit = undefined OR X.statusAtSubmit = null
 *   END FUNCTION
 *
 * On UNFIXED code, `resolveModal.status` on a Vue 3 reactive() object can silently
 * become undefined between openResolve() and submitResolve(), causing Axios to strip
 * the "status" key from the JSON body, which triggers a 422 from the backend.
 *
 * EXPECTED OUTCOME ON UNFIXED CODE
 * ---------------------------------
 * The test FAILS. The resolveStage spy receives either:
 *   - { status: undefined, staff_note: '' }   — or —
 *   - the spy is never called because submitResolve short-circuits
 *
 * That failure IS the proof the bug exists. Do NOT fix the test or the code here.
 *
 * EXPECTED OUTCOME AFTER THE FIX
 * --------------------------------
 * The test PASSES. The spy receives { status: 'approved', staff_note: '' }.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import type { RequestStage } from '../../types'

// ── Service mocks ──────────────────────────────────────────────────────────────
// Must be hoisted before the component import so that when StaffDashboard.vue
// is evaluated its import of '../services/stages' resolves to these mocks.
vi.mock('../../services/stages', () => ({
  fetchStaffQueue: vi.fn().mockResolvedValue([]),
  fetchMyCases:    vi.fn().mockResolvedValue([]),
  claimStage:      vi.fn().mockResolvedValue(undefined),
  resolveStage:    vi.fn().mockResolvedValue(undefined),
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
import { resolveStage } from '../../services/stages'

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

// ── Global stubs for child components ─────────────────────────────────────────
const globalStubs = {
  LevelBadge: true,
  RequestTimeline: true,
  DocumentViewer: true,
}

// ── Helper: mount and wait for onMounted to settle ───────────────────────────
async function mountDashboard() {
  const wrapper = mount(StaffDashboard, {
    global: { stubs: globalStubs },
  })
  await flushPromises()
  return wrapper
}

// ─────────────────────────────────────────────────────────────────────────────
describe('StaffDashboard — resolve modal bug condition exploration', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // ── Test 1: Default approve, no select interaction ────────────────────────
  /**
   * Bug condition: openResolve sets resolveModal.status = 'approved', but
   * submitResolve reads it back as undefined (Vue 3 reactive proxy edge case).
   *
   * On UNFIXED code this test FAILS because resolveStage is called with
   * status: undefined (or not called at all if submitResolve short-circuits).
   *
   * Counterexample (expected on unfixed code):
   *   resolveStage called with { status: undefined, staff_note: '' }
   *   OR resolveStage.mock.calls.length === 0
   *
   * Validates: Requirements 2.1, 2.3 (bugfix.md)
   */
  it('calls resolveStage with status "approved" when user submits without changing the select', async () => {
    const wrapper = await mountDashboard()

    // Access the component's exposed internals via vm
    const vm = wrapper.vm as any

    // Open the resolve modal with our mock stage
    vm.openResolve(mockStage)
    await flushPromises()

    // Submit WITHOUT touching the select (bug condition: default value may be undefined)
    await vm.submitResolve()
    await flushPromises()

    // Assert resolveStage was called exactly once with the correct payload
    expect(resolveStage).toHaveBeenCalledTimes(1)
    expect(resolveStage).toHaveBeenCalledWith(
      mockStage.request_id,
      mockStage.id,
      {
        status: 'approved',  // On unfixed code: undefined — this assertion will FAIL
        staff_note: '',
      }
    )
  })

  // ── Test 2: Change to reject, add note, submit ────────────────────────────
  /**
   * Bug condition: even when the user changes the select to 'rejected',
   * the reactive binding may not propagate the value correctly back to
   * resolveModal.status before submitResolve reads it.
   *
   * On UNFIXED code this test FAILS because resolveStage may receive
   * status: undefined instead of 'rejected'.
   *
   * Validates: Requirements 2.2, 2.3 (bugfix.md)
   */
  it('calls resolveStage with status "rejected" and note when user rejects with a note', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    // Open the resolve modal
    vm.openResolve(mockStage)
    await flushPromises()

    // Programmatically set the status to 'rejected' (simulates select change)
    // On unfixed code: resolveModal.status may still be undefined at submit time
    vm.resolveStatus = 'rejected'
    vm.resolveModal.note = 'Insufficient documents provided'
    await flushPromises()

    await vm.submitResolve()
    await flushPromises()

    expect(resolveStage).toHaveBeenCalledTimes(1)
    expect(resolveStage).toHaveBeenCalledWith(
      mockStage.request_id,
      mockStage.id,
      {
        status: 'rejected',  // On unfixed code: undefined — this assertion will FAIL
        staff_note: 'Insufficient documents provided',
      }
    )
  })

  // ── Test 3: Re-open after close (reactive state leak check) ───────────────
  /**
   * Bug condition: after the first open → cancel cycle, the reactive proxy
   * may leave resolveModal.status in a broken state. The second open calls
   * openResolve() which sets status = 'approved', but the value may not stick.
   *
   * On UNFIXED code this test FAILS for the same status: undefined reason.
   *
   * Validates: Requirements 2.1, 2.3, 3.3 (bugfix.md)
   */
  it('calls resolveStage with status "approved" after modal was opened, cancelled, and reopened', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    // First open → cancel cycle
    vm.openResolve(mockStage)
    await flushPromises()
    vm.resolveModal.open = false  // simulate Cancel
    await flushPromises()

    // Second open
    vm.openResolve(mockStage)
    await flushPromises()

    // Submit without changing anything
    await vm.submitResolve()
    await flushPromises()

    expect(resolveStage).toHaveBeenCalledTimes(1)
    expect(resolveStage).toHaveBeenCalledWith(
      mockStage.request_id,
      mockStage.id,
      {
        status: 'approved',  // On unfixed code: undefined — this assertion will FAIL
        staff_note: '',
      }
    )
  })

  // ── Test 4: Bug condition directly (undefined status guard) ───────────────
  /**
   * This test directly simulates the bug condition by manually setting
   * resolveModal.status = undefined after openResolve().
   * On unfixed code: submitResolve will call resolveStage({ status: undefined, ... })
   * or bypass the rejection guard and send the broken payload.
   *
   * On UNFIXED code this test FAILS — resolveStage is called with undefined status.
   *
   * Validates: Bug Condition function from design.md (Requirements 2.3)
   */
  it('does NOT call resolveStage with undefined status even when the reactive property is clobbered', async () => {
    const wrapper = await mountDashboard()
    const vm = wrapper.vm as any

    // Open the modal normally
    vm.openResolve(mockStage)
    await flushPromises()

    // Directly simulate the bug condition: clobber the resolveStatus ref
    // This is exactly the scenario described in the bug condition function
    vm.resolveStatus = undefined as any
    await flushPromises()

    await vm.submitResolve()
    await flushPromises()

    // resolveStage must NEVER receive undefined as status
    if ((resolveStage as ReturnType<typeof vi.fn>).mock.calls.length > 0) {
      const callArgs = (resolveStage as ReturnType<typeof vi.fn>).mock.calls[0]
      const payload = callArgs[2]
      // On UNFIXED code: payload.status will be undefined — assertion FAILS
      expect(payload.status).toBeDefined()
      expect(['approved', 'rejected']).toContain(payload.status)
    } else {
      // resolveStage not called at all — that's also a bug (approve path should call it)
      // Fail explicitly to document the counterexample
      expect(resolveStage).toHaveBeenCalled()
    }
  })
})
