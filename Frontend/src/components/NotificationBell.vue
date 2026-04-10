<template>
  <div class="relative">
    <button
      type="button"
      class="relative p-2 hover:bg-neutral-100 text-primary rounded-lg transition-colors"
      aria-label="Notifications"
      @click="open = !open"
    >
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
        />
      </svg>
      <span
        v-if="unreadCount > 0"
        class="absolute top-0 right-0 min-w-[1.1rem] h-4 px-1 flex items-center justify-center text-[10px] font-bold text-white bg-red-500 rounded-full"
      >
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>
    <div
      v-if="open"
      class="absolute right-0 mt-1 w-80 max-h-96 overflow-y-auto bg-white border border-neutral-200 rounded-lg shadow-lg z-50"
    >
      <div class="p-2 border-b border-neutral-100 text-sm font-semibold text-primary">Notifications</div>
      <div v-if="items.length === 0" class="p-4 text-sm text-neutral-500 text-center">No notifications</div>
      <button
        v-for="n in items"
        :key="n.id"
        type="button"
        class="w-full text-left px-3 py-2 border-b border-neutral-50 hover:bg-neutral-50 text-sm"
        :class="n.read ? 'opacity-70' : 'bg-primary/5'"
        @click="onClick(n.id)"
      >
        <p class="text-foreground">{{ n.message }}</p>
        <p class="text-xs text-neutral-500 mt-1">{{ formatTime(n.created_at) }}</p>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useMockData } from '@/composables/useMockData'
import { format } from 'date-fns'

const { sessionNotifications, unreadNotificationCount, markNotificationRead } = useMockData()

const open = ref(false)
const items = computed(() => sessionNotifications.value)
const unreadCount = computed(() => unreadNotificationCount.value)

function formatTime(iso: string) {
  try {
    return format(new Date(iso), 'MMM d, yyyy · HH:mm')
  } catch {
    return iso
  }
}

function onClick(id: number) {
  markNotificationRead(id)
}
</script>
