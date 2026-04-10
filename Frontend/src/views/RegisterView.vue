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
import { reactive, computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useMockData } from '@/composables/useMockData'
import type { StudentLevel } from '@/types'

const router = useRouter()
const { faculties, departments, programmes, registerStudent, mockUsers } = useMockData()

const levels: StudentLevel[] = ['L100', 'L200', 'L300', 'L400', 'L500', 'L600']
const error = ref('')

const form = reactive({
  name: '',
  email: '',
  password: '',
  matricule: '',
  faculty_id: faculties.value[0]?.id ?? 1,
  department_id: 0,
  programme_id: 0,
  level: 'L100' as StudentLevel
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

function onSubmit() {
  error.value = ''
  if (mockEmailTaken(form.email)) {
    error.value = 'That email is already registered.'
    return
  }
  registerStudent({
    name: form.name.trim(),
    email: form.email.trim(),
    password: form.password,
    matricule: form.matricule.trim(),
    faculty_id: form.faculty_id,
    department_id: form.department_id,
    programme_id: form.programme_id,
    level: form.level
  })
  router.push({ name: 'login', query: { registered: '1' } })
}

function mockEmailTaken(email: string) {
  return mockUsers.value.some(u => u.email.toLowerCase() === email.trim().toLowerCase())
}
</script>
