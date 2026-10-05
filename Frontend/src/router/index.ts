import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import StudentView from '@/views/StudentView.vue'
import StaffView from '@/views/StaffView.vue'
import DeptAdminView from '@/views/DeptAdminView.vue'
import SuperAdminView from '@/views/SuperAdminView.vue'

function homePathForUser(): string {
  const { user } = useAuth()
  const u = user.value
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
  scrollBehavior(_to, _from, savedPosition) {
    return savedPosition || { top: 0 }
  },
  routes: [
    { path: '/', redirect: () => homePathForUser() },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guest: true }
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView,
      meta: { guest: true }
    },
    {
      path: '/student',
      component: StudentView,
      meta: { requiresAuth: true, roles: ['student'] },
      children: [
        {
          path: '',
          name: 'student',
          component: () => import('@/views/student/StudentOverviewPage.vue')
        },
        {
          path: 'requests',
          name: 'student-requests',
          component: () => import('@/views/student/StudentRequestsPage.vue')
        },
        {
          path: 'requests/new',
          name: 'student-new-request',
          component: () => import('@/views/student/StudentNewRequestPage.vue')
        },
        {
          path: 'requests/:id',
          name: 'student-request-detail',
          component: () =>
            import('@/views/student/StudentRequestDetailPage.vue')
        }
      ]
    },
    {
      path: '/staff',
      component: StaffView,
      meta: { requiresAuth: true, staffLevel: 'plain' },
      children: [
        {
          path: '',
          name: 'staff',
          component: () => import('@/views/staff/StaffOverviewPage.vue')
        },
        {
          path: 'queue',
          name: 'staff-queue',
          component: () => import('@/views/staff/StaffQueuePage.vue')
        },
        {
          path: 'cases',
          name: 'staff-cases',
          component: () => import('@/views/staff/StaffCasesPage.vue')
        }
      ]
    },
    {
      path: '/dept-admin',
      component: DeptAdminView,
      meta: { requiresAuth: true, staffLevel: 'dept_admin' },
      children: [
        {
          path: '',
          name: 'dept-admin',
          component: () =>
            import('@/views/dept-admin/DeptAdminOverviewPage.vue')
        },
        {
          path: 'requests',
          name: 'dept-admin-requests',
          component: () =>
            import('@/views/dept-admin/DeptAdminRequestsPage.vue')
        }
      ]
    },
    {
      path: '/admin',
      component: SuperAdminView,
      meta: { requiresAuth: true, staffLevel: 'super_admin' },
      children: [
        {
          path: '',
          name: 'admin',
          component: () => import('@/views/admin/AdminOverviewPage.vue')
        },
        {
          path: 'requests',
          name: 'admin-requests',
          component: () => import('@/views/admin/AdminRequestsPage.vue')
        },
        {
          path: 'users',
          name: 'admin-users',
          component: () => import('@/views/admin/AdminUsersPage.vue')
        },
        ...(
          ['faculties', 'departments', 'programmes', 'request-types'] as const
        ).map((kind) => ({
          path: kind,
          name: `admin-${kind}`,
          component: () => import('@/views/admin/AdminReferencesPage.vue'),
          props: { kind }
        })),
        {
          path: 'history',
          name: 'admin-history',
          component: () => import('@/views/admin/AdminHistoryPage.vue')
        }
      ]
    },
    { path: '/:pathMatch(.*)*', redirect: () => homePathForUser() }
  ]
})

router.beforeEach((to, _from, next) => {
  const { isAuthenticated, user } = useAuth()
  const authed = isAuthenticated.value
  const u = user.value

  if (to.meta.guest && authed) {
    return next(homePathForUser())
  }

  if (to.meta.requiresAuth && !authed) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  if (to.meta.roles && u) {
    const roles = to.meta.roles as string[]
    if (!roles.includes(u.role)) return next(homePathForUser())
  }

  if (to.meta.staffLevel) {
    if (!u || u.role !== 'staff') return next(homePathForUser())
    const need = to.meta.staffLevel as string
    const level = u.staff_profile?.admin_level
    if (need === 'plain' && level !== null) return next(homePathForUser())
    if (need === 'dept_admin' && level !== 'dept_admin')
      return next(homePathForUser())
    if (need === 'super_admin' && level !== 'super_admin')
      return next(homePathForUser())
  }

  next()
})
