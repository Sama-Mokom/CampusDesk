<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import type { Notification } from '@/types'
import {
  fetchNotifications,
  markNotificationRead
} from '@/services/notifications'
import { useAuth } from '@/composables/useAuth'
import { useRealtime } from '@/composables/useRealtime'
import { parseApiError } from '@/utils/error'
import { format } from 'date-fns'
import SkeletonLoader from './ui/SkeletonLoader.vue'
import AppBadge from './ui/AppBadge.vue'

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const items = ref<Notification[]>([])
const loading = ref(false)
const error = ref('')
const reading = ref<number | null>(null)
const markingAll = ref(false)
const markAllStatus = ref('')
const unreadCount = computed(
  () => items.value.filter((item) => !item.read).length
)
const { isAuthenticated } = useAuth()
const { onCoalescedInvalidate, onReconnect } = useRealtime()

onCoalescedInvalidate(() => {
  if (isAuthenticated.value) {
    void load()
  }
})

onReconnect(() => {
  if (isAuthenticated.value) {
    void load()
  }
})
async function load() {
  if (!isAuthenticated.value || loading.value) return
  loading.value = true
  error.value = ''
  try {
    items.value = await fetchNotifications()
  } catch (cause) {
    error.value = parseApiError(cause, 'Notifications could not be loaded.')
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
  if (
    !isAuthenticated.value ||
    reading.value !== null ||
    markingAll.value
  )
    return
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

/**
 * Marks every unread notification as read using the existing single-item
 * endpoint. The UI updates optimistically and only the failed rows are
 * reverted, so a partial outage does not discard successful updates.
 */
async function markAllRead() {
  if (!isAuthenticated.value || markingAll.value || loading.value) return
  const unread = items.value.filter((item) => !item.read)
  if (!unread.length) return

  const ids = unread.map((item) => item.id)
  const snapshot = new Map(items.value.map((item) => [item.id, item]))
  markingAll.value = true
  error.value = ''
  markAllStatus.value = ''
  items.value = items.value.map((item) =>
    item.read
      ? item
      : { ...item, read: true, read_at: new Date().toISOString() }
  )

  const results = await Promise.allSettled(
    ids.map((id) => markNotificationRead(id))
  )
  const failed = new Set<number>()
  results.forEach((result, index) => {
    if (result.status === 'rejected') failed.add(ids[index]!)
  })

  if (failed.size) {
    items.value = items.value.map((item) =>
      failed.has(item.id) ? snapshot.get(item.id)! : item
    )
    error.value =
      failed.size === ids.length
        ? 'Notifications could not be marked as read. Please try again.'
        : 'Some notifications could not be marked as read. Please try again.'
  } else {
    markAllStatus.value = 'All notifications marked as read.'
  }
  markingAll.value = false
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
        class="absolute right-0 top-0 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-surface-page bg-danger-solid px-1 text-[10px] font-bold text-on-danger"
        >{{ unreadCount > 9 ? '9+' : unreadCount
        }}<span class="sr-only"> unread</span></span
      >
    </button>
    <Transition name="fade">
      <section
        v-if="open"
        id="notifications-panel"
        aria-label="Notifications"
        class="notification-panel overflow-y-auto rounded-xl border border-subtle bg-surface-raised shadow-xl"
      >
        <header
          class="notification-header flex flex-wrap items-center justify-between gap-2 p-4"
        >
          <div class="flex min-w-0 items-center gap-2">
            <h2 class="text-sm text-heading">Notifications</h2>
            <AppBadge
              v-if="unreadCount > 0"
              tone="danger"
              :dot="false"
              >{{ unreadCount }} unread</AppBadge
            >
          </div>
          <div class="flex items-center gap-1">
            <button
              v-if="unreadCount > 0"
              type="button"
              class="notification-action"
              :disabled="markingAll || loading"
              @click="markAllRead"
            >
              {{ markingAll ? 'Marking…' : 'Mark all read' }}
            </button>
            <button
              type="button"
              class="notification-action"
              :disabled="loading || markingAll"
              @click="load"
            >
              Refresh
            </button>
          </div>
        </header>
        <p
          class="sr-only"
          role="status"
        >
          {{ markAllStatus }}
        </p>
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
          class="px-4 py-10 text-center text-sm text-muted"
        >
          No notifications. You’re all caught up.
        </p>
        <button
          v-for="item in items"
          :key="item.id"
          type="button"
          class="notification-row block w-full px-4 py-4 text-left transition-colors"
          :class="item.read ? 'read' : 'unread'"
          :disabled="reading !== null || markingAll"
          :aria-label="
            item.read
              ? item.message
              : `${item.message}. Unread notification. Activate to mark as read.`
          "
          @click="onClick(item.id)"
        >
          <span class="flex items-start gap-2">
            <span
              v-if="!item.read"
              aria-hidden="true"
              class="notification-dot"
            />
            <span
              class="block min-w-0 break-words text-sm"
              :class="item.read ? 'text-body' : 'font-semibold text-heading'"
              >{{ item.message }}</span
            >
          </span>
          <span
            class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted"
          >
            <time :datetime="item.created_at">{{
              formatTime(item.created_at)
            }}</time>
            <span aria-hidden="true">·</span>
            <span :class="{ 'font-medium text-accent-text': !item.read }">{{
              item.read ? 'Read' : 'Unread · Mark as read'
            }}</span>
          </span>
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
  background-color: var(--surface-raised);
  border-color: var(--border-subtle);
}
.notification-header {
  border-bottom: 1px solid var(--border-subtle);
}
.notification-action {
  min-height: 2rem;
  border-radius: 0.5rem;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-muted);
}
.notification-action:hover:not(:disabled) {
  background-color: var(--surface-muted);
  color: var(--text-heading);
}
.notification-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.notification-row {
  border-bottom: 1px solid var(--border-subtle);
  border-left: 2px solid transparent;
}
.notification-row.read {
  background-color: transparent;
}
.notification-row.unread {
  background-color: var(--accent-soft-bg);
  border-left-color: var(--accent-text);
}
.notification-row:hover:not(:disabled) {
  background-color: var(--surface-muted);
}
.notification-row:disabled {
  cursor: default;
  opacity: 1;
}
.notification-dot {
  width: 0.5rem;
  height: 0.5rem;
  margin-top: 0.375rem;
  flex-shrink: 0;
  border-radius: 9999px;
  background-color: var(--accent-text);
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
