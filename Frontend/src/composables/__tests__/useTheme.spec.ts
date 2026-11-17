import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  THEME_STORAGE_KEY,
  _resetThemeForTesting,
  applyThemeToDom,
  useTheme
} from '../useTheme'

describe('useTheme composable', () => {
  let mediaChangeHandler: ((e: any) => void) | null = null
  let mockMatches = false

  beforeEach(() => {
    _resetThemeForTesting()
    localStorage.clear()
    document.documentElement.className = ''
    document.documentElement.style.colorScheme = ''

    let meta = document.getElementById('theme-color-meta')
    if (!meta) {
      meta = document.createElement('meta')
      meta.id = 'theme-color-meta'
      meta.setAttribute('name', 'theme-color')
      document.head.appendChild(meta)
    }

    mockMatches = false
    mediaChangeHandler = null

    vi.spyOn(window, 'matchMedia').mockImplementation((query: string) => {
      return {
        matches: mockMatches,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn((event: string, cb: any) => {
          if (event === 'change') mediaChangeHandler = cb
        }),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn()
      } as unknown as MediaQueryList
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
    localStorage.clear()
    _resetThemeForTesting()
  })

  it('applies dark class and colorScheme to DOM correctly', () => {
    applyThemeToDom(true)
    expect(document.documentElement.classList.contains('dark')).toBe(true)
    expect(document.documentElement.style.colorScheme).toBe('dark')
    expect(
      document.getElementById('theme-color-meta')?.getAttribute('content')
    ).toBe('#020617')

    applyThemeToDom(false)
    expect(document.documentElement.classList.contains('dark')).toBe(false)
    expect(document.documentElement.style.colorScheme).toBe('light')
    expect(
      document.getElementById('theme-color-meta')?.getAttribute('content')
    ).toBe('#f8fafc')
  })

  it('allows switching to dark mode, updates localStorage and DOM', () => {
    const { theme, resolvedTheme, setTheme } = useTheme()
    setTheme('dark')

    expect(theme.value).toBe('dark')
    expect(resolvedTheme.value).toBe('dark')
    expect(localStorage.getItem(THEME_STORAGE_KEY)).toBe('dark')
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('allows switching to light mode, updates localStorage and DOM', () => {
    const { theme, resolvedTheme, setTheme } = useTheme()
    setTheme('light')

    expect(theme.value).toBe('light')
    expect(resolvedTheme.value).toBe('light')
    expect(localStorage.getItem(THEME_STORAGE_KEY)).toBe('light')
    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })

  it('resolves system mode using matchMedia and reacts to changes', () => {
    mockMatches = true
    const { theme, resolvedTheme, setTheme } = useTheme()
    setTheme('system')

    expect(theme.value).toBe('system')
    expect(resolvedTheme.value).toBe('dark')

    // Simulate system preference changing to light
    if (mediaChangeHandler) {
      mediaChangeHandler({ matches: false } as MediaQueryListEvent)
      expect(resolvedTheme.value).toBe('light')
    }
  })
})
