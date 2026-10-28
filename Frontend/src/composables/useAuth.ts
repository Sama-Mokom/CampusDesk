import { computed, ref } from 'vue'
import type { DepartmentAssignment, User } from '../types'
import api from '../services/api'

// Module-level state — persists across component uses
const user = ref<User | null>(null)

function normalizeUser(rawUser: User | null): User | null {
  if (!rawUser || typeof rawUser !== 'object') return null
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
      const parsed = JSON.parse(storedUser)
      if (parsed && typeof parsed === 'object' && parsed.id) {
        user.value = normalizeUser(parsed)
      } else {
        localStorage.removeItem('user')
      }
    } catch {
      localStorage.removeItem('user')
    }
  }

  const isAuthenticated = computed(
    () => !!user.value && typeof user.value === 'object' && !!user.value.id && !!localStorage.getItem('token')
  )

  function setUser(newUser: User) {
    if (!newUser || typeof newUser !== 'object' || !newUser.id) return
    const normalized = normalizeUser(newUser) ?? newUser
    user.value = normalized
    localStorage.setItem('user', JSON.stringify(normalized))
  }

  async function refreshUser(): Promise<User | null> {
    const token = localStorage.getItem('token')
    if (!token) return null
    try {
      const response = await api.get('/user')
      if (response?.data && typeof response.data === 'object' && response.data.id) {
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
