import { describe, it, expect, vi, beforeEach } from 'vitest'
import { RealtimeManager, type RequestInvalidationEvent } from '../realtime'
import type { User } from '@/types'

function makeUser(id = 1, role: 'student' | 'staff' = 'student', departments: number[] = []): User {
  return {
    id,
    name: 'Test User',
    email: 'user@example.test',
    password: '',
    role,
    created_at: '2026-10-16T12:00:00Z',
    staff_profile: role === 'staff' ? {
      staff_id: 'S123',
      admin_level: null,
      departments: departments.map((dId) => ({
        id: dId,
        name: `Dept ${dId}`,
        code: `D${dId}`,
        is_primary: dId === departments[0],
      })),
    } : undefined,
  }
}

describe('RealtimeManager', () => {
  let mockEcho: any
  let channelListeners: Record<string, Record<string, (payload: any) => void>>

  beforeEach(() => {
    channelListeners = {}
    mockEcho = {
      private: vi.fn((channelName: string) => {
        channelListeners[channelName] = channelListeners[channelName] || {}
        return {
          listen: vi.fn((eventName: string, handler: (payload: any) => void) => {
            channelListeners[channelName]![eventName] = handler
            return mockEcho.private(channelName)
          }),
        }
      }),
      leave: vi.fn(),
      disconnect: vi.fn(),
      connector: {
        pusher: {
          connection: {
            bind: vi.fn(),
          },
        },
      },
    }
  })

  it('subscribes student user to users.{id} channel', () => {
    const manager = new RealtimeManager(() => mockEcho, { debounceMs: 0 })
    const user = makeUser(42, 'student')

    manager.syncUser(user)

    expect(mockEcho.private).toHaveBeenCalledWith('users.42')
    expect(manager.getSubscribedChannels()).toEqual(['users.42'])
  })

  it('subscribes staff user to users.{id} and all assigned departments.{id} channels', () => {
    const manager = new RealtimeManager(() => mockEcho, { debounceMs: 0 })
    const user = makeUser(7, 'staff', [10, 20])

    manager.syncUser(user)

    expect(mockEcho.private).toHaveBeenCalledWith('users.7')
    expect(mockEcho.private).toHaveBeenCalledWith('departments.10')
    expect(mockEcho.private).toHaveBeenCalledWith('departments.20')
    expect(manager.getSubscribedChannels()).toEqual(['users.7', 'departments.10', 'departments.20'])
  })

  it('subscribes staff user when profile is in camelCase staffProfile format', () => {
    const manager = new RealtimeManager(() => mockEcho, { debounceMs: 0 })
    const user: any = {
      id: 7,
      role: 'staff',
      staffProfile: {
        departments: [{ id: 15, name: 'Dept 15' }],
      },
    }

    manager.syncUser(user)

    expect(mockEcho.private).toHaveBeenCalledWith('users.7')
    expect(mockEcho.private).toHaveBeenCalledWith('departments.15')
    expect(manager.getSubscribedChannels()).toEqual(['users.7', 'departments.15'])
  })

  it('unsubscribes all channels and disconnects when user is null', () => {
    const manager = new RealtimeManager(() => mockEcho, { debounceMs: 0 })
    const user = makeUser(42, 'student')

    manager.syncUser(user)
    manager.syncUser(null)

    expect(mockEcho.leave).toHaveBeenCalledWith('users.42')
    expect(mockEcho.disconnect).toHaveBeenCalled()
    expect(manager.getSubscribedChannels()).toEqual([])
  })

  it('dispatches invalidation on valid incoming request.event', () => {
    const manager = new RealtimeManager(() => mockEcho, { debounceMs: 0 })
    const user = makeUser(42, 'student')
    manager.syncUser(user)

    const callback = vi.fn()
    manager.onInvalidate(callback)

    const event: RequestInvalidationEvent = {
      event_id: 'uuid-1',
      event_name: 'request.created',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 1,
      occurred_at: '2026-10-16T12:00:00Z',
      payload: { request_id: 101 },
    }

    const handler = channelListeners['users.42']?.['.request.event']
    expect(handler).toBeDefined()
    handler!(event)

    expect(callback).toHaveBeenCalledWith(event)
  })

  it('deduplicates events by event_id across channels and deliveries', () => {
    const manager = new RealtimeManager(() => mockEcho, { debounceMs: 0 })
    const user = makeUser(7, 'staff', [10])
    manager.syncUser(user)

    const callback = vi.fn()
    manager.onInvalidate(callback)

    const event: RequestInvalidationEvent = {
      event_id: 'uuid-duplicate-1',
      event_name: 'request.status_changed',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 2,
      occurred_at: '2026-10-16T12:00:00Z',
      payload: { request_id: 101 },
    }

    const userHandler = channelListeners['users.7']?.['.request.event']
    const deptHandler = channelListeners['departments.10']?.['.request.event']

    userHandler!(event)
    deptHandler!(event) // duplicate received via department channel

    expect(callback).toHaveBeenCalledTimes(1)
  })

  it('ignores stale events with aggregate_sequence <= high-watermark for that request', () => {
    const manager = new RealtimeManager(() => mockEcho, { debounceMs: 0 })
    const user = makeUser(42, 'student')
    manager.syncUser(user)

    const callback = vi.fn()
    manager.onInvalidate(callback)

    const handler = channelListeners['users.42']['.request.event']

    // Event sequence 3
    handler({
      event_id: 'uuid-seq-3',
      event_name: 'request.status_changed',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 3,
      occurred_at: '2026-10-16T12:00:00Z',
      payload: { request_id: 101 },
    })

    // Stale Event sequence 2 arrives late
    handler({
      event_id: 'uuid-seq-2',
      event_name: 'request.status_changed',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 2,
      occurred_at: '2026-10-16T11:59:00Z',
      payload: { request_id: 101 },
    })

    // Stale Event sequence 3 arrives with new UUID (e.g. re-delivery)
    handler({
      event_id: 'uuid-seq-3-dup',
      event_name: 'request.status_changed',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 3,
      occurred_at: '2026-10-16T12:00:00Z',
      payload: { request_id: 101 },
    })

    expect(callback).toHaveBeenCalledTimes(1)
  })

  it('tracks sequences independently per request_id', () => {
    const manager = new RealtimeManager(() => mockEcho, { debounceMs: 0 })
    const user = makeUser(42, 'student')
    manager.syncUser(user)

    const callback = vi.fn()
    manager.onInvalidate(callback)
    const handler = channelListeners['users.42']['.request.event']

    // Request 101 at seq 5
    handler({
      event_id: 'uuid-a',
      event_name: 'request.status_changed',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 5,
      occurred_at: '2026-10-16T12:00:00Z',
      payload: { request_id: 101 },
    })

    // Request 202 at seq 1 (should NOT be blocked by 101's sequence)
    handler({
      event_id: 'uuid-b',
      event_name: 'request.created',
      schema_version: 1,
      request_id: 202,
      aggregate_sequence: 1,
      occurred_at: '2026-10-16T12:00:00Z',
      payload: { request_id: 202 },
    })

    expect(callback).toHaveBeenCalledTimes(2)
  })

  it('triggers reconnect callbacks when Echo reconnects', () => {
    let reconnectHandler: (() => void) | undefined
    mockEcho.connector.pusher.connection.bind = vi.fn((event: string, fn: any) => {
      if (event === 'connected') {
        reconnectHandler = fn
      }
    })

    const manager = new RealtimeManager(() => mockEcho, { debounceMs: 0 })
    const user = makeUser(42, 'student')
    manager.syncUser(user)

    const onReconnect = vi.fn()
    manager.onReconnect(onReconnect)

    expect(reconnectHandler).toBeDefined()
    reconnectHandler!()

    expect(onReconnect).toHaveBeenCalledTimes(1)
  })

  it('coalesces rapid bursts of invalidations when debounce is configured', async () => {
    vi.useFakeTimers()
    const manager = new RealtimeManager(() => mockEcho, { debounceMs: 50 })
    const user = makeUser(42, 'student')
    manager.syncUser(user)

    const callback = vi.fn()
    manager.onCoalescedInvalidate(callback)
    const handler = channelListeners['users.42']['.request.event']

    handler({
      event_id: 'uuid-b1',
      event_name: 'request.created',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 1,
      occurred_at: '2026-10-16T12:00:00Z',
      payload: { request_id: 101 },
    })

    handler({
      event_id: 'uuid-b2',
      event_name: 'request.status_changed',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 2,
      occurred_at: '2026-10-16T12:00:01Z',
      payload: { request_id: 101 },
    })

    // Before debounce timer fires
    expect(callback).not.toHaveBeenCalled()

    vi.advanceTimersByTime(50)

    // After debounce timer fires
    expect(callback).toHaveBeenCalledTimes(1)
    vi.useRealTimers()
  })
})
