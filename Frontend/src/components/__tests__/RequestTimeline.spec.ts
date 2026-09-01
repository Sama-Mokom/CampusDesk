import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RequestTimeline from '../RequestTimeline.vue'
import type { RequestStage } from '../../types'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeStage(overrides: Partial<RequestStage> = {}): RequestStage {
  return {
    id: 1,
    request_id: 10,
    department_name: 'Registry',
    sequence_order: 1,
    status: 'pending',
    handled_by: null,
    staff_note: null,
    updated_at: null,
    ...overrides,
  }
}

// ---------------------------------------------------------------------------
// Tests — Requirements 2.4, 2.5, 4.6
// ---------------------------------------------------------------------------

describe('RequestTimeline', () => {
  /**
   * Test 1: empty stages array — no stage rows rendered, no crash.
   * Validates: Requirement 2.5 (graceful empty list)
   */
  it('renders without error when stages is an empty array', () => {
    const wrapper = mount(RequestTimeline, {
      props: { stages: [] as RequestStage[] },
    })
    // No stage rows should be in the DOM
    expect(wrapper.findAll('.flex.gap-3').length).toBe(0)
  })

  /**
   * Test 2: single approved stage — green node classes and ✓ icon.
   * Validates: Requirement 2.4 (correct status icons/colors)
   */
  it('renders a green node with ✓ icon for an approved stage', () => {
    const stage = makeStage({ status: 'approved' })
    const wrapper = mount(RequestTimeline, {
      props: { stages: [stage] },
    })

    const node = wrapper.find('.rounded-full')
    expect(node.classes()).toContain('bg-green-50')
    expect(node.classes()).toContain('border-green-500')
    expect(node.text()).toContain('✓')
  })

  /**
   * Test 3: single rejected stage — red node classes and ✕ icon.
   * Validates: Requirement 2.4 (correct status icons/colors)
   */
  it('renders a red node with ✕ icon for a rejected stage', () => {
    const stage = makeStage({ status: 'rejected' })
    const wrapper = mount(RequestTimeline, {
      props: { stages: [stage] },
    })

    const node = wrapper.find('.rounded-full')
    expect(node.classes()).toContain('bg-red-50')
    expect(node.classes()).toContain('border-red-500')
    expect(node.text()).toContain('✕')
  })

  /**
   * Test 4: isActive logic — stage 2 is active when stage 1 is approved.
   * An active (pending) stage that follows all approved predecessors gets
   * the primary ring class `border-primary`.
   * Validates: Requirement 2.4
   */
  it('marks the second stage as active when stage 1 is approved and stage 2 is pending', () => {
    const stage1 = makeStage({ id: 1, sequence_order: 1, status: 'approved' })
    const stage2 = makeStage({ id: 2, sequence_order: 2, status: 'pending' })

    const wrapper = mount(RequestTimeline, {
      props: { stages: [stage1, stage2] },
    })

    const nodes = wrapper.findAll('.rounded-full')
    expect(nodes.length).toBe(2)

    // stage1 — approved → green border
    expect(nodes[0]!.classes()).toContain('border-green-500')

    // stage2 — active → primary border
    expect(nodes[1]!.classes()).toContain('border-primary')
  })

  /**
   * Test 5: stages are rendered in ascending sequence_order regardless of
   * the order they are passed as props.
   * Validates: Requirement 2.4 (ordered by sequence_order)
   */
  it('renders stages sorted by sequence_order ascending regardless of input order', () => {
    const stage1 = makeStage({ id: 1, sequence_order: 1, department_name: 'Registry', status: 'approved' })
    const stage2 = makeStage({ id: 2, sequence_order: 2, department_name: 'Finance', status: 'pending' })
    const stage3 = makeStage({ id: 3, sequence_order: 3, department_name: 'Dean', status: 'pending' })

    // Pass them in reverse order
    const wrapper = mount(RequestTimeline, {
      props: { stages: [stage3, stage1, stage2] },
    })

    const deptNames = wrapper.findAll('.font-semibold').map(el => el.text())
    expect(deptNames).toEqual(['Registry', 'Finance', 'Dean'])
  })
})
