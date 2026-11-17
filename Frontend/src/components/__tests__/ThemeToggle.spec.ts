import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ThemeToggle from '../ThemeToggle.vue'
import { useTheme } from '../../composables/useTheme'

describe('ThemeToggle component', () => {
  beforeEach(() => {
    localStorage.clear()
    document.documentElement.className = ''
    document.documentElement.style.colorScheme = ''

    vi.spyOn(window, 'matchMedia').mockImplementation((query: string) => {
      return {
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn()
      } as unknown as MediaQueryList
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
    localStorage.clear()
  })

  it('renders the theme toggle button with accessibility attributes', () => {
    const wrapper = mount(ThemeToggle)
    const button = wrapper.find('button.icon-button')

    expect(button.exists()).toBe(true)
    expect(button.attributes('aria-haspopup')).toBe('menu')
    expect(button.attributes('aria-expanded')).toBe('false')
    expect(button.attributes('aria-label')).toContain('Theme:')
  })

  it('opens menu on click and lists Light, Dark, and System options', async () => {
    const wrapper = mount(ThemeToggle)
    const button = wrapper.find('button.icon-button')

    await button.trigger('click')
    await nextTick()

    expect(button.attributes('aria-expanded')).toBe('true')
    const menu = wrapper.find('[role="menu"]')
    expect(menu.exists()).toBe(true)

    const items = wrapper.findAll('[role="menuitemradio"]')
    expect(items).toHaveLength(3)
    expect(items[0]!.text()).toContain('Light')
    expect(items[1]!.text()).toContain('Dark')
    expect(items[2]!.text()).toContain('System')
  })

  it('selects Dark theme on click, updates theme state, and closes menu', async () => {
    const wrapper = mount(ThemeToggle)
    await wrapper.find('button.icon-button').trigger('click')
    await nextTick()

    const items = wrapper.findAll('[role="menuitemradio"]')
    // Option 1 is Dark
    await items[1]!.trigger('click')
    await nextTick()

    const { theme } = useTheme()
    expect(theme.value).toBe('dark')
    expect(wrapper.find('[role="menu"]').exists()).toBe(false)
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('closes menu when Escape is pressed', async () => {
    const wrapper = mount(ThemeToggle)
    await wrapper.find('button.icon-button').trigger('click')
    await nextTick()

    expect(wrapper.find('[role="menu"]').exists()).toBe(true)

    await wrapper.find('[role="menu"]').trigger('keydown', { key: 'Escape' })
    await nextTick()

    expect(wrapper.find('[role="menu"]').exists()).toBe(false)
  })
})
