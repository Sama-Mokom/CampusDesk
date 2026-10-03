<template>
  <div
    id="admin-workspace"
    class="space-y-6 scroll-mt-24"
  >
    <PageHeader
      eyebrow="Department administration"
      title="Department oversight"
      :description="
        overview.department.name ||
        'Your primary department’s requests, people and progress.'
      "
    >
      <template #actions
        ><button
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
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
    >
      {{ error }}
      <button
        class="ml-2 min-h-11 font-semibold underline"
        :disabled="loading"
        @click="load"
      >
        Try again
      </button>
    </div>
    <p
      v-if="success"
      role="status"
      class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
    >
      {{ success }}
    </p>
    <SkeletonLoader
      v-if="loading && !loaded"
      :count="3"
    />
    <template v-else-if="loaded">
      <section
        class="grid grid-cols-2 gap-3 xl:grid-cols-5"
        aria-label="Department stage statistics"
      >
        <div
          v-for="metric in metrics"
          :key="metric.label"
          class="card min-w-0"
        >
          <p class="text-xs font-medium text-slate-500">{{ metric.label }}</p>
          <p
            class="mt-3 text-3xl font-bold tracking-tight"
            :class="metric.color"
          >
            {{ metric.value }}
          </p>
          <p class="mt-1 text-xs text-slate-500">{{ metric.hint }}</p>
        </div>
      </section>
      <section
        class="card space-y-5"
        aria-labelledby="department-stages-title"
        :aria-busy="loading"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2
              id="department-stages-title"
              class="text-lg font-semibold text-slate-900"
            >
              Department work
            </h2>
            <p class="mt-1 text-sm text-slate-500">
              Monitor every stage and keep active cases moving.
            </p>
          </div>
          <span class="badge bg-slate-100 text-slate-600"
            >{{ overview.staff.length }} staff members</span
          >
        </div>
        <div
          class="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-[1fr_220px]"
        >
          <label class="text-xs font-medium text-slate-600"
            >Search department work<input
              v-model="search"
              type="search"
              class="input-field mt-1"
              placeholder="Student, matricule or request number"
          /></label>
          <label class="text-xs font-medium text-slate-600"
            >Availability<select
              v-model="filter"
              class="input-field mt-1"
            >
              <option value="all">All stages</option>
              <option value="claimable">Claimable now</option>
              <option value="blocked">Waiting on another stage</option>
              <option value="in_review">In review</option>
              <option value="completed">Completed</option>
            </select></label
          >
        </div>
        <EmptyState
          v-if="!overview.stages.length"
          title="No department work yet"
          description="Request stages will appear here when they are routed through your department."
        />
        <EmptyState
          v-else-if="!filteredStages.length"
          title="No matching stages"
          description="Try another name or clear your filters."
          ><button
            class="btn-secondary mt-3"
            @click="clearFilters"
          >
            Clear filters
          </button></EmptyState
        >
        <template v-else>
          <div
            class="hidden grid-cols-[minmax(0,2fr)_1fr_1fr_140px] gap-4 border-b border-slate-200 pb-3 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid"
          >
            <span>Student / request</span><span>Status / availability</span
            ><span>Assigned to</span><span class="text-right">Actions</span>
          </div>
          <div class="divide-y divide-slate-100">
            <article
              v-for="stage in visibleStages"
              :key="stage.id"
              class="grid gap-4 py-5 first:pt-0 lg:grid-cols-[minmax(0,2fr)_1fr_1fr_140px] lg:items-center lg:first:pt-2"
            >
              <div class="min-w-0">
                <p class="font-semibold text-slate-900 break-words">
                  {{ stage.request.student_name }}
                </p>
                <p class="mt-1 text-sm text-slate-600 break-words">
                  {{ stage.request.request_type }}
                  <span class="text-slate-500">· #{{ stage.request_id }}</span>
                </p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ stage.request.student_matricule }} · Stage
                  {{ stage.sequence_order }} ·
                  {{ formatDate(stage.updated_at) }}
                </p>
              </div>
              <div>
                <StatusBadge
                  kind="stage"
                  :status="stage.status"
                />
                <p
                  v-if="stage.is_claimable"
                  class="mt-2 text-xs font-medium text-emerald-700"
                >
                  Ready to pick up
                </p>
                <p
                  v-else-if="stage.blocked_reason"
                  class="mt-2 text-xs text-amber-800"
                >
                  {{ stage.blocked_reason }}
                </p>
              </div>
              <p class="text-sm text-slate-600">
                <span class="mr-1 text-slate-500 lg:hidden">Assigned to:</span>
                >{{ stage.handler?.name ?? 'Unclaimed' }}
              </p>
              <div class="flex flex-wrap gap-2 lg:justify-end">
                <button
                  v-if="stage.is_claimable"
                  class="btn-primary text-sm"
                  :disabled="claimingId !== null"
                  @click="pickUp(stage)"
                >
                  {{
                    claimingId === stage.id ? 'Claiming…' : 'Pick up'
                  }}</button
                ><button
                  v-else-if="
                    stage.status === 'in_review' && stage.handled_by !== null
                  "
                  class="btn-secondary text-sm"
                  @click="openReassign(stage)"
                >
                  Reassign</button
                ><button
                  class="btn-secondary text-sm"
                  :aria-label="`View stage ${stage.id} details`"
                  @click="selectedStage = stage"
                >
                  Details
                </button>
              </div>
            </article>
          </div>
          <AdminPagination
            :page="page"
            :last="lastPage"
            :total="filteredStages.length"
            @change="page = $event"
          />
        </template>
      </section>
    </template>
    <BaseModal
      :open="!!modal.stage"
      title="Reassign active case"
      size="md"
      :busy="modal.submitting"
      @close="closeModal"
    >
      <form
        id="reassign-form"
        class="space-y-5"
        @submit.prevent="submitReassign"
      >
        <div class="rounded-xl bg-slate-50 p-4">
          <p class="font-semibold text-slate-900">
            {{ modal.stage?.request.request_type }}
          </p>
          <p class="mt-1 text-sm text-slate-600">
            {{ modal.stage?.request.student_name }} · Request #{{
              modal.stage?.request_id
            }}
          </p>
          <p class="mt-2 text-xs text-slate-500">
            Currently assigned to {{ modal.stage?.handler?.name }}
          </p>
        </div>
        <label class="block text-sm font-medium text-slate-700"
          >Assign to<select
            v-model.number="modal.recipientId"
            class="input-field mt-2"
            required
            :disabled="modal.submitting || !eligibleStaff.length"
            aria-describedby="reassign-help"
          >
            <option
              :value="0"
              disabled
            >
              Select staff member
            </option>
            <option
              v-for="staff in eligibleStaff"
              :key="staff.id"
              :value="staff.id"
            >
              {{ staff.name }} ({{ staff.staff_id }})
            </option>
          </select></label
        >
        <p
          id="reassign-help"
          class="text-sm text-slate-500"
        >
          {{
            eligibleStaff.length
              ? 'The receiving staff member will be notified about this handoff.'
              : 'No other staff members are assigned to this department.'
          }}
        </p>
        <p
          v-if="modal.error"
          role="alert"
          class="rounded-lg bg-red-50 p-3 text-sm text-red-800"
        >
          {{ modal.error }}
        </p>
      </form>
      <template #footer
        ><button
          class="btn-secondary"
          :disabled="modal.submitting"
          @click="closeModal"
        >
          Cancel</button
        ><button
          type="submit"
          form="reassign-form"
          class="btn-primary"
          :disabled="modal.submitting || !eligibleStaff.length"
        >
          {{ modal.submitting ? 'Reassigning…' : 'Reassign' }}
        </button></template
      >
    </BaseModal>
    <BaseModal
      :open="!!selectedStage"
      :title="`Stage ${selectedStage?.sequence_order ?? ''} details`"
      size="lg"
      @close="selectedStage = null"
    >
      <div
        v-if="selectedStage"
        class="space-y-5"
      >
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="font-semibold">
              {{ selectedStage.request.request_type }}
            </p>
            <p class="text-sm text-slate-500">
              {{ selectedStage.request.student_name }} · #{{
                selectedStage.request_id
              }}
            </p>
          </div>
          <StatusBadge
            kind="stage"
            :status="selectedStage.status"
          />
        </div>
        <section>
          <h3 class="text-sm font-semibold">Request description</h3>
          <p
            class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600"
          >
            {{
              selectedStage.request.description || 'No description provided.'
            }}
          </p>
        </section>
        <section>
          <h3 class="text-sm font-semibold">Staff note</h3>
          <p
            class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-600"
          >
            {{ selectedStage.staff_note || 'No staff note recorded.' }}
          </p>
        </section>
        <section>
          <h3 class="text-sm font-semibold">Assignment history</h3>
          <p
            v-if="!selectedStage.reassignments.length"
            class="mt-2 text-sm text-slate-500"
          >
            No reassignments recorded.
          </p>
          <ol
            v-else
            class="mt-3 space-y-3"
          >
            <li
              v-for="entry in selectedStage.reassignments"
              :key="entry.id"
              class="rounded-lg border border-slate-200 p-3 text-sm"
            >
              <p>
                {{ entry.from_user ?? 'Unclaimed' }} →
                <span class="font-medium">{{ entry.to_user }}</span>
              </p>
              <p class="mt-1 text-xs text-slate-500">
                {{ entry.reassigned_by ?? 'Administrator' }} ·
                {{ formatDate(entry.created_at) }}
              </p>
            </li>
          </ol>
        </section>
      </div>
    </BaseModal>
  </div>
</template>
<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import {
  fetchDepartmentAdminRequests,
  reassignStage,
  type DepartmentAdminOverview,
  type DepartmentAdminStage
} from '../services/deptAdmin'
import { claimStage } from '../services/stages'
import StatusBadge from './StatusBadge.vue'
import BaseModal from './ui/BaseModal.vue'
import EmptyState from './ui/EmptyState.vue'
import SkeletonLoader from './ui/SkeletonLoader.vue'
import PageHeader from './ui/PageHeader.vue'
import AdminPagination from './admin/AdminPagination.vue'
const loading = ref(true),
  loaded = ref(false),
  error = ref(''),
  success = ref('')
const claimingId = ref<number | null>(null)
const search = ref(''),
  filter = ref('all'),
  page = ref(1)
const selectedStage = ref<DepartmentAdminStage | null>(null)
const overview = reactive<DepartmentAdminOverview>({
  department: { id: 0, name: '' },
  stages: [],
  stats: {
    total: 0,
    unclaimed: 0,
    claimable: 0,
    blocked: 0,
    in_review: 0,
    completed: 0
  },
  staff: []
})
const modal = reactive({
  stage: null as DepartmentAdminStage | null,
  recipientId: 0,
  submitting: false,
  error: ''
})
const metrics = computed(() => [
  {
    label: 'All stages',
    value: overview.stats.total,
    hint: 'Through this department',
    color: 'text-slate-900'
  },
  {
    label: 'Claimable now',
    value: overview.stats.claimable,
    hint: 'Ready for your team',
    color: 'text-sky-700'
  },
  {
    label: 'Blocked',
    value: overview.stats.blocked,
    hint: 'Awaiting earlier stages',
    color: 'text-amber-700'
  },
  {
    label: 'In review',
    value: overview.stats.in_review,
    hint: 'Currently being handled',
    color: 'text-sky-700'
  },
  {
    label: 'Completed',
    value: overview.stats.completed,
    hint: 'Approved or rejected',
    color: 'text-emerald-700'
  }
])
const eligibleStaff = computed(() =>
  overview.staff.filter((staff) => staff.id !== modal.stage?.handled_by)
)
const filteredStages = computed(() =>
  overview.stages.filter((stage) => {
    const term = search.value.trim().toLocaleLowerCase()
    if (
      term &&
      !`${stage.request.student_name} ${stage.request.student_matricule} ${stage.request.request_type} ${stage.request_id}`
        .toLocaleLowerCase()
        .includes(term)
    )
      return false
    if (filter.value === 'claimable') return stage.is_claimable
    if (filter.value === 'blocked') return !!stage.blocked_reason
    if (filter.value === 'in_review') return stage.status === 'in_review'
    if (filter.value === 'completed')
      return stage.status === 'approved' || stage.status === 'rejected'
    return true
  })
)
const lastPage = computed(() =>
  Math.max(1, Math.ceil(filteredStages.value.length / 20))
)
const visibleStages = computed(() =>
  filteredStages.value.slice((page.value - 1) * 20, page.value * 20)
)
watch([search, filter], () => {
  page.value = 1
})
watch(lastPage, (value) => {
  page.value = Math.min(page.value, value)
})
function clearFilters() {
  search.value = ''
  filter.value = 'all'
}
function message(err: unknown, fallback: string) {
  return axios.isAxiosError(err)
    ? (err.response?.data?.message ?? fallback)
    : fallback
}
async function load() {
  loading.value = true
  error.value = ''
  try {
    Object.assign(overview, await fetchDepartmentAdminRequests())
    loaded.value = true
  } catch (err) {
    error.value = message(
      err,
      'Unable to load department work. Please try again.'
    )
  } finally {
    loading.value = false
  }
}
function openReassign(stage: DepartmentAdminStage) {
  modal.stage = stage
  modal.recipientId = 0
  modal.error = ''
}
function closeModal() {
  if (!modal.submitting) {
    modal.stage = null
    modal.error = ''
  }
}
async function submitReassign() {
  if (modal.submitting || !modal.stage) return
  if (!eligibleStaff.value.some((staff) => staff.id === modal.recipientId)) {
    modal.error = 'Select an eligible staff member.'
    return
  }
  modal.submitting = true
  modal.error = ''
  success.value = ''
  try {
    await reassignStage(modal.stage.id, modal.recipientId)
    success.value =
      'Stage reassigned. The receiving staff member has been notified.'
    modal.stage = null
    await load()
  } catch (err) {
    modal.error = message(
      err,
      'Unable to reassign this stage. Please try again.'
    )
  } finally {
    modal.submitting = false
  }
}
async function pickUp(stage: DepartmentAdminStage) {
  if (claimingId.value !== null) return
  claimingId.value = stage.id
  error.value = ''
  success.value = ''
  try {
    await claimStage(stage.request_id, stage.id)
    success.value = 'Stage claimed. The department assignment has been updated.'
    await load()
  } catch (err) {
    error.value = message(err, 'Unable to claim this stage. Please try again.')
  } finally {
    claimingId.value = null
  }
}
function formatDate(value: string) {
  return value
    ? new Date(value).toLocaleDateString('en-GB', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      })
    : 'Not recorded'
}
onMounted(load)
</script>
