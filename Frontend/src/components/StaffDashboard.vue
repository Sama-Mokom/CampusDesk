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
      <div class="flex gap-2 shrink-0">
     </div>
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
              <button type="button" class="btn-secondary text-sm" @click="openDetails(stage)">Details</button>
            </div>
          </div>
        </div>
      </div>
    </div>

 <!-- Details Modal -->
  <div
    v-if="detailsModal.open && detailsModal.stage"
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
    @click.self="detailsModal.open = false"
  >
    <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden">
      <!-- Header -->
      <div class="p-6 border-b border-neutral-200 flex justify-between items-start bg-white shrink-0">
        <div>
          <h2 class="text-xl font-bold text-primary">{{ detailsModal.stage.request?.request_type }}</h2>
          <p class="text-sm text-neutral-600 mt-1">
            Student: <span class="font-semibold">{{ detailsModal.stage.request?.student_name }}</span> 
            ({{ detailsModal.stage.request?.student_matricule }})
          </p>
        </div>
        <button type="button" class="text-neutral-500 hover:text-foreground text-2xl" @click="detailsModal.open = false">×</button>
      </div>

      <!-- Description & Tab Navigation -->
      <div class="px-6 pt-4 bg-neutral-50 border-b border-neutral-200 shrink-0">
        <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-1">Description</p>
        <p class="text-sm text-foreground mb-4">{{ detailsModal.stage.request?.description }}</p>

        <div class="flex gap-4 border-b border-neutral-200">
          <button
            type="button"
            class="pb-2 text-sm font-medium border-b-2 transition-colors"
            :class="activeTab === 'timeline' ? 'border-primary text-primary' : 'border-transparent text-neutral-500 hover:text-foreground'"
            @click="activeTab = 'timeline'"
          >
            Progression Timeline
          </button>
          <button
            type="button"
            class="pb-2 text-sm font-medium border-b-2 transition-colors flex items-center gap-1.5"
            :class="activeTab === 'attachments' ? 'border-primary text-primary' : 'border-transparent text-neutral-500 hover:text-foreground'"
            @click="activeTab = 'attachments'"
          >
            Attachments
            <span 
              v-if="detailsModal.stage.request?.attachments?.length" 
              class="px-1.5 py-0.5 text-xs bg-neutral-200 rounded-full font-bold"
            >
              {{ detailsModal.stage.request.attachments.length }}
            </span>
          </button>
        </div>
      </div>

      <!-- Tab Body -->
      <div class="p-6 overflow-y-auto flex-1">
        <!-- Timeline Tab -->
        <div v-if="activeTab === 'timeline'">
          <div v-if="detailsModal.loading" class="text-center py-6 text-neutral-500">Loading timeline...</div>
          <RequestTimeline v-else :stages="detailsModal.stages" />
        </div>

        <!-- Attachments Tab -->
        <div v-else-if="activeTab === 'attachments'">
          <DocumentViewer :attachments="detailsModal.stage.request?.attachments ?? []" />
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
          <select v-model="resolveStatus" class="input-field">
            <option value="approved">Approve</option>
            <option value="rejected">Reject</option>
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
import { fetchRequestStages, fetchStaffQueue, resolveStage, claimStage, fetchMyCases } from '../services/stages'
import type { RequestStage } from '../types'
import LevelBadge from './LevelBadge.vue'
import RequestTimeline from './RequestTimeline.vue'
// import StatusBadge from './StatusBadge.vue'
import DocumentViewer from './DocumentViewer.vue'

const props = defineProps<{ deptAdminMode?: boolean }>()
const activeTab = ref<'timeline' | 'attachments'>('timeline')
const auth = useAuth()
const staffUser = computed(() => auth.user.value)
const sp = computed(() => staffUser.value?.staff_profile)

const allStages = ref<RequestStage[]>([])
const activeCases = ref<RequestStage[]>([])
const loading = ref(false)
const queueError = ref('')

const deptSelect = ref<number>(0)

const deptOptions = computed(() => sp.value?.departments ?? [])

const detailsModal = reactive({
  open: false,
  stage: null as RequestStage | null,
  stages: [] as RequestStage[],
  loading: false
})

function openDetails(stage: RequestStage) {
  detailsModal.stage = stage
  detailsModal.stages = []
  activeTab.value = 'timeline'
  detailsModal.open = true
  detailsModal.loading = true
  
  fetchRequestStages(stage.request_id)
    .then(data => { 
      // Handle Laravel resource wrapper `{ data: [...] }` or raw array `[...]`
      detailsModal.stages = Array.isArray(data) ? data : (data as any)?.data ?? [] 
    })
    .catch(() => { 
      detailsModal.stages = [stage] 
    })
    .finally(() => { 
      detailsModal.loading = false 
    })
}

const primaryDeptName = computed(() => {
  const p = sp.value?.departments?.find(d => d.is_primary)
  return p?.name ?? sp.value?.departments?.[0]?.name ?? ''
})

const selectedDeptName = computed(() => {
  return deptOptions.value.find(d => d.id === deptSelect.value)?.name ?? ''
})

async function loadQueue() {
  loading.value = true
  try {
    const [queue, cases] = await Promise.all([
      fetchStaffQueue(),
      fetchMyCases()
    ])
    allStages.value = queue
    activeCases.value = cases
  } catch {
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

const myActiveStages = computed(() => activeCases.value)


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
  note: ''
})
const resolveStatus = ref<'approved' | 'rejected'>('approved')
const resolveError = ref('')

function openResolve(stage: RequestStage) {
  resolveModal.stage = stage
  resolveStatus.value = 'approved'
  resolveModal.note = ''
  resolveError.value = ''
  resolveModal.open = true
}

async function submitResolve() {
  resolveError.value = ''
  const stage = resolveModal.stage
  if (!stage) return

  const validStatuses = ['approved', 'rejected'] as const
  let status: 'approved' | 'rejected' = resolveStatus.value
  if (!validStatuses.includes(status)) {
    console.warn('[submitResolve] resolveStatus.value was unexpected:', status, '— defaulting to "approved"')
    status = 'approved'
  }

  if (status === 'rejected' && !resolveModal.note.trim()) {
    resolveError.value = 'A staff note is required when rejecting.'
    return
  }
  try {
    await resolveStage(stage.request_id, stage.id, {
      status,
      staff_note: resolveModal.note.trim()
    })
    resolveModal.open = false
    await loadQueue()
  } catch {
    resolveError.value = 'Failed to resolve stage.'
  }
}
</script>
