import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { router } from '@/router'
import { useAuth } from '@/composables/useAuth'
import App from '@/App.vue'
import LoginView from '@/views/LoginView.vue'
import type { User } from '@/types'
import api from '@/services/api'

vi.mock('@/views/StudentView.vue', () => ({
  default: { template: '<div>Student dashboard</div>' }
}))
vi.mock('@/views/StaffView.vue', () => ({
  default: { template: '<div>Staff dashboard</div>' }
}))
vi.mock('@/views/DeptAdminView.vue', () => ({
  default: { template: '<div>Department dashboard</div>' }
}))
vi.mock('@/views/SuperAdminView.vue', () => ({
  default: { template: '<div>Admin dashboard</div>' }
}))
enableAutoUnmount(afterEach)
function user(
  role: 'student' | 'staff',
  level: 'dept_admin' | 'super_admin' | null = null
): User {
  return {
    id: 1,
    name: 'Example User',
    email: 'user@example.edu',
    password: '',
    created_at: '',
    role,
    staff_profile:
      role === 'staff'
        ? { staff_id: 'STAFF1', admin_level: level, departments: [] }
        : undefined
  }
}
function authenticate(value: User) {
  localStorage.setItem('token', 'test-token')
  useAuth().setUser(value)
}
beforeEach(async () => {
  vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
  useAuth().clearAuth()
  await router.push('/login')
})
afterEach(async () => {
  await flushPromises()
  vi.restoreAllMocks()
  useAuth().clearAuth()
})
describe('Authentication and role-aware shell', () => {
  const destinations = [
    ['student', null, '/student', ['requests', 'requests/new', 'requests/42']],
    ['staff', null, '/staff', ['queue', 'cases']],
    ['staff', 'dept_admin', '/dept-admin', ['requests']],
    [
      'staff',
      'super_admin',
      '/admin',
      [
        'requests',
        'users',
        'faculties',
        'departments',
        'programmes',
        'request-types',
        'history'
      ]
    ]
  ] as const

  it.each(destinations)(
    'protects and resolves every nested page for %s / %s',
    async (role, level, home, pages) => {
      for (const page of pages) {
        const path = `${home}/${page}`
        useAuth().clearAuth()
        await router.push(path)
        expect(router.currentRoute.value.path).toBe('/login')
        expect(router.currentRoute.value.query.redirect).toBe(path)
        authenticate(user(role, level))
        await router.push(path)
        expect(router.currentRoute.value.path).toBe(path)
        expect(router.currentRoute.value.matched).toHaveLength(2)
        expect(router.currentRoute.value.meta.requiresAuth).toBe(true)
        authenticate(
          role === 'student' ? user('staff', 'super_admin') : user('student')
        )
        await router.replace('/login')
        await router.push(path)
        expect(router.currentRoute.value.path).toBe(
          role === 'student' ? '/admin' : '/student'
        )
      }
    }
  )

  it.each(destinations)(
    'renders real page links for %s / %s',
    async (role, level, home) => {
      authenticate(user(role, level))
      await router.push(home)
      const wrapper = mount(App, {
        global: { plugins: [router], stubs: { NotificationBell: true } }
      })
      const nav = wrapper.get('[aria-label="Main navigation"]')
      const links = nav.findAll('a')
      expect(links.length).toBeGreaterThan(1)
      for (const link of links) {
        const href = link.attributes('href')!
        expect(href).not.toContain('#')
        expect(router.resolve(href).matched).toHaveLength(2)
      }
      const destination = links[1]!.attributes('href')!
      await links[1]!.trigger('click')
      await flushPromises()
      expect(router.currentRoute.value.path).toBe(destination)
      expect(nav.get('[aria-current="page"]').attributes('href')).toBe(
        destination
      )
      await wrapper.get('[aria-label="Open navigation"]').trigger('click')
      expect(
        wrapper
          .get('#mobile-navigation [aria-current="page"]')
          .attributes('href')
      ).toBe(destination)
      await wrapper.get(`#mobile-navigation a[href="${home}"]`).trigger('click')
      await flushPromises()
      expect(router.currentRoute.value.path).toBe(home)
      expect(wrapper.find('#mobile-navigation').exists()).toBe(false)
    }
  )

  it('marks only the most specific student destination active, including query strings', async () => {
    authenticate(user('student'))
    await router.push('/student/requests/new')
    const wrapper = mount(App, {
      global: { plugins: [router], stubs: { NotificationBell: true } }
    })
    const nav = wrapper.get('[aria-label="Main navigation"]')
    expect(nav.findAll('[aria-current="page"]')).toHaveLength(1)
    expect(nav.get('[aria-current="page"]').text()).toBe('New request')
    await router.push('/student/requests/42?from=recent')
    expect(nav.findAll('[aria-current="page"]')).toHaveLength(1)
    expect(nav.get('[aria-current="page"]').text()).toBe('My requests')
    await router.push('/student/requests?status=pending')
    expect(nav.get('[aria-current="page"]').text()).toBe('My requests')
  })

  it.each([
    [null, '/dept-admin/requests', '/staff'],
    [null, '/admin/users', '/staff'],
    ['dept_admin', '/staff/cases', '/dept-admin'],
    ['dept_admin', '/admin/history', '/dept-admin'],
    ['super_admin', '/staff/queue', '/admin'],
    ['super_admin', '/dept-admin/requests', '/admin']
  ] as const)(
    'retains staff-level restrictions for %s at %s',
    async (level, path, home) => {
      authenticate(user('staff', level))
      await router.push(path)
      expect(router.currentRoute.value.path).toBe(home)
    }
  )
  it.each([
    ['student', null, '/student'],
    ['staff', null, '/staff'],
    ['staff', 'dept_admin', '/dept-admin'],
    ['staff', 'super_admin', '/admin']
  ] as const)(
    'restores %s / %s to its authorized home',
    async (role, level, path) => {
      // Restoration uses the same persisted shape as the login API.
      localStorage.setItem('token', 'test-token')
      localStorage.setItem('user', JSON.stringify(user(role, level)))
      expect(useAuth().isAuthenticated.value).toBe(true)
      await router.push('/')
      expect(router.currentRoute.value.path).toBe(path)
      await router.push(role === 'student' ? '/admin' : '/student')
      expect(router.currentRoute.value.path).toBe(path)
    }
  )
  it('redirects a guest to login while retaining the requested destination', async () => {
    await router.push('/student')
    expect(router.currentRoute.value.path).toBe('/login')
    expect(router.currentRoute.value.query.redirect).toBe('/student')
  })
  it('renders role navigation and a keyboard-operable mobile disclosure', async () => {
    authenticate(user('student'))
    await router.push('/student')
    const wrapper = mount(App, {
      global: { plugins: [router], stubs: { NotificationBell: true } }
    })
    expect(wrapper.get('[aria-label="Main navigation"]').text()).toContain(
      'My requests'
    )
    const trigger = wrapper.get('[aria-label="Open navigation"]')
    await trigger.trigger('click')
    expect(trigger.attributes('aria-expanded')).toBe('true')
    expect(wrapper.get('#mobile-navigation').text()).toContain('New request')
    await wrapper
      .get('#mobile-navigation')
      .trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('#mobile-navigation').exists()).toBe(false)
  })
  it('revokes the backend token before clearing the session and prevents duplicate logout', async () => {
    authenticate(user('staff'))
    await router.push('/staff')
    let finish!: () => void
    const post = vi.spyOn(api, 'post').mockImplementation(
      () =>
        new Promise((resolve) => {
          finish = () => resolve({ data: {} })
        })
    )
    const wrapper = mount(App, {
      global: { plugins: [router], stubs: { NotificationBell: true } }
    })
    await wrapper.get('[aria-label="Log out"]').trigger('click')
    expect(post).toHaveBeenCalledWith('/logout')
    expect(localStorage.getItem('token')).toBe('test-token')
    expect(
      wrapper.get('[aria-label="Logging out"]').attributes('disabled')
    ).toBeDefined()
    finish()
    await flushPromises()
    expect(localStorage.getItem('token')).toBeNull()
    expect(useAuth().user.value).toBeNull()
    expect(router.currentRoute.value.path).toBe('/login')
  })
  it('submits labeled login fields once and surfaces API validation failures', async () => {
    const post = vi.spyOn(api, 'post').mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: { message: 'These credentials do not match our records.' }
      }
    })
    const wrapper = mount(LoginView, { global: { plugins: [router] } })
    await wrapper.get('#login-email').setValue('person@example.edu')
    await wrapper.get('#login-password').setValue('wrong-password')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(post).toHaveBeenCalledWith('/login', {
      email: 'person@example.edu',
      password: 'wrong-password'
    })
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'credentials do not match'
    )
    expect(
      wrapper.get('button[type="submit"]').attributes('disabled')
    ).toBeUndefined()
  })
})
