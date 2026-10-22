import { describe, it, expect, vi, beforeEach } from 'vitest'
import { effectScope } from 'vue'
import { useRealtime } from '../useRealtime'
import { realtimeManager, type RequestInvalidationEvent } from '@/services/realtime'

describe('useRealtime composable', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('subscribes to invalidations and unsubscribes on scope disposal', () => {
    const scope = effectScope()
    const callback = vi.fn()

    scope.run(() => {
      const { onInvalidate } = useRealtime()
      onInvalidate(callback)
    })
    
    // Test that callback receives event when triggered via manager
    const event: RequestInvalidationEvent = {
      event_id: 'test-event-uuid',
      event_name: 'request.created',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 1,
      occurred_at: '2026-10-16T12:00:00Z',
      payload: { request_id: 101 },
    }

    // Trigger internal handleIncomingEvent
    ;(realtimeManager as any).handleIncomingEvent(event)
    expect(callback).toHaveBeenCalledWith(event)

    // Dispose scope
    scope.stop()

    // Trigger again with new ID
    const event2: RequestInvalidationEvent = {
      event_id: 'test-event-uuid-2',
      event_name: 'request.created',
      schema_version: 1,
      request_id: 101,
      aggregate_sequence: 2,
      occurred_at: '2026-10-16T12:01:00Z',
      payload: { request_id: 101 },
    }
    ;(realtimeManager as any).handleIncomingEvent(event2)

    // Should NOT have been called again because scope was stopped
    expect(callback).toHaveBeenCalledTimes(1)
  })
})
