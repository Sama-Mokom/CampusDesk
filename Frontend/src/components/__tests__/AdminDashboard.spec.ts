import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminDashboard from '../AdminDashboard.vue'
import * as admin from '@/services/admin'
import * as requests from '@/services/requests'
import api from '@/services/api'

vi.mock('@/services/admin', () => ({
  listAdmin: vi.fn(), adminStats: vi.fn(), adminRequest: vi.fn(),
  createAdmin: vi.fn(), updateAdmin: vi.fn(), deleteAdmin: vi.fn(), setAdminLevel: vi.fn(),
}))
vi.mock('@/services/requests', () => ({ reopenRequest: vi.fn() }))

const page = (data: unknown[] = []) => ({ data, meta: { current_page: 1, last_page: 2, per_page: 20, total: data.length + 1 }, links: { next: '/next', prev: null } })

describe('Super Admin dashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(admin.listAdmin).mockResolvedValue(page() as never)
    vi.mocked(admin.adminStats).mockResolvedValue({ total: 0, requests_today: 0, by_status: {}, avg_resolution_hours: null, recent_activity: [] })
    vi.mocked(admin.adminRequest).mockResolvedValue({ id: 8, status: 'rejected', description: '', is_reopened: false, created_at: '', request_type: { id: 1, name: 'Transcript' }, student: { id: 2, name: 'Student' }, stages: [], attachments: [], status_history: [] })
    vi.mocked(requests.reopenRequest).mockResolvedValue({} as never)
  })

  it('loads server collections and uses returned pagination', async () => {
    const wrapper = mount(AdminDashboard)
    await flushPromises()
    expect(admin.listAdmin).toHaveBeenCalledWith('requests', expect.objectContaining({ page: 1 }))
    expect(admin.listAdmin).toHaveBeenCalledWith('audit-log', expect.objectContaining({ page: 1 }))
    expect(wrapper.text()).toContain('Page 1 of 2')
    expect(wrapper.text()).not.toContain('Admin override')
  })

  it('reopens a rejected request through the existing transition', async () => {
    vi.mocked(admin.listAdmin).mockImplementation(async kind => kind === 'requests' ? page([{ id: 8, status: 'rejected', created_at: new Date().toISOString(), student: { name: 'Student' }, request_type: { name: 'Transcript' } }]) as never : page() as never)
    const wrapper = mount(AdminDashboard)
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'View')!.trigger('click')
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Reopen request')!.trigger('click')
    await flushPromises()
    expect(requests.reopenRequest).toHaveBeenCalledWith(8)
  })

  it('shows request cards, labeled filters, and the protected document viewer', async () => {
    vi.mocked(admin.listAdmin).mockImplementation(async kind => kind === 'requests' ? page([{ id: 8, status: 'pending', created_at: new Date().toISOString(), student: { name: 'Student', student_profile: { matricule: 'SC123' } }, request_type: { name: 'Transcript' } }]) as never : page() as never)
    vi.mocked(admin.adminRequest).mockResolvedValue({ id: 8, status: 'pending', description: 'Please process', is_reopened: false, created_at: new Date().toISOString(), request_type: { id: 1, name: 'Transcript' }, student: { id: 2, name: 'Student' }, stages: [], attachments: [{ id: 17, original_name: 'record.pdf', mime_type: 'application/pdf' }], status_history: [] })
    const wrapper = mount(AdminDashboard)
    await flushPromises()
    expect(wrapper.text()).toContain('Request #8')
    expect(wrapper.find('input[type="search"]').exists()).toBe(true)
    expect(wrapper.find('label').text()).toContain('Search')
    await wrapper.find('button[aria-label="View request 8"]').trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="dialog"]').text()).toContain('Attachments')
    expect(wrapper.find('[role="dialog"]').text()).toContain('record.pdf')

    const get = vi.spyOn(api, 'get').mockResolvedValue({ data: new Blob(['pdf'], { type: 'application/pdf' }) })
    const createObjectURL = vi.fn().mockReturnValue('blob:admin-document')
    const previousCreateObjectURL = URL.createObjectURL
    URL.createObjectURL = createObjectURL
    try {
      await wrapper.findAll('[role="dialog"] button').find(button => button.text().includes('record.pdf'))!.trigger('click')
      await flushPromises()
      expect(get).toHaveBeenCalledWith('/attachments/17', { responseType: 'blob' })
    } finally {
      URL.createObjectURL = previousCreateObjectURL
      get.mockRestore()
    }
  })

  it('applies and clears request filters through the server list', async () => {
    const wrapper = mount(AdminDashboard)
    await flushPromises()
    await wrapper.findAll('select').find(select => select.element.querySelector('option[value="rejected"]'))!.setValue('rejected')
    await flushPromises()
    expect(admin.listAdmin).toHaveBeenCalledWith('requests', expect.objectContaining({ status: 'rejected', page: 1 }))
    await wrapper.findAll('button').find(button => button.text().includes('Clear filters'))!.trigger('click')
    await flushPromises()
    expect(admin.listAdmin).toHaveBeenCalledWith('requests', expect.objectContaining({ status: '', page: 1 }))
  })

  it('searches grouped departments and submits staff memberships with one primary', async () => {
    const onePage = (data: unknown[]) => ({ data, meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length }, links: { next: null, prev: null } })
    vi.mocked(admin.listAdmin).mockImplementation(async kind => {
      if (kind === 'faculties') return onePage([{ id: 1, name: 'Science', code: 'SCI' }, { id: 2, name: 'Arts', code: 'ART' }]) as never
      if (kind === 'departments') return onePage([{ id: 10, faculty_id: 1, name: 'Mathematics', code: 'MATH' }, { id: 11, faculty_id: 1, name: 'Physics', code: 'PHY' }, { id: 20, faculty_id: 2, name: 'History', code: 'HIS' }]) as never
      return onePage([]) as never
    })
    vi.mocked(admin.createAdmin).mockResolvedValue({} as never)
    const wrapper = mount(AdminDashboard)
    await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Create user')!.trigger('click')
    const dialog = wrapper.find('[aria-labelledby="user-modal-title"]')
    await dialog.find('select').setValue('staff')
    await dialog.find('input[type="search"]').setValue('math')
    expect(dialog.text()).toContain('Mathematics')
    expect(dialog.find('input[aria-label="Assign Physics"]').exists()).toBe(false)
    await dialog.find('input[aria-label="Assign Mathematics"]').setValue(true)
    expect(dialog.text()).toContain('1 selected')
    await dialog.find('input[type="search"]').setValue('history')
    await dialog.find('input[aria-label="Assign History"]').setValue(true)
    const primary = dialog.findAll('select').find(select => select.find('option[value="20"]').exists())!
    await primary.setValue('20')
    await dialog.find('input[placeholder="Full name"]').setValue('New Staff')
    await dialog.find('input[type="email"]').setValue('staff@example.com')
    await dialog.find('input[type="password"]').setValue('password123')
    await dialog.find('input[placeholder="Staff ID"]').setValue('STAFF-1')
    await dialog.trigger('submit')
    await flushPromises()
    expect(admin.createAdmin).toHaveBeenCalledWith('users', expect.objectContaining({ role: 'staff', department_ids: [10, 20], primary_department_id: 20 }))
  })
})
