import { computed, onScopeDispose, ref } from 'vue'
import axios from 'axios'
import { fetchRequests } from '@/services/requests'
import type { Request as DocumentRequest } from '@/types'

export function studentApiMessage(cause: unknown, fallback: string): string {
  if (
    axios.isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(
      cause
    )
  ) {
    const data = cause.response?.data
    return data?.errors
      ? Object.values(data.errors).flat().join(' ')
      : (data?.message ?? fallback)
  }
  return fallback
}

/** Each mounted page owns its records; no student data survives the page/session. */
export function useStudentRequests() {
  const requests = ref<DocumentRequest[]>([])
  const loading = ref(true)
  const loaded = ref(false)
  const error = ref('')
  let version = 0
  onScopeDispose(() => {
    version++
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
