import { computed, onMounted, reactive, ref, watch } from 'vue'
import { listAdmin } from '@/services/admin'
import type { AuditRow, Page } from '@/services/admin'
import { emptyPage, message } from './shared'

export function useAdminHistory() {
  const audit = ref<Page<AuditRow>>(emptyPage())
  const auditPage = ref(1)
  const auditLoading = ref(true)
  const collectionErrors = reactive({ audit: '' })
  const auditFilters = reactive({
    request_id: '',
    actor_id: '',
    new_status: '',
    date_from: '',
    date_to: ''
  })
  const hasAuditFilters = computed(() =>
    Object.values(auditFilters).some(Boolean)
  )
  let auditVersion = 0
  function clearAuditFilters() {
    Object.assign(auditFilters, {
      request_id: '',
      actor_id: '',
      new_status: '',
      date_from: '',
      date_to: ''
    })
  }
  async function loadAudit() {
    const version = ++auditVersion
    auditLoading.value = true
    collectionErrors.audit = ''
    try {
      const result = await listAdmin<AuditRow>('audit-log', {
        ...auditFilters,
        page: auditPage.value
      })
      if (version === auditVersion) audit.value = result
    } catch (e) {
      if (version === auditVersion) collectionErrors.audit = message(e)
    } finally {
      if (version === auditVersion) auditLoading.value = false
    }
  }
  onMounted(loadAudit)
  watch(
    [auditPage, () => JSON.stringify(auditFilters)],
    ([, filters], [, oldFilters]) => {
      if (filters !== oldFilters && auditPage.value !== 1) {
        auditPage.value = 1
        return
      }
      void loadAudit()
    }
  )
  return {
    audit,
    auditPage,
    auditLoading,
    collectionErrors,
    auditFilters,
    hasAuditFilters,
    clearAuditFilters,
    loadAudit
  }
}
