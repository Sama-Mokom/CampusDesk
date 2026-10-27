import { computed, getCurrentScope, onScopeDispose, ref } from 'vue'
import { fetchRequests } from '@/services/requests'
import { parseApiError } from '@/utils/error'
import { useRealtime } from './useRealtime'
import type { Request as DocumentRequest } from '@/types'

export function studentApiMessage(cause: unknown, fallback: string): string {
  return parseApiError(cause, fallback)
}

/** Each mounted page owns its records; no student data survives the page/session. */
export function useStudentRequests() {
  const requests = ref<DocumentRequest[]>([])
  const loading = ref(true)
  const loaded = ref(false)
  const error = ref('')
  let version = 0
  if (getCurrentScope()) {
    onScopeDispose(() => {
      version++
    })
  }

  const { onCoalescedInvalidate, onReconnect } = useRealtime()

  onCoalescedInvalidate(() => {
    if (loaded.value) {
      void loadRequests()
    }
  })

  onReconnect(() => {
    if (loaded.value) {
      void loadRequests()
    }
  })

  const sortedRequests = computed(() =>
    [...requests.value].sort(
      (a, b) =>
        new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    )
  )
  const stats = computed(() => ({
    total: requests.value.length,
    active: requests.value.filter((request) =>
      ['pending', 'in_review', 'forwarded'].includes(request.status)
    ).length,
    ready: requests.value.filter((request) => request.status === 'ready').length
  }))

  async function loadRequests() {
    const current = ++version
    loading.value = true
    error.value = ''
    try {
      const result = await fetchRequests()
      if (current !== version) return
      requests.value = result
      loaded.value = true
    } catch (cause) {
      if (current === version)
        error.value = studentApiMessage(
          cause,
          'Your requests could not be loaded. Please try again.'
        )
    } finally {
      if (current === version) loading.value = false
    }
  }

  return {
    requests,
    sortedRequests,
    stats,
    loading,
    loaded,
    error,
    loadRequests
  }
}
