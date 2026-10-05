import { onMounted, ref } from 'vue'
import { adminStats } from '@/services/admin'
import type { Stats } from '@/services/admin'
import { message, statuses } from './shared'

export function useAdminOverview() {
  const stats = ref<Stats | null>(null)
  const statsLoading = ref(true)
  const error = ref('')
  async function loadStats() {
    if (statsLoading.value && stats.value) return
    statsLoading.value = true
    error.value = ''
    try {
      stats.value = await adminStats()
    } catch (e) {
      error.value = message(e)
    } finally {
      statsLoading.value = false
    }
  }
  onMounted(loadStats)
  return { stats, statsLoading, error, statuses, loadStats }
}
