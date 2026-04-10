<template>
  <div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4">
    <div class="card max-w-md w-full">
      <h2 class="text-primary font-semibold text-xl mb-6">Sign in</h2>
      <p class="text-sm text-neutral-600 mb-4">
        Use mock accounts: <span class="font-mono text-xs">sama@ub.cm</span> / student ·
        <span class="font-mono text-xs">tabi@ub.cm</span> / staff ·
        <span class="font-mono text-xs">mbah@ub.cm</span> / staff ·
        <span class="font-mono text-xs">admin@ub.cm</span> / admin
      </p>
      <form @submit.prevent="onSubmit" class="space-y-4">
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
import { useRoute, useRouter } from 'vue-router'
import { useMockData } from '@/composables/useMockData'

const router = useRouter()
const route = useRoute()
const { login, sessionUser } = useMockData()

const email = ref('')
const password = ref('')
const error = ref('')

function homePath(): string {
  const u = sessionUser.value
  if (!u) return '/login'
  if (u.role === 'student') return '/student'
  const level = u.staff_profile?.admin_level
  if (level === 'super_admin') return '/admin'
  if (level === 'dept_admin') return '/dept-admin'
  return '/staff'
}

function onSubmit() {
  error.value = ''
  const u = login(email.value.trim(), password.value)
  if (!u) {
    error.value = 'Invalid email or password.'
    return
  }
  const redir = route.query.redirect as string | undefined
  if (redir && redir.startsWith('/')) {
    router.replace(redir)
  } else {
    router.replace(homePath())
  }
}
</script>
