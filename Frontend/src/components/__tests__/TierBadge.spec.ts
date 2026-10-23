import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import TierBadge, { formatXAF } from '../TierBadge.vue'

describe('TierBadge component and helpers', () => {
  it('formats amounts in XAF', () => {
    expect(formatXAF(5000)).toBe('5,000 XAF')
    expect(formatXAF(1000)).toBe('1,000 XAF')
    expect(formatXAF(null)).toBe('')
    expect(formatXAF(undefined)).toBe('')
  })

  it('renders Priority 3 with super-fast badge class and bolt icon', () => {
    const wrapper = mount(TierBadge, {
      props: {
        name: 'Super Fast Mode',
        priority: 3,
        turnaroundDays: 3,
        feeAmount: 5000
      }
    })
    expect(wrapper.classes()).toContain('badge-tier-super-fast')
    expect(wrapper.text()).toContain('⚡')
    expect(wrapper.text()).toContain('Super Fast Mode')
    expect(wrapper.attributes('title')).toBe('5,000 XAF · ~3 days')
  })

  it('renders Priority 2 with fast badge class', () => {
    const wrapper = mount(TierBadge, {
      props: {
        name: 'Fast Mode',
        priority: 2,
        turnaroundDays: 7,
        feeAmount: 2500
      }
    })
    expect(wrapper.classes()).toContain('badge-tier-fast')
    expect(wrapper.text()).toContain('Fast Mode')
    expect(wrapper.attributes('title')).toBe('2,500 XAF · ~7 days')
  })

  it('renders Priority 1 with normal badge class', () => {
    const wrapper = mount(TierBadge, {
      props: {
        name: 'Normal Mode',
        priority: 1,
        turnaroundDays: 14,
        feeAmount: 1000
      }
    })
    expect(wrapper.classes()).toContain('badge-tier-normal')
    expect(wrapper.text()).toContain('Normal Mode')
    expect(wrapper.attributes('title')).toBe('1,000 XAF · ~14 days')
  })
})
