<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  fetchDepartmentAdminRequests,
  type DepartmentAdminOverview
} from '@/services/deptAdmin'
import PageHeader from '@/components/ui/PageHeader.vue'
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue'

const overview = ref<DepartmentAdminOverview | null>(null)
const loading = ref(true)
const error = ref('')
const metrics = computed(() => [
  {
    label: 'All stages',
    value: overview.value?.stats.total,
    hint: 'Through this department'
  },
  {
    label: 'Claimable now',
    value: overview.value?.stats.claimable,
    hint: 'Ready for your team'
  },
  {
    label: 'Blocked',
    value: overview.value?.stats.blocked,
    hint: 'Awaiting earlier stages'
  },
  {
    label: 'In review',
    value: overview.value?.stats.in_review,
    hint: 'Currently being handled'
  },
  {
    label: 'Completed',
    value: overview.value?.stats.completed,
    hint: 'Approved or rejected'
  }
])
async function load() {
  loading.value = true
  error.value = ''
  try {
    overview.value = await fetchDepartmentAdminRequests()
  } catch {
    error.value = 'Unable to load department overview. Please try again.'
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Department administration"
      title="Department overview"
      :description="
        overview?.department.name || 'Your primary department at a glance.'
      "
    >
      <template #actions
        ><button
          type="button"
          class="btn-secondary"
          :disabled="loading"
          @click="load"
        >
          {{ loading ? 'Refreshing…' : 'Refresh overview' }}
        </button></template
      >
    </PageHeader>
    <div
      v-if="error"
      role="alert"
      class="feedback-error"
    >
      {{ error
      }}<button
        type="button"
        class="btn-secondary ml-3"
        :disabled="loading"
        @click="load"
      >
        Try again
      </button>
    </div>
    <SkeletonLoader
      v-if="loading && !overview"
      :count="3"
    />
    <section
      v-else-if="overview"
      class="grid grid-cols-2 gap-3 xl:grid-cols-5"
      aria-label="Department stage statistics"
      :aria-busy="loading"
    >
      <div
        v-for="metric in metrics"
        :key="metric.label"
        class="card min-w-0"
      >
        <p class="text-xs font-medium text-slate-500">{{ metric.label }}</p>
        <p class="mt-3 text-3xl font-bold tracking-tight">
          {{ loading || error ? '—' : metric.value }}
        </p>
        <p class="mt-1 text-xs text-slate-500">{{ metric.hint }}</p>
      </div>
    </section>
    <section class="card">
      <h2 class="text-lg font-semibold">Manage department work</h2>
      <p class="mt-2 text-sm text-slate-500">
        Review claimable and blocked stages, inspect assignments, and reassign
        active work to your team.
      </p>
      <p
        v-if="overview"
        class="mt-2 text-xs text-slate-500"
      >
        {{ overview.staff.length }} staff members in this department
      </p>
      <router-link
        to="/dept-admin/requests"
        class="btn-primary mt-5"
        >Open department requests</router-link
      >
    </section>
  </div>
</template>
