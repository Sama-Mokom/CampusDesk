<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import { useAuth } from '../composables/useAuth'
import {
  fetchRequests,
  fetchRequestById,
  createRequest,
  reopenRequest,
  markRequestCollected
} from '../services/requests'
import {
  fetchRequestTypes,
  fetchFaculties,
  fetchDepartments
} from '../services/reference'
import type {
  CreateRequestPayload,
  Request as DocumentRequest,
  RequestTypeEntity,
  Faculty,
  Department,
  RequestStatus
} from '../types'
import { requestStatusLabel } from '../types'
import StatusBadge from './StatusBadge.vue'
import LevelBadge from './LevelBadge.vue'
import RequestTimeline from './RequestTimeline.vue'
import DocumentViewer from './DocumentViewer.vue'
import BaseModal from './ui/BaseModal.vue'
import EmptyState from './ui/EmptyState.vue'
import SkeletonLoader from './ui/SkeletonLoader.vue'
import PageHeader from './ui/PageHeader.vue'
import StudentRequestForm from './student/StudentRequestForm.vue'
import StudentRequestCard from './student/StudentRequestCard.vue'

const { user } = useAuth()
const requestTypes = ref<RequestTypeEntity[]>([])
const faculties = ref<Faculty[]>([])
const departments = ref<Department[]>([])
const studentRequests = ref<DocumentRequest[]>([])
const loading = ref(true)
const loadError = ref('')
const requestsLoaded = ref(false)
const requestsLoadError = ref(false)
const error = ref('')
const success = ref('')
const submitting = ref(false)
const submitError = ref('')
const confirmation = ref<DocumentRequest | null>(null)
const requestForm = ref<InstanceType<typeof StudentRequestForm> | null>(null)
const selectedRequest = ref<DocumentRequest | null>(null)
const detailId = ref<number | null>(null)
const detailLoading = ref(false)
const detailError = ref('')
const historyOpen = ref(false)
const reopening = ref(false)
const collecting = ref(false)
const query = ref('')
const statusFilter = ref<RequestStatus | ''>('')
const page = ref(1)
const pageSize = 6
const statuses: RequestStatus[] = [
  'draft',
  'pending',
  'in_review',
  'forwarded',
  'ready',
  'collected',
  'rejected'
]

const profile = computed(() => user.value)
const sp = computed(() => user.value?.student_profile ?? null)
const facultyName = computed(
  () =>
    faculties.value.find((faculty) => faculty.id === sp.value?.faculty_id)
      ?.name ?? ''
)
const departmentName = computed(
  () =>
    departments.value.find(
      (department) => department.id === sp.value?.department_id
    )?.name ?? ''
)
const studentStats = computed(() => ({
  total: studentRequests.value.length,
  active: studentRequests.value.filter((request) =>
    ['pending', 'in_review', 'forwarded'].includes(request.status)
  ).length,
  ready: studentRequests.value.filter((request) => request.status === 'ready')
    .length
}))
const filteredRequests = computed(() => {
  const search = query.value.trim().toLowerCase()
  return [...studentRequests.value]
    .filter(
      (request) =>
        (!statusFilter.value || request.status === statusFilter.value) &&
        (!search ||
          `${request.id} ${request.request_type} ${request.description} ${(request.stages ?? []).map((stage) => stage.department_name).join(' ')}`
            .toLowerCase()
            .includes(search))
    )
    .sort(
      (a, b) =>
        new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    )
})
const visibleRequests = computed(() =>
  filteredRequests.value.slice(
    (page.value - 1) * pageSize,
    page.value * pageSize
  )
)
const pageCount = computed(() =>
  Math.max(1, Math.ceil(filteredRequests.value.length / pageSize))
)
const modalOpen = computed(
  () => detailId.value !== null || selectedRequest.value !== null
)
watch([query, statusFilter], () => {
  page.value = 1
})
watch(pageCount, (count) => {
  page.value = Math.min(page.value, count)
})

function apiMessage(cause: unknown, fallback: string): string {
  if (
    axios.isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(
      cause
    )
  ) {
    const messages = cause.response?.data?.errors
    return messages
      ? Object.values(messages).flat().join(' ')
      : (cause.response?.data?.message ?? fallback)
  }
  return fallback
}
async function loadDashboard() {
  loading.value = true
  loadError.value = ''
  const results = await Promise.allSettled([
    fetchRequests(),
    fetchRequestTypes(),
    fetchFaculties(),
    fetchDepartments()
  ] as const)
  requestsLoadError.value = results[0].status === 'rejected'
  if (results[0].status === 'fulfilled') {
    studentRequests.value = results[0].value
    requestsLoaded.value = true
  }
  if (results[1].status === 'fulfilled') requestTypes.value = results[1].value
  if (results[2].status === 'fulfilled') faculties.value = results[2].value
  if (results[3].status === 'fulfilled') departments.value = results[3].value
  if (results.some((result) => result.status === 'rejected'))
    loadError.value =
      'Some dashboard data could not be loaded. Please try again.'
  loading.value = false
}
onMounted(loadDashboard)

function clearFilters() {
  query.value = ''
  statusFilter.value = ''
}
function focusRequestForm() {
  void requestForm.value?.focus()
}
function resetRequestForm() {
  confirmation.value = null
  success.value = ''
  submitError.value = ''
}

async function submitRequest(payload: CreateRequestPayload) {
  if (submitting.value || user.value?.role !== 'student') return
  submitError.value = ''
  success.value = ''
  submitting.value = true
  try {
    const created = await createRequest(payload)
    studentRequests.value.unshift(created)
    confirmation.value = created
    clearFilters()
    page.value = 1
    success.value = `Request #${created.id} submitted successfully.`
  } catch (cause) {
    submitError.value = apiMessage(
      cause,
      'Failed to submit the request. Please try again.'
    )
  } finally {
    submitting.value = false
  }
}

async function openRequest(request: DocumentRequest) {
  detailId.value = request.id
  selectedRequest.value = null
  historyOpen.value = false
  error.value = ''
  success.value = ''
  detailError.value = ''
  detailLoading.value = true
  try {
    const details = await fetchRequestById(request.id)
    if (detailId.value === request.id) selectedRequest.value = details
  } catch (cause) {
    if (detailId.value === request.id)
      detailError.value = apiMessage(
        cause,
        'Failed to load request details. Please try again.'
      )
  } finally {
    if (detailId.value === request.id) detailLoading.value = false
  }
}
function retryDetails() {
  const request = studentRequests.value.find(
    (item) => item.id === detailId.value
  )
  if (request) void openRequest(request)
}
function closeDetails() {
  if (reopening.value || collecting.value) return
  detailId.value = null
  selectedRequest.value = null
  detailError.value = ''
}
function updateRequest(request: DocumentRequest) {
  const index = studentRequests.value.findIndex(
    (item) => item.id === request.id
  )
  if (index !== -1) studentRequests.value[index] = request
  selectedRequest.value = request
}
async function doReopen() {
  if (!selectedRequest.value || reopening.value || collecting.value) return
  error.value = ''
  success.value = ''
  reopening.value = true
  try {
    updateRequest(await reopenRequest(selectedRequest.value.id))
    success.value = 'Request reopened and returned to the pending queue.'
  } catch (cause) {
    error.value = apiMessage(
      cause,
      'Failed to reopen the request. Please try again.'
    )
  } finally {
    reopening.value = false
  }
}
async function doCollected() {
  if (!selectedRequest.value || collecting.value || reopening.value) return
  error.value = ''
  success.value = ''
  collecting.value = true
  try {
    updateRequest(await markRequestCollected(selectedRequest.value.id))
    success.value = 'Request marked as collected.'
  } catch (cause) {
    error.value = apiMessage(
      cause,
      'Failed to mark the request as collected. Please try again.'
    )
  } finally {
    collecting.value = false
  }
}
function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  })
}
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Student portal"
      :title="`Welcome back, ${profile?.name?.split(' ')[0] ?? 'Student'}.`"
      description="Your academic requests, all in one place."
    >
      <template #actions>
        <button
          type="button"
          class="btn-primary"
          @click="focusRequestForm"
        >
          <span aria-hidden="true">+</span> New request
        </button>
      </template>
    </PageHeader>
    <div
      v-if="loadError"
      role="alert"
      class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
      <span>{{ loadError }}</span
      ><button
        type="button"
        class="btn-secondary"
        :disabled="loading"
        @click="loadDashboard"
      >
        Try again
      </button>
    </div>
    <p
      v-if="success && !modalOpen"
      role="status"
      class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
    >
      {{ success }}
    </p>

    <div
      class="grid min-w-0 items-start gap-6 xl:grid-cols-[minmax(0,1.85fr)_minmax(0,1fr)]"
    >
      <div class="min-w-0 space-y-6">
        <section
          class="card flex flex-wrap items-center gap-4"
          aria-label="Academic profile"
        >
          <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white"
            aria-hidden="true"
          >
            {{
              profile?.name
                ?.split(' ')
                .map((part) => part[0])
                .slice(0, 2)
                .join('')
            }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="font-semibold text-slate-900">
              {{ profile?.name ?? 'Student' }}
            </p>
            <p class="mt-1 break-words text-xs leading-5 text-slate-500">
              <span
                v-if="sp"
                class="font-medium text-slate-700"
                >{{ sp.matricule }}</span
              ><span v-if="facultyName"> · {{ facultyName }}</span
              ><span v-if="departmentName"> · {{ departmentName }}</span>
            </p>
          </div>
          <LevelBadge
            v-if="sp"
            :level="sp.level"
          />
        </section>

        <SkeletonLoader
          v-if="loading"
          :count="2"
        />
        <dl
          v-else
          class="grid grid-cols-3 gap-2 sm:gap-4"
        >
          <div class="rounded-xl border border-slate-200 bg-white p-3 sm:p-5">
            <dt
              class="min-h-10 text-xs font-medium leading-5 text-slate-500 sm:min-h-0 sm:text-sm"
            >
              Total requests
            </dt>
            <dd class="mt-3 text-3xl font-bold tracking-tight text-slate-900">
              {{ requestsLoaded ? studentStats.total : '—' }}
            </dd>
            <dd class="mt-2 hidden text-xs text-slate-500 sm:block">
              Your request history
            </dd>
          </div>
          <div class="rounded-xl border border-slate-200 bg-white p-3 sm:p-5">
            <dt
              class="min-h-10 text-xs font-medium leading-5 text-slate-500 sm:min-h-0 sm:text-sm"
            >
              In progress
            </dt>
            <dd class="mt-3 text-3xl font-bold tracking-tight text-sky-700">
              {{ requestsLoaded ? studentStats.active : '—' }}
            </dd>
            <dd class="mt-2 hidden text-xs text-slate-500 sm:block">
              Moving through review
            </dd>
          </div>
          <div class="rounded-xl border border-slate-200 bg-white p-3 sm:p-5">
            <dt
              class="min-h-10 text-xs font-medium leading-5 text-slate-500 sm:min-h-0 sm:text-sm"
            >
              Ready to collect
            </dt>
            <dd class="mt-3 text-3xl font-bold tracking-tight text-green-700">
              {{ requestsLoaded ? studentStats.ready : '—' }}
            </dd>
            <dd class="mt-2 hidden text-xs text-slate-500 sm:block">
              All stages approved
            </dd>
          </div>
        </dl>

        <section
          id="requests"
          class="scroll-mt-24"
          aria-labelledby="student-requests-heading"
        >
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2
              id="student-requests-heading"
              class="text-lg font-semibold text-slate-900"
            >
              My requests
              <span class="ml-1 text-sm font-normal text-slate-500">{{
                studentRequests.length
              }}</span>
            </h2>
            <button
              type="button"
              class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-sky-700 hover:bg-sky-50"
              :disabled="loading"
              @click="loadDashboard"
            >
              {{ loading ? 'Refreshing…' : 'Refresh' }}
            </button>
          </div>
          <div class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px]">
            <div>
              <label
                for="student-request-search"
                class="sr-only"
                >Search requests</label
              ><input
                id="student-request-search"
                v-model="query"
                type="search"
                class="input-field"
                placeholder="Search by type, description, or ID"
              />
            </div>
            <div>
              <label
                for="student-status-filter"
                class="sr-only"
                >Filter by status</label
              ><select
                id="student-status-filter"
                v-model="statusFilter"
                class="input-field"
              >
                <option value="">All statuses</option>
                <option
                  v-for="status in statuses"
                  :key="status"
                  :value="status"
                >
                  {{ requestStatusLabel(status) }}
                </option>
              </select>
            </div>
          </div>
          <SkeletonLoader
            v-if="loading"
            :count="3"
          />
          <EmptyState
            v-else-if="requestsLoadError && !requestsLoaded"
            title="Requests are unavailable"
            description="Refresh the dashboard to try loading your requests again."
          />
          <EmptyState
            v-else-if="!studentRequests.length"
            title="Your first request starts here"
            description="Submit an academic request and follow its progress through each department."
          >
            <button
              type="button"
              class="btn-primary mt-4"
              @click="focusRequestForm"
            >
              Create a request
            </button>
          </EmptyState>
          <EmptyState
            v-else-if="!filteredRequests.length"
            title="No matching requests"
            description="Try another search or clear your filters to see all your requests."
          >
            <button
              type="button"
              class="btn-secondary mt-4"
              @click="clearFilters"
            >
              Clear filters
            </button>
          </EmptyState>
          <div
            v-else
            class="space-y-3"
          >
            <StudentRequestCard
              v-for="request in visibleRequests"
              :key="request.id"
              :request="request"
              @view="openRequest"
            />
          </div>
          <nav
            v-if="pageCount > 1"
            class="mt-4 flex flex-wrap items-center justify-between gap-2"
            aria-label="Request pages"
          >
            <span class="text-xs text-slate-500"
              >Page {{ page }} of {{ pageCount }} ·
              {{ filteredRequests.length }} requests</span
            >
            <div class="flex gap-2">
              <button
                type="button"
                class="btn-secondary"
                :disabled="page === 1"
                @click="page--"
              >
                Previous</button
              ><button
                type="button"
                class="btn-secondary"
                :disabled="page === pageCount"
                @click="page++"
              >
                Next
              </button>
            </div>
          </nav>
        </section>
      </div>
      <aside class="min-w-0 space-y-4 xl:sticky xl:top-24">
        <StudentRequestForm
          ref="requestForm"
          :request-types="requestTypes"
          :loading="loading"
          :submitting="submitting"
          :error="submitError"
          :confirmation="confirmation"
          @submit="submitRequest"
          @reset="resetRequestForm"
          @view="openRequest"
        />
        <div class="rounded-xl border border-sky-100 bg-sky-50/60 p-5">
          <h3 class="text-sm font-semibold text-sky-900">
            A clear path from start to finish
          </h3>
          <p class="mt-2 text-xs leading-6 text-sky-800">
            Open any request to see the departments involved, review notes, and
            the latest status. Your documents stay securely attached to your
            request.
          </p>
        </div>
      </aside>
    </div>

    <BaseModal
      :open="modalOpen"
      :title="selectedRequest?.request_type ?? 'Request details'"
      size="xl"
      :busy="reopening || collecting"
      @close="closeDetails"
    >
      <SkeletonLoader
        v-if="detailLoading"
        :count="3"
      />
      <div
        v-else-if="detailError"
        role="alert"
        class="space-y-4"
      >
        <p class="text-sm text-red-700">{{ detailError }}</p>
        <button
          type="button"
          class="btn-secondary"
          @click="retryDetails"
        >
          Try again
        </button>
      </div>
      <div
        v-else-if="selectedRequest"
        class="space-y-6"
      >
        <p
          v-if="error"
          role="alert"
          class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"
        >
          {{ error }}
        </p>
        <p
          v-if="success"
          role="status"
          class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800"
        >
          {{ success }}
        </p>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="text-sm font-medium text-slate-500">
            Request #{{ selectedRequest.id
            }}<span
              v-if="selectedRequest.is_reopened"
              class="ml-2 text-amber-700"
              >· Reopened</span
            >
          </p>
          <StatusBadge
            kind="request"
            :status="selectedRequest.status"
          />
        </div>
        <dl class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs text-slate-500">Submitted</dt>
            <dd class="mt-1 text-sm font-medium text-slate-900">
              {{ formatDate(selectedRequest.created_at) }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">Request type</dt>
            <dd class="mt-1 break-words text-sm font-medium text-slate-900">
              {{ selectedRequest.request_type }}
            </dd>
          </div>
        </dl>
        <section>
          <h3 class="mb-2 text-sm font-semibold text-slate-900">Description</h3>
          <p
            class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-600"
          >
            {{ selectedRequest.description || 'No description provided.' }}
          </p>
        </section>
        <section>
          <h3 class="mb-4 text-sm font-semibold text-slate-900">
            Stage timeline
          </h3>
          <RequestTimeline :stages="selectedRequest.stages ?? []" />
        </section>
        <section>
          <h3 class="mb-3 text-sm font-semibold text-slate-900">
            Documents
            <span class="font-normal text-slate-500"
              >({{ selectedRequest.attachments?.length ?? 0 }})</span
            >
          </h3>
          <DocumentViewer
            v-if="selectedRequest.attachments?.length"
            :attachments="selectedRequest.attachments"
          />
          <p
            v-else
            class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500"
          >
            No documents were attached to this request.
          </p>
        </section>
        <section class="border-t border-slate-100 pt-3">
          <button
            type="button"
            class="flex min-h-11 w-full items-center justify-between rounded-lg text-sm font-semibold text-slate-700"
            :aria-expanded="historyOpen"
            aria-controls="student-status-history"
            @click="historyOpen = !historyOpen"
          >
            Status history<span aria-hidden="true">{{
              historyOpen ? '−' : '+'
            }}</span>
          </button>
          <div
            v-show="historyOpen"
            id="student-status-history"
            class="mt-3 space-y-3"
          >
            <p
              v-if="!selectedRequest.status_history?.length"
              class="text-sm text-slate-500"
            >
              No status changes recorded yet.
            </p>
            <div
              v-for="entry in [
                ...(selectedRequest.status_history ?? [])
              ].reverse()"
              :key="entry.id"
              class="rounded-lg bg-slate-50 p-3 text-sm"
            >
              <p class="font-medium text-slate-700">
                {{
                  entry.old_status
                    ? requestStatusLabel(entry.old_status)
                    : 'Created'
                }}
                <span aria-hidden="true">→</span
                ><span class="sr-only"> to </span>
                {{ requestStatusLabel(entry.new_status) }}
              </p>
              <p class="mt-1 text-xs text-slate-500">
                {{ entry.changed_by?.name ?? 'System' }} ·
                {{ formatDate(entry.changed_at) }}
              </p>
              <p
                v-if="entry.note"
                class="mt-2 whitespace-pre-wrap break-words text-slate-600"
              >
                {{ entry.note }}
              </p>
            </div>
          </div>
        </section>
      </div>
      <template #footer>
        <button
          type="button"
          class="btn-secondary"
          :disabled="reopening || collecting"
          @click="closeDetails"
        >
          Close
        </button>
        <button
          v-if="selectedRequest?.status === 'rejected'"
          type="button"
          class="btn-primary"
          data-testid="reopen-request"
          :disabled="reopening"
          @click="doReopen"
        >
          {{ reopening ? 'Reopening…' : 'Reopen request' }}
        </button>
        <button
          v-if="selectedRequest?.status === 'ready'"
          type="button"
          class="btn-primary"
          :disabled="collecting"
          @click="doCollected"
        >
          {{ collecting ? 'Marking as collected…' : 'Mark as collected' }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>
