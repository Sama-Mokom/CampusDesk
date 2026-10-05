import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import type { Request as DocumentRequest } from '@/types'
import { useAuth } from '@/composables/useAuth'
import StudentView from '@/views/StudentView.vue'
import StudentOverviewPage from '@/views/student/StudentOverviewPage.vue'
import StudentRequestsPage from '@/views/student/StudentRequestsPage.vue'
import StudentNewRequestPage from '@/views/student/StudentNewRequestPage.vue'
import StudentRequestDetailPage from '@/views/student/StudentRequestDetailPage.vue'

vi.mock('@/services/requests', () => ({
  fetchRequests: vi.fn(),
  fetchRequestById: vi.fn(),
  createRequest: vi.fn(),
  reopenRequest: vi.fn(),
  markRequestCollected: vi.fn()
}))
vi.mock('@/services/reference', () => ({
  fetchRequestTypes: vi.fn(),
  fetchFaculties: vi.fn(),
  fetchDepartments: vi.fn()
}))
import {
  createRequest,
  fetchRequestById,
  fetchRequests,
  markRequestCollected,
  reopenRequest
} from '@/services/requests'
import {
  fetchDepartments,
  fetchFaculties,
  fetchRequestTypes
} from '@/services/reference'

enableAutoUnmount(afterEach)
afterEach(() => useAuth().clearAuth())

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
const student = {
  id: 1,
  name: 'Test Student',
  email: 'student@test.edu',
  password: '',
  role: 'student' as const,
  created_at: '2026-01-01T00:00:00Z'
}

async function renderPage(path = '/student') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/student',
        component: StudentView,
        children: [
          { path: '', name: 'student', component: StudentOverviewPage },
          {
            path: 'requests',
            name: 'student-requests',
            component: StudentRequestsPage
          },
          {
            path: 'requests/new',
            name: 'student-new-request',
            component: StudentNewRequestPage
          },
          {
            path: 'requests/:id',
            name: 'student-request-detail',
            component: StudentRequestDetailPage
          }
        ]
      }
    ]
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(defineComponent({ template: '<RouterView />' }), {
    global: { plugins: [router] }
  })
  await flushPromises()
  return { wrapper, router }
}
type PageWrapper = Awaited<ReturnType<typeof renderPage>>['wrapper']
function button(wrapper: PageWrapper, text: string) {
  return wrapper.findAll('button').find((item) => item.text() === text)!
}
function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => {
    resolve = done
  })
  return { promise, resolve }
}
async function completeForm(wrapper: PageWrapper) {
  await wrapper.get('#student-request-type').setValue('17')
  await wrapper.get('#student-description').setValue('  For my application  ')
}

beforeEach(() => {
  vi.resetAllMocks()
  useAuth().setUser(student)
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

describe('Student pages and navigation', () => {
  it('keeps the overview limited to summaries and recent requests', async () => {
    vi.mocked(fetchRequests).mockResolvedValue(
      Array.from({ length: 8 }, (_, index) => ({ ...request, id: index + 1 }))
    )
    const { wrapper } = await renderPage()
    expect(wrapper.get('h1').text()).toContain('Welcome back')
    expect(wrapper.findAll('article')).toHaveLength(3)
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.find('input[type="search"]').exists()).toBe(false)
    expect(wrapper.find('dialog').exists()).toBe(false)
    expect(wrapper.get('a[href="/student/requests"]').text()).toContain(
      'View all requests'
    )
    expect(fetchRequestTypes).not.toHaveBeenCalled()
  })

  it('navigates to separate request-list, creation and detail pages with no hash sections', async () => {
    const { wrapper, router } = await renderPage()
    await wrapper.get('a[href="/student/requests"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('student-requests')
    expect(wrapper.get('h1').text()).toBe('My requests')
    expect(wrapper.find('form').exists()).toBe(false)
    await wrapper.get('a[href="/student/requests/new"]').trigger('click')
    await flushPromises()
    expect(wrapper.get('h1').text()).toBe('New request')
    expect(wrapper.findAll('article')).toHaveLength(0)
    expect(wrapper.find('#student-request-search').exists()).toBe(false)
    expect(wrapper.find('form').exists()).toBe(true)
    await router.push('/student/requests')
    await flushPromises()
    await wrapper.get('article button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.fullPath).toBe('/student/requests/42')
    expect(wrapper.get('h1').text()).toBe('Transcript')
    expect(wrapper.find('dialog').exists()).toBe(false)
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.find('#student-request-search').exists()).toBe(false)
  })

  it('loads a bookmarked detail URL without first loading any dashboard collections', async () => {
    const { wrapper } = await renderPage('/student/requests/42')
    expect(fetchRequestById).toHaveBeenCalledWith(42)
    expect(fetchRequests).not.toHaveBeenCalled()
    expect(fetchRequestTypes).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Stage timeline')
    expect(
      wrapper.findComponent({ name: 'RequestTimeline' }).props('stages')
    ).toEqual(request.stages)
    expect(wrapper.get('a[href="/student/requests"]').text()).toContain(
      'Back to my requests'
    )
  })

  it('updates the detail when the route ID changes and ignores late responses', async () => {
    const slow = deferred<DocumentRequest>()
    vi.mocked(fetchRequestById).mockImplementation((id) =>
      id === 42
        ? slow.promise
        : Promise.resolve({ ...request, id, request_type: 'Enrolment letter' })
    )
    const { wrapper, router } = await renderPage('/student/requests/42')
    await router.push('/student/requests/43')
    await flushPromises()
    slow.resolve(request)
    await flushPromises()
    expect(wrapper.get('h1').text()).toBe('Enrolment letter')
    expect(wrapper.text()).toContain('Request #43')
  })

  it.each(['missing', '0', '9007199254740992'])(
    'handles invalid detail ID %s without calling the API',
    async (id) => {
      const { wrapper } = await renderPage(`/student/requests/${id}`)
      expect(wrapper.text()).toContain('Request unavailable')
      expect(fetchRequestById).not.toHaveBeenCalled()
    }
  )

  it.each([403, 404])(
    'shows an unavailable state for inaccessible detail responses (%s)',
    async (status) => {
      vi.mocked(fetchRequestById).mockRejectedValue({
        isAxiosError: true,
        response: { status }
      })
      const { wrapper } = await renderPage('/student/requests/42')
      expect(wrapper.text()).toContain('Request unavailable')
      expect(wrapper.find('button').exists()).toBe(false)
    }
  )

  it('shows skeletons while loading, then the legitimate empty list', async () => {
    const pending = deferred<DocumentRequest[]>()
    vi.mocked(fetchRequests).mockReturnValue(pending.promise)
    const { wrapper } = await renderPage('/student/requests')
    expect(wrapper.findComponent({ name: 'SkeletonLoader' }).exists()).toBe(
      true
    )
    expect(wrapper.text()).not.toContain('Your first request starts here')
    pending.resolve([])
    await flushPromises()
    expect(wrapper.text()).toContain('Your first request starts here')
  })

  it('filters, paginates and clears the request-list filtered empty state', async () => {
    vi.mocked(fetchRequests).mockResolvedValue(
      Array.from({ length: 9 }, (_, index) => ({ ...request, id: index + 1 }))
    )
    const { wrapper } = await renderPage('/student/requests')
    expect(wrapper.findAll('article')).toHaveLength(6)
    await button(wrapper, 'Next').trigger('click')
    expect(wrapper.findAll('article')).toHaveLength(3)
    await wrapper.get('#student-request-search').setValue('Transcript')
    expect(wrapper.findAll('article')).toHaveLength(6)
    await wrapper.get('#student-status-filter').setValue('rejected')
    expect(wrapper.text()).toContain('No matching requests')
    await button(wrapper, 'Clear filters').trigger('click')
    expect(wrapper.findAll('article')).toHaveLength(6)
  })

  it('retries failed list loading without treating it as an empty list', async () => {
    vi.mocked(fetchRequests).mockRejectedValueOnce(new Error('offline'))
    const { wrapper } = await renderPage('/student/requests')
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'could not be loaded'
    )
    expect(wrapper.text()).toContain('Requests are unavailable')
    await button(wrapper, 'Try again').trigger('click')
    await flushPromises()
    expect(wrapper.get('article').text()).toContain('Transcript')
  })

  it('loads only request types on the new page and validates the form', async () => {
    const { wrapper } = await renderPage('/student/requests/new')
    expect(fetchRequests).not.toHaveBeenCalled()
    expect(fetchRequestTypes).toHaveBeenCalledOnce()
    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'Choose a request type'
    )
    await wrapper.get('#student-request-type').setValue('17')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('[role="alert"]').text()).toContain('Add a description')
    expect(createRequest).not.toHaveBeenCalled()
  })

  it('retries a failed type lookup without exposing a broken submission form', async () => {
    vi.mocked(fetchRequestTypes).mockRejectedValueOnce(new Error('offline'))
    const { wrapper } = await renderPage('/student/requests/new')
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'Request types could not be loaded'
    )
    expect(wrapper.find('form').exists()).toBe(false)
    await button(wrapper, 'Try again').trigger('click')
    await flushPromises()
    expect(wrapper.find('form').exists()).toBe(true)
    expect(fetchRequests).not.toHaveBeenCalled()
  })

  it('submits selected files once, confirms routing, then navigates to the created request', async () => {
    const pending = deferred<DocumentRequest>()
    vi.mocked(createRequest).mockReturnValue(pending.promise)
    vi.mocked(fetchRequestById).mockResolvedValue({ ...request, id: 43 })
    const { wrapper, router } = await renderPage('/student/requests/new')
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
    expect(
      wrapper.get('[aria-label="Processing departments"]').text()
    ).toContain('Registry')
    await button(wrapper, 'Track this request').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.fullPath).toBe('/student/requests/43')
    expect(wrapper.text()).toContain('Request #43')
    vi.mocked(fetchRequests).mockResolvedValue([{ ...request, id: 43 }])
    await wrapper.get('a[href="/student/requests"]').trigger('click')
    await flushPromises()
    expect(wrapper.get('article').text()).toContain('Request #43')
  })

  it('clears submitted fields and uploads before starting another request', async () => {
    vi.mocked(createRequest).mockResolvedValue(request)
    const { wrapper } = await renderPage('/student/requests/new')
    await completeForm(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    await button(wrapper, 'Submit another').trigger('click')
    expect(
      (wrapper.get('#student-description').element as HTMLTextAreaElement).value
    ).toBe('')
    expect(wrapper.find('[aria-label="Selected attachments"]').exists()).toBe(
      false
    )
  })

  it('rejects unsupported and oversized files before submission', async () => {
    const { wrapper } = await renderPage('/student/requests/new')
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

  it('preserves failed submission input and displays the API validation message', async () => {
    vi.mocked(createRequest).mockRejectedValue({
      isAxiosError: true,
      response: {
        data: { errors: { description: ['A specific detail is required.'] } }
      }
    })
    const { wrapper } = await renderPage('/student/requests/new')
    await completeForm(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'A specific detail is required.'
    )
    expect(
      (wrapper.get('#student-description').element as HTMLTextAreaElement).value
    ).toBe('  For my application  ')
  })

  it('retries a failed direct detail request and passes protected attachments to the viewer', async () => {
    const attachment = {
      id: 3,
      original_name: 'support.pdf',
      file_path: 'protected/support.pdf',
      mime_type: 'application/pdf'
    }
    vi.mocked(fetchRequestById)
      .mockRejectedValueOnce(new Error('offline'))
      .mockResolvedValue({ ...request, attachments: [attachment] })
    const { wrapper } = await renderPage('/student/requests/42')
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'Failed to load request details'
    )
    await button(wrapper, 'Try again').trigger('click')
    await flushPromises()
    expect(
      wrapper.findComponent({ name: 'DocumentViewer' }).props('attachments')
    ).toEqual([attachment])
  })

  it('reopens rejected requests using their route-loaded ID and updates the standalone page', async () => {
    vi.mocked(fetchRequestById).mockResolvedValue({
      ...request,
      status: 'rejected'
    })
    vi.mocked(reopenRequest).mockResolvedValue({
      ...request,
      status: 'pending',
      is_reopened: true
    })
    const { wrapper } = await renderPage('/student/requests/42')
    await wrapper.get('[data-testid="reopen-request"]').trigger('click')
    await flushPromises()
    expect(reopenRequest).toHaveBeenCalledOnce()
    expect(reopenRequest).toHaveBeenCalledWith(42)
    expect(wrapper.find('[data-testid="reopen-request"]').exists()).toBe(false)
    expect(wrapper.get('[role="status"]').text()).toContain(
      'Request reopened and returned to the pending queue.'
    )
  })

  it('retains a rejected request and its retry action when reopening fails', async () => {
    vi.mocked(fetchRequestById).mockResolvedValue({
      ...request,
      status: 'rejected'
    })
    vi.mocked(reopenRequest).mockRejectedValue({
      isAxiosError: true,
      response: { data: { message: 'Only rejected requests can be reopened.' } }
    })
    const { wrapper } = await renderPage('/student/requests/42')
    await wrapper.get('[data-testid="reopen-request"]').trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'Only rejected requests can be reopened.'
    )
    expect(wrapper.get('[data-testid="reopen-request"]').text()).toBe(
      'Reopen request'
    )
  })

  it('marks ready requests collected once and updates their server status', async () => {
    vi.mocked(fetchRequestById).mockResolvedValue({
      ...request,
      status: 'ready'
    })
    const pending = deferred<DocumentRequest>()
    vi.mocked(markRequestCollected).mockReturnValue(pending.promise)
    const { wrapper } = await renderPage('/student/requests/42')
    await button(wrapper, 'Mark as collected').trigger('click')
    expect(
      button(wrapper, 'Marking as collected…').attributes('disabled')
    ).toBeDefined()
    pending.resolve({ ...request, status: 'collected' })
    await flushPromises()
    expect(markRequestCollected).toHaveBeenCalledOnce()
    expect(markRequestCollected).toHaveBeenCalledWith(42)
    expect(wrapper.text()).toContain('Collected')
    expect(wrapper.get('[role="status"]').text()).toContain(
      'Request marked as collected.'
    )
  })

  it('refreshes the standalone request to show the latest department status', async () => {
    const { wrapper } = await renderPage('/student/requests/42')
    vi.mocked(fetchRequestById).mockResolvedValue({
      ...request,
      status: 'ready'
    })
    await button(wrapper, 'Refresh request').trigger('click')
    await flushPromises()
    expect(fetchRequestById).toHaveBeenCalledTimes(2)
    expect(button(wrapper, 'Mark as collected').exists()).toBe(true)
  })

  it('isolates data when the authenticated student changes in the same route', async () => {
    const { wrapper } = await renderPage('/student/requests')
    expect(wrapper.text()).toContain('For my application')
    vi.mocked(fetchRequests).mockResolvedValue([
      { ...request, id: 99, description: 'Second student request' }
    ])
    useAuth().setUser({ ...student, id: 2, name: 'Second Student' })
    await flushPromises()
    expect(fetchRequests).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Second student request')
    expect(wrapper.text()).not.toContain('For my application')
  })
})
