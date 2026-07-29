<template>
  <div class="space-y-6">
    <!-- Header Card -->
    <div class="card">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 class="text-xl text-primary font-semibold">{{ staffUser?.name }}</h2>
          <p class="text-sm text-neutral-600 mt-1">
            <span class="font-mono">{{ sp?.staff_id }}</span>
            <span v-if="primaryDeptName"> · Primary: {{ primaryDeptName }}</span>
          </p>
        </div>
        <div v-if="deptOptions.length > 1" class="flex flex-wrap gap-2 items-center">
          <span class="text-sm text-neutral-600">Department:</span>
          <select v-model.number="deptSelect" class="input-field max-w-xs">
            <option v-for="d in deptOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Error Banner -->
    <div v-if="queueError" class="p-4 bg-red-100 text-red-700 rounded-lg">
      {{ queueError }}
    </div>

    <!-- Main Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="card">
        <p class="text-sm text-neutral-600">Unclaimed (this dept)</p>
        <p class="text-3xl font-bold text-yellow-600">{{ unclaimedStages.length }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">My active cases</p>
        <p class="text-3xl font-bold text-blue-600">{{ myActiveStages.length }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">Resolved today</p>
        <p class="text-3xl font-bold text-green-600">{{ resolvedTodayCount }}</p>
      </div>
    </div>

    <!-- Unclaimed Queue -->
    <div>
      <h2 class="text-xl text-primary font-semibold mb-3">Unclaimed queue</h2>
      <div v-if="loading" class="card text-center text-neutral-500">Loading queue...</div>
      <div v-else-if="unclaimedStages.length === 0" class="card text-center text-neutral-500">
        <p>No unclaimed stages for this department.</p>
      </div>
      <div v-else class="space-y-3">
        <div v-for="stage in unclaimedStages" :key="stage.id" class="card">
          <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
            <div class="flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-foreground">{{ stage.request?.student_name ?? 'Unknown' }}</h3>
                <span class="text-xs font-mono text-neutral-600">{{ stage.request?.student_matricule }}</span>
                <LevelBadge v-if="stage.request?.student_level" :level="stage.request.student_level" />
              </div>
              <p class="text-sm text-primary font-medium mt-1">{{ stage.request?.request_type }}</p>
              <p class="text-sm text-foreground mt-2 line-clamp-3">{{ stage.request?.description }}</p>
              <p class="text-xs text-neutral-500 mt-2">{{ stage.request?.created_at ? formatDate(stage.request.created_at) : '' }}</p>
            </div>
            <button type="button" class="btn-primary self-start shrink-0" @click="pickUp(stage)">Pick up</button>
          </div>
        </div>
      </div>
    </div>

    <!-- My Active Cases -->
    <div>
      <h2 class="text-xl text-primary font-semibold mb-3">My active cases</h2>
      <div v-if="myActiveStages.length === 0" class="card text-center text-neutral-500">
        <p>No stages in review assigned to you.</p>
      </div>
      <div v-else class="space-y-3">
        <div v-for="stage in myActiveStages" :key="stage.id" class="card">
          <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
            <div class="flex-1">
              <h3 class="font-semibold text-foreground">{{ stage.request?.student_name ?? 'Unknown' }}</h3>
              <p class="text-xs text-neutral-600">{{ stage.request?.student_matricule }}</p>
              <p class="text-sm text-primary font-medium mt-1">{{ stage.request?.request_type }}</p>
              <p class="text-sm text-foreground mt-2 line-clamp-3">{{ stage.request?.description }}</p>
              <p class="text-xs text-neutral-500 mt-2">{{ stage.request?.created_at ? formatDate(stage.request.created_at) : '' }}</p>
            </div>
            <div class="flex gap-2 shrink-0">
              <button type="button" class="btn-primary text-sm" @click="openResolve(stage)">Update status</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Resolve Modal -->
    <div
      v-if="resolveModal.open && resolveModal.stage"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
    >
      <div class="bg-white rounded-lg max-w-md w-full">
        <div class="border-b border-neutral-200 p-4">
          <h2 class="text-lg text-primary font-bold">Update stage status</h2>
        </div>
        <div class="p-4 space-y-4">
          <p class="text-sm text-neutral-600">{{ resolveModal.stage.request?.request_type }}</p>
          <label class="block text-sm text-primary font-medium">Resolution</label>
          <select v-model="resolveModal.action" class="input-field">
            <option value="approve">Approve</option>
            <option value="reject">Reject</option>
          </select>
          <label class="block text-sm text-primary font-medium">Staff note</label>
          <textarea v-model="resolveModal.note" class="input-field" rows="3" placeholder="Required if rejecting" />
          <p v-if="resolveError" class="text-sm text-red-600">{{ resolveError }}</p>
          <div class="flex gap-2 justify-end">
            <button type="button" class="btn-secondary" @click="resolveModal.open = false">Cancel</button>
            <button type="button" class="btn-primary" @click="submitResolve">Submit</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useAuth } from '../composables/useAuth'
import { fetchStaffQueue, resolveStage, claimStage } from '../services/stages'
import type { RequestStage } from '../types'
import LevelBadge from './LevelBadge.vue'

const props = defineProps<{ deptAdminMode?: boolean }>()

const auth = useAuth()
const staffUser = computed(() => auth.user.value)
const sp = computed(() => staffUser.value?.staff_profile)

const allStages = ref<RequestStage[]>([])
const loading = ref(false)
const queueError = ref('')

const deptSelect = ref<number>(0)

const deptOptions = computed(() => sp.value?.departments ?? [])

const primaryDeptName = computed(() => {
  const p = sp.value?.departments?.find(d => d.is_primary)
  return p?.name ?? sp.value?.departments?.[0]?.name ?? ''
})

const selectedDeptName = computed(() => {
  return deptOptions.value.find(d => d.id === deptSelect.value)?.name ?? ''
})

async function loadQueue() {
  loading.value = true
  queueError.value = ''
  try {
    allStages.value = await fetchStaffQueue()
  } catch (err) {
    queueError.value = 'Failed to load queue.'
  } finally {
    loading.value = false
  }
}

const unclaimedStages = computed(() =>
  allStages.value.filter(s => 
    s.status === 'pending' && 
    !s.handled_by &&
    (!selectedDeptName.value || s.department_name === selectedDeptName.value)
  )
)

const myActiveStages = computed(() =>
  allStages.value.filter(s => 
    s.status === 'in_review' && 
    s.handled_by === staffUser.value?.name
  )
)

const resolvedTodayCount = computed(() => {
  const today = new Date().toDateString()
  return allStages.value.filter(s => 
    (s.status === 'approved' || s.status === 'rejected') &&
    s.updated_at &&
    new Date(s.updated_at).toDateString() === today
  ).length
})

onMounted(async () => {
  const primary = sp.value?.departments?.find(d => d.is_primary)
  deptSelect.value = primary?.id ?? sp.value?.departments?.[0]?.id ?? 0
  await loadQueue()
})

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

async function pickUp(stage: RequestStage) {
  try {
    await claimStage(stage.request_id, stage.id)
    await loadQueue()
  } catch {
    queueError.value = 'Failed to claim stage.'
  }
}

const resolveModal = reactive({
  open: false,
  stage: null as RequestStage | null,
  action: 'approve' as 'approve' | 'reject',
  note: ''
})
const resolveError = ref('')

function openResolve(stage: RequestStage) {
  resolveModal.stage = stage
  resolveModal.action = 'approve'
  resolveModal.note = ''
  resolveError.value = ''
  resolveModal.open = true
}

async function submitResolve() {
  resolveError.value = ''
  const stage = resolveModal.stage
  if (!stage) return

  if (resolveModal.action === 'reject' && !resolveModal.note.trim()) {
    resolveError.value = 'A staff note is required when rejecting.'
    return
  }

  try {
    await resolveStage(stage.request_id, stage.id, {
      action: resolveModal.action,
      staff_note: resolveModal.note.trim()
    })
    resolveModal.open = false
    await loadQueue()
  } catch {
    resolveError.value = 'Failed to resolve stage.'
  }
}
</script>
