<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useStudentRequests } from '@/composables/useStudentRequests'
import {
  requestStatusLabel,
  type RequestStatus,
  type Request as DocumentRequest
} from '@/types'
import PageHeader from '@/components/ui/PageHeader.vue'
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StudentRequestCard from '@/components/student/StudentRequestCard.vue'

const router = useRouter()
const { requests, sortedRequests, loading, loaded, error, loadRequests } =
  useStudentRequests()
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
const filteredRequests = computed(() => {
  const search = query.value.trim().toLowerCase()
  return sortedRequests.value.filter(
    (request) =>
      (!statusFilter.value || request.status === statusFilter.value) &&
      (!search ||
        `${request.id} ${request.request_type} ${request.description} ${(request.stages ?? []).map((stage) => stage.department_name).join(' ')}`
          .toLowerCase()
          .includes(search))
  )
})
const pageCount = computed(() =>
  Math.max(1, Math.ceil(filteredRequests.value.length / pageSize))
)
const visibleRequests = computed(() =>
  filteredRequests.value.slice(
    (page.value - 1) * pageSize,
    page.value * pageSize
  )
)
watch([query, statusFilter], () => {
  page.value = 1
})
watch(pageCount, (count) => {
  page.value = Math.min(page.value, count)
})
function clearFilters() {
  query.value = ''
  statusFilter.value = ''
}
function viewRequest(request: DocumentRequest) {
  void router.push({
    name: 'student-request-detail',
    params: { id: request.id }
  })
}
onMounted(loadRequests)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Student portal"
      title="My requests"
      description="Find a request and follow its progress through each department."
    >
      <template #actions
        ><button
          type="button"
          class="btn-secondary"
          :disabled="loading"
          @click="loadRequests"
        >
          {{ loading ? 'Refreshing…' : 'Refresh' }}</button
        ><RouterLink
          :to="{ name: 'student-new-request' }"
          class="btn-primary"
          >New request</RouterLink
        ></template
      >
    </PageHeader>
    <div
      v-if="error"
      role="alert"
      class="feedback-error flex flex-wrap items-center justify-between gap-3"
    >
      <span>{{ error }}</span
      ><button
        type="button"
        class="btn-secondary"
        :disabled="loading"
        @click="loadRequests"
      >
        Try again
      </button>
    </div>
    <section
      aria-label="Request list"
      class="space-y-4"
    >
      <div class="card grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px]">
        <label
          for="student-request-search"
          class="text-sm font-medium text-slate-700"
          >Search requests<input
            id="student-request-search"
            v-model="query"
            type="search"
            class="input-field mt-2"
            placeholder="Type, description, department, or ID"
        /></label>
        <label
          for="student-status-filter"
          class="text-sm font-medium text-slate-700"
          >Status<select
            id="student-status-filter"
            v-model="statusFilter"
            class="input-field mt-2"
          >
            <option value="">All statuses</option>
            <option
              v-for="status in statuses"
              :key="status"
              :value="status"
            >
              {{ requestStatusLabel(status) }}
            </option>
          </select></label
        >
      </div>
      <p
        v-if="loaded && !loading"
        class="text-sm text-slate-500"
        role="status"
      >
        {{ filteredRequests.length }} of {{ requests.length }} requests
      </p>
      <SkeletonLoader
        v-if="loading"
        :count="3"
      />
      <EmptyState
        v-else-if="!loaded"
        title="Requests are unavailable"
        description="Try loading your requests again using the retry button above."
      />
      <EmptyState
        v-else-if="!requests.length"
        title="Your first request starts here"
        description="Create an academic request to get started."
        ><RouterLink
          :to="{ name: 'student-new-request' }"
          class="btn-primary"
          >Create a request</RouterLink
        ></EmptyState
      >
      <EmptyState
        v-else-if="!filteredRequests.length"
        title="No matching requests"
        description="Try another search or clear your filters."
        ><button
          type="button"
          class="btn-secondary"
          @click="clearFilters"
        >
          Clear filters
        </button></EmptyState
      >
      <div
        v-else
        class="space-y-3"
      >
        <StudentRequestCard
          v-for="request in visibleRequests"
          :key="request.id"
          :request="request"
          @view="viewRequest"
        />
      </div>
      <nav
        v-if="pageCount > 1"
        class="flex flex-wrap items-center justify-between gap-3"
        aria-label="Request pages"
      >
        <span class="text-sm text-slate-500"
          >Page {{ page }} of {{ pageCount }}</span
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
</template>
