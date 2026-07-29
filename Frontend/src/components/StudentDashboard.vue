<template>
  <div class="space-y-6">
    <div class="card">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 class="text-xl text-primary font-semibold">Hello, {{ profile?.name ?? 'Student' }}</h2>
          <p class="text-sm text-neutral-600 mt-1">
            <span class="font-mono font-medium text-foreground">{{ sp?.matricule }}</span>
            <span v-if="sp"> · {{ facultyName }} · {{ departmentName }}</span>
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
          @click="openRequest(req)"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <h3 class="font-semibold text-foreground">{{ req.request_type }}</h3>
              <p class="text-sm text-neutral-600 mt-1 line-clamp-2">{{ req.description }}</p>
              <div class="flex items-center gap-2 mt-2 flex-wrap">
                <StatusBadge kind="request" :status="req.status" />
                <span class="text-xs text-neutral-500">{{ formatDate(req.created_at) }}</span>
              </div>
            </div>
            <button type="button" class="btn-secondary text-sm shrink-0" @click.stop="openRequest(req)" >View</button>
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
            <h2 class="text-2xl font-bold">{{ selectedRequest.request_type }}</h2>
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
                <p class="text-xs text-neutral-600">{{ h.changed_by?.name ?? 'System' }} · {{ formatDate(h.changed_at) }}</p>
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
import { ref, reactive, computed, onMounted } from 'vue'
import { useAuth } from '../composables/useAuth'
import { fetchRequests, fetchRequestById, createRequest } from '../services/requests'
import { fetchRequestTypes, fetchFaculties, fetchDepartments } from '../services/reference'
import type { Request as DocumentRequest, RequestTypeEntity, Faculty, Department } from '../types'
import type { RequestStatus } from '../types'
import { requestStatusLabel } from '../types'
import { watch } from 'vue'
import StatusBadge from './StatusBadge.vue'
import LevelBadge from './LevelBadge.vue'
import RequestTimeline from './RequestTimeline.vue'


const { user} = useAuth()

// Reference data
const requestTypes = ref<RequestTypeEntity[]>([])
const faculties = ref<Faculty[]>([])
const departments = ref<Department[]>([])

// Student requests
const studentRequests = ref<DocumentRequest[]>([])

// UI state
const loading = ref(true)
const error = ref('')

const profile = computed(() => user.value)
const sp = computed(() => user.value?.student_profile ?? null)

// Resolve faculty and department names from loaded reference data
const facultyName = computed(() => {
  if (!sp.value) return ''
  return faculties.value.find(f => f.id === sp.value!.faculty_id)?.name ?? ''
})
const departmentName = computed(() => {
  if (!sp.value) return ''
  return departments.value.find(d => d.id === sp.value!.department_id)?.name ?? ''
})

const studentStats = computed(() => ({
  total: studentRequests.value.length,
  pending: studentRequests.value.filter(r => r.status === 'pending').length,
  ready_for_collection: studentRequests.value.filter(r => r.status === 'ready').length,
}))

const form = reactive({
  request_type_id: requestTypes.value[0]?.id ?? 1,
  description: ''
})
watch(requestTypes, (types) => {
  if (types.length && !form.request_type_id) {
    form.request_type_id = types[0].id
  }
}, { immediate: true })

const fileList = ref<File[]>([])
const confirmStep = ref(false)
const confirmDepartments = ref<string[]>([])
const selectedRequest = ref<DocumentRequest | null>(null)
const historyOpen = ref(false)

const sortedRequests = computed(() =>
  [...studentRequests.value].sort(
    (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
  )
)

onMounted(async () => {
  try {
    const [requests, types, fetchedFaculties, fetchedDepartments] = await Promise.all([
      fetchRequests(),
      fetchRequestTypes(),
      fetchFaculties(),
      fetchDepartments(),
    ])
    studentRequests.value = requests
    requestTypes.value = types
    faculties.value = fetchedFaculties
    departments.value = fetchedDepartments
    console.log('requests response:', requests)
  } catch (err) {
    error.value = 'Failed to load dashboard data.'
  } finally {
    loading.value = false
  }
  console.log('sp:', sp.value)
  console.log('faculties:', faculties.value)
  console.log('facultyName:', facultyName.value)
})

async function openRequest(req: DocumentRequest) {
  try {
    const res = await fetchRequestById(req.id)
    
    // Handle both wrapped array [{...}] and single object {...} scenarios cleanly
    const payload = res.data ?? res
    selectedRequest.value = Array.isArray(payload) ? payload[0] : payload
  } catch (err) {
    console.error('Failed to load request details:', err)
    error.value = 'Failed to load request details.'
  }
}

function onFiles(e: Event) {
  const input = e.target as HTMLInputElement
  fileList.value = []
  if (!input.files) return
  for (let i = 0; i < input.files.length; i++) {
    fileList.value.push(input.files[i]!)
  }
}



async function submitRequest() {
  const u = user.value
  error.value = '' 

  if (!u || u.role !== 'student' || !form.description.trim()) return
  
  const rt = requestTypes.value.find(t => t.id === form.request_type_id)
  if (!rt) return
  try {
    const stuRequest = await createRequest({
      request_type_id: form.request_type_id, 
      description: form.description.trim(), 
      attachments: fileList.value
    });

    
    // Map the department sequence names for confirmation
    confirmDepartments.value = stuRequest.stages?.map(s => s.department_name) ?? []
    studentRequests.value.unshift(stuRequest)
    confirmStep.value = true
    
    // Reset form fields
    form.description = ''
    fileList.value = []

  } catch (err: any) {
    console.error('Request submission failed:', err);
    if (err.response && err.response.data && err.response.data.message) {
      error.value = err.response.data.message;
    } else {
      error.value = 'Failed to submit the request. Please try again.';
    }
  }
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
  // TODO: implement reopen endpoint
  console.log('Reopen not yet implemented')
}

function doCollected() {
  // TODO: implement collected endpoint
  console.log('Mark collected not yet implemented')
}
</script>
