import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import DeptAdminDashboard from '../DeptAdminDashboard.vue'
import {
  fetchDepartmentAdminRequests,
  reassignStage,
  type DepartmentAdminOverview
} from '@/services/deptAdmin'
import { claimStage } from '@/services/stages'

vi.mock('@/services/deptAdmin', () => ({
  fetchDepartmentAdminRequests: vi.fn(),
  reassignStage: vi.fn()
}))
vi.mock('@/services/stages', () => ({ claimStage: vi.fn() }))
enableAutoUnmount(afterEach)
const overview: DepartmentAdminOverview = {
  department: { id: 3, name: 'Faculty records' },
  stats: {
    total: 1,
    unclaimed: 0,
    claimable: 0,
    blocked: 0,
    in_review: 1,
    completed: 0
  },
  staff: [
    { id: 7, name: 'Current Handler', staff_id: 'STAFF7' },
    { id: 9, name: 'Next Handler', staff_id: 'STAFF9' }
  ],
  stages: [
    {
      id: 12,
      request_id: 8,
      department_name: 'Faculty records',
      sequence_order: 2,
      status: 'in_review',
      handled_by: 7,
      handler: { id: 7, name: 'Current Handler' },
      staff_note: 'Checking originals',
      updated_at: '2026-10-02T10:00:00Z',
      is_claimable: false,
      blocked_reason: null,
      reassignments: [],
      request: {
        id: 8,
        description: 'Transcript for graduation',
        request_type: 'Transcript',
        created_at: '2026-10-01T10:00:00Z',
        student_name: 'Student One',
        student_matricule: 'SC123',
        student_level: '400'
      }
    }
  ]
}
function render() {
  return mount(DeptAdminDashboard, { global: { stubs: { teleport: true } } })
}
beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(fetchDepartmentAdminRequests).mockResolvedValue(
    structuredClone(overview)
  )
  vi.mocked(reassignStage).mockResolvedValue(overview.stages[0]!)
})
describe('Department oversight', () => {
  it('renders API-scoped work and distinguishes filtered empty from no work', async () => {
    const wrapper = render()
    await flushPromises()
    expect(wrapper.text()).toContain('Faculty records')
    expect(wrapper.text()).toContain('Student One')
    await wrapper.get('input[type="search"]').setValue('missing')
    expect(wrapper.text()).toContain('No matching stages')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Clear filters')!
      .trigger('click')
    expect(wrapper.text()).toContain('Student One')
  })
  it('only reassigns to another department member and preserves stage identity', async () => {
    const wrapper = render()
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Reassign')!
      .trigger('click')
    const dialog = wrapper.get('dialog')
    expect(dialog.find('option[value="7"]').exists()).toBe(false)
    await dialog.get('form').trigger('submit')
    await flushPromises()
    expect(reassignStage).not.toHaveBeenCalled()
    expect(wrapper.get('dialog [role="alert"]').text()).toContain(
      'Select an eligible staff member'
    )
    await dialog.get('select').setValue('9')
    await dialog.get('form').trigger('submit')
    await flushPromises()
    expect(reassignStage).toHaveBeenCalledWith(12, 9)
    expect(wrapper.find('dialog').exists()).toBe(false)
    expect(wrapper.get('[role="status"]').text()).toContain('Stage reassigned')
  })
  it('keeps failed reassignment available for retry', async () => {
    vi.mocked(reassignStage).mockRejectedValueOnce(new Error('Offline'))
    const wrapper = render()
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Reassign')!
      .trigger('click')
    await wrapper.get('dialog select').setValue('9')
    await wrapper.get('dialog form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('dialog [role="alert"]').text()).toContain(
      'Unable to reassign'
    )
    expect(
      (wrapper.get('dialog select').element as HTMLSelectElement).value
    ).toBe('9')
  })
  it('claims only an eligible stage using both original identifiers', async () => {
    const data = structuredClone(overview)
    Object.assign(data.stages[0]!, {
      status: 'pending',
      handled_by: null,
      handler: null,
      is_claimable: true
    })
    vi.mocked(fetchDepartmentAdminRequests).mockResolvedValue(data)
    const wrapper = render()
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Pick up')!
      .trigger('click')
    await flushPromises()
    expect(claimStage).toHaveBeenCalledWith(8, 12)
    expect(fetchDepartmentAdminRequests).toHaveBeenCalledTimes(2)
  })
  it('shows load failure without misleading metrics, then recovers', async () => {
    vi.mocked(fetchDepartmentAdminRequests).mockRejectedValueOnce(
      new Error('Offline')
    )
    const wrapper = render()
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Unable to load')
    expect(
      wrapper.find('[aria-label="Department stage statistics"]').exists()
    ).toBe(false)
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Try again')!
      .trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Student One')
  })
})
