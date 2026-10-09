import { onMounted, reactive, ref, watch } from 'vue'
import {
  createAdmin,
  disableAdminUser,
  enableAdminUser,
  listAdmin,
  setAdminLevel,
  updateAdmin
} from '@/services/admin'
import type { AdminUser, Page } from '@/services/admin'
import type { UserForm } from '@/components/admin/AdminUserFields.vue'
import {
  emptyPage,
  message,
  useAdminChoices,
  useAdminDelete,
  useAdminFeedback
} from './shared'
import { useAuth } from '@/composables/useAuth'

export function useAdminUsers() {
  const { user: signedInUser } = useAuth()
  const feedback = useAdminFeedback()
  const { error, success, formError, pending } = feedback
  const {
    faculties,
    departments,
    programmes,
    choicesReady,
    choicesError,
    loadChoices
  } = useAdminChoices(['faculties', 'departments', 'programmes'])
  const loading = ref(false)
  const usersLoading = ref(true)
  const collectionErrors = reactive({ users: '' })
  const users = ref<Page<AdminUser>>(emptyPage())
  const userPage = ref(1)
  const userSearch = ref('')
  const userRole = ref('')
  const userStatus = ref('')
  const userModal = ref(false)
  const disableTarget = ref<{ id: number; name: string } | null>(null)
  const disableReason = ref('')
  const disableError = ref('')
  const emptyUserForm = (): UserForm => ({
    id: 0,
    role: 'student',
    name: '',
    email: '',
    password: '',
    matricule: '',
    faculty_id: 0,
    department_id: 0,
    programme_id: 0,
    level: '100',
    staff_id: '',
    department_ids: [],
    primary_department_id: 0
  })
  const userForm = ref<UserForm>(emptyUserForm())

  let userVersion = 0
  async function loadUsers() {
    const version = ++userVersion
    usersLoading.value = true
    collectionErrors.users = ''
    try {
      const result = await listAdmin<AdminUser>('users', {
        page: userPage.value,
        search: userSearch.value,
        role: userRole.value,
        status: userStatus.value
      })
      if (version === userVersion) users.value = result
    } catch (e) {
      if (version === userVersion) collectionErrors.users = message(e)
    } finally {
      if (version === userVersion) usersLoading.value = false
    }
  }
  const { deleteTarget, askRemove, closeDelete, remove } = useAdminDelete(
    feedback,
    loadUsers
  )
  async function initialize() {
    if (loading.value) return
    loading.value = true
    error.value = ''
    await loadChoices()
    await loadUsers()
    loading.value = false
  }
  onMounted(initialize)
  watch(
    [userPage, userSearch, userRole, userStatus],
    ([, search, role, status], [, oldSearch, oldRole, oldStatus]) => {
      if ((search !== oldSearch || role !== oldRole || status !== oldStatus) && userPage.value !== 1) {
        userPage.value = 1
        return
      }
      void loadUsers()
    }
  )
  function openUser(user?: AdminUser) {
    formError.value = ''
    userForm.value = emptyUserForm()
    if (user)
      Object.assign(userForm.value, {
        id: user.id,
        role: user.role,
        name: user.name,
        email: user.email,
        password: '',
        matricule: user.student_profile?.matricule ?? '',
        faculty_id: user.student_profile?.faculty_id ?? 0,
        department_id: user.student_profile?.department_id ?? 0,
        programme_id: user.student_profile?.programme_id ?? 0,
        level: user.student_profile?.level ?? '100',
        staff_id: user.staff_profile?.staff_id ?? '',
        department_ids:
          user.staff_profile?.departments.map((department) => department.id) ??
          [],
        primary_department_id:
          user.staff_profile?.departments.find(
            (department) => department.is_primary
          )?.id ?? 0
      })
    userModal.value = true
  }
  function closeUser() {
    if (!pending.value) userModal.value = false
  }
  async function saveUser() {
    if (pending.value) return
    formError.value = ''
    const form = userForm.value
    if (!form.name.trim() || !form.email.trim()) {
      formError.value = 'Name and email are required.'
      return
    }
    if ((!form.id || form.password) && form.password.length < 8) {
      formError.value = 'Use a password with at least 8 characters.'
      return
    }
    if (
      form.role === 'student' &&
      (!form.matricule.trim() ||
        !form.faculty_id ||
        !form.department_id ||
        !form.programme_id)
    ) {
      formError.value =
        'Complete the matricule, faculty, department and programme.'
      return
    }
    if (
      form.role === 'staff' &&
      (!form.staff_id.trim() ||
        !form.department_ids.length ||
        !form.department_ids.includes(form.primary_department_id))
    ) {
      formError.value =
        'Enter a staff ID and choose department memberships with one primary department.'
      return
    }
    const body: Record<string, unknown> = { name: form.name, email: form.email }
    if (!form.id) body.role = form.role
    if (form.password) body.password = form.password
    if (form.role === 'student')
      Object.assign(body, {
        matricule: form.matricule,
        faculty_id: form.faculty_id,
        department_id: form.department_id,
        programme_id: form.programme_id,
        level: form.level
      })
    else
      Object.assign(body, {
        staff_id: form.staff_id,
        department_ids: form.department_ids,
        primary_department_id: form.primary_department_id
      })
    pending.value = true
    success.value = ''
    try {
      if (form.id) await updateAdmin('users', form.id, body)
      else await createAdmin('users', body)
      userModal.value = false
      success.value = 'User saved.'
      await loadUsers()
    } catch (e) {
      formError.value = message(e)
    } finally {
      pending.value = false
    }
  }
  async function changeLevel(user: AdminUser, level: string) {
    if (pending.value) return
    pending.value = true
    error.value = ''
    success.value = ''
    try {
      await setAdminLevel(
        user.id,
        (level || null) as 'dept_admin' | 'super_admin' | null
      )
      success.value = `Admin access updated for ${user.name}.`
    } catch (e) {
      error.value = message(e)
    } finally {
      await loadUsers()
      pending.value = false
    }
  }
  function askDisable(user: AdminUser) {
    disableError.value = ''
    disableReason.value = ''
    disableTarget.value = { id: user.id, name: user.name }
  }
  function closeDisable() {
    if (!pending.value) disableTarget.value = null
  }
  async function confirmDisable() {
    if (pending.value || !disableTarget.value) return
    pending.value = true
    disableError.value = ''
    success.value = ''
    try {
      const target = disableTarget.value
      await disableAdminUser(target.id, disableReason.value)
      disableTarget.value = null
      success.value = `${target.name} has been disabled.`
      await loadUsers()
    } catch (e) {
      disableError.value = message(e)
    } finally {
      pending.value = false
    }
  }
  async function enableAccount(user: AdminUser) {
    if (pending.value) return
    pending.value = true
    error.value = ''
    success.value = ''
    try {
      await enableAdminUser(user.id)
      success.value = `${user.name} has been re-enabled. They must sign in again.`
    } catch (e) {
      error.value = message(e)
    } finally {
      await loadUsers()
      pending.value = false
    }
  }
  return {
    users,
    userPage,
    usersLoading,
    collectionErrors,
    userSearch,
    userRole,
    userStatus,
    signedInUser,
    loadUsers,
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
    programmes,
    userModal,
    userForm,
    openUser,
    closeUser,
    saveUser,
    changeLevel,
    disableTarget,
    disableReason,
    disableError,
    askDisable,
    closeDisable,
    confirmDisable,
    enableAccount,
    deleteTarget,
    askRemove,
    closeDelete,
    remove
  }
}
