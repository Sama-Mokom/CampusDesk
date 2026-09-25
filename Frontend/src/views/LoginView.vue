<template>
  <div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4">
    <div class="card max-w-md w-full">
      <h2 class="text-primary font-semibold text-xl mb-6">Sign in</h2>
      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Email</label>
          <input v-model="email" type="email" class="input-field" autocomplete="username" required />
        </div>
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Password</label>
          <input v-model="password" type="password" class="input-field" autocomplete="current-password" required />
        </div>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <button type="submit" class="btn-primary w-full">Sign in</button>
      </form>
      <p class="mt-4 text-sm text-neutral-600 text-center">
        New student?
        <router-link to="/register" class="text-primary font-medium hover:underline">Create an account</router-link>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import { login } from '../services/auth'

const router = useRouter()
const route = useRoute()
const { user, setUser } = useAuth()
const email = ref('')
const password = ref('')
const error = ref('')

function homePath(): string {
  const currentUser = user.value
  if (!currentUser) return '/login'
  if (currentUser.role === 'student') return '/student'
  if (currentUser.staff_profile?.admin_level === 'super_admin') return '/admin'
  if (currentUser.staff_profile?.admin_level === 'dept_admin') return '/dept-admin'
  return '/staff'
}

async function onSubmit() {
  error.value = ''
  try {
    const loggedInUser = await login({ email: email.value.trim(), password: password.value })
    setUser(loggedInUser)
    const redirect = route.query.redirect as string | undefined
    router.replace(redirect?.startsWith('/') ? redirect : homePath())
  } catch (err) {
    error.value = axios.isAxiosError(err) && err.response?.status === 422
      ? err.response.data.message || 'Invalid email or password.'
      : 'A connection error occurred. Please try again.'
  }
}
</script>
