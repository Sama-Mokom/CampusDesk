<template>
  <div class="space-y-6">
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
          <select v-model.number="deptSelect" class="input-field max-w-xs" @change="onDeptSelect">
            <option v-for="d in deptOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="deptAdminMode" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="card">
        <p class="text-sm text-neutral-600">Requests through department</p>
        <p class="text-3xl font-bold text-primary">{{ departmentStats.total_through_dept }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">Avg resolution (hours, est.)</p>
        <p class="text-3xl font-bold text-primary-light">{{ deptAvgHours }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">Rejection rate</p>
        <p class="text-3xl font-bold text-red-600">{{ rejectionPct }}%</p>
      </div>
    </div>

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

    <div v-if="deptAdminMode" class="card overflow-x-auto">
      <h2 class="text-lg text-primary font-semibold mb-4">Department overview</h2>
      <div class="flex flex-wrap gap-4 mb-4">
        <select v-model="overviewStageFilter" class="input-field max-w-xs">
          <option value="">All stage statuses</option>
          <option value="pending">Pending</option>
          <option value="in_review">In review</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
        <input v-model="overviewDateFrom" type="date" class="input-field max-w-[11rem]" />
        <input v-model="overviewDateTo" type="date" class="input-field max-w-[11rem]" />
      </div>
      <table class="w-full text-sm">
        <thead class="border-b border-neutral-300 bg-neutral-50">
          <tr>
            <th class="text-left py-2 px-2 font-semibold text-primary">Student</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Request type</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Stage</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Claimed by</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-200">
          <tr v-for="row in filteredOverview" :key="`${row.request.id}-${row.stage.id}`">
            <td class="py-2 px-2">
              <p class="font-medium">{{ studentName(row.request.student_id) }}</p>
              <p class="text-xs text-neutral-500">{{ studentMatricule(row.request.student_id) }}</p>
            </td>
            <td class="py-2 px-2">{{ row.request.request_type.name }}</td>
            <td class="py-2 px-2">
              <StatusBadge kind="stage" :status="row.stage.status" />
            </td>
            <td class="py-2 px-2">{{ row.stage.handled_by?.name ?? 'Unclaimed' }}</td>
            <td class="py-2 px-2">
              <button
                v-if="row.stage.handled_by && row.stage.status === 'in_review'"
                type="button"
                class="text-xs btn-secondary"
                @click="openReassign(row)"
              >
                Reassign
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div>
      <h2 class="text-xl text-primary font-semibold mb-3">Unclaimed queue</h2>
      <div v-if="unclaimedStages.length === 0" class="card text-center text-neutral-500">
        <p>No unclaimed stages for this department.</p>
      </div>
      <div v-else class="space-y-3">
        <div v-for="item in unclaimedStages" :key="`${item.request.id}-${item.stage.id}`" class="card">
          <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
            <div class="flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-foreground">{{ studentName(item.request.student_id) }}</h3>
                <span class="text-xs font-mono text-neutral-600">{{ studentMatricule(item.request.student_id) }}</span>
                <LevelBadge v-if="studentLevel(item.request.student_id)" :level="studentLevel(item.request.student_id)!" />
              </div>
              <p class="text-sm text-primary font-medium mt-1">{{ item.request.request_type.name }}</p>
              <p class="text-sm text-foreground mt-2 line-clamp-3">{{ item.request.description }}</p>
              <p class="text-xs text-neutral-500 mt-2">{{ formatDate(item.request.created_at) }}</p>
            </div>
            <button type="button" class="btn-primary self-start shrink-0" @click="pickUp(item)">Pick up</button>
          </div>
        </div>
      </div>
    </div>

    <div>
      <h2 class="text-xl text-primary font-semibold mb-3">My active cases</h2>
      <div v-if="myActiveStages.length === 0" class="card text-center text-neutral-500">
        <p>No stages in review assigned to you.</p>
      </div>
      <div v-else class="space-y-3">
        <div v-for="item in myActiveStages" :key="`${item.request.id}-${item.stage.id}`" class="card">
          <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
            <div class="flex-1">
              <h3 class="font-semibold text-foreground">{{ studentName(item.request.student_id) }}</h3>
              <p class="text-xs text-neutral-600">{{ studentMatricule(item.request.student_id) }}</p>
              <p class="text-sm text-primary font-medium mt-1">{{ item.request.request_type.name }}</p>
              <p class="text-sm text-foreground mt-2 line-clamp-3">{{ item.request.description }}</p>
              <p class="text-xs text-neutral-500 mt-2">{{ formatDate(item.request.created_at) }}</p>
            </div>
            <div class="flex gap-2 shrink-0">
              <button type="button" class="btn-primary text-sm" @click="openResolve(item)">Update status</button>
              <button type="button" class="btn-secondary text-sm" @click="selectedRequest = item.request">Details</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="resolveModal.open && resolveModal.item"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
    >
      <div class="bg-white rounded-lg max-w-md w-full">
        <div class="border-b border-neutral-200 p-4">
          <h2 class="text-lg text-primary font-bold">Update stage status</h2>
        </div>
        <div class="p-4 space-y-4">
          <p class="text-sm text-neutral-600">{{ resolveModal.item.request.request_type.name }}</p>
          <label class="block text-sm text-primary font-medium">Resolution</label>
          <select v-model="resolveModal.resolution" class="input-field">
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
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

    <div
      v-if="reassignModal.open && reassignModal.row"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
    >
      <div class="bg-white rounded-lg max-w-md w-full">
        <div class="border-b border-neutral-200 p-4">
          <h2 class="text-lg text-primary font-bold">Reassign stage</h2>
        </div>
        <div class="p-4 space-y-4">
          <label class="block text-sm text-primary font-medium">Assign to</label>
          <select v-model.number="reassignModal.newStaffId" class="input-field">
            <option v-for="s in reassignCandidates" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
          <div class="flex gap-2 justify-end">
            <button type="button" class="btn-secondary" @click="reassignModal.open = false">Cancel</button>
            <button type="button" class="btn-primary" @click="submitReassign">Save</button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="selectedRequest"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="selectedRequest = null"
    >
      <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-neutral-200 p-6 flex justify-between items-start">
          <h2 class="text-2xl font-bold">{{ selectedRequest.request_type.name }}</h2>
          <button type="button" class="text-neutral-500 hover:text-foreground text-2xl" @click="selectedRequest = null">×</button>
        </div>
        <div class="p-6 space-y-6">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-neutral-600">Student</p>
              <p class="font-semibold">{{ studentName(selectedRequest.student_id) }}</p>
            </div>
            <div>
              <p class="text-sm text-neutral-600">Request status</p>
              <StatusBadge kind="request" :status="selectedRequest.status" />
            </div>
          </div>
          <p class="text-foreground">{{ selectedRequest.description }}</p>
          <div>
            <p class="text-sm text-neutral-600 mb-2">Stages</p>
            <RequestTimeline :stages="selectedRequest.stages" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useMockData } from '@/composables/useMockData'
import type { Request, StageQueueItem } from '@/types'
import StatusBadge from './StatusBadge.vue'
import LevelBadge from './LevelBadge.vue'
import RequestTimeline from './RequestTimeline.vue'

const props = defineProps<{ deptAdminMode?: boolean }>()

const {
  sessionUser,
  currentDepartmentId,
  setCurrentDepartmentId,
  primaryDepartmentId,
  unclaimedStages,
  myActiveStages,
  resolvedTodayCount,
  pickUpStage,
  updateStageResolution,
  reassignStage,
  getUserById,
  staffInDepartment,
  deptAdminOverviewStages,
  departmentStats,
  staffPrimaryDepartmentId
} = useMockData()

const staffUser = computed(() => sessionUser.value)
const sp = computed(() => staffUser.value?.staff_profile)

const deptOptions = computed(() => sp.value?.departments ?? [])

const primaryDeptName = computed(() => {
  const p = sp.value?.departments.find(d => d.is_primary)
  return p?.name ?? sp.value?.departments[0]?.name ?? ''
})

const deptSelect = ref(0)

onMounted(() => {
  const id = currentDepartmentId.value ?? primaryDepartmentId.value
  deptSelect.value = id ?? 0
})

watch(
  () => currentDepartmentId.value,
  v => {
    if (v != null) deptSelect.value = v
  }
)

function onDeptSelect() {
  setCurrentDepartmentId(deptSelect.value)
}

const overviewStageFilter = ref('')
const overviewDateFrom = ref('')
const overviewDateTo = ref('')

const filteredOverview = computed(() => {
  let rows = deptAdminOverviewStages.value
  if (!props.deptAdminMode) return []
  if (overviewStageFilter.value) {
    rows = rows.filter(r => r.stage.status === overviewStageFilter.value)
  }
  if (overviewDateFrom.value) {
    const from = new Date(overviewDateFrom.value).getTime()
    rows = rows.filter(r => {
      const t = r.stage.updated_at || r.request.created_at
      return new Date(t).getTime() >= from
    })
  }
  if (overviewDateTo.value) {
    const to = new Date(overviewDateTo.value)
    to.setHours(23, 59, 59, 999)
    rows = rows.filter(r => {
      const t = r.stage.updated_at || r.request.created_at
      return new Date(t).getTime() <= to.getTime()
    })
  }
  return rows
})

const deptAvgHours = computed(() => departmentStats.value.avg_resolution_hours.toFixed(1))
const rejectionPct = computed(() => Math.round(departmentStats.value.rejection_rate * 100))

function studentName(id: number) {
  return getUserById(id)?.name ?? `User ${id}`
}

function studentMatricule(id: number) {
  return getUserById(id)?.student_profile?.matricule ?? ''
}

function studentLevel(id: number) {
  return getUserById(id)?.student_profile?.level ?? null
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function pickUp(item: StageQueueItem) {
  const u = sessionUser.value
  if (!u) return
  pickUpStage(item.request.id, item.stage.id, u)
}

const resolveModal = reactive({
  open: false,
  item: null as StageQueueItem | null,
  resolution: 'approved' as 'approved' | 'rejected',
  note: ''
})
const resolveError = ref('')

function openResolve(item: StageQueueItem) {
  resolveModal.item = item
  resolveModal.resolution = 'approved'
  resolveModal.note = ''
  resolveError.value = ''
  resolveModal.open = true
}

function submitResolve() {
  resolveError.value = ''
  const u = sessionUser.value
  const it = resolveModal.item
  if (!u || !it) return
  if (resolveModal.resolution === 'rejected' && !resolveModal.note.trim()) {
    resolveError.value = 'A staff note is required when rejecting.'
    return
  }
  updateStageResolution(
    it.request.id,
    it.stage.id,
    resolveModal.resolution,
    resolveModal.note.trim() || null,
    u
  )
  resolveModal.open = false
}

const selectedRequest = ref<Request | null>(null)

const reassignModal = reactive({
  open: false,
  row: null as StageQueueItem | null,
  newStaffId: 0
})

const reassignCandidates = computed(() => {
  const admin = sessionUser.value
  const primary = admin ? staffPrimaryDepartmentId(admin) : null
  if (primary === null) return []
  return staffInDepartment(primary).filter(s => s.id !== reassignModal.row?.stage.handled_by?.id)
})

function openReassign(row: StageQueueItem) {
  reassignModal.row = row
  const list = reassignCandidates.value
  reassignModal.newStaffId = list[0]?.id ?? 0
  reassignModal.open = true
}

function submitReassign() {
  const admin = sessionUser.value
  const row = reassignModal.row
  if (!admin || !row || !reassignModal.newStaffId) return
  reassignStage(row.request.id, row.stage.id, reassignModal.newStaffId, admin)
  reassignModal.open = false
}
</script>
