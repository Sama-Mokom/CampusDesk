import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'

const authState = vi.hoisted(() => ({ isAuthenticated: { value: false } }))

vi.mock('../../composables/useAuth', () => ({
  useAuth: () => authState
}))

vi.mock('../../services/notifications', () => ({
  fetchNotifications: vi.fn().mockResolvedValue([]),
  markNotificationRead: vi.fn()
}))

import NotificationBell from '../NotificationBell.vue'
import {
  fetchNotifications,
  markNotificationRead
} from '../../services/notifications'

enableAutoUnmount(afterEach)

describe('NotificationBell', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(fetchNotifications).mockResolvedValue([])
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

  it('reports failed loading and retries rather than showing an empty inbox', async () => {
    authState.isAuthenticated.value = true
    vi.mocked(fetchNotifications).mockRejectedValueOnce(new Error('Offline'))
    const wrapper = mount(NotificationBell)
    await flushPromises()
    await wrapper.get('[aria-label="Notifications"]').trigger('click')
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'could not be loaded'
    )
    expect(wrapper.text()).not.toContain('You’re all caught up')
    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('You’re all caught up')
  })

  it('preserves unread notifications on failure and updates from a successful retry', async () => {
    authState.isAuthenticated.value = true
    const item = {
      id: 4,
      user_id: 1,
      type: 'status',
      message: 'Your request is ready',
      read: false,
      read_at: null,
      created_at: '2026-10-02T10:00:00Z'
    }
    vi.mocked(fetchNotifications).mockResolvedValue([item])
    vi.mocked(markNotificationRead)
      .mockRejectedValueOnce(new Error('Offline'))
      .mockResolvedValueOnce({
        ...item,
        read: true,
        read_at: '2026-10-03T10:00:00Z'
      })
    const wrapper = mount(NotificationBell)
    await flushPromises()
    await wrapper.get('[aria-label="Notifications"]').trigger('click')
    const notification = () =>
      wrapper
        .findAll('button')
        .find((button) => button.text().includes('Your request is ready'))!
    await notification().trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Could not mark')
    expect(notification().text()).toContain('Mark as read')
    await notification().trigger('click')
    await flushPromises()
    expect(markNotificationRead).toHaveBeenLastCalledWith(4)
    expect(notification().text()).toContain('Read')
  })
})
