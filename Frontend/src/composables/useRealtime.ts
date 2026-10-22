import { getCurrentScope, onScopeDispose } from 'vue'
import {
  realtimeManager,
  type RequestInvalidationEvent,
} from '@/services/realtime'
import type { User } from '@/types'

export function useRealtime() {
  function onInvalidate(listener: (event: RequestInvalidationEvent) => void) {
    const unbind = realtimeManager.onInvalidate(listener)
    if (getCurrentScope()) {
      onScopeDispose(unbind)
    }
    return unbind
  }

  function onCoalescedInvalidate(
    listener: (events: RequestInvalidationEvent[]) => void
  ) {
    const unbind = realtimeManager.onCoalescedInvalidate(listener)
    if (getCurrentScope()) {
      onScopeDispose(unbind)
    }
    return unbind
  }

  function onReconnect(listener: () => void) {
    const unbind = realtimeManager.onReconnect(listener)
    if (getCurrentScope()) {
      onScopeDispose(unbind)
    }
    return unbind
  }

  function syncUser(user: User | null) {
    realtimeManager.syncUser(user)
  }

  return {
    realtimeManager,
    onInvalidate,
    onCoalescedInvalidate,
    onReconnect,
    syncUser,
  }
}
