import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import type { RequestStage, Request as DocumentRequest } from '../../types'

vi.mock('../../services/stages', () => ({
  fetchStaffQueue: vi.fn(),
  fetchMyCases: vi.fn(),
  claimStage: vi.fn(),
  resolveStage: vi.fn()
}))
vi.mock('../../services/requests', () => ({ fetchRequestById: vi.fn() }))
vi.mock('../../composables/useAuth', () => ({
  useAuth: () => ({
    user: {
      value: {
        id: 1,
        name: 'Staff Reviewer',
        staff_profile: {
          staff_id: 'S-001',
          departments: [
            { id: 1, name: 'Registry', is_primary: true },
            { id: 2, name: 'Student Affairs', is_primary: false }
          ]
        }
      }
    }
  })
}))

import StaffView from '@/views/StaffView.vue'
import StaffQueuePage from '@/views/staff/StaffQueuePage.vue'
import StaffCasesPage from '@/views/staff/StaffCasesPage.vue'
import StaffOverviewPage from '@/views/staff/StaffOverviewPage.vue'
import StaffCaseWorkspace from '@/components/staff/StaffCaseWorkspace.vue'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import {
  claimStage,
  fetchMyCases,
  fetchStaffQueue,
  resolveStage
} from '../../services/stages'
import { fetchRequestById } from '../../services/requests'

const queueStage: RequestStage = {
  id: 11,
  request_id: 101,
  department_name: 'Registry',
  sequence_order: 1,
  status: 'pending',
  handled_by: null,
  staff_note: null,
  updated_at: null,
  request: {
    id: 101,
    request_type: 'Transcript',
    student_name: 'Alice Student',
    student_matricule: 'UB001',
    student_level: '300',
    description: 'A transcript for my application',
    created_at: '2026-09-20T10:00:00Z',
    attachments: []
  }
}
const otherDepartment: RequestStage = {
  ...queueStage,
  id: 12,
  request_id: 102,
  department_name: 'Student Affairs',
  request: {
    ...queueStage.request!,
    id: 102,
    student_name: 'Bob Student',
    request_type: 'Attestation'
  }
}
const activeStage: RequestStage = {
  ...otherDepartment,
  id: 13,
  request_id: 103,
  status: 'in_review',
  handled_by: 'Staff Reviewer',
  request: {
    ...otherDepartment.request!,
    id: 103,
    student_name: 'Charlie Student'
  }
}
const requestDetails: DocumentRequest = {
  id: 101,
  request_type: 'Transcript',
  description: 'Full request description',
  status: 'pending',
  is_reopened: false,
  created_at: '2026-09-20T10:00:00Z',
  stages: [queueStage],
  status_history: [],
  attachments: [
    {
      id: 21,
      original_name: 'Supporting document.pdf',
      mime_type: 'application/pdf',
      file_path: 'private/file.pdf'
    }
  ]
}

let wrapper: VueWrapper
const findButton = (text: string) => {
  const button = wrapper.findAll('button').find((node) => node.text() === text)
  if (!button) throw new Error(`Missing button: ${text}`)
  return button
}
let router: Router
async function render(path = '/staff/queue') {
  router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/staff',
        component: StaffView,
        children: [
          { path: '', component: StaffOverviewPage },
          { path: 'queue', component: StaffQueuePage },
          { path: 'cases', component: StaffCasesPage }
        ]
      }
    ]
  })
  await router.push(path)
  await router.isReady()
  wrapper = mount(StaffView, {
    attachTo: document.body,
    global: {
      plugins: [router],
      stubs: {
        teleport: true,
        RequestTimeline: {
          props: ['stages'],
          template: '<div data-test="timeline">{{ stages.length }} stages</div>'
        },
        DocumentViewer: {
          props: ['attachments'],
          template:
            '<div data-test="documents">{{ attachments.map(item => item.original_name).join(", ") }}</div>'
        }
      }
    }
  })
  await flushPromises()
}

beforeEach(() => {
  vi.resetAllMocks()
  vi.mocked(fetchStaffQueue).mockResolvedValue([queueStage, otherDepartment])
  vi.mocked(fetchMyCases).mockResolvedValue([activeStage])
  vi.mocked(claimStage).mockResolvedValue(undefined)
  vi.mocked(resolveStage).mockResolvedValue(undefined)
  vi.mocked(fetchRequestById).mockResolvedValue(requestDetails)
})
afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
  document.body.style.overflow = ''
})

describe('Staff workspace', () => {
  it('does not restore a resolved case when an older refresh finishes late', async () => {
    await render('/staff/cases')
    let finishRefresh!: (stages: RequestStage[]) => void
    vi.mocked(fetchMyCases)
      .mockReturnValueOnce(
        new Promise((resolve) => {
          finishRefresh = resolve
        })
      )
      .mockResolvedValueOnce([])
    const workspace = wrapper.findComponent(StaffCaseWorkspace).vm as any
    const refresh = workspace.loadQueue()
    workspace.openResolve(activeStage)
    await workspace.submitResolve()
    finishRefresh([activeStage])
    await refresh
    await flushPromises()
    expect(resolveStage).toHaveBeenCalledWith(103, 13, {
      status: 'approved',
      staff_note: ''
    })
    expect(wrapper.text()).toContain('No active cases yet')
    expect(wrapper.find('article').exists()).toBe(false)
  })
  it('defaults queue to the primary department and keeps active cases across departments', async () => {
    await render()
    expect(wrapper.findAll('article')).toHaveLength(1)
    expect(wrapper.find('article').text()).toContain('Alice Student')
    await wrapper.get('#staff-department').setValue('2')
    expect(wrapper.find('article').text()).toContain('Bob Student')
    await wrapper.get('#staff-department').setValue('1')
    await router.push('/staff/cases')
    await flushPromises()
    expect(wrapper.find('article').text()).toContain('Charlie Student')
    expect(wrapper.text()).toContain('Assigned to you · All your departments')
  })

  it('searches by matricule, filters by type, and distinguishes a filtered empty state', async () => {
    await render()
    await wrapper.get('#staff-search').setValue('ub001')
    expect(wrapper.findAll('article')).toHaveLength(1)
    await wrapper.get('#staff-type').setValue('Attestation')
    expect(wrapper.text()).toContain('No matching requests')
    expect(wrapper.text()).not.toContain('Your queue is clear')
    await findButton('Clear filters').trigger('click')
    expect(wrapper.findAll('article')).toHaveLength(1)
  })

  it('keeps overview, queue, and active cases on distinct pages', async () => {
    await render('/staff')
    expect(wrapper.find('[aria-label="Workspace overview"]').exists()).toBe(
      true
    )
    expect(wrapper.find('#staff-search').exists()).toBe(false)
    await wrapper.get('a[href="/staff/queue"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/staff/queue')
    expect(wrapper.find('[aria-label="Workspace overview"]').exists()).toBe(
      false
    )
    expect(wrapper.find('[role="tablist"]').exists()).toBe(false)
    expect(wrapper.findAll('article')).toHaveLength(1)
    await router.push('/staff/cases')
    await flushPromises()
    expect(wrapper.get('h1').text()).toBe('My active cases')
    expect(wrapper.find('#staff-department').exists()).toBe(false)
  })

  it('loads only active cases on a direct cases URL', async () => {
    await render('/staff/cases')
    expect(fetchStaffQueue).not.toHaveBeenCalled()
    expect(fetchMyCases).toHaveBeenCalledTimes(1)
    expect(wrapper.find('article').text()).toContain('Charlie Student')
  })

  it('confirms a claim, prevents duplicate submission, and navigates to active cases', async () => {
    let finishClaim!: () => void
    vi.mocked(claimStage).mockReturnValue(
      new Promise((resolve) => {
        finishClaim = resolve
      })
    )
    await render()
    await wrapper
      .get('article [aria-label="Claim request 101"]')
      .trigger('click')
    expect(claimStage).not.toHaveBeenCalled()
    const confirm = wrapper.get('dialog .btn-primary')
    await confirm.trigger('click')
    await flushPromises()
    expect(claimStage).toHaveBeenCalledWith(101, 11)
    expect(findButton('Claiming…').attributes('disabled')).toBeDefined()
    await findButton('Claiming…').trigger('click')
    expect(claimStage).toHaveBeenCalledTimes(1)
    expect(claimStage).toHaveBeenCalledWith(101, 11)
    finishClaim()
    await flushPromises()
    expect(fetchStaffQueue).toHaveBeenCalledTimes(2)
    expect(fetchMyCases).toHaveBeenCalledTimes(1)
    expect(wrapper.find('dialog').exists()).toBe(false)
    expect(router.currentRoute.value.path).toBe('/staff/cases')
    expect(wrapper.get('h1').text()).toBe('My active cases')
  })

  it('keeps a failed claim open and displays the server conflict', async () => {
    vi.mocked(claimStage).mockRejectedValue({
      isAxiosError: true,
      response: { data: { message: 'This stage has already been claimed.' } }
    })
    await render()
    await wrapper
      .get('article [aria-label="Claim request 101"]')
      .trigger('click')
    await wrapper.get('dialog .btn-primary').trigger('click')
    await flushPromises()
    expect(wrapper.get('dialog [role="alert"]').text()).toContain(
      'already been claimed'
    )
    expect(
      wrapper.get('dialog .btn-primary').attributes('disabled')
    ).toBeUndefined()
  })

  it('requires a rejection note and submits the original stage identifiers with trimmed text', async () => {
    await render()
    await router.push('/staff/cases')
    await flushPromises()
    await wrapper
      .get('article [aria-label="Update status for request 103"]')
      .trigger('click')
    await wrapper.get('#staff-resolution').setValue('rejected')
    await wrapper.get('#staff-resolution-form').trigger('submit')
    expect(resolveStage).not.toHaveBeenCalled()
    expect(wrapper.get('#staff-resolution-error').text()).toBe(
      'A staff note is required when rejecting.'
    )
    expect(wrapper.get('#staff-note').attributes('aria-invalid')).toBe('true')
    await wrapper
      .get('#staff-note')
      .setValue('  Please attach proof of registration.  ')
    await wrapper.get('#staff-resolution-form').trigger('submit')
    await flushPromises()
    expect(resolveStage).toHaveBeenCalledWith(103, 13, {
      status: 'rejected',
      staff_note: 'Please attach proof of registration.'
    })
  })

  it('allows approval without a note and prevents duplicate resolution', async () => {
    let finishResolution!: () => void
    vi.mocked(resolveStage).mockReturnValue(
      new Promise((resolve) => {
        finishResolution = resolve
      })
    )
    await render()
    await router.push('/staff/cases')
    await flushPromises()
    await wrapper
      .get('article [aria-label="Update status for request 103"]')
      .trigger('click')
    await wrapper.get('#staff-resolution-form').trigger('submit')
    await wrapper.get('#staff-resolution-form').trigger('submit')
    expect(resolveStage).toHaveBeenCalledTimes(1)
    expect(resolveStage).toHaveBeenCalledWith(103, 13, {
      status: 'approved',
      staff_note: ''
    })
    expect(findButton('Saving…').attributes('disabled')).toBeDefined()
    finishResolution()
    await flushPromises()
    expect(wrapper.find('dialog').exists()).toBe(false)
  })

  it('retains resolution notes after API failure', async () => {
    vi.mocked(resolveStage).mockRejectedValue(new Error('Network failed'))
    await render()
    await router.push('/staff/cases')
    await flushPromises()
    await wrapper
      .get('article [aria-label="Update status for request 103"]')
      .trigger('click')
    await wrapper.get('#staff-note').setValue('Reviewed original documents')
    await wrapper.get('#staff-resolution-form').trigger('submit')
    await flushPromises()
    expect(
      (wrapper.get('#staff-note').element as HTMLTextAreaElement).value
    ).toBe('Reviewed original documents')
    expect(wrapper.get('#staff-resolution-error').text()).toContain(
      'Your note has been kept'
    )
  })

  it('uses full request details for timeline and protected attachment metadata', async () => {
    await render()
    await wrapper
      .get('article [aria-label="View details for request 101"]')
      .trigger('click')
    await flushPromises()
    expect(fetchRequestById).toHaveBeenCalledWith(101)
    expect(wrapper.get('[data-test="timeline"]').text()).toBe('1 stages')
    expect(wrapper.get('dialog').text()).toContain('Full request description')
    await findButton('Documents').trigger('click')
    expect(wrapper.get('[data-test="documents"]').text()).toContain(
      'Supporting document.pdf'
    )
    expect(wrapper.get('dialog').html()).not.toContain('private/file.pdf')
  })

  it('shows detail failures honestly and allows retry', async () => {
    vi.mocked(fetchRequestById)
      .mockRejectedValueOnce(new Error('Failed'))
      .mockResolvedValueOnce(requestDetails)
    await render()
    await wrapper
      .get('article [aria-label="View details for request 101"]')
      .trigger('click')
    await flushPromises()
    expect(wrapper.get('dialog [role="alert"]').text()).toContain(
      'Unable to load request details'
    )
    expect(wrapper.find('[data-test="timeline"]').exists()).toBe(false)
    await findButton('Retry details').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-test="timeline"]').text()).toBe('1 stages')
  })

  it('shows loading, then legitimate empty states for both lists', async () => {
    let finishQueue!: (stages: RequestStage[]) => void
    vi.mocked(fetchStaffQueue).mockReturnValue(
      new Promise((resolve) => {
        finishQueue = resolve
      })
    )
    vi.mocked(fetchMyCases).mockResolvedValue([])
    await render()
    expect(wrapper.get('#staff-case-panel').attributes('aria-busy')).toBe(
      'true'
    )
    expect(wrapper.text()).not.toContain('Your queue is clear')
    finishQueue([])
    await flushPromises()
    expect(wrapper.text()).toContain('Your queue is clear')
    await router.push('/staff/cases')
    await flushPromises()
    expect(wrapper.text()).toContain('No active cases yet')
  })

  it('keeps active cases available if the queue fails, and reloads when returning', async () => {
    vi.mocked(fetchStaffQueue)
      .mockRejectedValueOnce(new Error('Network failed'))
      .mockResolvedValueOnce([queueStage])
    await render()
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'Unable to load the department queue'
    )
    await router.push('/staff/cases')
    await flushPromises()
    expect(wrapper.find('article').text()).toContain('Charlie Student')
    await router.push('/staff/queue')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.find('article').text()).toContain('Alice Student')
  })
})
