<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import AppIcon from './ui/AppIcon.vue'
import { type ThemePreference, useTheme } from '../composables/useTheme'

const { theme, resolvedTheme, setTheme } = useTheme()

const isOpen = ref(false)
const containerRef = ref<HTMLElement | null>(null)
const triggerRef = ref<HTMLButtonElement | null>(null)
const itemRefs = ref<HTMLButtonElement[]>([])

const options: { id: ThemePreference; label: string; icon: 'sun' | 'moon' | 'monitor' }[] = [
  { id: 'light', label: 'Light', icon: 'sun' },
  { id: 'dark', label: 'Dark', icon: 'moon' },
  { id: 'system', label: 'System', icon: 'monitor' }
]

const currentIcon = computed(() => {
  if (theme.value === 'system') return 'monitor'
  return theme.value === 'dark' ? 'moon' : 'sun'
})

const activeLabel = computed(() => {
  if (theme.value === 'system') {
    return `System (${resolvedTheme.value})`
  }
  return theme.value === 'dark' ? 'Dark' : 'Light'
})

function toggleMenu() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    void nextTick(() => {
      // Focus currently selected item or first item
      const activeIdx = options.findIndex((opt) => opt.id === theme.value)
      const target = itemRefs.value[activeIdx >= 0 ? activeIdx : 0]
      target?.focus()
    })
  }
}

function selectTheme(val: ThemePreference) {
  setTheme(val)
  isOpen.value = false
  triggerRef.value?.focus()
}

function onDocumentClick(e: MouseEvent) {
  if (!isOpen.value) return
  if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
    isOpen.value = false
  }
}

function onKeydown(e: KeyboardEvent) {
  if (!isOpen.value) {
    if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
      e.preventDefault()
      toggleMenu()
    }
    return
  }

  if (e.key === 'Escape') {
    e.preventDefault()
    isOpen.value = false
    triggerRef.value?.focus()
    return
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    const activeEl = document.activeElement
    const currentIdx = itemRefs.value.findIndex((el) => el === activeEl)
    const nextIdx = (currentIdx + 1) % itemRefs.value.length
    itemRefs.value[nextIdx]?.focus()
    return
  }

  if (e.key === 'ArrowUp') {
    e.preventDefault()
    const activeEl = document.activeElement
    const currentIdx = itemRefs.value.findIndex((el) => el === activeEl)
    const prevIdx = (currentIdx - 1 + itemRefs.value.length) % itemRefs.value.length
    itemRefs.value[prevIdx]?.focus()
    return
  }

  if (e.key === 'Tab') {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
})
</script>

<template>
  <div
    ref="containerRef"
    class="relative inline-block text-left"
    @keydown="onKeydown"
  >
    <button
      ref="triggerRef"
      type="button"
      class="icon-button"
      :aria-label="`Theme: currently ${activeLabel}. Click to change theme.`"
      :title="`Theme: ${activeLabel}`"
      :aria-expanded="isOpen"
      aria-haspopup="menu"
      @click="toggleMenu"
    >
      <AppIcon :name="currentIcon" />
    </button>

    <div
      v-if="isOpen"
      role="menu"
      aria-label="Select theme"
      class="absolute right-0 z-50 mt-2 w-44 origin-top-right rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg backdrop-blur focus:outline-none dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="px-2.5 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
        Theme
      </div>
      <button
        v-for="(opt, idx) in options"
        :key="opt.id"
        :ref="(el) => { if (el) itemRefs[idx] = el as HTMLButtonElement }"
        type="button"
        role="menuitemradio"
        :aria-checked="theme === opt.id"
        class="flex w-full items-center justify-between gap-2.5 rounded-lg px-2.5 py-2 text-xs font-medium transition-colors"
        :class="[
          theme === opt.id
            ? 'bg-sky-50 font-semibold text-sky-800 dark:bg-sky-950/60 dark:text-sky-300'
            : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
        ]"
        @click="selectTheme(opt.id)"
      >
        <span class="flex items-center gap-2">
          <AppIcon :name="opt.icon" />
          {{ opt.label }}
        </span>
        <span
          v-if="theme === opt.id"
          class="text-sky-600 dark:text-sky-400"
        >
          <AppIcon name="check" />
        </span>
      </button>
    </div>
  </div>
</template>
