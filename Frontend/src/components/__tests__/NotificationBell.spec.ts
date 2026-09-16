import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const authState = vi.hoisted(() => ({ isAuthenticated: { value: false } }))

vi.mock('../../composables/useAuth', () => ({
  useAuth: () => authState,
}))

vi.mock('../../services/notifications', () => ({
  fetchNotifications: vi.fn().mockResolvedValue([]),
  markNotificationRead: vi.fn(),
}))

import NotificationBell from '../NotificationBell.vue'
import { fetchNotifications } from '../../services/notifications'

describe('NotificationBell', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authState.isAuthenticated.value = false
  })

  it('does not request notifications for a guest', async () => {
    mount(NotificationBell)
    await flushPromises()

    expect(fetchNotifications).not.toHaveBeenCalled()
  })

  it('loads notifications for an authenticated user', async () => {
    authState.isAuthenticated.value = true
    mount(NotificationBell)
    await flushPromises()

    expect(fetchNotifications).toHaveBeenCalledOnce()
  })
})
