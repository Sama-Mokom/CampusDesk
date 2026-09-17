import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { Request as DocumentRequest } from '../../types'

vi.mock('../../services/requests', () => ({
  fetchRequests: vi.fn().mockResolvedValue([]),
  fetchRequestById: vi.fn(),
  createRequest: vi.fn(),
  reopenRequest: vi.fn(),
  markRequestCollected: vi.fn(),
}))

vi.mock('../../services/reference', () => ({
  fetchRequestTypes: vi.fn().mockResolvedValue([]),
  fetchFaculties: vi.fn().mockResolvedValue([]),
  fetchDepartments: vi.fn().mockResolvedValue([]),
}))

vi.mock('../../composables/useAuth', () => ({
  useAuth: () => ({
    user: { value: {
      id: 1,
      name: 'Test Student',
      email: 'student@example.test',
      role: 'student',
      created_at: '2026-01-01T00:00:00Z',
      student_profile: null,
    }},
  }),
}))

import StudentDashboard from '../StudentDashboard.vue'
import { reopenRequest } from '../../services/requests'

const rejectedRequest: DocumentRequest = {
  id: 42,
  request_type: 'Transcript',
  description: 'Needed for an application.',
  status: 'rejected',
  is_reopened: false,
  created_at: '2026-01-01T00:00:00Z',
  attachments: [],
  stages: [],
  status_history: [],
}

const reopenedRequest: DocumentRequest = {
  ...rejectedRequest,
  status: 'pending',
  is_reopened: true,
}

async function mountDashboard() {
  const wrapper = mount(StudentDashboard, {
    global: {
      stubs: {
        StatusBadge: true,
        LevelBadge: true,
        RequestTimeline: true,
        DocumentViewer: true,
      },
    },
  })
  await flushPromises()

  const vm = wrapper.vm as any
  vm.studentRequests = [rejectedRequest]
  vm.selectedRequest = rejectedRequest
  await wrapper.vm.$nextTick()

  return wrapper
}

describe('StudentDashboard — reopen request', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('calls reopenRequest with the selected request ID', async () => {
    vi.mocked(reopenRequest).mockResolvedValue(reopenedRequest)
    const wrapper = await mountDashboard()

    await wrapper.get('[data-testid="reopen-request"]').trigger('click')
    await flushPromises()

    expect(reopenRequest).toHaveBeenCalledOnce()
    expect(reopenRequest).toHaveBeenCalledWith(rejectedRequest.id)
  })

  it('updates the selected request and request list from the API response, then hides reopen', async () => {
    vi.mocked(reopenRequest).mockResolvedValue(reopenedRequest)
    const wrapper = await mountDashboard()

    await wrapper.get('[data-testid="reopen-request"]').trigger('click')
    await flushPromises()

    const vm = wrapper.vm as any
    expect(vm.selectedRequest).toEqual(reopenedRequest)
    expect(vm.studentRequests).toEqual([reopenedRequest])
    expect(wrapper.find('[data-testid="reopen-request"]').exists()).toBe(false)
    expect(wrapper.get('[role="status"]').text()).toContain('Request reopened and returned to the pending queue.')
  })

  it('shows the API error and preserves the rejected request when reopening fails', async () => {
    vi.mocked(reopenRequest).mockRejectedValue({
      response: { data: { message: 'Only rejected requests can be reopened.' } },
    })
    const wrapper = await mountDashboard()

    await wrapper.get('[data-testid="reopen-request"]').trigger('click')
    await flushPromises()

    const vm = wrapper.vm as any
    expect(vm.selectedRequest).toEqual(rejectedRequest)
    expect(vm.studentRequests).toEqual([rejectedRequest])
    expect(wrapper.get('[role="alert"]').text()).toContain('Only rejected requests can be reopened.')
    expect(wrapper.get('[data-testid="reopen-request"]').text()).toBe('Reopen request')
  })
})
