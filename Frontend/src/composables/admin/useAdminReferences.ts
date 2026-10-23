import { onMounted, reactive, ref, watch } from 'vue'
import { createAdmin, listAdmin, updateAdmin } from '@/services/admin'
import type { Page } from '@/services/admin'
import type { RequestTypeTier } from '@/types'
import {
  degrees,
  emptyPage,
  message,
  singularTabLabel,
  tabLabel,
  useAdminChoices,
  useAdminDelete,
  useAdminFeedback
} from './shared'
import type { ReferenceKind, ReferenceRecord } from './shared'

export function useAdminReferences(kind: ReferenceKind) {
  const activeTab = ref(kind)
  const feedback = useAdminFeedback()
  const { error, success, formError, pending } = feedback
  const requiredChoices: ReferenceKind[] =
    kind === 'faculties'
      ? []
      : kind === 'departments'
        ? ['faculties']
        : ['departments']
  const { faculties, departments, choicesReady, choicesError, loadChoices } =
    useAdminChoices(requiredChoices)
  const loading = ref(false)
  const referencesLoading = ref(true)
  const collectionErrors = reactive({ references: '' })
  const references = ref<Page<ReferenceRecord>>(emptyPage())
  const refPage = ref(1)
  const refSearch = ref('')
  const referenceModal = ref(false)
  const refForm = reactive({
    id: 0,
    name: '',
    code: '',
    matricule_prefix: '',
    faculty_id: 0,
    department_id: 0,
    type: 'academic',
    degree_type: 'BACHELOR',
    description: '',
    default_department_sequence: [] as (number | string)[],
    tiers: [] as RequestTypeTier[]
  })

  let referenceVersion = 0
  function routeLabel(step: number | string) {
    return step === 'STUDENT_DEPARTMENT'
      ? 'Student department'
      : step === 'FACULTY_RECORDS'
        ? 'Faculty records'
        : (departments.value.find(
            (department) => department.id === Number(step)
          )?.name ?? `Department #${step}`)
  }
  async function loadReferences() {
    const version = ++referenceVersion
    referencesLoading.value = true
    collectionErrors.references = ''
    try {
      const result = await listAdmin<ReferenceRecord>(activeTab.value, {
        page: refPage.value,
        search: refSearch.value
      })
      if (version === referenceVersion) references.value = result
    } catch (e) {
      if (version === referenceVersion) collectionErrors.references = message(e)
    } finally {
      if (version === referenceVersion) referencesLoading.value = false
    }
  }
  const { deleteTarget, askRemove, closeDelete, remove } = useAdminDelete(
    feedback,
    async () => {
      await loadReferences()
      await loadChoices()
    }
  )
  async function initialize() {
    if (loading.value) return
    loading.value = true
    error.value = ''
    await loadChoices()
    await loadReferences()
    loading.value = false
  }
  onMounted(initialize)
  watch([refPage, refSearch], ([, search], [, oldSearch]) => {
    if (search !== oldSearch && refPage.value !== 1) {
      refPage.value = 1
      return
    }
    void loadReferences()
  })
  function openReference(row?: ReferenceRecord) {
    formError.value = ''
    Object.assign(refForm, {
      id: 0,
      name: '',
      code: '',
      matricule_prefix: '',
      faculty_id: 0,
      department_id: 0,
      type: 'academic',
      degree_type: 'BACHELOR',
      description: '',
      default_department_sequence: [],
      tiers: []
    })
    if (row)
      Object.assign(refForm, row, {
        default_department_sequence: [
          ...(row.default_department_sequence ?? [])
        ],
        tiers: (row.tiers ?? []).map((t) => ({ ...t }))
      })
    referenceModal.value = true
  }
  function closeReference() {
    if (!pending.value) referenceModal.value = false
  }
  function moveStep(index: number, delta: number) {
    const next = index + delta
    if (next < 0 || next >= refForm.default_department_sequence.length) return
    ;[
      refForm.default_department_sequence[index],
      refForm.default_department_sequence[next]
    ] = [
      refForm.default_department_sequence[next]!,
      refForm.default_department_sequence[index]!
    ]
  }

  function addTier() {
    const nextOrder = refForm.tiers.length + 1
    refForm.tiers.push({
      id: 0,
      request_type_id: refForm.id,
      name: '',
      code: '',
      description: '',
      fee_amount: 1000,
      currency: 'XAF',
      turnaround_days: 7,
      priority: 2,
      is_active: true,
      sort_order: nextOrder
    })
  }

  function removeTier(index: number) {
    refForm.tiers.splice(index, 1)
  }

  function moveTier(index: number, delta: number) {
    const next = index + delta
    if (next < 0 || next >= refForm.tiers.length) return
    ;[refForm.tiers[index], refForm.tiers[next]] = [
      refForm.tiers[next]!,
      refForm.tiers[index]!
    ]
    refForm.tiers.forEach((t, i) => {
      t.sort_order = i + 1
    })
  }

  async function saveReference() {
    if (pending.value) return
    formError.value = ''
    if (!refForm.name.trim()) {
      formError.value = 'Enter a name.'
      return
    }
    if (
      (activeTab.value === 'departments' && !refForm.faculty_id) ||
      (activeTab.value === 'programmes' && !refForm.department_id)
    ) {
      formError.value = 'Choose the academic unit this record belongs to.'
      return
    }
    if (
      activeTab.value === 'request-types' &&
      !refForm.default_department_sequence.length
    ) {
      formError.value = 'Add at least one routing step.'
      return
    }
    if (activeTab.value === 'request-types' && refForm.tiers.length > 0) {
      for (let i = 0; i < refForm.tiers.length; i++) {
        const t = refForm.tiers[i]!
        if (!t.name.trim() || !t.code.trim()) {
          formError.value = `Tier #${i + 1} must have a name and code.`
          return
        }
        if (Number(t.fee_amount) < 100) {
          formError.value = `Tier "${t.name}" fee must be at least 100 XAF.`
          return
        }
        if (Number(t.turnaround_days) < 1) {
          formError.value = `Tier "${t.name}" turnaround days must be at least 1.`
          return
        }
      }
      const codes = refForm.tiers.map((t) => t.code.trim().toLowerCase())
      if (new Set(codes).size !== codes.length) {
        formError.value = 'Each tier must have a unique code.'
        return
      }
    }

    const body =
      activeTab.value === 'faculties'
        ? {
            name: refForm.name,
            code: refForm.code,
            matricule_prefix: refForm.matricule_prefix
          }
        : activeTab.value === 'departments'
          ? {
              name: refForm.name,
              code: refForm.code,
              faculty_id: refForm.faculty_id,
              type: refForm.type
            }
          : activeTab.value === 'programmes'
            ? {
                name: refForm.name,
                code: refForm.code,
                department_id: refForm.department_id,
                degree_type: refForm.degree_type
              }
            : {
                name: refForm.name,
                description: refForm.description,
                default_department_sequence: refForm.default_department_sequence,
                ...(refForm.tiers.length > 0
                  ? {
                      tiers: refForm.tiers.map((t, idx) => ({
                        ...(t.id ? { id: t.id } : {}),
                        name: t.name.trim(),
                        code: t.code.trim().toLowerCase(),
                        description: t.description || null,
                        fee_amount: Number(t.fee_amount),
                        currency: t.currency || 'XAF',
                        turnaround_days: Number(t.turnaround_days),
                        priority: Number(t.priority) as 1 | 2 | 3,
                        is_active: Boolean(t.is_active),
                        sort_order: idx + 1
                      }))
                    }
                  : {})
              }
    pending.value = true
    success.value = ''
    try {
      if (refForm.id) await updateAdmin(activeTab.value, refForm.id, body)
      else await createAdmin(activeTab.value, body)
      success.value = 'Record saved.'
      referenceModal.value = false
      await loadReferences()
      await loadChoices()
    } catch (e) {
      formError.value = message(e)
    } finally {
      pending.value = false
    }
  }
  return {
    references,
    refPage,
    referencesLoading,
    collectionErrors,
    refSearch,
    loadReferences,
    initialize,
    loading,
    error,
    success,
    pending,
    formError,
    choicesReady,
    choicesError,
    faculties,
    departments,
    activeTab,
    tabLabel,
    singularTabLabel,
    degrees,
    routeLabel,
    referenceModal,
    refForm,
    openReference,
    closeReference,
    saveReference,
    moveStep,
    addTier,
    removeTier,
    moveTier,
    deleteTarget,
    askRemove,
    closeDelete,
    remove
  }
}
