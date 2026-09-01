<template>
  <div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4 py-8">
    <div class="card max-w-lg w-full">
      <h2 class="text-primary font-semibold text-xl mb-2">Student registration</h2>
      <p class="text-sm text-neutral-600 mb-6">Staff accounts are created by a super administrator.</p>
      <form @submit.prevent="onSubmit" class="space-y-4">
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Full name</label>
          <input v-model="form.name" type="text" class="input-field" required />
        </div>
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Email</label>
          <input v-model="form.email" type="email" class="input-field" required />
        </div>
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Password</label>
          <input v-model="form.password" type="password" class="input-field" required />
        </div>
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Matricule</label>
          <input v-model="form.matricule" type="text" class="input-field" required />
        </div>
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Faculty</label>
          <select v-model.number="form.faculty_id" class="input-field" required @change="onFacultyChange">
            <option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Department</label>
          <select v-model.number="form.department_id" class="input-field" required @change="onDeptChange">
            <option v-for="d in filteredDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Programme</label>
          <select v-model.number="form.programme_id" class="input-field" required>
            <option v-for="p in filteredProgrammes" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Level</label>
          <select v-model="form.level" class="input-field" required>
            <option v-for="lv in levels" :key="lv" :value="lv">{{ lv }}</option>
          </select>
        </div>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <button type="submit" class="btn-primary w-full">Register</button>
      </form>
      <p class="mt-4 text-sm text-neutral-600 text-center">
        Already have an account?
        <router-link to="/login" class="text-primary font-medium hover:underline">Sign in</router-link>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, computed, ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import type { StudentLevel, Faculty, Department, Programme } from '../types'
import { register } from '../services/auth'
import { useAuth } from '../composables/useAuth'
import { fetchDepartments, fetchFaculties, fetchProgrammes } from '../services/reference'

const router = useRouter()
const faculties = ref<Faculty[]>([])
const departments = ref<Department[]>([])
const programmes = ref<Programme[]>([])
const { user, setUser } = useAuth()

const levels: StudentLevel[] = ['100', '200', '300', '400', '500', '600']
const error = ref('')

onMounted(async () =>{
  try {
    const [fetchedFaculties, fetchedDepartments, fetchedProgrammes] = await Promise.all([
      fetchFaculties(),
      fetchDepartments(),
      fetchProgrammes(),
    ])
    faculties.value = fetchedFaculties
    departments.value = fetchedDepartments
    programmes.value = fetchedProgrammes
  } catch (err) {
    error.value = 'Failed tp load registration data. Please refresh.'
  }
})
function homePath(): string {
  const u = user.value
  if (!u) return '/login'
  if (u.role === 'student') return '/student'
  const level = u.staff_profile?.admin_level
  if (level === 'super_admin') return '/admin'
  if (level === 'dept_admin') return '/dept-admin'
  return '/staff'
}

const form = reactive({
  name: '',
  email: '',
  password: '',
  matricule: '',
  faculty_id: faculties.value[1]?.id ?? 0,
  department_id: 0,
  programme_id: 0,
  level: '100' as StudentLevel
})

const filteredDepartments = computed(() =>
  departments.value.filter(d => d.faculty_id === form.faculty_id)
)

const filteredProgrammes = computed(() =>
  programmes.value.filter(p => p.faculty_id === form.faculty_id)
)

function syncDefaults() {
  const fds = filteredDepartments.value
  if (fds.length && !fds.some(d => d.id === form.department_id)) {
    form.department_id = fds[0]!.id
  }
  const fps = filteredProgrammes.value
  if (fps.length && !fps.some(p => p.id === form.programme_id)) {
    form.programme_id = fps[0]!.id
  }
}

watch([() => form.faculty_id, faculties, departments, programmes], syncDefaults, { immediate: true })

function onFacultyChange() {
  syncDefaults()
}

function onDeptChange() {
  syncDefaults()
}

async function onSubmit() {
  error.value = ''
  try {
    const registeredUser = await register({
      name: form.name.trim(),
      email: form.email.trim(),
      password: form.password,
      password_confirmation: form.password,
      matricule: form.matricule.trim(),
      faculty_id: form.faculty_id,
      department_id: form.department_id,
      programme_id: form.programme_id,
      level: form.level
    });
    setUser(registeredUser)
    router.replace(homePath())
  } catch (err: any){
    if (err.response && err.response.status === 422) {
      error.value = err.response.data.message || 'Registration failed. Please check your details.';
  } else {
    error.value = 'A connection error occurred. Please try again.';
   }
  }
}

// function mockEmailTaken(email: string) {
//   return mockUsers.value.some(u => u.email.toLowerCase() === email.trim().toLowerCase())
// }
</script>
