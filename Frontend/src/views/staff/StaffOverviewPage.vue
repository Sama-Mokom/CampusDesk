<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { fetchStaffQueue, fetchMyCases } from '@/services/stages'
import PageHeader from '@/components/ui/PageHeader.vue'
import type { RequestStage } from '@/types'

const { user } = useAuth()
const departments = computed(() => user.value?.staff_profile?.departments ?? [])
const primaryDepartment = computed(
  () =>
    departments.value.find((item) => item.is_primary) ?? departments.value[0]
)
const queue = ref<RequestStage[]>([])
const cases = ref<RequestStage[]>([])
const loading = ref(true)
const queueError = ref('')
const casesError = ref('')
const unclaimed = computed(() =>
  queue.value.filter(
    (stage) =>
      stage.status === 'pending' &&
      !stage.handled_by &&
      (!primaryDepartment.value ||
        stage.department_name === primaryDepartment.value.name)
  )
)

async function load() {
  loading.value = true
  queueError.value = ''
  casesError.value = ''
  const [queueResult, casesResult] = await Promise.allSettled([
    fetchStaffQueue(),
    fetchMyCases()
  ])
  if (queueResult.status === 'fulfilled') queue.value = queueResult.value
  else queueError.value = 'The department queue could not be loaded.'
  if (casesResult.status === 'fulfilled') cases.value = casesResult.value
  else casesError.value = 'Your active cases could not be loaded.'
  loading.value = false
}
onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Staff workspace"
      title="Workspace overview"
      description="Your department queue and assigned work at a glance."
    >
      <template #actions>
        <button
          type="button"
          class="btn-secondary"
          :disabled="loading"
          @click="load"
        >
          {{ loading ? 'Refreshing…' : 'Refresh overview' }}
        </button>
      </template>
    </PageHeader>
    <section
      class="card"
      aria-label="Staff profile"
    >
      <h2 class="text-lg font-semibold">{{ user?.name }}</h2>
      <p class="mt-1 text-sm text-slate-500">
        {{ user?.staff_profile?.staff_id }} ·
        {{ primaryDepartment?.name || 'No department assigned' }}
      </p>
    </section>
    <div
      v-if="queueError || casesError"
      role="alert"
      class="feedback-error"
    >
      <p v-if="queueError">{{ queueError }}</p>
      <p v-if="casesError">{{ casesError }}</p>
      <button
        type="button"
        class="btn-secondary mt-3"
        :disabled="loading"
        @click="load"
      >
        Try again
      </button>
    </div>
    <section
      class="grid gap-4 sm:grid-cols-3"
      aria-label="Workspace overview"
      :aria-busy="loading"
    >
      <div class="card">
        <p class="text-sm text-slate-500">Unclaimed requests</p>
        <p class="mt-3 text-3xl font-bold">
          {{ loading || queueError ? '—' : unclaimed.length }}
        </p>
        <p class="mt-2 text-xs text-slate-500">In your primary department</p>
      </div>
      <div class="card">
        <p class="text-sm text-slate-500">My active cases</p>
        <p class="mt-3 text-3xl font-bold text-sky-700">
          {{ loading || casesError ? '—' : cases.length }}
        </p>
        <p class="mt-2 text-xs text-slate-500">Across your departments</p>
      </div>
      <div class="card">
        <p class="text-sm text-slate-500">My departments</p>
        <p class="mt-3 text-3xl font-bold">{{ departments.length }}</p>
        <p class="mt-2 text-xs text-slate-500">
          Assigned to your staff profile
        </p>
      </div>
    </section>
    <div class="grid gap-4 md:grid-cols-2">
      <section class="card">
        <h2 class="text-lg font-semibold">Pick up a request</h2>
        <p class="my-3 text-sm text-slate-500">
          Browse eligible requests and choose a stage to work on.
        </p>
        <router-link
          to="/staff/queue"
          class="btn-primary"
          >Open unclaimed queue</router-link
        >
      </section>
      <section class="card">
        <h2 class="text-lg font-semibold">Continue your work</h2>
        <p class="my-3 text-sm text-slate-500">
          Inspect documents and record decisions for your assigned cases.
        </p>
        <router-link
          to="/staff/cases"
          class="btn-secondary"
          >Open my active cases</router-link
        >
      </section>
    </div>
  </div>
</template>
