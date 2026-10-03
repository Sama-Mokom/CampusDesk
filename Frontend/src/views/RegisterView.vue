<script setup lang="ts">
import { reactive, computed, ref, watch, onMounted } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import type { StudentLevel, Faculty, Department, Programme } from '../types'
import { register } from '../services/auth'
import { useAuth } from '../composables/useAuth'
import {
  fetchDepartments,
  fetchFaculties,
  fetchProgrammes
} from '../services/reference'
import AuthLayout from '../components/layout/AuthLayout.vue'
import SkeletonLoader from '../components/ui/SkeletonLoader.vue'
const router = useRouter()
const faculties = ref<Faculty[]>([])
const departments = ref<Department[]>([])
const programmes = ref<Programme[]>([])
const { setUser } = useAuth()
const levels: StudentLevel[] = ['100', '200', '300', '400', '500', '600']
const error = ref('')
const referenceError = ref('')
const loading = ref(true)
const busy = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const form = reactive({
  name: '',
  email: '',
  password: '',
  matricule: '',
  faculty_id: 0,
  department_id: 0,
  programme_id: 0,
  level: '100' as StudentLevel
})
const filteredDepartments = computed(() =>
  departments.value.filter((item) => item.faculty_id === form.faculty_id)
)
const filteredProgrammes = computed(() =>
  programmes.value.filter(
    (item) =>
      item.faculty_id === form.faculty_id &&
      item.department_id === form.department_id
  )
)
watch(
  () => form.faculty_id,
  () => {
    form.department_id = 0
    form.programme_id = 0
  }
)
watch(
  () => form.department_id,
  () => {
    form.programme_id = 0
  }
)
async function loadReferences() {
  loading.value = true
  referenceError.value = ''
  try {
    const [f, d, p] = await Promise.all([
      fetchFaculties(),
      fetchDepartments(),
      fetchProgrammes()
    ])
    faculties.value = f
    departments.value = d
    programmes.value = p
  } catch {
    referenceError.value =
      'Registration options could not be loaded. Please try again.'
  } finally {
    loading.value = false
  }
}
onMounted(loadReferences)
async function onSubmit() {
  if (busy.value || loading.value || referenceError.value) return
  error.value = ''
  fieldErrors.value = {}
  if (!form.faculty_id || !form.department_id || !form.programme_id) {
    error.value = 'Choose your faculty, department, and programme.'
    return
  }
  busy.value = true
  try {
    const registeredUser = await register({
      ...form,
      name: form.name.trim(),
      email: form.email.trim(),
      matricule: form.matricule.trim(),
      password_confirmation: form.password
    })
    setUser(registeredUser)
    await router.replace('/student')
  } catch (err) {
    if (axios.isAxiosError(err) && err.response?.status === 422) {
      error.value =
        err.response.data.message || 'Please check the highlighted details.'
      fieldErrors.value = err.response.data.errors ?? {}
    } else {
      error.value =
        'We could not create your account. Check your connection and try again.'
    }
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <AuthLayout
    title="Your campus starts here"
    description="Create your student account to request and track university documents."
  >
    <SkeletonLoader
      v-if="loading"
      :count="3"
    />
    <div
      v-else-if="referenceError"
      role="alert"
      class="feedback-error"
    >
      {{ referenceError
      }}<button
        type="button"
        class="btn-secondary mt-3"
        @click="loadReferences"
      >
        Retry
      </button>
    </div>
    <form
      v-else
      class="space-y-5"
      :aria-busy="busy"
      @submit.prevent="onSubmit"
    >
      <div>
        <label
          class="field-label"
          for="register-name"
          >Full name</label
        ><input
          id="register-name"
          v-model="form.name"
          class="input-field"
          autocomplete="name"
          maxlength="255"
          required
          :aria-invalid="!!fieldErrors.name"
          aria-describedby="name-error"
        />
        <p
          v-if="fieldErrors.name"
          id="name-error"
          class="mt-1 text-xs text-red-700"
        >
          {{ fieldErrors.name.join(' ') }}
        </p>
      </div>
      <div>
        <label
          class="field-label"
          for="register-email"
          >Email address</label
        ><input
          id="register-email"
          v-model="form.email"
          type="email"
          class="input-field"
          autocomplete="email"
          maxlength="255"
          required
          :aria-invalid="!!fieldErrors.email"
          aria-describedby="email-error"
        />
        <p
          v-if="fieldErrors.email"
          id="email-error"
          class="mt-1 text-xs text-red-700"
        >
          {{ fieldErrors.email.join(' ') }}
        </p>
      </div>
      <div>
        <label
          class="field-label"
          for="register-password"
          >Password</label
        ><input
          id="register-password"
          v-model="form.password"
          type="password"
          class="input-field"
          autocomplete="new-password"
          minlength="8"
          required
          :aria-invalid="!!fieldErrors.password"
          aria-describedby="password-help password-error"
        />
        <p
          id="password-help"
          class="mt-1 text-xs text-slate-500"
        >
          Use at least 8 characters.
        </p>
        <p
          v-if="fieldErrors.password"
          id="password-error"
          class="mt-1 text-xs text-red-700"
        >
          {{ fieldErrors.password.join(' ') }}
        </p>
      </div>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label
            class="field-label"
            for="register-matricule"
            >Matricule</label
          ><input
            id="register-matricule"
            v-model="form.matricule"
            class="input-field"
            required
            :aria-invalid="!!fieldErrors.matricule"
            aria-describedby="matricule-error"
          />
          <p
            v-if="fieldErrors.matricule"
            id="matricule-error"
            class="mt-1 text-xs text-red-700"
          >
            {{ fieldErrors.matricule.join(' ') }}
          </p>
        </div>
        <div>
          <label
            class="field-label"
            for="register-level"
            >Level</label
          ><select
            id="register-level"
            v-model="form.level"
            class="input-field"
            required
          >
            <option
              v-for="level in levels"
              :key="level"
              :value="level"
            >
              Level {{ level }}
            </option>
          </select>
        </div>
      </div>
      <fieldset class="space-y-4 rounded-xl border border-slate-200 p-4">
        <legend
          class="px-2 text-xs font-semibold uppercase tracking-wider text-slate-500"
        >
          Academic details
        </legend>
        <div>
          <label
            class="field-label"
            for="register-faculty"
            >Faculty</label
          ><select
            id="register-faculty"
            v-model.number="form.faculty_id"
            class="input-field"
            required
          >
            <option
              disabled
              :value="0"
            >
              Choose your faculty
            </option>
            <option
              v-for="item in faculties"
              :key="item.id"
              :value="item.id"
            >
              {{ item.name }}
            </option>
          </select>
        </div>
        <div>
          <label
            class="field-label"
            for="register-department"
            >Department</label
          ><select
            id="register-department"
            v-model.number="form.department_id"
            class="input-field"
            required
            :disabled="!form.faculty_id"
          >
            <option
              disabled
              :value="0"
            >
              Choose your department
            </option>
            <option
              v-for="item in filteredDepartments"
              :key="item.id"
              :value="item.id"
            >
              {{ item.name }}
            </option>
          </select>
        </div>
        <div>
          <label
            class="field-label"
            for="register-programme"
            >Programme</label
          ><select
            id="register-programme"
            v-model.number="form.programme_id"
            class="input-field"
            required
            :disabled="!form.department_id"
          >
            <option
              disabled
              :value="0"
            >
              Choose your programme
            </option>
            <option
              v-for="item in filteredProgrammes"
              :key="item.id"
              :value="item.id"
            >
              {{ item.name }}
            </option>
          </select>
          <p
            v-if="form.department_id && !filteredProgrammes.length"
            class="mt-2 text-xs text-amber-800"
          >
            No programmes are available for this department. Contact your
            university administrator.
          </p>
        </div>
      </fieldset>
      <div
        v-if="error"
        role="alert"
        class="feedback-error"
      >
        <p>{{ error }}</p>
        <p
          v-for="key in [
            'faculty_id',
            'department_id',
            'programme_id',
            'level'
          ]"
          :key="key"
        >
          {{ fieldErrors[key]?.join(' ') }}
        </p>
      </div>
      <button
        type="submit"
        class="btn-primary w-full"
        :disabled="busy"
      >
        {{ busy ? 'Creating account…' : 'Create account' }}
      </button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">
      Already registered?
      <router-link
        to="/login"
        class="inline-flex min-h-11 items-center font-semibold text-primary hover:underline"
        >Sign in</router-link
      >
    </p>
  </AuthLayout>
</template>
