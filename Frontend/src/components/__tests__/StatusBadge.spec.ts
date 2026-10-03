import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StatusBadge from '../StatusBadge.vue'
import type { RequestStatus } from '@/types'

describe('Workflow status presentation', () => {
  it.each<[RequestStatus, string]>([
    ['draft', 'Draft'],
    ['pending', 'Pending'],
    ['in_review', 'In review'],
    ['forwarded', 'Forwarded'],
    ['ready', 'Ready'],
    ['collected', 'Collected'],
    ['rejected', 'Rejected']
  ])('labels %s without relying on color', (status, label) => {
    const wrapper = mount(StatusBadge, { props: { kind: 'request', status } })
    expect(wrapper.text()).toBe(label)
    expect(wrapper.get('[aria-hidden="true"]').text()).toBe('')
  })
  it('distinguishes approved workflow stages from ready requests', () => {
    expect(
      mount(StatusBadge, {
        props: { kind: 'stage', status: 'approved' }
      }).text()
    ).toBe('Approved')
  })
})
