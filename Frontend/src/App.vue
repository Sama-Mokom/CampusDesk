<template>
  <div class="min-h-screen bg-background">
    <header
      v-if="layout !== 'auth'"
      class="sticky top-0 z-40 bg-white border-b border-neutral-200 shadow-sm"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="h-16 flex items-center justify-between">
          <router-link to="/" class="flex items-center gap-3">
            <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
              <span class="text-white font-bold text-lg">CD</span>
            </div>
            <div>
              <h1 class="text-xl font-bold text-primary">CampusDesk</h1>
              <p class="text-xs text-neutral-600">University Request Management System</p>
            </div>
          </router-link>
          <div class="flex items-center gap-3">
            <NotificationBell />
            <div class="text-sm text-right hidden sm:block">
              <p class="font-semibold text-primary">{{ sessionUser?.name }}</p>
              <p class="text-xs text-neutral-600">{{ roleLabel }}</p>
            </div>
            <button type="button" class="btn-secondary text-sm" @click="onLogout">Log out</button>
          </div>
        </div>
      </div>
    </header>

    <header v-else class="sticky top-0 z-40 bg-white border-b border-neutral-200 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <router-link to="/login" class="flex items-center gap-3">
          <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
            <span class="text-white font-bold text-lg">CD</span>
          </div>
          <span class="text-xl font-bold text-primary">CampusDesk</span>
        </router-link>
      </div>
    </header>

    <main :class="layout === 'auth' ? '' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8'">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>

    <footer class="mt-16 bg-neutral-50 border-t border-neutral-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div>
            <h3 class="font-semibold text-foreground mb-2">CampusDesk</h3>
            <p class="text-sm text-neutral-600">Streamlining university student requests and support processes.</p>
          </div>
          <div>
            <h4 class="font-semibold text-foreground mb-2">Quick Links</h4>
            <ul class="space-y-1">
              <li><a href="#" class="text-sm text-neutral-600 hover:text-primary">Help Center</a></li>
              <li><a href="#" class="text-sm text-neutral-600 hover:text-primary">Contact Support</a></li>
              <li><a href="#" class="text-sm text-neutral-600 hover:text-primary">Documentation</a></li>
            </ul>
          </div>
          <div>
            <h4 class="font-semibold text-foreground mb-2">System Status</h4>
            <p class="text-sm text-green-600">All systems operational</p>
            <p class="text-xs text-neutral-600 mt-2">Last updated: {{ currentTime }}</p>
          </div>
        </div>
        <div class="mt-8 pt-8 border-t border-neutral-200 text-center">
          <p class="text-sm text-neutral-600">© 2026 CampusDesk. All rights reserved.</p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NotificationBell from './components/NotificationBell.vue'
import { useMockData } from './composables/useMockData'

const route = useRoute()
const router = useRouter()
const { sessionUser, logout } = useMockData()

const currentTime = ref('')

const layout = computed(() => {
  if (route.name === 'login' || route.name === 'register') return 'auth'
  return 'app'
})

const roleLabel = computed(() => {
  const u = sessionUser.value
  if (!u) return ''
  if (u.role === 'student') return 'Student'
  const al = u.staff_profile?.admin_level
  if (al === 'super_admin') return 'Super Admin'
  if (al === 'dept_admin') return 'Department Admin'
  return 'Staff'
})

function onLogout() {
  logout()
  router.push({ name: 'login' })
}

onMounted(() => {
  const tick = () => {
    currentTime.value = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  }
  tick()
  setInterval(tick, 60000)
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
