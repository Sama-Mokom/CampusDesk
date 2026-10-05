import { computed, onMounted, reactive, ref, watch } from 'vue'
import { adminRequest, listAdmin } from '@/services/admin'
import type { AdminRequest, Page } from '@/services/admin'
import { reopenRequest } from '@/services/requests'
import type { Attachment, RequestStage, StageStatus } from '@/types'
import {
  date,
  emptyPage,
  message,
  statuses,
  useAdminChoices,
  useAdminFeedback
} from './shared'

export function useAdminRequests() {
  const { error, success, formError, pending } = useAdminFeedback()
  const { faculties, departments, requestTypes, choicesError, loadChoices } =
    useAdminChoices(['faculties', 'departments', 'request-types'])
  const loading = ref(false)
  const requestsLoading = ref(true)
  const collectionErrors = reactive({ requests: '' })
  const requests = ref<Page<AdminRequest>>(emptyPage())
  const requestPage = ref(1)
  const requestFilters = reactive({
    search: '',
    faculty_id: 0,
    department_id: 0,
    request_type_id: 0,
    status: '',
    reopened: false,
    date_from: '',
    date_to: ''
  })

  const detail = ref<AdminRequest | null>(null),
    detailOpen = ref(false),
    detailLoading = ref(false),
    detailId = ref(0),
    detailError = ref('')

  const activeRequestFilterCount = computed(
    () =>
      Object.values(requestFilters).filter(
        (value) => value !== '' && value !== 0 && value !== false
      ).length
  )

  const detailAttachments = computed<Attachment[]>(() =>
    (detail.value?.attachments ?? []).map((file) => ({
      ...file,
      file_path: ''
    }))
  )
  const detailStages = computed<RequestStage[]>(() =>
    (detail.value?.stages ?? []).map((stage) => ({
      id: stage.id,
      request_id: detail.value!.id,
      department_name: stage.department?.name ?? 'Unknown department',
      sequence_order: stage.sequence_order,
      status: stage.status as StageStatus,
      handled_by: stage.handled_by ? `#${stage.handled_by}` : null,
      staff_note: stage.staff_note ?? null,
      updated_at: stage.updated_at ?? null
    }))
  )

  let requestVersion = 0,
    detailVersion = 0
  function clearRequestFilters() {
    Object.assign(requestFilters, {
      search: '',
      faculty_id: 0,
      department_id: 0,
      request_type_id: 0,
      status: '',
      reopened: false,
      date_from: '',
      date_to: ''
    })
  }
  async function loadRequests() {
    const version = ++requestVersion
    requestsLoading.value = true
    collectionErrors.requests = ''
    try {
      const result = await listAdmin<AdminRequest>('requests', {
        ...requestFilters,
        reopened: requestFilters.reopened ? 1 : undefined,
        page: requestPage.value
      })
      if (version === requestVersion) requests.value = result
    } catch (e) {
      if (version === requestVersion) collectionErrors.requests = message(e)
    } finally {
      if (version === requestVersion) requestsLoading.value = false
    }
  }
  async function initialize() {
    if (loading.value) return
    loading.value = true
    error.value = ''
    await loadChoices()
    await loadRequests()
    loading.value = false
  }
  onMounted(initialize)
  watch(
    [requestPage, () => JSON.stringify(requestFilters)],
    ([, filters], [, oldFilters]) => {
      if (filters !== oldFilters && requestPage.value !== 1) {
        requestPage.value = 1
        return
      }
      void loadRequests()
    }
  )
  async function openRequest(id: number) {
    const version = ++detailVersion
    detailId.value = id
    detailOpen.value = true
    detailLoading.value = true
    detailError.value = ''
    formError.value = ''
    detail.value = null
    try {
      const result = await adminRequest(id)
      if (version === detailVersion) detail.value = result
    } catch (e) {
      if (version === detailVersion) detailError.value = message(e)
    } finally {
      if (version === detailVersion) detailLoading.value = false
    }
  }
  function closeDetail() {
    if (!pending.value) {
      detailOpen.value = false
      detailVersion++
      detail.value = null
    }
  }
  async function reopen() {
    if (!detail.value || pending.value) return
    const id = detail.value.id
    pending.value = true
    formError.value = ''
    success.value = ''
    try {
      await reopenRequest(id)
      success.value = `Request #${id} reopened.`
      await openRequest(id)
      await loadRequests()
    } catch (e) {
      formError.value = message(e)
    } finally {
      pending.value = false
    }
  }
  return {
    requests,
    requestPage,
    requestsLoading,
    collectionErrors,
    requestFilters,
    activeRequestFilterCount,
    clearRequestFilters,
    loadRequests,
    initialize,
    loading,
    error,
    success,
    pending,
    formError,
    choicesError,
    faculties,
    departments,
    requestTypes,
    statuses,
    date,
    detail,
    detailOpen,
    detailLoading,
    detailId,
    detailError,
    detailAttachments,
    detailStages,
    openRequest,
    closeDetail,
    reopen
  }
}
