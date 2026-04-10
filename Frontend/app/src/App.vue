<template>
  <div class="min-h-screen bg-background">
    <!-- Header -->
    <header class="sticky top-0 z-40 bg-white border-b border-neutral-200 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="h-16 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
              <span class="text-white font-bold text-lg">CD</span>
            </div>
            <div>
              <h1 class="text-xl font-bold text-primary">CampusDesk</h1>
              <p class="text-xs text-neutral-600">University Request Management System</p>
            </div>
          </div>
          <div class="flex items-center gap-4">
            <div class="text-sm">
              <p class="font-semibold">{{ currentUser.name }}</p>
              <p class="text-xs text-neutral-600 capitalize">{{ currentUser.role }}</p>
            </div>
            <button @click="toggleUserMenu" class="p-2 hover:bg-neutral-100 rounded-lg transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- User Role Switcher Dropdown -->
      <div v-if="showUserMenu" class="absolute top-16 right-4 bg-white border border-neutral-200 rounded-lg shadow-lg p-2 z-50">
        <button
          @click="switchRoleHandler('student')"
          :class="['w-full text-left px-4 py-2 rounded transition-colors', currentUser.role === 'student' ? 'bg-primary text-white' : 'hover:bg-neutral-100']"
        >
          Student View
        </button>
        <button
          @click="switchRoleHandler('staff')"
          :class="['w-full text-left px-4 py-2 rounded transition-colors', currentUser.role === 'staff' ? 'bg-primary text-white' : 'hover:bg-neutral-100']"
        >
          Staff View
        </button>
        <button
          @click="switchRoleHandler('admin')"
          :class="['w-full text-left px-4 py-2 rounded transition-colors', currentUser.role === 'admin' ? 'bg-primary text-white' : 'hover:bg-neutral-100']"
        >
          Admin View
        </button>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Tab Navigation -->
      <div class="mb-8 border-b border-neutral-200">
        <div class="flex gap-1">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'px-4 py-3 font-medium transition-colors relative',
              activeTab === tab.id
                ? 'text-primary'
                : 'text-neutral-600 hover:text-foreground'
            ]"
          >
            {{ tab.label }}
            <div
              v-if="activeTab === tab.id"
              class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary"
            ></div>
          </button>
        </div>
      </div>

      <!-- Content Area -->
      <div class="animate-fadeIn">
        <StudentDashboard v-if="activeTab === 'student'" />
        <StaffDashboard v-else-if="activeTab === 'staff'" />
        <AdminDashboard v-else-if="activeTab === 'admin'" />
      </div>
    </main>

    <!-- Footer -->
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
          <p class="text-sm text-neutral-600">© 2024 CampusDesk. All rights reserved.</p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import StudentDashboard from './components/StudentDashboard.vue'
import StaffDashboard from './components/StaffDashboard.vue'
import AdminDashboard from './components/AdminDashboard.vue'
import { useMockData } from './composables/useMockData'

const { currentUser, switchRole } = useMockData()

const activeTab = ref('student')
const showUserMenu = ref(false)
const currentTime = ref('')

const tabs = [
  { id: 'student', label: 'Student Request Portal' },
  { id: 'staff', label: 'Staff Queue Management' },
  { id: 'admin', label: 'Admin Dashboard' }
]

const toggleUserMenu = () => {
  showUserMenu.value = !showUserMenu.value
}

const switchRoleHandler = (role: 'student' | 'staff' | 'admin') => {
  switchRole(role)
  activeTab.value = role
  showUserMenu.value = false
}

onMounted(() => {
  console.log("[v0] App mounted successfully")
  console.log("[v0] Current user:", currentUser)
  // Update current time
  const updateTime = () => {
    const now = new Date()
    currentTime.value = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  }
  updateTime()
  setInterval(updateTime, 60000)
})
</script>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fadeIn {
  animation: fadeIn 0.3s ease-in-out;
}
</style>
