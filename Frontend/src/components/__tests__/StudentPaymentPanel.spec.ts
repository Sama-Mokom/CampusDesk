import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import StudentPaymentPanel from '../student/StudentPaymentPanel.vue'
import type { Payment, Request as DocumentRequest, RequestTypeTier } from '@/types'

vi.mock('@/services/requests', () => ({
  initiatePayment: vi.fn(),
  verifyPayment: vi.fn(),
  switchRequestTier: vi.fn(),
  reopenRequest: vi.fn()
}))

import {
  initiatePayment,
  reopenRequest,
  switchRequestTier,
  verifyPayment
} from '@/services/requests'

const sampleTiers: RequestTypeTier[] = [
  {
    id: 1,
    request_type_id: 10,
    code: 'NORMAL',
    name: 'Normal Mode',
    priority: 1,
    turnaround_days: 14,
    fee_amount: 1000,
    currency: 'XAF',
    is_active: true
  },
  {
    id: 2,
    request_type_id: 10,
    code: 'FAST',
    name: 'Fast Mode',
    priority: 2,
    turnaround_days: 7,
    fee_amount: 2500,
    currency: 'XAF',
    is_active: true
  },
  {
    id: 3,
    request_type_id: 10,
    code: 'SUPER_FAST',
    name: 'Super Fast Mode',
    priority: 3,
    turnaround_days: 3,
    fee_amount: 5000,
    currency: 'XAF',
    is_active: true
  }
]

const baseRequest: DocumentRequest = {
  id: 42,
  request_type: 'Transcript',
  request_type_id: 10,
  request_type_tier_id: 1,
  tier_name: 'Normal Mode',
  tier_priority: 1,
  turnaround_days: 14,
  fee_amount: 1000,
  amount_paid: 0,
  amount_due: 1000,
  description: 'Transcript request for scholarship',
  status: 'awaiting_payment',
  is_reopened: false,
  created_at: '2026-10-23T10:00:00Z',
  attachments: [],
  status_history: [],
  stages: [],
  payments: []
}

describe('StudentPaymentPanel.vue', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('renders amount due, tier badge, and fee instructions', () => {
    const wrapper = mount(StudentPaymentPanel, {
      props: {
        request: baseRequest,
        availableTiers: sampleTiers
      }
    })

    expect(wrapper.text()).toContain('Processing Fee Payment Required')
    expect(wrapper.text()).toContain('1,000 XAF')
    expect(wrapper.text()).toContain('Normal Mode')
  })

  it('initiates hosted checkout and renders redirect button', async () => {
    const initiatedPayment: Payment = {
      id: 101,
      request_id: 42,
      external_id: 'PAY-ULID-101',
      provider: 'fake',
      checkout_mode: 'hosted',
      amount: 1000,
      currency: 'XAF',
      status: 'pending',
      checkout_url: 'https://fake-momo.test/checkout/PAY-ULID-101',
      created_at: '2026-10-23T10:05:00Z'
    }

    vi.mocked(initiatePayment).mockResolvedValue(initiatedPayment)

    const wrapper = mount(StudentPaymentPanel, {
      props: {
        request: baseRequest,
        availableTiers: sampleTiers
      }
    })

    const proceedBtn = wrapper.findAll('button').find((b) => b.text().includes('Proceed to checkout'))
    expect(proceedBtn).toBeDefined()
    await proceedBtn!.trigger('click')
    await flushPromises()

    expect(initiatePayment).toHaveBeenCalledWith(42, {
      checkout_mode: 'hosted'
    })

    expect(wrapper.text()).toContain('Payment checkout link is ready')
    expect(wrapper.find('a[href="https://fake-momo.test/checkout/PAY-ULID-101"]').exists()).toBe(true)
  })

  it('switches to direct USSD push tab, validates phone and initiates USSD prompt', async () => {
    const initiatedPayment: Payment = {
      id: 102,
      request_id: 42,
      external_id: 'PAY-ULID-102',
      provider: 'fake',
      checkout_mode: 'direct_push',
      payment_method: 'mtn_momo',
      payer_phone: '237670000001',
      amount: 1000,
      currency: 'XAF',
      status: 'pending',
      created_at: '2026-10-23T10:05:00Z'
    }

    vi.mocked(initiatePayment).mockResolvedValue(initiatedPayment)

    const wrapper = mount(StudentPaymentPanel, {
      props: {
        request: baseRequest,
        availableTiers: sampleTiers
      }
    })

    const ussdTab = wrapper.findAll('button').find((b) => b.text().includes('Direct Mobile Money Push'))
    expect(ussdTab).toBeDefined()
    await ussdTab!.trigger('click')

    const phoneInput = wrapper.find('input[type="tel"]')
    expect(phoneInput.exists()).toBe(true)
    await phoneInput.setValue('237670000001')

    const pushBtn = wrapper.findAll('button').find((b) => b.text().includes('Send USSD push to phone'))
    expect(pushBtn).toBeDefined()
    await pushBtn!.trigger('click')
    await flushPromises()

    expect(initiatePayment).toHaveBeenCalledWith(42, {
      checkout_mode: 'direct_push',
      payment_method: 'mtn_momo',
      phone: '237670000001'
    })

    expect(wrapper.text()).toContain('USSD Prompt Sent')
    expect(wrapper.text()).toContain('237670000001')
  })

  it('supports simulation tools in fake mode (simulate successful outcome)', async () => {
    const pendingPayment: Payment = {
      id: 103,
      request_id: 42,
      external_id: 'PAY-ULID-103',
      provider: 'fake',
      checkout_mode: 'hosted',
      amount: 1000,
      currency: 'XAF',
      status: 'pending',
      created_at: '2026-10-23T10:05:00Z'
    }

    const paidRequest: DocumentRequest = {
      ...baseRequest,
      status: 'pending',
      amount_paid: 1000,
      amount_due: 0,
      paid_at: '2026-10-23T10:06:00Z'
    }

    vi.mocked(verifyPayment).mockResolvedValue({
      payment: { ...pendingPayment, status: 'successful' },
      request: paidRequest
    })

    const wrapper = mount(StudentPaymentPanel, {
      props: {
        request: {
          ...baseRequest,
          latest_payment: pendingPayment
        },
        availableTiers: sampleTiers
      }
    })

    const simulateSuccessBtn = wrapper.findAll('button').find((b) => b.text().includes('Simulate Success'))
    expect(simulateSuccessBtn).toBeDefined()
    await simulateSuccessBtn!.trigger('click')
    await flushPromises()

    expect(verifyPayment).toHaveBeenCalledWith(42, 103, 'successful')
    expect(wrapper.emitted('updated')).toBeTruthy()
    expect(wrapper.emitted('updated')![0]).toEqual([paidRequest])
  })

  it('supports tier switching before payment', async () => {
    const updatedRequest: DocumentRequest = {
      ...baseRequest,
      request_type_tier_id: 2,
      tier_name: 'Fast Mode',
      tier_priority: 2,
      turnaround_days: 7,
      fee_amount: 2500,
      amount_due: 2500
    }

    vi.mocked(switchRequestTier).mockResolvedValue(updatedRequest)

    const wrapper = mount(StudentPaymentPanel, {
      props: {
        request: baseRequest,
        availableTiers: sampleTiers
      }
    })

    const toggleBtn = wrapper.findAll('button').find((b) => b.text().includes('Change processing speed / tier'))
    expect(toggleBtn).toBeDefined()
    await toggleBtn!.trigger('click')

    const fastTierBtn = wrapper.findAll('button').find((b) => b.text().includes('Fast Mode'))
    expect(fastTierBtn).toBeDefined()
    await fastTierBtn!.trigger('click')
    await flushPromises()

    expect(switchRequestTier).toHaveBeenCalledWith(42, 2)
    expect(wrapper.emitted('updated')).toBeTruthy()
    expect(wrapper.emitted('updated')![0]).toEqual([updatedRequest])
  })

  it('renders upgrade top-up notice and allows cancelling upgrade', async () => {
    const reopenWithUpgradeRequest: DocumentRequest = {
      ...baseRequest,
      is_reopened: true,
      amount_paid: 1000,
      fee_amount: 1000,
      pending_upgrade_tier_id: 3,
      pending_upgrade_tier: {
        id: 3,
        request_type_id: 10,
        code: 'SUPER_FAST',
        name: 'Super Fast Mode',
        priority: 3,
        turnaround_days: 3,
        fee_amount: 5000,
        currency: 'XAF',
        is_active: true
      },
      amount_due: 4000
    }

    const cancelledUpgradeRequest: DocumentRequest = {
      ...baseRequest,
      is_reopened: true,
      status: 'pending',
      amount_paid: 1000,
      fee_amount: 1000,
      amount_due: 0,
      pending_upgrade_tier_id: null,
      pending_upgrade_tier: null
    }

    vi.mocked(reopenRequest).mockResolvedValue(cancelledUpgradeRequest)

    const wrapper = mount(StudentPaymentPanel, {
      props: {
        request: reopenWithUpgradeRequest,
        availableTiers: sampleTiers
      }
    })

    expect(wrapper.text()).toContain('Tier Upgrade Top-Up')
    expect(wrapper.text()).toContain('4,000 XAF')

    const cancelBtn = wrapper.findAll('button').find((b) => b.text().includes('Cancel upgrade'))
    expect(cancelBtn).toBeDefined()
    await cancelBtn!.trigger('click')
    await flushPromises()

    expect(reopenRequest).toHaveBeenCalledWith(42, {
      cancel_upgrade: true
    })
    expect(wrapper.emitted('updated')).toBeTruthy()
    expect(wrapper.emitted('updated')![0]).toEqual([cancelledUpgradeRequest])
  })
})
