<template>
  <div class="space-y-6">
    <div class="card">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 class="text-xl text-primary font-semibold">Hello, {{ profile?.name ?? 'Student' }}</h2>
          <p class="text-sm text-neutral-600 mt-1">
            <span class="font-mono font-medium text-foreground">{{ sp?.matricule }}</span>
            <span v-if="sp"> · {{ sp.faculty.name }} · {{ sp.department.name }}</span>
          </p>
        </div>
        <LevelBadge v-if="sp" :level="sp.level" />
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="card">
        <p class="text-sm text-neutral-600">Total requests</p>
        <p class="text-3xl font-bold text-primary">{{ studentStats.total }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">Pending</p>
        <p class="text-3xl font-bold text-yellow-600">{{ studentStats.pending }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">Ready for collection</p>
        <p class="text-3xl font-bold text-teal-600">{{ studentStats.ready_for_collection }}</p>
      </div>
    </div>

    <div v-if="!confirmStep" class="card">
      <h2 class="text-primary font-semibold mb-4">Submit new request</h2>
      <form @submit.prevent="submitRequest" class="space-y-4">
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Request type</label>
          <select v-model.number="form.request_type_id" class="input-field" required>
            <option v-for="rt in requestTypes" :key="rt.id" :value="rt.id">{{ rt.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Description</label>
          <textarea
            v-model="form.description"
            class="input-field"
            rows="4"
            placeholder="Describe what you need"
            required
          />
        </div>
        <div>
          <label class="block text-sm text-primary font-medium mb-1">Attachments (optional)</label>
          <input
            type="file"
            class="input-field text-sm"
            multiple
            @change="onFiles"
          />
        </div>
        <button type="submit" class="btn-primary">Submit request</button>
      </form>
    </div>

    <div v-else class="card space-y-4">
      <h2 class="text-primary font-semibold">Request submitted</h2>
      <p class="text-sm text-neutral-700">Your request has been queued. The following departments will process it in order:</p>
      <ol class="list-decimal list-inside space-y-2 text-sm text-foreground">
        <li v-for="(name, i) in confirmDepartments" :key="i">{{ name }}</li>
      </ol>
      <button type="button" class="btn-secondary" @click="resetForm">Submit another</button>
    </div>

    <div>
      <h2 class="text-xl text-primary font-semibold mb-4">Recent requests</h2>
      <div v-if="studentRequests.length === 0" class="card text-center text-neutral-500">
        <p>You have no requests yet.</p>
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="req in sortedRequests"
          :key="req.id"
          class="card cursor-pointer hover:border-primary transition-colors"
          @click="selectedRequest = req"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <h3 class="font-semibold text-foreground">{{ req.request_type.name }}</h3>
              <p class="text-sm text-neutral-600 mt-1 line-clamp-2">{{ req.description }}</p>
              <div class="flex items-center gap-2 mt-2 flex-wrap">
                <StatusBadge kind="request" :status="req.status" />
                <span class="text-xs text-neutral-500">{{ formatDate(req.created_at) }}</span>
              </div>
            </div>
            <button type="button" class="btn-secondary text-sm shrink-0" @click.stop="selectedRequest = req">View</button>
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
          <div>
            <h2 class="text-2xl font-bold">{{ selectedRequest.request_type.name }}</h2>
            <p v-if="selectedRequest.is_reopened" class="text-xs font-medium text-amber-700 mt-1">Reopened request</p>
          </div>
          <button type="button" class="text-neutral-500 hover:text-foreground text-2xl" @click="selectedRequest = null">×</button>
        </div>

        <div class="p-6 space-y-6">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-neutral-600">Status</p>
              <div class="mt-1">
                <StatusBadge kind="request" :status="selectedRequest.status" />
              </div>
            </div>
            <div>
              <p class="text-sm text-neutral-600">Created</p>
              <p class="font-semibold">{{ formatDate(selectedRequest.created_at) }}</p>
            </div>
          </div>

          <div>
            <p class="text-sm text-neutral-600 mb-2">Description</p>
            <p class="text-foreground">{{ selectedRequest.description }}</p>
          </div>

          <div v-if="selectedRequest.attachments.length">
            <p class="text-sm text-neutral-600 mb-2">Attachments</p>
            <ul class="text-sm space-y-1">
              <li v-for="a in selectedRequest.attachments" :key="a.id" class="text-primary">{{ a.original_name }}</li>
            </ul>
          </div>

          <div>
            <p class="text-sm text-neutral-600 mb-3">Stage timeline</p>
            <RequestTimeline :stages="selectedRequest.stages" />
          </div>

          <div>
            <button
              type="button"
              class="text-sm font-medium text-primary flex items-center gap-2"
              @click="historyOpen = !historyOpen"
            >
              Status history {{ historyOpen ? '▼' : '▶' }}
            </button>
            <div v-show="historyOpen" class="mt-3 space-y-2 border border-neutral-200 rounded-md p-3 bg-neutral-50">
              <div
                v-for="h in [...selectedRequest.status_history].reverse()"
                :key="h.id"
                class="text-sm border-b border-neutral-200 last:border-0 pb-2 last:pb-0"
              >
                <p>
                  <span class="font-medium">{{ formatHistStatus(h.old_status) }}</span>
                  →
                  <span class="font-medium">{{ formatHistStatus(h.new_status) }}</span>
                </p>
                <p class="text-xs text-neutral-600">{{ h.changed_by.name }} · {{ formatDate(h.changed_at) }}</p>
                <p v-if="h.note" class="text-neutral-700 mt-1">{{ h.note }}</p>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              v-if="selectedRequest.status === 'rejected'"
              type="button"
              class="btn-primary"
              @click="doReopen"
            >
              Reopen request
            </button>
            <button
              v-if="selectedRequest.status === 'ready'"
              type="button"
              class="btn-primary"
              @click="doCollected"
            >
              Mark as collected
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useMockData } from '@/composables/useMockData'
import type { Request, RequestStatus } from '@/types'
import { requestStatusLabel } from '@/types'
import StatusBadge from './StatusBadge.vue'
import LevelBadge from './LevelBadge.vue'
import RequestTimeline from './RequestTimeline.vue'

const {
  sessionUser,
  requestTypes,
  studentRequests,
  studentStats,
  createStudentRequest,
  reopenRequest,
  markCollected,
  deptSummary
} = useMockData()

const profile = computed(() => sessionUser.value)
const sp = computed(() => profile.value?.student_profile)

const form = reactive({
  request_type_id: requestTypes.value[0]?.id ?? 1,
  description: ''
})
const fileList = ref<{ name: string; type: string }[]>([])
const confirmStep = ref(false)
const confirmDepartments = ref<string[]>([])
const selectedRequest = ref<Request | null>(null)
const historyOpen = ref(false)

const sortedRequests = computed(() =>
  [...studentRequests.value].sort(
    (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
  )
)

function onFiles(e: Event) {
  const input = e.target as HTMLInputElement
  fileList.value = []
  if (!input.files) return
  for (let i = 0; i < input.files.length; i++) {
    const f = input.files[i]!
    fileList.value.push({ name: f.name, type: f.type })
  }
}

function submitRequest() {
  const u = sessionUser.value
  if (!u || u.role !== 'student' || !form.description.trim()) return
  const rt = requestTypes.value.find(t => t.id === form.request_type_id)
  if (!rt) return
  createStudentRequest(u, form.request_type_id, form.description.trim(), fileList.value)
  confirmDepartments.value = rt.default_department_sequence.map(id => deptSummary(id).name)
  confirmStep.value = true
  form.description = ''
  fileList.value = []
}

function resetForm() {
  confirmStep.value = false
  confirmDepartments.value = []
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatHistStatus(s: RequestStatus | null) {
  if (s === null) return '—'
  return requestStatusLabel(s)
}

function doReopen() {
  const u = sessionUser.value
  const r = selectedRequest.value
  if (!u || !r) return
  reopenRequest(r.id, u)
}

function doCollected() {
  const u = sessionUser.value
  const r = selectedRequest.value
  if (!u || !r) return
  markCollected(r.id, u)
}
</script>
