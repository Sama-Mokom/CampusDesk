import { computed, ref } from 'vue'
import type { User } from '../types'

// Module-level state — persists across component uses
const user = ref<User | null>(null)

export function useAuth() {
  // On first load, try to restore user from localStorage
  const storedUser = localStorage.getItem('user')
  if (storedUser && !user.value) {
    user.value = JSON.parse(storedUser)
  }

  const isAuthenticated = computed(() => !!user.value && !!localStorage.getItem('token'))

  function setUser(newUser: User) {
    user.value = newUser
    localStorage.setItem('user', JSON.stringify(newUser))
  }

  function clearAuth() {
    user.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('user')
  }

  return {
    user,
    isAuthenticated,
    setUser,
    clearAuth,
  }
}