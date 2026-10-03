import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import type { Request as DocumentRequest } from '../../types'

vi.mock('../../services/requests', () => ({
  fetchRequests: vi.fn(),
  fetchRequestById: vi.fn(),
  createRequest: vi.fn(),
  reopenRequest: vi.fn(),
  markRequestCollected: vi.fn()
}))
vi.mock('../../services/reference', () => ({
  fetchRequestTypes: vi.fn(),
  fetchFaculties: vi.fn(),
  fetchDepartments: vi.fn()
}))
vi.mock('../../composables/useAuth', () => ({
  useAuth: () => ({
    user: { value: { id: 1, name: 'Test Student', role: 'student' } }
  })
}))

import StudentDashboard from '../StudentDashboard.vue'
import {
  createRequest,
  fetchRequestById,
  fetchRequests,
  markRequestCollected
} from '../../services/requests'
import {
  fetchDepartments,
  fetchFaculties,
  fetchRequestTypes
} from '../../services/reference'

enableAutoUnmount(afterEach)
const request: DocumentRequest = {
  id: 42,
  request_type: 'Transcript',
  description: 'For my application',
  status: 'pending',
  is_reopened: false,
  created_at: '2026-01-01T00:00:00Z',
  attachments: [],
  status_history: [],
  stages: [
    {
      id: 9,
      request_id: 42,
      department_name: 'Registry',
      sequence_order: 1,
      status: 'pending',
      handled_by: null,
      staff_note: null,
      updated_at: null
    }
  ]
}
function renderDashboard() {
  return mount(StudentDashboard, { global: { stubs: { teleport: true } } })
}
function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => {
    resolve = done
  })
  return { promise, resolve }
}
async function completeForm(wrapper: ReturnType<typeof renderDashboard>) {
  await wrapper.get('#student-request-type').setValue('17')
  await wrapper.get('#student-description').setValue('  For my application  ')
}

describe('Student dashboard workflows', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.mocked(fetchRequests).mockResolvedValue([request])
    vi.mocked(fetchRequestById).mockResolvedValue(request)
    vi.mocked(fetchRequestTypes).mockResolvedValue([
      {
        id: 17,
        name: 'Transcript',
        description: 'Official academic transcript',
        default_department_sequence: [1]
      }
    ])
    vi.mocked(fetchFaculties).mockResolvedValue([])
    vi.mocked(fetchDepartments).mockResolvedValue([])
  })

  it('shows skeletons instead of an empty state while requests load', async () => {
    const pending = deferred<DocumentRequest[]>()
    vi.mocked(fetchRequests).mockReturnValue(pending.promise)
    const wrapper = renderDashboard()
    expect(wrapper.findComponent({ name: 'SkeletonLoader' }).exists()).toBe(
      true
    )
    expect(wrapper.text()).not.toContain('Your first request starts here')
    pending.resolve([])
    await flushPromises()
    expect(wrapper.text()).toContain('Your first request starts here')
  })

  it('filters request data and clears the filtered empty state', async () => {
    const wrapper = renderDashboard()
    await flushPromises()
    await wrapper.get('#student-request-search').setValue('missing document')
    expect(wrapper.text()).toContain('No matching requests')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Clear filters')!
      .trigger('click')
    expect(wrapper.get('article').text()).toContain('Transcript')
    await wrapper.get('#student-status-filter').setValue('rejected')
    expect(wrapper.text()).toContain('No matching requests')
  })

  it('recovers from a loading failure with the retry control', async () => {
    vi.mocked(fetchRequests).mockRejectedValueOnce(new Error('offline'))
    const wrapper = renderDashboard()
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'could not be loaded'
    )
    expect(wrapper.text()).toContain('Requests are unavailable')
    expect(wrapper.text()).not.toContain('Your first request starts here')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Try again')!
      .trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.get('article').text()).toContain('Transcript')
  })

  it('validates request type and description without calling the API', async () => {
    const wrapper = renderDashboard()
    await flushPromises()
    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'Choose a request type'
    )
    await wrapper.get('#student-request-type').setValue('17')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('[role="alert"]').text()).toContain('Add a description')
    expect(createRequest).not.toHaveBeenCalled()
  })

  it('submits the actual selected type and files once, then shows returned department routing', async () => {
    const pending = deferred<DocumentRequest>()
    vi.mocked(createRequest).mockReturnValue(pending.promise)
    const wrapper = renderDashboard()
    await flushPromises()
    await completeForm(wrapper)
    const file = new File(['pdf'], 'support.pdf', { type: 'application/pdf' })
    Object.defineProperty(
      wrapper.get('#student-attachments').element,
      'files',
      { value: [file], configurable: true }
    )
    await wrapper.get('#student-attachments').trigger('change')
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')
    expect(createRequest).toHaveBeenCalledOnce()
    expect(createRequest).toHaveBeenCalledWith({
      request_type_id: 17,
      description: 'For my application',
      attachments: [file]
    })
    expect(
      wrapper.get('button[type="submit"]').attributes('disabled')
    ).toBeDefined()
    pending.resolve({ ...request, id: 43 })
    await flushPromises()
    expect(wrapper.text()).toContain('Request submitted')
    expect(
      wrapper.get('[aria-label="Processing departments"]').text()
    ).toContain('Registry')
    expect(wrapper.findAll('article')).toHaveLength(2)
    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('New request'))!
      .trigger('click')
    expect(
      (wrapper.get('#student-description').element as HTMLTextAreaElement).value
    ).toBe('')
    expect(wrapper.find('[aria-label="Selected attachments"]').exists()).toBe(
      false
    )
  })

  it('rejects unsupported and oversized attachments before submission', async () => {
    const wrapper = renderDashboard()
    await flushPromises()
    const input = wrapper.get('#student-attachments')
    Object.defineProperty(input.element, 'files', {
      value: [new File(['data'], 'script.exe')],
      configurable: true
    })
    await input.trigger('change')
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'Choose PDF, DOCX, JPG, or PNG files'
    )
    const oversized = new File(['pdf'], 'large.pdf', {
      type: 'application/pdf'
    })
    Object.defineProperty(oversized, 'size', { value: 6 * 1024 * 1024 })
    Object.defineProperty(input.element, 'files', {
      value: [oversized],
      configurable: true
    })
    await input.trigger('change')
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'exceeds the 5 MB limit'
    )
    expect(wrapper.find('[aria-label="Selected attachments"]').exists()).toBe(
      false
    )
  })

  it('preserves input when the API rejects a submission', async () => {
    vi.mocked(createRequest).mockRejectedValue({
      isAxiosError: true,
      response: {
        data: { errors: { description: ['A specific detail is required.'] } }
      }
    })
    const wrapper = renderDashboard()
    await flushPromises()
    await completeForm(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'A specific detail is required.'
    )
    expect(
      (wrapper.get('#student-description').element as HTMLTextAreaElement).value
    ).toBe('  For my application  ')
    expect(wrapper.findAll('article')).toHaveLength(1)
  })

  it('loads secure detail components from the actual detail response', async () => {
    const attachment = {
      id: 3,
      original_name: 'support.pdf',
      file_path: 'protected/support.pdf',
      mime_type: 'application/pdf'
    }
    vi.mocked(fetchRequestById).mockResolvedValue({
      ...request,
      attachments: [attachment]
    })
    const wrapper = renderDashboard()
    await flushPromises()
    await wrapper.get('article button').trigger('click')
    await flushPromises()
    expect(fetchRequestById).toHaveBeenCalledWith(42)
    expect(
      wrapper.findComponent({ name: 'RequestTimeline' }).props('stages')
    ).toEqual(request.stages)
    expect(
      wrapper.findComponent({ name: 'DocumentViewer' }).props('attachments')
    ).toEqual([attachment])
  })

  it('retries failed detail loading and restores usable details', async () => {
    vi.mocked(fetchRequestById).mockRejectedValueOnce(new Error('offline'))
    const wrapper = renderDashboard()
    await flushPromises()
    await wrapper.get('article button').trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'Failed to load request details'
    )
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Try again')!
      .trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Stage timeline')
  })

  it('marks ready requests as collected and updates the card from the server response', async () => {
    vi.mocked(fetchRequests).mockResolvedValue([
      { ...request, status: 'ready' }
    ])
    vi.mocked(fetchRequestById).mockResolvedValue({
      ...request,
      status: 'ready'
    })
    vi.mocked(markRequestCollected).mockResolvedValue({
      ...request,
      status: 'collected'
    })
    const wrapper = renderDashboard()
    await flushPromises()
    await wrapper.get('article button').trigger('click')
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Mark as collected')!
      .trigger('click')
    await flushPromises()
    expect(markRequestCollected).toHaveBeenCalledWith(42)
    expect(wrapper.get('article').text()).toContain('Collected')
    expect(wrapper.text()).toContain('Request marked as collected.')
  })

  it('paginates larger lists and resets pagination when filters change', async () => {
    vi.mocked(fetchRequests).mockResolvedValue(
      Array.from({ length: 9 }, (_, index) => ({ ...request, id: index + 1 }))
    )
    const wrapper = renderDashboard()
    await flushPromises()
    expect(wrapper.findAll('article')).toHaveLength(6)
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Next')!
      .trigger('click')
    expect(wrapper.findAll('article')).toHaveLength(3)
    await wrapper.get('#student-request-search').setValue('Transcript')
    expect(wrapper.findAll('article')).toHaveLength(6)
  })
})
