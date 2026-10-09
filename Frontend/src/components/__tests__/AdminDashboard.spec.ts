import {
  enableAutoUnmount,
  flushPromises,
  mount as mountComponent
} from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Component } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import AdminOverviewPage from '@/views/admin/AdminOverviewPage.vue'
import AdminRequestsPage from '@/views/admin/AdminRequestsPage.vue'
import AdminUsersPage from '@/views/admin/AdminUsersPage.vue'
import AdminReferencesPage from '@/views/admin/AdminReferencesPage.vue'
import AdminHistoryPage from '@/views/admin/AdminHistoryPage.vue'
import SuperAdminView from '@/views/SuperAdminView.vue'
import { useAuth } from '@/composables/useAuth'
import type { User } from '@/types'
import * as admin from '@/services/admin'
import * as requests from '@/services/requests'
import api from '@/services/api'

vi.mock('@/services/admin', () => ({
  listAdmin: vi.fn(),
  adminStats: vi.fn(),
  adminRequest: vi.fn(),
  createAdmin: vi.fn(),
  updateAdmin: vi.fn(),
  deleteAdmin: vi.fn(),
  setAdminLevel: vi.fn(),
  disableAdminUser: vi.fn(),
  enableAdminUser: vi.fn()
}))
vi.mock('@/services/requests', () => ({ reopenRequest: vi.fn() }))

const page = (data: unknown[] = []) => ({
  data,
  meta: { current_page: 1, last_page: 2, per_page: 20, total: data.length + 1 },
  links: { next: '/next', prev: null }
})
const mount = (component: Component) =>
  mountComponent(component, { global: { stubs: { teleport: true } } })

async function mountRoute(path: string) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/admin',
        component: SuperAdminView,
        children: [
          { path: '', component: AdminOverviewPage },
          { path: 'requests', component: AdminRequestsPage },
          { path: 'users', component: AdminUsersPage },
          { path: 'history', component: AdminHistoryPage },
          ...(
            ['faculties', 'departments', 'programmes', 'request-types'] as const
          ).map((kind) => ({
            path: kind,
            component: AdminReferencesPage,
            props: { kind }
          }))
        ]
      }
    ]
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mountComponent(
    { template: '<RouterView />' },
    {
      global: { plugins: [router], stubs: { teleport: true } }
    }
  )
  await flushPromises()
  return { wrapper, router }
}
enableAutoUnmount(afterEach)

describe('Super Admin pages', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    useAuth().clearAuth()
    vi.mocked(admin.listAdmin).mockResolvedValue(page() as never)
    vi.mocked(admin.adminStats).mockResolvedValue({
      total: 0,
      requests_today: 0,
      by_status: {},
      avg_resolution_hours: null,
      recent_activity: []
    })
    vi.mocked(admin.adminRequest).mockResolvedValue({
      id: 8,
      status: 'rejected',
      description: '',
      is_reopened: false,
      created_at: '',
      request_type: { id: 1, name: 'Transcript' },
      student: { id: 2, name: 'Student' },
      stages: [],
      attachments: [],
      status_history: []
    })
    vi.mocked(requests.reopenRequest).mockResolvedValue({} as never)
  })

  it('keeps overview and history on separate URLs and supports returning to overview', async () => {
    const { wrapper, router } = await mountRoute('/admin')
    expect(wrapper.get('h1').text()).toBe('Campus overview')
    expect(admin.adminStats).toHaveBeenCalledTimes(1)
    expect(admin.listAdmin).not.toHaveBeenCalled()
    expect(wrapper.find('#admin-history').exists()).toBe(false)
    expect(wrapper.find('#admin-requests').exists()).toBe(false)
    expect(wrapper.get('a').attributes('href')).toBe('/admin/history')
    await wrapper.get('a').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/admin/history')
    expect(wrapper.get('h1').text()).toBe('Status history')
    expect(wrapper.find('[aria-label="System overview"]').exists()).toBe(false)
    expect(admin.listAdmin).toHaveBeenCalledWith(
      'audit-log',
      expect.objectContaining({ page: 1 })
    )
    router.back()
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/admin')
    expect(wrapper.get('h1').text()).toBe('Campus overview')
    expect(admin.adminStats).toHaveBeenCalledTimes(2)
  })

  it('discards the previous account page state and late response on an account switch', async () => {
    const auth = useAuth()
    const adminUser = (id: number): User => ({
      id,
      name: 'Administrator',
      email: 'admin@example.edu',
      password: '',
      role: 'staff',
      created_at: '',
      staff_profile: {
        staff_id: 'ADMIN-' + id,
        admin_level: 'super_admin',
        departments: []
      }
    })
    auth.setUser(adminUser(1))
    let resolvePrevious!: (
      stats: Awaited<ReturnType<typeof admin.adminStats>>
    ) => void
    vi.mocked(admin.adminStats)
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolvePrevious = resolve
          })
      )
      .mockResolvedValue({
        total: 99,
        requests_today: 0,
        by_status: {},
        avg_resolution_hours: null,
        recent_activity: []
      })
    const { wrapper } = await mountRoute('/admin')
    auth.setUser(adminUser(2))
    await flushPromises()
    expect(admin.adminStats).toHaveBeenCalledTimes(2)
    resolvePrevious({
      total: 1234,
      requests_today: 0,
      by_status: {},
      avg_resolution_hours: null,
      recent_activity: []
    })
    await flushPromises()
    expect(wrapper.get('[aria-label="System overview"]').text()).toContain('99')
    expect(wrapper.text()).not.toContain('1234')
    wrapper.unmount()
    auth.clearAuth()
  })

  it.each([
    ['faculties', 'Faculties', []],
    ['departments', 'Departments', ['faculties']],
    ['programmes', 'Programmes', ['departments']],
    ['request-types', 'Request types', ['departments']]
  ] as const)(
    'opens the %s page directly and loads only its records and choices',
    async (kind, title, dependencies) => {
      const { wrapper } = await mountRoute('/admin/' + kind)
      expect(wrapper.get('h1').text()).toBe(title)
      const loadedKinds = [
        ...new Set(
          vi
            .mocked(admin.listAdmin)
            .mock.calls.map(([collection]) => collection)
        )
      ]
      expect(loadedKinds.sort()).toEqual([kind, ...dependencies].sort())
      expect(admin.adminStats).not.toHaveBeenCalled()
      expect(wrapper.find('[aria-label="Reference categories"]').exists()).toBe(
        false
      )
      expect(wrapper.find('#admin-users').exists()).toBe(false)
      expect(wrapper.find('#admin-requests').exists()).toBe(false)
      expect(wrapper.find('#admin-history').exists()).toBe(false)
    }
  )

  it('resets reference forms when moving to another reference page', async () => {
    const { wrapper, router } = await mountRoute('/admin/faculties')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Create')!
      .trigger('click')
    await wrapper.get('dialog input').setValue('Unsaved faculty')
    await router.push('/admin/departments')
    await flushPromises()
    expect(wrapper.find('dialog').exists()).toBe(false)
    expect(wrapper.get('h1').text()).toBe('Departments')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Create')!
      .trigger('click')
    expect(wrapper.get<HTMLInputElement>('dialog input').element.value).toBe('')
    expect(wrapper.get('dialog').text()).toContain('Create department')
    expect(wrapper.get('dialog').text()).toContain('Choose a faculty')
  })

  it('preserves routing sequence order when creating a request type from its page', async () => {
    const { wrapper } = await mountRoute('/admin/request-types')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Create')!
      .trigger('click')
    await wrapper.get('dialog input').setValue('Transcript')
    await wrapper.get('dialog textarea').setValue('Official transcript')
    await wrapper
      .findAll('dialog button')
      .find((button) => button.text() === 'Add step')!
      .trigger('click')
    await wrapper
      .findAll('dialog button')
      .find((button) => button.text() === 'Add step')!
      .trigger('click')
    await wrapper.findAll('dialog select')[1]!.setValue('FACULTY_RECORDS')
    await wrapper.get('[aria-label="Move step 2 up"]').trigger('click')
    await wrapper.get('dialog form').trigger('submit')
    await flushPromises()
    expect(admin.createAdmin).toHaveBeenCalledWith('request-types', {
      name: 'Transcript',
      description: 'Official transcript',
      default_department_sequence: ['FACULTY_RECORDS', 'STUDENT_DEPARTMENT']
    })
    expect(wrapper.find('dialog').exists()).toBe(false)
  })

  it('loads only history and applies its filters and server pagination', async () => {
    const wrapper = mount(AdminHistoryPage)
    await flushPromises()
    expect(
      vi
        .mocked(admin.listAdmin)
        .mock.calls.every(([kind]) => kind === 'audit-log')
    ).toBe(true)
    await wrapper.get('input[placeholder="All requests"]').setValue('8')
    await flushPromises()
    expect(admin.listAdmin).toHaveBeenLastCalledWith(
      'audit-log',
      expect.objectContaining({ request_id: 8, page: 1 })
    )
    expect(wrapper.text()).toContain('Page 1 of 2')
    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Clear history filters'))!
      .trigger('click')
    await flushPromises()
    expect(admin.listAdmin).toHaveBeenLastCalledWith(
      'audit-log',
      expect.objectContaining({ request_id: '', page: 1 })
    )
  })

  it('edits the user identifier even when the returned student profile has a different id', async () => {
    const onePage = (data: unknown[]) => ({
      data,
      meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
      links: { next: null, prev: null }
    })
    vi.mocked(admin.listAdmin).mockImplementation(async (kind) => {
      if (kind === 'users')
        return onePage([
          {
            id: 12,
            name: 'Student User',
            email: 'student@example.edu',
            role: 'student',
            student_profile: {
              id: 77,
              user_id: 12,
              matricule: 'SC123',
              faculty_id: 1,
              department_id: 10,
              programme_id: 100,
              level: '400'
            }
          }
        ]) as never
      if (kind === 'faculties')
        return onePage([{ id: 1, name: 'Science', code: 'SCI' }]) as never
      if (kind === 'departments')
        return onePage([
          { id: 10, faculty_id: 1, name: 'Physics', code: 'PHY' }
        ]) as never
      if (kind === 'programmes')
        return onePage([
          { id: 100, faculty_id: 1, department_id: 10, name: 'BSc Physics' }
        ]) as never
      return onePage([]) as never
    })
    const wrapper = mount(AdminUsersPage)
    await flushPromises()
    await wrapper.get('[aria-label="Edit Student User"]').trigger('click')
    await wrapper
      .get('dialog input[placeholder="Full name"]')
      .setValue('Updated Student')
    await wrapper.get('dialog form').trigger('submit')
    await flushPromises()
    expect(admin.updateAdmin).toHaveBeenCalledWith(
      'users',
      12,
      expect.objectContaining({ name: 'Updated Student', matricule: 'SC123' })
    )
  })

  it('shows account-state badges, filters by state, and hides self-disable', async () => {
    useAuth().setUser({
      id: 1,
      name: 'Current Admin',
      email: 'admin@example.edu',
      password: '',
      role: 'staff',
      created_at: '',
      staff_profile: {
        staff_id: 'ADMIN-1',
        admin_level: 'super_admin',
        departments: []
      }
    })
    vi.mocked(admin.listAdmin).mockImplementation(async (kind) =>
      kind === 'users'
        ? (page([
            {
              id: 1,
              name: 'Current Admin',
              email: 'admin@example.edu',
              role: 'staff',
              disabled_at: null,
              is_disabled: false,
              student_profile: null,
              staff_profile: {
                staff_id: 'ADMIN-1',
                admin_level: 'super_admin',
                departments: []
              }
            },
            {
              id: 2,
              name: 'Disabled User',
              email: 'disabled@example.edu',
              role: 'staff',
              disabled_at: '2026-10-08T18:00:00Z',
              is_disabled: true,
              student_profile: null,
              staff_profile: {
                staff_id: 'STAFF-2',
                admin_level: null,
                departments: []
              }
            }
          ]) as never)
        : (page() as never)
    )

    const wrapper = mount(AdminUsersPage)
    await flushPromises()

    expect(wrapper.text()).toContain('Enabled')
    expect(wrapper.text()).toContain('Disabled')
    expect(wrapper.find('[aria-label="Disable Current Admin"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label="Re-enable Disabled User"]').exists()).toBe(true)

    await wrapper
      .findAll('select')
      .find((select) => select.find('option[value="disabled"]').exists())!
      .setValue('disabled')
    await flushPromises()
    expect(admin.listAdmin).toHaveBeenLastCalledWith(
      'users',
      expect.objectContaining({ status: 'disabled', page: 1 })
    )
  })

  it('confirms disabling with an optional reason and keeps server errors visible', async () => {
    vi.mocked(admin.listAdmin).mockImplementation(async (kind) =>
      kind === 'users'
        ? (page([
            {
              id: 12,
              name: 'Other Admin',
              email: 'other@example.edu',
              role: 'staff',
              disabled_at: null,
              is_disabled: false,
              student_profile: null,
              staff_profile: {
                staff_id: 'ADMIN-12',
                admin_level: 'super_admin',
                departments: []
              }
            }
          ]) as never)
        : (page() as never)
    )
    vi.mocked(admin.disableAdminUser).mockRejectedValue({
      response: {
        data: { message: 'The last active Super Admin cannot be disabled or removed.' }
      }
    })

    const wrapper = mount(AdminUsersPage)
    await flushPromises()
    await wrapper.get('[aria-label="Disable Other Admin"]').trigger('click')
    await wrapper.get('dialog textarea').setValue('No longer required')
    await wrapper
      .findAll('dialog button')
      .find((button) => button.text() === 'Disable account')!
      .trigger('click')
    await flushPromises()

    expect(admin.disableAdminUser).toHaveBeenCalledWith(12, 'No longer required')
    expect(wrapper.get('dialog [role="alert"]').text()).toContain('last active Super Admin')
    expect(wrapper.find('dialog').exists()).toBe(true)
  })

  it('re-enables an account and explains that a fresh sign-in is required', async () => {
    vi.mocked(admin.listAdmin).mockImplementation(async (kind) =>
      kind === 'users'
        ? (page([
            {
              id: 12,
              name: 'Disabled User',
              email: 'disabled@example.edu',
              role: 'student',
              disabled_at: '2026-10-08T18:00:00Z',
              is_disabled: true,
              student_profile: null,
              staff_profile: null
            }
          ]) as never)
        : (page() as never)
    )
    vi.mocked(admin.enableAdminUser).mockResolvedValue({} as never)

    const wrapper = mount(AdminUsersPage)
    await flushPromises()
    await wrapper.get('[aria-label="Re-enable Disabled User"]').trigger('click')
    await flushPromises()

    expect(admin.enableAdminUser).toHaveBeenCalledWith(12)
    expect(wrapper.text()).toContain('They must sign in again.')
  })

  it('loads only the request page collections and uses returned pagination', async () => {
    const wrapper = mount(AdminRequestsPage)
    await flushPromises()
    expect(admin.listAdmin).toHaveBeenCalledWith(
      'requests',
      expect.objectContaining({ page: 1 })
    )
    expect(admin.listAdmin).not.toHaveBeenCalledWith(
      'audit-log',
      expect.anything()
    )
    expect(admin.listAdmin).not.toHaveBeenCalledWith('users', expect.anything())
    expect(admin.adminStats).not.toHaveBeenCalled()
    expect(wrapper.find('#admin-users').exists()).toBe(false)
    expect(wrapper.find('#admin-organisation').exists()).toBe(false)
    expect(wrapper.find('#admin-history').exists()).toBe(false)
    expect(wrapper.text()).toContain('Page 1 of 2')
    expect(wrapper.text()).not.toContain('Admin override')
  })

  it('reopens a rejected request through the existing transition', async () => {
    vi.mocked(admin.listAdmin).mockImplementation(async (kind) =>
      kind === 'requests'
        ? (page([
            {
              id: 8,
              status: 'rejected',
              created_at: new Date().toISOString(),
              student: { name: 'Student' },
              request_type: { name: 'Transcript' }
            }
          ]) as never)
        : (page() as never)
    )
    const wrapper = mount(AdminRequestsPage)
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'View')!
      .trigger('click')
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Reopen request')!
      .trigger('click')
    await flushPromises()
    expect(requests.reopenRequest).toHaveBeenCalledWith(8)
  })

  it('shows request cards, labeled filters, and the protected document viewer', async () => {
    vi.mocked(admin.listAdmin).mockImplementation(async (kind) =>
      kind === 'requests'
        ? (page([
            {
              id: 8,
              status: 'pending',
              created_at: new Date().toISOString(),
              student: {
                name: 'Student',
                student_profile: { matricule: 'SC123' }
              },
              request_type: { name: 'Transcript' }
            }
          ]) as never)
        : (page() as never)
    )
    vi.mocked(admin.adminRequest).mockResolvedValue({
      id: 8,
      status: 'pending',
      description: 'Please process',
      is_reopened: false,
      created_at: new Date().toISOString(),
      request_type: { id: 1, name: 'Transcript' },
      student: { id: 2, name: 'Student' },
      stages: [],
      attachments: [
        { id: 17, original_name: 'record.pdf', mime_type: 'application/pdf' }
      ],
      status_history: []
    })
    const wrapper = mount(AdminRequestsPage)
    await flushPromises()
    expect(wrapper.text()).toContain('Request #8')
    expect(wrapper.find('input[type="search"]').exists()).toBe(true)
    expect(wrapper.find('label').text()).toContain('Search')
    await wrapper.find('button[aria-label="View request 8"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="dialog"]').text()).toContain('Attachments')
    expect(wrapper.find('[role="dialog"]').text()).toContain('record.pdf')

    const get = vi.spyOn(api, 'get').mockResolvedValue({
      data: new Blob(['pdf'], { type: 'application/pdf' })
    })
    const createObjectURL = vi.fn().mockReturnValue('blob:admin-document')
    const previousCreateObjectURL = URL.createObjectURL
    const previousRevokeObjectURL = URL.revokeObjectURL
    URL.revokeObjectURL = vi.fn()
    URL.createObjectURL = createObjectURL
    try {
      await wrapper
        .findAll('[role="dialog"] button')
        .find((button) => button.text().includes('record.pdf'))!
        .trigger('click')
      await flushPromises()
      expect(get).toHaveBeenCalledWith('/attachments/17', {
        responseType: 'blob'
      })
    } finally {
      wrapper.unmount()
      URL.createObjectURL = previousCreateObjectURL
      URL.revokeObjectURL = previousRevokeObjectURL
      get.mockRestore()
    }
  })

  it('applies and clears request filters through the server list', async () => {
    const wrapper = mount(AdminRequestsPage)
    await flushPromises()
    await wrapper
      .findAll('select')
      .find((select) =>
        select.element.querySelector('option[value="rejected"]')
      )!
      .setValue('rejected')
    await flushPromises()
    expect(admin.listAdmin).toHaveBeenCalledWith(
      'requests',
      expect.objectContaining({ status: 'rejected', page: 1 })
    )
    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Clear filters'))!
      .trigger('click')
    await flushPromises()
    expect(admin.listAdmin).toHaveBeenCalledWith(
      'requests',
      expect.objectContaining({ status: '', page: 1 })
    )
  })

  it('searches grouped departments and submits staff memberships with one primary', async () => {
    const onePage = (data: unknown[]) => ({
      data,
      meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length },
      links: { next: null, prev: null }
    })
    vi.mocked(admin.listAdmin).mockImplementation(async (kind) => {
      if (kind === 'faculties')
        return onePage([
          { id: 1, name: 'Science', code: 'SCI' },
          { id: 2, name: 'Arts', code: 'ART' }
        ]) as never
      if (kind === 'departments')
        return onePage([
          { id: 10, faculty_id: 1, name: 'Mathematics', code: 'MATH' },
          { id: 11, faculty_id: 1, name: 'Physics', code: 'PHY' },
          { id: 20, faculty_id: 2, name: 'History', code: 'HIS' }
        ]) as never
      return onePage([]) as never
    })
    vi.mocked(admin.createAdmin).mockResolvedValue({} as never)
    const wrapper = mount(AdminUsersPage)
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Create user')!
      .trigger('click')
    const dialog = wrapper.find('[role="dialog"]')
    await dialog.find('select').setValue('staff')
    await dialog.find('input[type="search"]').setValue('math')
    expect(dialog.text()).toContain('Mathematics')
    expect(dialog.find('input[aria-label="Assign Physics"]').exists()).toBe(
      false
    )
    await dialog.find('input[aria-label="Assign Mathematics"]').setValue(true)
    expect(dialog.text()).toContain('1 selected')
    await dialog.find('input[type="search"]').setValue('history')
    await dialog.find('input[aria-label="Assign History"]').setValue(true)
    const primary = dialog
      .findAll('select')
      .find((select) => select.find('option[value="20"]').exists())!
    await primary.setValue('20')
    await dialog.find('input[placeholder="Full name"]').setValue('New Staff')
    await dialog.find('input[type="email"]').setValue('staff@example.com')
    await dialog.find('input[type="password"]').setValue('password123')
    await dialog.find('input[placeholder="Staff ID"]').setValue('STAFF-1')
    await dialog.find('form').trigger('submit')
    await flushPromises()
    expect(admin.createAdmin).toHaveBeenCalledWith(
      'users',
      expect.objectContaining({
        role: 'staff',
        department_ids: [10, 20],
        primary_department_id: 20
      })
    )
  })

  it('requires a concrete delete confirmation and reports dependency conflicts in that dialog', async () => {
    vi.mocked(admin.listAdmin).mockImplementation(async (kind) =>
      kind === 'users'
        ? (page([
            {
              id: 12,
              name: 'Example User',
              email: 'example@test.edu',
              role: 'student'
            }
          ]) as never)
        : (page() as never)
    )
    vi.mocked(admin.deleteAdmin).mockRejectedValue({
      response: { data: { message: 'This record is referenced by requests.' } }
    })
    const wrapper = mount(AdminUsersPage)
    await flushPromises()
    await wrapper.get('[aria-label="Delete Example User"]').trigger('click')
    expect(admin.deleteAdmin).not.toHaveBeenCalled()
    expect(wrapper.get('dialog').text()).toContain('Example User')
    await wrapper
      .findAll('dialog button')
      .find((button) => button.text() === 'Delete record')!
      .trigger('click')
    await flushPromises()
    expect(admin.deleteAdmin).toHaveBeenCalledWith('users', 12)
    expect(wrapper.get('dialog [role="alert"]').text()).toContain(
      'referenced by requests'
    )
  })

  it('shows collection failure with a retry instead of presenting it as empty data', async () => {
    vi.mocked(admin.listAdmin).mockImplementation(async (kind) => {
      if (kind === 'requests') throw new Error('Offline')
      return page() as never
    })
    const wrapper = mount(AdminRequestsPage)
    await flushPromises()
    expect(wrapper.get('#admin-requests [role="alert"]').text()).toContain(
      'The action failed'
    )
    expect(wrapper.get('#admin-requests').text()).not.toContain(
      'No requests yet'
    )
    vi.mocked(admin.listAdmin).mockResolvedValue(page() as never)
    await wrapper.get('#admin-requests [role="alert"] button').trigger('click')
    await flushPromises()
    expect(wrapper.get('#admin-requests').text()).toContain('No requests yet')
  })
})
