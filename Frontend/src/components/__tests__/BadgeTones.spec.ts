import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AppBadge from '../ui/AppBadge.vue'
import AppCallout from '../ui/AppCallout.vue'
import StatusBadge from '../StatusBadge.vue'
import type { RequestStatus } from '@/types'

describe('Shared tone components', () => {
  it.each([
    ['neutral', 'badge-neutral'],
    ['success', 'badge-success'],
    ['warning', 'badge-warning'],
    ['info', 'badge-info'],
    ['purple', 'badge-purple'],
    ['danger', 'badge-danger'],
    ['orange', 'badge-orange']
  ] as const)('renders the %s badge tone', (tone, className) => {
    const wrapper = mount(AppBadge, { props: { tone } })
    expect(wrapper.classes()).toContain('badge')
    expect(wrapper.classes()).toContain(className)
    expect(wrapper.get('[aria-hidden="true"]').text()).toBe('')
  })

  it.each([
    ['info', 'callout-info'],
    ['success', 'callout-success'],
    ['warning', 'callout-warning'],
    ['danger', 'callout-danger'],
    ['neutral', 'callout-neutral']
  ] as const)('renders the %s callout tone', (tone, className) => {
    const wrapper = mount(AppCallout, { props: { tone } })
    expect(wrapper.classes()).toContain('callout')
    expect(wrapper.classes()).toContain(className)
    expect(wrapper.attributes('role')).toBe('note')
  })

  it.each<[RequestStatus, string]>([
    ['ready', 'badge-success'],
    ['collected', 'badge-success'],
    ['rejected', 'badge-danger'],
    ['pending', 'badge-warning'],
    ['in_review', 'badge-info'],
    ['forwarded', 'badge-purple']
  ])('maps the %s status to its visible tone', (status, className) => {
    const wrapper = mount(StatusBadge, { props: { kind: 'request', status } })
    expect(wrapper.classes()).toContain(className)
  })
})
