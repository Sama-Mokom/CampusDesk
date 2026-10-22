import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import NotificationBell from '../NotificationBell.vue'
import * as notificationsService from '@/services/notifications'
import * as requestsService from '@/services/requests'
import { realtimeManager, type RequestInvalidationEvent } from '@/services/realtime'
import { useStudentRequests } from '@/composables/useStudentRequests'

vi.mock('@/services/notifications', () => ({
  fetchNotifications: vi.fn(),
  markNotificationRead: vi.fn(),
}))

vi.mock('@/services/requests', () => ({
  fetchRequests: vi.fn(),
  fetchRequestById: vi.fn(),
}))

describe('Frontend Realtime Integration', () => {
  let activeWrapper: any = null

  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    localStorage.setItem('token', 'fake-jwt-token')
    localStorage.setItem(
      'user',
      JSON.stringify({
        id: 42,
        name: 'Student User',
        email: 'student@example.test',
        role: 'student',
      })
    )
  })

  afterEach(() => {
    if (activeWrapper) {
      activeWrapper.unmount()
      activeWrapper = null
    }
  })

  it('NotificationBell refetches notifications when realtime invalidation arrives', async () => {
    vi.useFakeTimers()
    const mockNotifications = [
      {
        id: 1,
        user_id: 42,
        type: 'request_status',
        message: 'Your request was updated',
        read: false,
        read_at: null,
        created_at: '2026-10-16T12:00:00Z',
      },
    ]

    vi.mocked(notificationsService.fetchNotifications).mockResolvedValue(mockNotifications)

    activeWrapper = mount(NotificationBell)
    await nextTick()
    await Promise.resolve()

    expect(notificationsService.fetchNotifications).toHaveBeenCalledTimes(1)

    // Trigger realtime coalesced invalidation
    const event: RequestInvalidationEvent = {
      event_id: 'realtime-notif-uuid-1',
      event_name: 'request.status_changed',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 1,
      occurred_at: '2026-10-16T12:05:00Z',
      payload: { request_id: 101 },
    }

    ;(realtimeManager as any).handleIncomingEvent(event)

    // Advance beyond debounce window
    await vi.advanceTimersByTimeAsync(60)

    // NotificationBell should have refetched
    expect(notificationsService.fetchNotifications).toHaveBeenCalledTimes(2)
    vi.useRealTimers()
  })

  it('useStudentRequests refetches request list when realtime invalidation arrives', async () => {
    vi.useFakeTimers()
    vi.mocked(requestsService.fetchRequests).mockResolvedValue([
      {
        id: 101,
        request_type: 'Transcript',
        description: 'Test',
        status: 'pending',
        is_reopened: false,
        created_at: '2026-10-16T12:00:00Z',
        attachments: [],
        stages: [],
        status_history: [],
      },
    ])

    const { loadRequests, loaded } = useStudentRequests()
    await loadRequests()

    expect(requestsService.fetchRequests).toHaveBeenCalledTimes(1)
    expect(loaded.value).toBe(true)

    // Trigger realtime invalidation
    const event: RequestInvalidationEvent = {
      event_id: 'realtime-req-uuid-1',
      event_name: 'request.status_changed',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 2,
      occurred_at: '2026-10-16T12:06:00Z',
      payload: { request_id: 101 },
    }

    ;(realtimeManager as any).handleIncomingEvent(event)

    // Advance beyond debounce window
    await vi.advanceTimersByTimeAsync(60)

    expect(requestsService.fetchRequests).toHaveBeenCalledTimes(2)
    vi.useRealTimers()
  })

  it('reconnect triggers refetch of NotificationBell and loaded views', async () => {
    vi.mocked(notificationsService.fetchNotifications).mockResolvedValue([])

    activeWrapper = mount(NotificationBell)
    await nextTick()
    await Promise.resolve()

    expect(notificationsService.fetchNotifications).toHaveBeenCalledTimes(1)

    // Trigger reconnect
    ;(realtimeManager as any).handleReconnect()
    await nextTick()
    await Promise.resolve()

    expect(notificationsService.fetchNotifications).toHaveBeenCalledTimes(2)
  })
})

