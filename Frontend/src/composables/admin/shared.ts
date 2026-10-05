import { ref } from 'vue'
import type { Ref } from 'vue'
import { deleteAdmin, listAdmin } from '@/services/admin'
import type {
  Department,
  Faculty,
  Page,
  Programme,
  RequestType
} from '@/services/admin'
import type { RequestStatus } from '@/types'

export type ReferenceKind =
  | 'faculties'
  | 'departments'
  | 'programmes'
  | 'request-types'
export type ReferenceRecord = {
  id: number
  name: string
  code?: string
  matricule_prefix?: string
  type?: string
  degree_type?: string
  default_department_sequence?: (number | string)[]
  description?: string | null
  faculty_id?: number
  department_id?: number
}
export const statuses: RequestStatus[] = [
  'draft',
  'pending',
  'in_review',
  'forwarded',
  'ready',
  'collected',
  'rejected'
]
export const degrees = ['BACHELOR', 'CERTIFICATE', 'MASTER', 'PHD']
const labels: Record<ReferenceKind, string> = {
  faculties: 'Faculties',
  departments: 'Departments',
  programmes: 'Programmes',
  'request-types': 'Request types'
}
const singularLabels: Record<ReferenceKind, string> = {
  faculties: 'faculty',
  departments: 'department',
  programmes: 'programme',
  'request-types': 'request type'
}
export const tabLabel = (kind: ReferenceKind) => labels[kind]
export const singularTabLabel = (kind: ReferenceKind) => singularLabels[kind]
export const emptyPage = <T>(): Page<T> => ({
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
  links: { next: null, prev: null }
})
export const date = (value: string) =>
  value
    ? new Date(value).toLocaleString('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short'
      })
    : 'Not recorded'
export function message(e: unknown) {
  const response = (
    e as {
      response?: {
        data?: { message?: string; errors?: Record<string, string[]> }
      }
    }
  )?.response?.data
  return response?.errors
    ? Object.values(response.errors).flat().join(' ')
    : (response?.message ?? 'The action failed. Please try again.')
}

export function useAdminFeedback() {
  return {
    error: ref(''),
    success: ref(''),
    formError: ref(''),
    pending: ref(false)
  }
}

async function allChoices<T>(kind: string): Promise<T[]> {
  const result: T[] = []
  let page = 1
  while (true) {
    const batch = await listAdmin<T>(kind, { page, per_page: 100 })
    result.push(...batch.data)
    if (page >= batch.meta.last_page) return result
    page++
  }
}
export function useAdminChoices(kinds: ReferenceKind[]) {
  const faculties = ref<Faculty[]>([])
  const departments = ref<Department[]>([])
  const programmes = ref<Programme[]>([])
  const requestTypes = ref<RequestType[]>([])
  const choicesReady = ref(kinds.length === 0)
  const choicesError = ref('')
  async function loadChoices() {
    choicesReady.value = false
    choicesError.value = ''
    try {
      // Ordered reads also work with the single-worker PHP development server.
      if (kinds.includes('faculties'))
        faculties.value = await allChoices<Faculty>('faculties')
      if (kinds.includes('departments'))
        departments.value = await allChoices<Department>('departments')
      if (kinds.includes('programmes'))
        programmes.value = await allChoices<Programme>('programmes')
      if (kinds.includes('request-types'))
        requestTypes.value = await allChoices<RequestType>('request-types')
      choicesReady.value = true
    } catch (e) {
      choicesError.value = `Unable to load form choices. ${message(e)} Refresh this page to retry.`
    }
  }
  return {
    faculties,
    departments,
    programmes,
    requestTypes,
    choicesReady,
    choicesError,
    loadChoices
  }
}

export function useAdminDelete(
  feedback: {
    formError: Ref<string>
    pending: Ref<boolean>
    success: Ref<string>
  },
  reload: () => Promise<void>
) {
  const { formError, pending, success } = feedback
  const deleteTarget = ref<{ kind: string; id: number; name: string } | null>(
    null
  )
  function askRemove(kind: string, id: number, name: string) {
    formError.value = ''
    deleteTarget.value = { kind, id, name }
  }
  function closeDelete() {
    if (!pending.value) deleteTarget.value = null
  }
  async function remove() {
    if (pending.value || !deleteTarget.value) return
    const { kind, id } = deleteTarget.value
    pending.value = true
    formError.value = ''
    success.value = ''
    try {
      await deleteAdmin(kind, id)
      deleteTarget.value = null
      success.value = 'Record deleted.'
      await reload()
    } catch (e) {
      formError.value = message(e)
    } finally {
      pending.value = false
    }
  }
  return { deleteTarget, askRemove, closeDelete, remove }
}
