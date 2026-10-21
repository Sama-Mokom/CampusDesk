import type Echo from 'laravel-echo'
import type { User } from '@/types'
import { createEchoInstance } from './echo'

export interface RequestInvalidationEvent {
  event_id: string
  event_name: string
  schema_version: number
  request_id: number
  aggregate_sequence: number
  occurred_at: string
  payload: {
    request_id: number
    [key: string]: unknown
  }
}

export interface RealtimeManagerOptions {
  debounceMs?: number
  enabled?: boolean
}

export class RealtimeManager {
  private echo: Echo<'reverb'> | null = null
  private subscribedChannels = new Set<string>()
  private seenEventIds = new Set<string>()
  private highWatermarks: Record<number, number> = {}
  private invalidationListeners = new Set<(event: RequestInvalidationEvent) => void>()
  private coalescedListeners = new Set<(events: RequestInvalidationEvent[]) => void>()
  private reconnectListeners = new Set<() => void>()
  private pendingEvents: RequestInvalidationEvent[] = []
  private debounceTimer: ReturnType<typeof setTimeout> | null = null
  private reconnectBound = false

  private readonly debounceMs: number
  private readonly enabled: boolean

  constructor(
    private readonly echoFactory: () => Echo<'reverb'> = createEchoInstance,
    options?: RealtimeManagerOptions
  ) {
    this.debounceMs = options?.debounceMs ?? 50
    this.enabled =
      options?.enabled ??
      (import.meta.env.VITE_REALTIME_ENABLED !== 'false')
  }

  public getSubscribedChannels(): string[] {
    return Array.from(this.subscribedChannels)
  }

  public syncUser(user: User | null): void {
    if (!user || !this.enabled) {
      this.teardown()
      return
    }

    const targetChannels = this.calculateChannelsForUser(user)

    // Unsubscribe from channels no longer needed
    for (const channel of this.subscribedChannels) {
      if (!targetChannels.has(channel)) {
        this.echo?.leave(channel)
        this.subscribedChannels.delete(channel)
      }
    }

    if (targetChannels.size === 0) {
      return
    }

    if (!this.echo) {
      this.echo = this.echoFactory()
      this.bindReconnect()
    }

    // Subscribe to new channels
    for (const channel of targetChannels) {
      if (!this.subscribedChannels.has(channel)) {
        this.echo
          .private(channel)
          .listen('.request.event', (event: RequestInvalidationEvent) => {
            this.handleIncomingEvent(event)
          })
        this.subscribedChannels.add(channel)
      }
    }
  }

  public onInvalidate(listener: (event: RequestInvalidationEvent) => void): () => void {
    this.invalidationListeners.add(listener)
    return () => this.invalidationListeners.delete(listener)
  }

  public onCoalescedInvalidate(
    listener: (events: RequestInvalidationEvent[]) => void
  ): () => void {
    this.coalescedListeners.add(listener)
    return () => this.coalescedListeners.delete(listener)
  }

  public onReconnect(listener: () => void): () => void {
    this.reconnectListeners.add(listener)
    return () => this.reconnectListeners.delete(listener)
  }

  private handleIncomingEvent(event: RequestInvalidationEvent): void {
    if (!event || !event.event_id || !event.request_id) {
      return
    }

    // Deduplicate exact event ID
    if (this.seenEventIds.has(event.event_id)) {
      return
    }
    this.seenEventIds.add(event.event_id)

    // High-watermark check per request
    const lastSeq = this.highWatermarks[event.request_id] ?? 0
    if (event.aggregate_sequence <= lastSeq) {
      return
    }
    this.highWatermarks[event.request_id] = event.aggregate_sequence

    // Notify immediate listeners
    for (const listener of this.invalidationListeners) {
      try {
        listener(event)
      } catch (err) {
        console.error('Realtime invalidation listener error:', err)
      }
    }

    // Enqueue for burst coalescing
    this.pendingEvents.push(event)
    if (this.debounceMs <= 0) {
      this.flushCoalesced()
    } else {
      if (this.debounceTimer) {
        clearTimeout(this.debounceTimer)
      }
      this.debounceTimer = setTimeout(() => {
        this.flushCoalesced()
      }, this.debounceMs)
    }
  }

  private flushCoalesced(): void {
    if (this.debounceTimer) {
      clearTimeout(this.debounceTimer)
      this.debounceTimer = null
    }

    if (this.pendingEvents.length === 0) {
      return
    }

    const events = [...this.pendingEvents]
    this.pendingEvents = []

    for (const listener of this.coalescedListeners) {
      try {
        listener(events)
      } catch (err) {
        console.error('Realtime coalesced listener error:', err)
      }
    }
  }

  private handleReconnect(): void {
    for (const listener of this.reconnectListeners) {
      try {
        listener()
      } catch (err) {
        console.error('Realtime reconnect listener error:', err)
      }
    }
  }

  private bindReconnect(): void {
    if (this.reconnectBound || !this.echo) {
      return
    }

    const pusherConnection = (
      this.echo as unknown as {
        connector?: {
          pusher?: {
            connection?: {
              bind?: (event: string, callback: () => void) => void
            }
          }
        }
      }
    )?.connector?.pusher?.connection
    if (pusherConnection?.bind) {
      pusherConnection.bind('connected', () => {
        this.handleReconnect()
      })
      this.reconnectBound = true
    }
  }

  private calculateChannelsForUser(user: User): Set<string> {
    const channels = new Set<string>()

    if (user.id) {
      channels.add(`users.${user.id}`)
    }

    const userWithProfile = user as User & { staffProfile?: User['staff_profile'] }
    const staffProfile = userWithProfile.staff_profile ?? userWithProfile.staffProfile
    const departments = staffProfile?.departments

    if (user.role === 'staff' && Array.isArray(departments)) {
      for (const dept of departments) {
        if (dept?.id) {
          channels.add(`departments.${dept.id}`)
        }
      }
    }

    return channels
  }

  private teardown(): void {
    for (const channel of this.subscribedChannels) {
      this.echo?.leave(channel)
    }
    this.subscribedChannels.clear()

    if (this.echo) {
      this.echo.disconnect()
      this.echo = null
      this.reconnectBound = false
    }

    if (this.debounceTimer) {
      clearTimeout(this.debounceTimer)
      this.debounceTimer = null
    }
    this.pendingEvents = []
  }
}

export const realtimeManager = new RealtimeManager()
