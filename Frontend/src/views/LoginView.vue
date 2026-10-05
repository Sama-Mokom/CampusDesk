<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import { login } from '../services/auth'
import AuthLayout from '../components/layout/AuthLayout.vue'
import AppIcon from '../components/ui/AppIcon.vue'
const router = useRouter()
const route = useRoute()
const { user, setUser } = useAuth()
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const busy = ref(false)
const error = ref('')
function homePath() {
  if (user.value?.role === 'student') return '/student'
  if (user.value?.staff_profile?.admin_level === 'super_admin') return '/admin'
  if (user.value?.staff_profile?.admin_level === 'dept_admin')
    return '/dept-admin'
  return '/staff'
}
async function onSubmit() {
  if (busy.value) return
  error.value = ''
  busy.value = true
  try {
    setUser(
      await login({ email: email.value.trim(), password: password.value })
    )
    const redirect = route.query.redirect
    await router.replace(
      typeof redirect === 'string' &&
        redirect.startsWith('/') &&
        !redirect.startsWith('//')
        ? redirect
        : homePath()
    )
  } catch (err) {
    error.value =
      axios.isAxiosError(err) &&
      (err.response?.status === 422 || err.response?.status === 401)
        ? err.response.data.message || 'Invalid email or password.'
        : 'We could not sign you in. Check your connection and try again.'
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <AuthLayout
    title="Welcome back"
    description="Sign in to manage your requests and campus services."
  >
    <form
      class="space-y-5"
      :aria-busy="busy"
      @submit.prevent="onSubmit"
    >
      <div>
        <label
          class="field-label"
          for="login-email"
          >Email address</label
        ><input
          id="login-email"
          v-model="email"
          type="email"
          class="input-field"
          autocomplete="username"
          placeholder="you@example.edu"
          required
          :disabled="busy"
        />
      </div>
      <div>
        <label
          class="field-label"
          for="login-password"
          >Password</label
        >
        <div class="relative">
          <input
            id="login-password"
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            class="input-field pr-20"
            autocomplete="current-password"
            required
            :disabled="busy"
          /><button
            type="button"
            class="absolute inset-y-0 right-0 min-w-16 px-3 text-xs font-semibold text-primary"
            :aria-pressed="showPassword"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
            @click="showPassword = !showPassword"
          >
            {{ showPassword ? 'Hide' : 'Show' }}
          </button>
        </div>
      </div>
      <p
        v-if="error"
        role="alert"
        class="feedback-error"
      >
        {{ error }}
      </p>
      <button
        type="submit"
        class="btn-primary w-full"
        :disabled="busy"
      >
        {{ busy ? 'Signing in…' : 'Sign in' }}<AppIcon name="arrow" />
      </button>
    </form>
    <p
      class="mt-8 border-t border-slate-200 pt-6 text-center text-sm text-slate-500"
    >
      New student?
      <router-link
        to="/register"
        class="inline-flex min-h-11 items-center font-semibold text-primary hover:underline"
        >Create an account</router-link
      >
    </p>
    <p class="mt-4 text-center text-xs leading-relaxed text-slate-500">
      Staff accounts are provided by your university administrator.
    </p>
  </AuthLayout>
</template>
