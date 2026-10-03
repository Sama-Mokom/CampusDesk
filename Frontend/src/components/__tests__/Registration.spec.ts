import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import RegisterView from '@/views/RegisterView.vue'
import { register } from '@/services/auth'
import {
  fetchFaculties,
  fetchDepartments,
  fetchProgrammes
} from '@/services/reference'

vi.mock('@/services/auth', () => ({ register: vi.fn() }))
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({ setUser: vi.fn() })
}))
vi.mock('@/services/reference', () => ({
  fetchFaculties: vi.fn(),
  fetchDepartments: vi.fn(),
  fetchProgrammes: vi.fn()
}))
enableAutoUnmount(afterEach)
beforeEach(() => {
  vi.resetAllMocks()
  vi.mocked(fetchFaculties).mockResolvedValue([
    { id: 1, name: 'Science', code: 'SCI', created_at: '' }
  ])
  vi.mocked(fetchDepartments).mockResolvedValue([
    { id: 10, faculty_id: 1, name: 'Physics', code: 'PHY', created_at: '' },
    { id: 11, faculty_id: 1, name: 'Maths', code: 'MTH', created_at: '' }
  ])
  vi.mocked(fetchProgrammes).mockResolvedValue([
    {
      id: 100,
      faculty_id: 1,
      department_id: 10,
      name: 'BSc Physics',
      code: 'PHYS',
      degree_type: 'BACHELOR'
    },
    {
      id: 110,
      faculty_id: 1,
      department_id: 11,
      name: 'BSc Maths',
      code: 'MATH',
      degree_type: 'BACHELOR'
    }
  ])
})
async function render() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/register', component: RegisterView },
      { path: '/login', component: { template: '<div />' } },
      { path: '/student', component: { template: '<div />' } }
    ]
  })
  await router.push('/register')
  const wrapper = mount(RegisterView, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}
describe('Student registration', () => {
  it('filters programmes by department and clears stale academic selections', async () => {
    const { wrapper } = await render()
    await wrapper.get('#register-faculty').setValue('1')
    await wrapper.get('#register-department').setValue('10')
    expect(wrapper.get('#register-programme').text()).toContain('BSc Physics')
    expect(wrapper.get('#register-programme').text()).not.toContain('BSc Maths')
    await wrapper.get('#register-programme').setValue('100')
    await wrapper.get('#register-department').setValue('11')
    expect(
      (wrapper.get('#register-programme').element as HTMLSelectElement).value
    ).toBe('0')
  })
  it('submits the existing registration contract and redirects to the student dashboard', async () => {
    const { wrapper, router } = await render()
    vi.mocked(register).mockResolvedValue({
      id: 1,
      name: 'Student',
      email: 'student@example.edu',
      password: '',
      created_at: '',
      role: 'student'
    })
    await wrapper.get('#register-name').setValue('Student')
    await wrapper.get('#register-email').setValue('student@example.edu')
    await wrapper.get('#register-password').setValue('password123')
    await wrapper.get('#register-matricule').setValue('SC123')
    await wrapper.get('#register-faculty').setValue('1')
    await wrapper.get('#register-department').setValue('10')
    await wrapper.get('#register-programme').setValue('100')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(register).toHaveBeenCalledWith({
      name: 'Student',
      email: 'student@example.edu',
      password: 'password123',
      password_confirmation: 'password123',
      matricule: 'SC123',
      faculty_id: 1,
      department_id: 10,
      programme_id: 100,
      level: '100'
    })
    expect(router.currentRoute.value.path).toBe('/student')
  })
  it('retries failed reference loading before allowing registration', async () => {
    vi.mocked(fetchFaculties).mockRejectedValueOnce(new Error('Offline'))
    const { wrapper } = await render()
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'could not be loaded'
    )
    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()
    expect(wrapper.find('form').exists()).toBe(true)
  })
})
