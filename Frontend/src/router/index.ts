import { createRouter, createWebHistory } from 'vue-router'
import { useMockData } from '@/composables/useMockData'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import StudentView from '@/views/StudentView.vue'
import StaffView from '@/views/StaffView.vue'
import DeptAdminView from '@/views/DeptAdminView.vue'
import SuperAdminView from '@/views/SuperAdminView.vue'

function homePathForUser(): string {
  const { sessionUser } = useMockData()
  const u = sessionUser.value
  if (!u) return '/login'
  if (u.role === 'student') return '/student'
  if (u.role === 'staff') {
    const level = u.staff_profile?.admin_level
    if (level === 'super_admin') return '/admin'
    if (level === 'dept_admin') return '/dept-admin'
    return '/staff'
  }
  return '/login'
}

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: () => homePathForUser() },
    { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
    { path: '/register', name: 'register', component: RegisterView, meta: { guest: true } },
    {
      path: '/student',
      name: 'student',
      component: StudentView,
      meta: { requiresAuth: true, roles: ['student'] }
    },
    {
      path: '/staff',
      name: 'staff',
      component: StaffView,
      meta: { requiresAuth: true, staffLevel: 'plain' }
    },
    {
      path: '/dept-admin',
      name: 'dept-admin',
      component: DeptAdminView,
      meta: { requiresAuth: true, staffLevel: 'dept_admin' }
    },
    {
      path: '/admin',
      name: 'admin',
      component: SuperAdminView,
      meta: { requiresAuth: true, staffLevel: 'super_admin' }
    }
  ]
})

router.beforeEach((to, _from, next) => {
  const { isAuthenticated, sessionUser } = useMockData()
  const authed = isAuthenticated.value

  if (to.meta.guest && authed) {
    return next(homePathForUser())
  }

  if (to.meta.requiresAuth && !authed) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  const u = sessionUser.value
  if (to.meta.roles && u) {
    const roles = to.meta.roles as string[]
    if (!roles.includes(u.role)) return next(homePathForUser())
  }

  if (to.meta.staffLevel) {
    if (!u || u.role !== 'staff') return next(homePathForUser())
    const need = to.meta.staffLevel as string
    const level = u.staff_profile?.admin_level
    if (need === 'plain' && level !== null) return next(homePathForUser())
    if (need === 'dept_admin' && level !== 'dept_admin') return next(homePathForUser())
    if (need === 'super_admin' && level !== 'super_admin') return next(homePathForUser())
  }

  next()
})
