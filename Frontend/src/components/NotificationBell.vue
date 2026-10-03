<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import type { Notification } from '@/types'
import {
  fetchNotifications,
  markNotificationRead
} from '@/services/notifications'
import { useAuth } from '@/composables/useAuth'
import { format } from 'date-fns'
import SkeletonLoader from './ui/SkeletonLoader.vue'

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const items = ref<Notification[]>([])
const loading = ref(false)
const error = ref('')
const reading = ref<number | null>(null)
const unreadCount = computed(
  () => items.value.filter((item) => !item.read).length
)
const { isAuthenticated } = useAuth()
async function load() {
  if (!isAuthenticated.value || loading.value) return
  loading.value = true
  error.value = ''
  try {
    items.value = await fetchNotifications()
  } catch {
    error.value = 'Notifications could not be loaded.'
  } finally {
    loading.value = false
  }
}
function outside(event: MouseEvent) {
  if (!root.value?.contains(event.target as Node)) open.value = false
}
function escape(event: KeyboardEvent) {
  if (event.key === 'Escape' && open.value) {
    open.value = false
    trigger.value?.focus()
  }
}
onMounted(() => {
  void load()
  document.addEventListener('click', outside)
  document.addEventListener('keydown', escape)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', outside)
  document.removeEventListener('keydown', escape)
})
function formatTime(iso: string) {
  try {
    return format(new Date(iso), 'MMM d, yyyy · HH:mm')
  } catch {
    return iso
  }
}
async function onClick(id: number) {
  if (!isAuthenticated.value || reading.value !== null) return
  const index = items.value.findIndex((item) => item.id === id)
  if (index === -1 || items.value[index]!.read) return
  reading.value = id
  error.value = ''
  try {
    items.value[index] = await markNotificationRead(id)
  } catch {
    error.value = 'Could not mark this notification as read. Please try again.'
  } finally {
    reading.value = null
  }
}
</script>

<template>
  <div
    ref="root"
    class="relative"
  >
    <button
      ref="trigger"
      type="button"
      class="icon-button relative"
      aria-label="Notifications"
      :aria-expanded="open"
      aria-controls="notifications-panel"
      @click="open = !open"
    >
      <svg
        aria-hidden="true"
        class="h-5 w-5"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
      </svg>
      <span
        v-if="unreadCount > 0"
        class="absolute right-0 top-0 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-red-700 px-1 text-[10px] font-bold text-white"
        >{{ unreadCount > 9 ? '9+' : unreadCount
        }}<span class="sr-only"> unread</span></span
      >
    </button>
    <Transition name="fade">
      <section
        v-if="open"
        id="notifications-panel"
        aria-label="Notifications"
        class="notification-panel overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl"
      >
        <header
          class="flex items-center justify-between border-b border-slate-100 p-4"
        >
          <h2 class="text-sm">Notifications</h2>
          <button
            type="button"
            class="btn-ghost"
            :disabled="loading"
            @click="load"
          >
            Refresh
          </button>
        </header>
        <div
          v-if="loading"
          class="p-3"
        >
          <SkeletonLoader :count="2" />
        </div>
        <div
          v-if="error"
          role="alert"
          class="feedback-error m-3"
        >
          {{ error
          }}<button
            class="btn-secondary mt-2"
            type="button"
            @click="load"
          >
            Retry
          </button>
        </div>
        <p
          v-if="!loading && !error && !items.length"
          class="px-4 py-10 text-center text-sm text-slate-500"
        >
          No notifications. You’re all caught up.
        </p>
        <button
          v-for="item in items"
          :key="item.id"
          type="button"
          class="block w-full border-b border-slate-100 px-4 py-4 text-left transition-colors hover:bg-slate-50"
          :class="item.read ? 'bg-white' : 'bg-sky-50'"
          :disabled="reading !== null"
          @click="onClick(item.id)"
        >
          <span class="block text-sm text-slate-700">{{ item.message }}</span
          ><span class="mt-2 block text-xs text-slate-500"
            >{{ formatTime(item.created_at) }} ·
            {{ item.read ? 'Read' : 'Mark as read' }}</span
          >
        </button>
      </section>
    </Transition>
  </div>
</template>
<style scoped>
.notification-panel {
  position: absolute;
  top: calc(100% + 0.75rem);
  right: 0;
  width: 23rem;
  max-height: min(32rem, 75dvh);
  z-index: 40;
}
@media (max-width: 639px) {
  .notification-panel {
    position: fixed;
    top: 5.25rem;
    right: 1rem;
    left: 1rem;
    width: auto;
  }
}
</style>
