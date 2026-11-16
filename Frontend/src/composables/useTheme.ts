import { computed, ref, watch } from 'vue'

export type ThemePreference = 'light' | 'dark' | 'system'
export type ResolvedTheme = 'light' | 'dark'

export const THEME_STORAGE_KEY = 'campusdesk_theme'

const theme = ref<ThemePreference>('system')
const systemPrefersDark = ref(false)
let initialized = false

function getSafeStorage(key: string): string | null {
  try {
    return typeof localStorage !== 'undefined' ? localStorage.getItem(key) : null
  } catch {
    return null
  }
}

function setSafeStorage(key: string, value: string): void {
  try {
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(key, value)
    }
  } catch {
    // Ignore storage errors in private browsing / sandbox
  }
}

export function applyThemeToDom(isDark: boolean): void {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  if (isDark) {
    root.classList.add('dark')
    root.style.colorScheme = 'dark'
  } else {
    root.classList.remove('dark')
    root.style.colorScheme = 'light'
  }
  const meta = document.getElementById('theme-color-meta')
  if (meta) {
    meta.setAttribute('content', isDark ? '#0b1120' : '#f8fafc')
  }
}

export function _resetThemeForTesting(): void {
  initialized = false
  theme.value = 'system'
  systemPrefersDark.value = false
}

export function initTheme(): void {
  if (initialized) return
  initialized = true

  const stored = getSafeStorage(THEME_STORAGE_KEY)
  if (stored === 'light' || stored === 'dark' || stored === 'system') {
    theme.value = stored
  } else {
    theme.value = 'system'
  }

  if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
    const mq = window.matchMedia('(prefers-color-scheme: dark)')
    systemPrefersDark.value = mq.matches
    const handler = (e: MediaQueryListEvent | MediaQueryList) => {
      systemPrefersDark.value = e.matches
    }
    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', handler)
    } else if (typeof (mq as any).addListener === 'function') {
      ;(mq as any).addListener(handler)
    }
  }

  // Initial DOM synchronization
  const isDark =
    theme.value === 'dark' ||
    (theme.value === 'system' && systemPrefersDark.value)
  applyThemeToDom(isDark)
}

export function useTheme() {
  initTheme()

  const resolvedTheme = computed<ResolvedTheme>(() => {
    if (theme.value === 'system') {
      return systemPrefersDark.value ? 'dark' : 'light'
    }
    return theme.value
  })

  function setTheme(next: ThemePreference) {
    theme.value = next
    setSafeStorage(THEME_STORAGE_KEY, next)
    if (next === 'system' && typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
      systemPrefersDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches
    }
    applyThemeToDom(resolvedTheme.value === 'dark')
  }

  watch(
    resolvedTheme,
    (val) => {
      applyThemeToDom(val === 'dark')
    },
    { immediate: true }
  )

  return {
    theme,
    resolvedTheme,
    setTheme
  }
}
