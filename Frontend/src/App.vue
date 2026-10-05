<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NotificationBell from './components/NotificationBell.vue'
import AppIcon from './components/ui/AppIcon.vue'
import { useAuth } from './composables/useAuth'
import { logout } from './services/auth'

const route = useRoute()
const router = useRouter()
const { user, isAuthenticated, clearAuth } = useAuth()
const mobileOpen = ref(false)
const loggingOut = ref(false)
const isAuthPage = computed(
  () => route.name === 'login' || route.name === 'register'
)
const roleLabel = computed(() => {
  if (user.value?.role === 'student') return 'Student portal'
  const level = user.value?.staff_profile?.admin_level
  return level === 'super_admin'
    ? 'System administration'
    : level === 'dept_admin'
      ? 'Department administration'
      : 'Staff workspace'
})
const initials = computed(
  () =>
    user.value?.name
      .split(/\s+/)
      .slice(0, 2)
      .map((part) => part[0])
      .join('')
      .toUpperCase() || 'CD'
)
const home = computed(() =>
  user.value?.role === 'student'
    ? '/student'
    : user.value?.staff_profile?.admin_level === 'super_admin'
      ? '/admin'
      : user.value?.staff_profile?.admin_level === 'dept_admin'
        ? '/dept-admin'
        : '/staff'
)
const navigation = computed(() => {
  const items: {
    label: string
    href: string
    icon: 'home' | 'requests' | 'plus' | 'users' | 'building'
  }[] = [{ label: 'Overview', href: home.value, icon: 'home' }]
  if (user.value?.role === 'student')
    items.push(
      {
        label: 'My requests',
        href: '/student/requests',
        icon: 'requests'
      },
      { label: 'New request', href: '/student/requests/new', icon: 'plus' }
    )
  else if (home.value === '/staff')
    items.push(
      { label: 'Unclaimed queue', href: '/staff/queue', icon: 'requests' },
      { label: 'My active cases', href: '/staff/cases', icon: 'users' }
    )
  else if (home.value === '/dept-admin')
    items.push({
      label: 'Department requests',
      href: '/dept-admin/requests',
      icon: 'requests'
    })
  else if (home.value === '/admin')
    items.push(
      { label: 'Requests', href: '/admin/requests', icon: 'requests' },
      { label: 'Users', href: '/admin/users', icon: 'users' },
      { label: 'Faculties', href: '/admin/faculties', icon: 'building' },
      { label: 'Departments', href: '/admin/departments', icon: 'building' },
      { label: 'Programmes', href: '/admin/programmes', icon: 'building' },
      {
        label: 'Request types',
        href: '/admin/request-types',
        icon: 'requests'
      },
      { label: 'Status history', href: '/admin/history', icon: 'requests' }
    )
  return items
})
const activeHref = computed(
  () =>
    navigation.value
      .filter(
        (item) =>
          route.path === item.href ||
          (item.href !== home.value && route.path.startsWith(`${item.href}/`))
      )
      .sort((a, b) => b.href.length - a.href.length)[0]?.href
)
watch(
  () => route.path,
  async () => {
    mobileOpen.value = false
    await nextTick()
    document.getElementById('main-content')?.focus({ preventScroll: true })
  }
)
async function onLogout() {
  if (loggingOut.value) return
  loggingOut.value = true
  try {
    await logout()
  } finally {
    clearAuth()
    mobileOpen.value = false
    loggingOut.value = false
    await router.replace('/login')
  }
}
</script>

<template>
  <div class="min-h-screen bg-background">
    <a
      href="#main-content"
      class="skip-link"
      >Skip to main content</a
    >
    <aside
      v-if="!isAuthPage"
      class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col bg-slate-900 text-white lg:flex"
    >
      <router-link
        :to="home"
        class="flex h-20 items-center gap-3 px-6"
      >
        <span
          class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-500/15 text-sky-300"
          ><AppIcon name="building"
        /></span>
        <span class="text-xl font-bold tracking-tight"
          >CampusDesk<span class="text-sky-400">.</span></span
        >
      </router-link>
      <div class="px-6 pb-6 pt-4">
        <p
          class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400"
        >
          Your workspace
        </p>
        <p class="mt-2 text-sm text-slate-200">{{ roleLabel }}</p>
      </div>
      <nav
        aria-label="Main navigation"
        class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 pb-4"
      >
        <router-link
          v-for="item in navigation"
          :key="item.href"
          :to="item.href"
          :aria-current="activeHref === item.href ? 'page' : undefined"
          class="flex min-h-12 items-center gap-3 rounded-lg px-4 py-3 text-sm transition-colors"
          :class="
            activeHref === item.href
              ? 'bg-sky-800 font-semibold text-white'
              : 'text-slate-300 hover:bg-slate-800 hover:text-white'
          "
        >
          <AppIcon :name="item.icon" />{{ item.label }}
        </router-link>
      </nav>
      <div class="mx-5 mb-6 shrink-0 border-t border-slate-700 pt-5">
        <div class="flex items-start gap-3 text-slate-400">
          <AppIcon name="shield" />
          <p class="text-xs leading-relaxed">
            Your requests, documents, and progress. All in one place.
          </p>
        </div>
        <p class="mt-6 text-[11px] text-slate-400">
          CampusDesk · University services
        </p>
      </div>
    </aside>
    <div :class="{ 'lg:pl-60': !isAuthPage }">
      <header
        class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur"
      >
        <div
          class="mx-auto flex h-20 max-w-[1440px] items-center justify-between gap-3 px-4 sm:px-6 lg:px-8"
        >
          <div class="flex min-w-0 items-center gap-2">
            <button
              v-if="!isAuthPage"
              class="icon-button lg:hidden"
              type="button"
              :aria-expanded="mobileOpen"
              aria-controls="mobile-navigation"
              :aria-label="mobileOpen ? 'Close navigation' : 'Open navigation'"
              @click="mobileOpen = !mobileOpen"
            >
              <AppIcon :name="mobileOpen ? 'close' : 'menu'" />
            </button>
            <router-link
              :to="isAuthPage ? '/login' : home"
              class="flex items-center gap-2 font-bold text-slate-900"
              :class="isAuthPage ? 'text-xl' : 'text-lg lg:hidden'"
              ><AppIcon name="building" />CampusDesk<span class="text-sky-600"
                >.</span
              ></router-link
            >
            <div
              v-if="!isAuthPage"
              class="hidden lg:block"
            >
              <p class="text-sm font-semibold text-slate-900">
                {{ roleLabel }}
              </p>
              <p class="mt-0.5 text-xs text-slate-500">
                University request management
              </p>
            </div>
          </div>
          <div
            v-if="isAuthenticated && !isAuthPage"
            class="flex shrink-0 items-center gap-2 sm:gap-4"
          >
            <NotificationBell />
            <div
              class="hidden items-center gap-3 border-l border-slate-200 pl-4 sm:flex"
            >
              <span
                class="flex h-9 w-9 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-sky-800"
                >{{ initials }}</span
              ><span class="max-w-44 truncate text-sm font-semibold">{{
                user?.name
              }}</span>
            </div>
            <button
              type="button"
              class="icon-button"
              :disabled="loggingOut"
              :aria-label="loggingOut ? 'Logging out' : 'Log out'"
              title="Log out"
              @click="onLogout"
            >
              <AppIcon name="logout" />
            </button>
          </div>
          <span
            v-else
            class="hidden text-xs text-slate-500 sm:block"
            >University services, connected.</span
          >
        </div>
        <nav
          v-if="mobileOpen && !isAuthPage"
          id="mobile-navigation"
          aria-label="Mobile navigation"
          class="max-h-[calc(100dvh-5rem)] space-y-1 overflow-y-auto border-t border-slate-100 bg-white p-4 lg:hidden"
          @keydown.esc="mobileOpen = false"
        >
          <p class="mb-3 px-3 text-xs text-slate-500">
            {{ user?.name }} · {{ roleLabel }}
          </p>
          <router-link
            v-for="item in navigation"
            :key="item.href"
            :to="item.href"
            class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 font-medium text-slate-700 hover:bg-slate-50"
            :aria-current="activeHref === item.href ? 'page' : undefined"
            :class="{ 'bg-sky-50 text-sky-800': activeHref === item.href }"
            @click="mobileOpen = false"
            ><AppIcon :name="item.icon" />{{ item.label }}</router-link
          >
        </nav>
      </header>
      <main
        id="main-content"
        tabindex="-1"
        :class="
          isAuthPage
            ? 'px-4 py-6 sm:p-8'
            : 'mx-auto max-w-[1440px] px-4 py-6 sm:px-6 lg:p-8'
        "
      >
        <router-view v-slot="{ Component }"
          ><transition
            name="fade"
            mode="out-in"
            ><component :is="Component" /></transition
        ></router-view>
      </main>
      <footer class="mx-auto max-w-[1440px] px-6 py-6 text-xs text-slate-500">
        <div
          class="flex flex-wrap justify-between gap-2 border-t border-slate-200 pt-5"
        >
          <span>CampusDesk · University request management</span
          ><span>Designed around your next step.</span>
        </div>
      </footer>
    </div>
  </div>
</template>
