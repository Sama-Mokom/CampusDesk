<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { isAxiosError } from 'axios'
import { useAuth } from '../composables/useAuth'
import {
  fetchStaffQueue,
  resolveStage,
  claimStage,
  fetchMyCases
} from '../services/stages'
import { fetchRequestById } from '../services/requests'
import type { RequestStage, Request as DocumentRequest } from '../types'
import StatusBadge from './StatusBadge.vue'
import RequestTimeline from './RequestTimeline.vue'
import DocumentViewer from './DocumentViewer.vue'
import StaffCaseCard from './staff/StaffCaseCard.vue'
import BaseModal from './ui/BaseModal.vue'
import EmptyState from './ui/EmptyState.vue'
import SkeletonLoader from './ui/SkeletonLoader.vue'
import PageHeader from './ui/PageHeader.vue'

const auth = useAuth()
const staffUser = computed(() => auth.user.value)
const sp = computed(() => staffUser.value?.staff_profile)
const deptOptions = computed(() => sp.value?.departments ?? [])
const deptSelect = ref(0)
const selectedDeptName = computed(
  () => deptOptions.value.find((d) => d.id === deptSelect.value)?.name ?? ''
)
const primaryDeptName = computed(
  () =>
    deptOptions.value.find((d) => d.is_primary)?.name ??
    deptOptions.value[0]?.name ??
    ''
)
const allStages = ref<RequestStage[]>([])
const activeCases = ref<RequestStage[]>([])
const loading = ref(false)
const queueError = ref('')
const casesError = ref('')
const successMessage = ref('')
const workspaceTab = ref<'queue' | 'active'>('queue')
const search = ref('')
const typeFilter = ref('')
const unclaimedStages = computed(() =>
  allStages.value.filter(
    (stage) =>
      stage.status === 'pending' &&
      !stage.handled_by &&
      (!selectedDeptName.value ||
        stage.department_name === selectedDeptName.value)
  )
)
// My cases intentionally spans every assigned department, as the endpoint does.
const myActiveStages = computed(() => activeCases.value)
const currentStages = computed(() =>
  workspaceTab.value === 'queue' ? unclaimedStages.value : myActiveStages.value
)
const currentError = computed(() =>
  workspaceTab.value === 'queue' ? queueError.value : casesError.value
)
const requestTypes = computed(() =>
  [
    ...new Set(
      [...allStages.value, ...activeCases.value]
        .map((stage) => stage.request?.request_type)
        .filter((name): name is string => Boolean(name))
    )
  ].sort()
)
const filteredStages = computed(() => {
  const query = search.value.trim().toLowerCase()
  return currentStages.value.filter((stage) => {
    const request = stage.request
    const searchable = [
      stage.request_id,
      stage.department_name,
      request?.student_name,
      request?.student_matricule,
      request?.description,
      request?.request_type
    ]
      .join(' ')
      .toLowerCase()
    return (
      (!query || searchable.includes(query)) &&
      (!typeFilter.value || request?.request_type === typeFilter.value)
    )
  })
})

function errorMessage(error: unknown, fallback: string) {
  if (
    isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(error)
  ) {
    const validation = Object.values(error.response?.data?.errors ?? {})
      .flat()
      .join(' ')
    return validation || error.response?.data?.message || fallback
  }
  return fallback
}
async function loadQueue() {
  if (loading.value) return
  loading.value = true
  queueError.value = ''
  casesError.value = ''
  const [queue, cases] = await Promise.allSettled([
    fetchStaffQueue(),
    fetchMyCases()
  ])
  if (queue.status === 'fulfilled') allStages.value = queue.value
  else
    queueError.value = errorMessage(
      queue.reason,
      'Unable to load the department queue. Please try again.'
    )
  if (cases.status === 'fulfilled') activeCases.value = cases.value
  else
    casesError.value = errorMessage(
      cases.reason,
      'Unable to load your active cases. Please try again.'
    )
  loading.value = false
}
function clearFilters() {
  search.value = ''
  typeFilter.value = ''
}
function moveTab(event: KeyboardEvent) {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
  event.preventDefault()
  workspaceTab.value =
    event.key === 'Home'
      ? 'queue'
      : event.key === 'End'
        ? 'active'
        : workspaceTab.value === 'queue'
          ? 'active'
          : 'queue'
  document.getElementById(`staff-tab-${workspaceTab.value}`)?.focus()
}

const claimModal = reactive({
  open: false,
  stage: null as RequestStage | null,
  error: ''
})
const claiming = ref(false)
function openClaim(stage: RequestStage) {
  claimModal.stage = stage
  claimModal.error = ''
  claimModal.open = true
}
async function pickUp(stage: RequestStage) {
  if (claiming.value) return
  claiming.value = true
  claimModal.error = ''
  successMessage.value = ''
  try {
    await claimStage(stage.request_id, stage.id)
    claimModal.open = false
    successMessage.value = `Request #${stage.request_id} is now assigned to you.`
    workspaceTab.value = 'active'
    clearFilters()
    await loadQueue()
  } catch (error) {
    claimModal.error = errorMessage(
      error,
      'Unable to claim this stage. Refresh the queue and try again.'
    )
  } finally {
    claiming.value = false
  }
}

const detailsModal = reactive({
  open: false,
  stage: null as RequestStage | null,
  request: null as DocumentRequest | null,
  stages: [] as RequestStage[],
  loading: false,
  error: ''
})
const activeTab = ref<'timeline' | 'attachments' | 'history'>('timeline')
let detailsLoadId = 0
async function openDetails(stage: RequestStage) {
  const loadId = ++detailsLoadId
  detailsModal.stage = stage
  detailsModal.request = null
  detailsModal.stages = []
  detailsModal.error = ''
  detailsModal.open = true
  detailsModal.loading = true
  activeTab.value = 'timeline'
  try {
    const data = await fetchRequestById(stage.request_id)
    if (loadId !== detailsLoadId) return
    detailsModal.request = data
    detailsModal.stages = data.stages ?? []
  } catch (error) {
    if (loadId === detailsLoadId)
      detailsModal.error = errorMessage(
        error,
        'Unable to load request details. Please try again.'
      )
  } finally {
    if (loadId === detailsLoadId) detailsModal.loading = false
  }
}

const resolveModal = reactive({
  open: false,
  stage: null as RequestStage | null,
  note: ''
})
const resolveStatus = ref<'approved' | 'rejected'>('approved')
const resolveError = ref('')
const resolving = ref(false)
function openResolve(stage: RequestStage) {
  resolveModal.stage = stage
  resolveStatus.value = 'approved'
  resolveModal.note = ''
  resolveError.value = ''
  resolveModal.open = true
}
async function submitResolve() {
  if (resolving.value) return
  resolveError.value = ''
  const stage = resolveModal.stage
  if (!stage) return
  const status = resolveStatus.value === 'rejected' ? 'rejected' : 'approved'
  if (status === 'rejected' && !resolveModal.note.trim()) {
    resolveError.value = 'A staff note is required when rejecting.'
    return
  }
  if (resolveModal.note.trim().length > 1000) {
    resolveError.value = 'Keep the staff note to 1,000 characters or fewer.'
    return
  }
  resolving.value = true
  successMessage.value = ''
  try {
    await resolveStage(stage.request_id, stage.id, {
      status,
      staff_note: resolveModal.note.trim()
    })
    resolveModal.open = false
    successMessage.value = `Request #${stage.request_id}: stage ${status}.`
    await loadQueue()
  } catch (error) {
    resolveError.value = errorMessage(
      error,
      'Unable to update this stage. Your note has been kept; please try again.'
    )
  } finally {
    resolving.value = false
  }
}
function formatDate(iso: string) {
  return new Date(iso).toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
onMounted(() => {
  deptSelect.value =
    deptOptions.value.find((d) => d.is_primary)?.id ??
    deptOptions.value[0]?.id ??
    0
  void loadQueue()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="STAFF WORKSPACE"
      title="Make every request count"
      description="Review your department queue and keep student requests moving."
    >
      <template #actions
        ><button
          type="button"
          class="btn-secondary"
          :disabled="loading"
          @click="loadQueue"
        >
          {{ loading ? 'Refreshing…' : 'Refresh workspace' }}
        </button></template
      >
    </PageHeader>
    <section
      class="staff-identity"
      aria-label="Staff profile and department"
    >
      <div class="flex min-w-0 items-center gap-3">
        <div
          class="staff-avatar"
          aria-hidden="true"
        >
          {{ staffUser?.name?.charAt(0) ?? 'S' }}
        </div>
        <div class="min-w-0">
          <h2 class="break-words text-base font-semibold text-slate-900">
            {{ staffUser?.name }}
          </h2>
          <p class="mt-1 text-xs text-slate-500">
            <span class="font-mono">{{ sp?.staff_id }}</span
            ><span v-if="primaryDeptName"> · {{ primaryDeptName }}</span>
          </p>
        </div>
      </div>
      <div class="w-full sm:w-auto sm:max-w-xs">
        <label
          v-if="deptOptions.length > 1"
          for="staff-department"
          class="mb-1.5 block text-xs font-semibold text-slate-500"
          >QUEUE DEPARTMENT</label
        >
        <select
          v-if="deptOptions.length > 1"
          id="staff-department"
          v-model.number="deptSelect"
          class="input-field"
        >
          <option
            v-for="department in deptOptions"
            :key="department.id"
            :value="department.id"
          >
            {{ department.name }}{{ department.is_primary ? ' (Primary)' : '' }}
          </option>
        </select>
        <p
          v-else
          class="text-sm font-medium text-slate-700"
        >
          {{ selectedDeptName || 'No department assigned' }}
        </p>
      </div>
    </section>
    <div
      v-if="successMessage"
      class="staff-success"
      role="status"
    >
      {{ successMessage }}
    </div>
    <div
      class="grid grid-cols-2 gap-3 lg:grid-cols-3"
      aria-label="Workspace overview"
    >
      <div class="staff-stat">
        <p>Unclaimed requests</p>
        <strong>{{
          loading ? '—' : queueError ? '—' : unclaimedStages.length
        }}</strong
        ><span>{{
          queueError ? 'Unavailable' : 'In the selected department'
        }}</span>
      </div>
      <div class="staff-stat">
        <p>My active cases</p>
        <strong class="!text-sky-700">{{
          loading ? '—' : casesError ? '—' : myActiveStages.length
        }}</strong
        ><span>{{
          casesError ? 'Unavailable' : 'Across your departments'
        }}</span>
      </div>
      <div class="staff-stat col-span-2 lg:col-span-1">
        <p>My departments</p>
        <strong>{{ deptOptions.length }}</strong
        ><span>{{
          deptOptions.length === 1
            ? 'Department assigned'
            : 'Departments assigned'
        }}</span>
      </div>
    </div>
    <section
      id="staff-workspace"
      class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
      aria-label="Request workspace"
    >
      <div
        class="staff-tabs"
        role="tablist"
        aria-label="Case lists"
        @keydown="moveTab"
      >
        <button
          id="staff-tab-queue"
          type="button"
          role="tab"
          :aria-selected="workspaceTab === 'queue'"
          :tabindex="workspaceTab === 'queue' ? 0 : -1"
          aria-controls="staff-case-panel"
          :class="{ selected: workspaceTab === 'queue' }"
          @click="workspaceTab = 'queue'"
        >
          Unclaimed Queue <span>{{ unclaimedStages.length }}</span>
        </button>
        <button
          id="staff-tab-active"
          type="button"
          role="tab"
          :aria-selected="workspaceTab === 'active'"
          :tabindex="workspaceTab === 'active' ? 0 : -1"
          aria-controls="staff-case-panel"
          :class="{ selected: workspaceTab === 'active' }"
          @click="workspaceTab = 'active'"
        >
          My Active Cases <span>{{ myActiveStages.length }}</span>
        </button>
      </div>
      <div
        id="staff-case-panel"
        role="tabpanel"
        :aria-labelledby="`staff-tab-${workspaceTab}`"
        :aria-busy="loading"
        class="p-4 sm:p-6"
      >
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end">
          <div class="min-w-0 flex-1">
            <label
              for="staff-search"
              class="mb-1.5 block text-xs font-medium text-slate-600"
              >Search requests</label
            ><input
              id="staff-search"
              v-model="search"
              type="search"
              class="input-field"
              placeholder="Search student, matricule or request…"
            />
          </div>
          <div class="sm:w-52">
            <label
              for="staff-type"
              class="mb-1.5 block text-xs font-medium text-slate-600"
              >Request type</label
            ><select
              id="staff-type"
              v-model="typeFilter"
              class="input-field"
            >
              <option value="">All request types</option>
              <option
                v-for="type in requestTypes"
                :key="type"
                :value="type"
              >
                {{ type }}
              </option>
            </select>
          </div>
        </div>
        <p class="mb-4 text-xs text-slate-500">
          {{
            workspaceTab === 'queue'
              ? `${selectedDeptName || 'Your departments'} · Ready to be claimed`
              : 'Assigned to you · All your departments'
          }}
        </p>
        <SkeletonLoader
          v-if="loading"
          :count="3"
        />
        <div
          v-else-if="currentError"
          class="staff-error"
          role="alert"
        >
          <p>{{ currentError }}</p>
          <button
            type="button"
            class="btn-secondary mt-3"
            @click="loadQueue"
          >
            Try again
          </button>
        </div>
        <EmptyState
          v-else-if="currentStages.length === 0"
          :title="
            workspaceTab === 'queue'
              ? 'Your queue is clear'
              : 'No active cases yet'
          "
          :description="
            workspaceTab === 'queue'
              ? 'There are no unclaimed stages in this department. Refresh to check for new requests.'
              : 'Claim a request from the unclaimed queue to start reviewing it.'
          "
          ><button
            v-if="workspaceTab === 'active'"
            type="button"
            class="btn-primary mt-4"
            @click="workspaceTab = 'queue'"
          >
            Browse queue
          </button></EmptyState
        >
        <EmptyState
          v-else-if="filteredStages.length === 0"
          title="No matching requests"
          description="Try a different search or clear your filters to see all requests."
          ><button
            type="button"
            class="btn-secondary mt-4"
            @click="clearFilters"
          >
            Clear filters
          </button></EmptyState
        >
        <div
          v-else
          class="space-y-3"
        >
          <p
            class="sr-only"
            role="status"
          >
            {{ filteredStages.length }} requests shown
          </p>
          <StaffCaseCard
            v-for="stage in filteredStages"
            :key="stage.id"
            :stage="stage"
            :active="workspaceTab === 'active'"
            @claim="openClaim(stage)"
            @resolve="openResolve(stage)"
            @details="openDetails(stage)"
          />
        </div>
      </div>
    </section>

    <BaseModal
      :open="claimModal.open"
      title="Claim this request"
      size="md"
      :busy="claiming"
      @close="claimModal.open = false"
    >
      <div
        v-if="claimModal.stage"
        class="space-y-4"
      >
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs text-slate-500">
            Request #{{ claimModal.stage.request_id }} · Stage
            {{ claimModal.stage.sequence_order }}
          </p>
          <h3 class="mt-2 text-base font-semibold">
            {{ claimModal.stage.request?.request_type }}
          </h3>
          <p class="mt-1 text-sm text-slate-600">
            {{ claimModal.stage.request?.student_name }} ·
            {{ claimModal.stage.department_name }}
          </p>
        </div>
        <p class="text-sm text-slate-600">
          This stage will be assigned to you and moved into My Active Cases,
          where you can review the documents and record a decision.
        </p>
        <p
          v-if="claimModal.error"
          class="staff-error"
          role="alert"
        >
          {{ claimModal.error }}
        </p>
      </div>
      <template #footer
        ><button
          type="button"
          class="btn-secondary"
          :disabled="claiming"
          @click="claimModal.open = false"
        >
          Cancel</button
        ><button
          type="button"
          class="btn-primary"
          :disabled="claiming"
          @click="claimModal.stage && pickUp(claimModal.stage)"
        >
          {{ claiming ? 'Claiming…' : 'Claim request' }}
        </button></template
      >
    </BaseModal>

    <BaseModal
      :open="detailsModal.open"
      :title="detailsModal.stage?.request?.request_type || 'Request details'"
      size="xl"
      @close="detailsModal.open = false"
    >
      <div
        v-if="detailsModal.stage"
        class="space-y-5"
      >
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs font-medium text-slate-500"
              >REQUEST #{{ detailsModal.stage.request_id }}</span
            ><StatusBadge
              kind="stage"
              :status="detailsModal.stage.status"
            />
          </div>
          <p class="mt-3 font-semibold text-slate-900">
            {{ detailsModal.stage.request?.student_name }}
          </p>
          <p class="mt-1 text-xs text-slate-500">
            {{ detailsModal.stage.request?.student_matricule }} ·
            {{ detailsModal.stage.department_name }} · Stage
            {{ detailsModal.stage.sequence_order }}
          </p>
          <p
            class="mt-4 whitespace-pre-wrap break-words text-sm text-slate-700"
          >
            {{
              detailsModal.request?.description ??
              detailsModal.stage.request?.description
            }}
          </p>
          <p
            v-if="detailsModal.stage.request?.created_at"
            class="mt-3 text-xs text-slate-500"
          >
            Submitted {{ formatDate(detailsModal.stage.request.created_at) }}
          </p>
        </div>
        <div
          class="flex flex-wrap gap-2"
          aria-label="Request detail sections"
        >
          <button
            v-for="tab in ['timeline', 'attachments', 'history'] as const"
            :key="tab"
            type="button"
            class="staff-detail-tab"
            :class="{ selected: activeTab === tab }"
            :aria-pressed="activeTab === tab"
            @click="activeTab = tab"
          >
            {{
              tab === 'timeline'
                ? 'Progression timeline'
                : tab === 'attachments'
                  ? 'Documents'
                  : 'History'
            }}
          </button>
        </div>
        <SkeletonLoader
          v-if="detailsModal.loading"
          :count="2"
        />
        <div
          v-else-if="detailsModal.error"
          class="staff-error"
          role="alert"
        >
          <p>{{ detailsModal.error }}</p>
          <button
            type="button"
            class="btn-secondary mt-3"
            @click="openDetails(detailsModal.stage)"
          >
            Retry details
          </button>
        </div>
        <RequestTimeline
          v-else-if="activeTab === 'timeline'"
          :stages="detailsModal.stages"
        />
        <DocumentViewer
          v-else-if="activeTab === 'attachments'"
          :attachments="
            detailsModal.request?.attachments ??
            detailsModal.stage.request?.attachments ??
            []
          "
        />
        <div v-else>
          <h3 class="mb-4 text-base font-semibold">Status history</h3>
          <ol
            v-if="detailsModal.request?.status_history?.length"
            class="space-y-4"
          >
            <li
              v-for="entry in detailsModal.request.status_history"
              :key="entry.id"
              class="border-l-2 border-slate-200 pl-4"
            >
              <StatusBadge
                kind="request"
                :status="entry.new_status"
              />
              <p class="mt-2 text-xs text-slate-500">
                {{ formatDate(entry.changed_at) }} ·
                {{ entry.changed_by?.name || 'System' }}
              </p>
              <p
                v-if="entry.note"
                class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-700"
              >
                {{ entry.note }}
              </p>
            </li>
          </ol>
          <EmptyState
            v-else
            title="No history recorded"
            description="Status changes will appear here as this request moves through its workflow."
          />
        </div>
      </div>
    </BaseModal>

    <BaseModal
      :open="resolveModal.open"
      title="Update stage status"
      size="md"
      :busy="resolving"
      @close="resolveModal.open = false"
    >
      <form
        id="staff-resolution-form"
        class="space-y-5"
        novalidate
        @submit.prevent="submitResolve"
      >
        <div
          v-if="resolveModal.stage"
          class="rounded-xl bg-slate-50 p-4"
        >
          <p class="text-xs text-slate-500">
            Request #{{ resolveModal.stage.request_id }} ·
            {{ resolveModal.stage.department_name }}
          </p>
          <p class="mt-1 font-semibold">
            {{ resolveModal.stage.request?.request_type }}
          </p>
          <p class="mt-1 text-sm text-slate-600">
            {{ resolveModal.stage.request?.student_name }}
          </p>
        </div>
        <div>
          <label
            for="staff-resolution"
            class="mb-2 block text-sm font-medium text-slate-700"
            >Resolution</label
          ><select
            id="staff-resolution"
            v-model="resolveStatus"
            class="input-field"
            :disabled="resolving"
          >
            <option value="approved">Approve stage</option>
            <option value="rejected">Reject request</option>
          </select>
          <p class="mt-2 text-xs leading-relaxed text-slate-500">
            {{
              resolveStatus === 'approved'
                ? 'Approval forwards the request to the next department, or marks it ready when this is the final stage.'
                : 'The request will be rejected. Give the student a clear reason and explain what needs to change.'
            }}
          </p>
        </div>
        <div>
          <label
            for="staff-note"
            class="mb-2 block text-sm font-medium text-slate-700"
            >Staff note
            <span class="font-normal text-slate-500"
              >({{
                resolveStatus === 'rejected' ? 'required' : 'optional'
              }})</span
            ></label
          ><textarea
            id="staff-note"
            v-model="resolveModal.note"
            class="input-field"
            rows="4"
            :disabled="resolving"
            :required="resolveStatus === 'rejected'"
            :aria-invalid="Boolean(resolveError)"
            :aria-describedby="
              resolveError ? 'staff-resolution-error' : undefined
            "
            placeholder="Add context for the student and the next reviewer…"
          />
        </div>
        <p
          v-if="resolveError"
          id="staff-resolution-error"
          class="staff-error"
          role="alert"
        >
          {{ resolveError }}
        </p>
      </form>
      <template #footer
        ><button
          type="button"
          class="btn-secondary"
          :disabled="resolving"
          @click="resolveModal.open = false"
        >
          Cancel</button
        ><button
          type="submit"
          form="staff-resolution-form"
          :class="resolveStatus === 'rejected' ? 'btn-danger' : 'btn-primary'"
          :disabled="resolving"
        >
          {{
            resolving
              ? 'Saving…'
              : resolveStatus === 'rejected'
                ? 'Reject request'
                : 'Approve stage'
          }}
        </button></template
      >
    </BaseModal>
  </div>
</template>

<style scoped>
.staff-identity {
  @apply flex flex-col justify-between gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:p-6;
}
.staff-avatar {
  @apply flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-xl font-semibold text-sky-700;
}
.staff-stat {
  @apply min-w-0 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5;
}
.staff-stat p {
  @apply text-xs font-medium text-slate-600 sm:text-sm;
}
.staff-stat strong {
  @apply my-2 block break-words text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl;
}
.staff-stat span {
  @apply text-xs text-slate-500;
}
.staff-success {
  @apply rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800;
}
.staff-error {
  @apply rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800;
}
.staff-tabs {
  @apply grid grid-cols-2 border-b border-slate-200 px-2 sm:flex sm:gap-6 sm:px-6;
}
.staff-tabs button {
  @apply flex min-h-14 items-center justify-center gap-2 border-b-2 border-transparent px-1 py-3 text-xs font-semibold text-slate-500 transition-colors sm:text-sm;
}
.staff-tabs button.selected {
  @apply border-sky-600 text-sky-700;
}
.staff-tabs button span {
  @apply rounded-full bg-slate-100 px-2 py-0.5 text-xs;
}
.staff-tabs button.selected span {
  @apply bg-sky-50 text-sky-700;
}
.staff-detail-tab {
  @apply min-h-11 rounded-lg px-3 py-2 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-50;
}
.staff-detail-tab.selected {
  @apply bg-sky-50 text-sky-700;
}
</style>
