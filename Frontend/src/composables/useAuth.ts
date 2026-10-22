import { computed, ref } from 'vue'
import type { DepartmentAssignment, User } from '../types'
import api from '../services/api'

// Module-level state — persists across component uses
const user = ref<User | null>(null)

function normalizeUser(rawUser: User | null): User | null {
  if (!rawUser) return null
  if (rawUser.staff_profile?.departments) {
    rawUser.staff_profile.departments = rawUser.staff_profile.departments.map((dept: DepartmentAssignment) => ({
      ...dept,
      is_primary: Boolean(dept.is_primary || dept.pivot?.is_primary)
    }))
  }
  return rawUser
}

export function useAuth() {
  // On first load, try to restore user from localStorage
  const storedUser = localStorage.getItem('user')
  if (storedUser && !user.value) {
    try {
      user.value = normalizeUser(JSON.parse(storedUser))
    } catch {
      // Ignore JSON parse errors from corrupt localStorage
    }
  }

  const isAuthenticated = computed(() => !!user.value && !!localStorage.getItem('token'))

  function setUser(newUser: User) {
    const normalized = normalizeUser(newUser) ?? newUser
    user.value = normalized
    localStorage.setItem('user', JSON.stringify(normalized))
  }

  async function refreshUser(): Promise<User | null> {
    const token = localStorage.getItem('token')
    if (!token) return null
    try {
      const response = await api.get('/user')
      if (response?.data) {
        setUser(response.data)
        return response.data
      }
    } catch {
      // If the token is invalid or endpoint fails, keep current state
    }
    return user.value
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
    refreshUser,
    clearAuth,
  }
}