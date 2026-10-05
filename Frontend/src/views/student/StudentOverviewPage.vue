<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useStudentRequests } from '@/composables/useStudentRequests'
import { fetchDepartments, fetchFaculties } from '@/services/reference'
import type { Department, Faculty, Request as DocumentRequest } from '@/types'
import PageHeader from '@/components/ui/PageHeader.vue'
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LevelBadge from '@/components/LevelBadge.vue'
import StudentRequestCard from '@/components/student/StudentRequestCard.vue'

const { user } = useAuth()
const router = useRouter()
const { sortedRequests, stats, loading, loaded, error, loadRequests } =
  useStudentRequests()
const faculties = ref<Faculty[]>([])
const departments = ref<Department[]>([])
const academicError = ref('')
const sp = computed(() => user.value?.student_profile)
const facultyName = computed(
  () =>
    faculties.value.find((faculty) => faculty.id === sp.value?.faculty_id)?.name
)
const departmentName = computed(
  () =>
    departments.value.find(
      (department) => department.id === sp.value?.department_id
    )?.name
)
const recentRequests = computed(() => sortedRequests.value.slice(0, 3))

async function loadAcademicContext() {
  if (!sp.value) return
  academicError.value = ''
  const results = await Promise.allSettled([
    fetchFaculties(),
    fetchDepartments()
  ] as const)
  if (results[0].status === 'fulfilled') faculties.value = results[0].value
  if (results[1].status === 'fulfilled') departments.value = results[1].value
  if (results.some((result) => result.status === 'rejected'))
    academicError.value = 'Academic profile details could not be loaded.'
}
function viewRequest(request: DocumentRequest) {
  void router.push({
    name: 'student-request-detail',
    params: { id: request.id }
  })
}
onMounted(() => {
  void loadRequests()
  void loadAcademicContext()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Student portal"
      :title="`Welcome back, ${user?.name?.split(' ')[0] ?? 'Student'}.`"
      description="An overview of your academic requests and progress."
    >
      <template #actions
        ><RouterLink
          :to="{ name: 'student-new-request' }"
          class="btn-primary"
          ><span aria-hidden="true">+</span> New request</RouterLink
        ></template
      >
    </PageHeader>
    <section
      class="card flex flex-wrap items-center gap-4"
      aria-label="Academic profile"
    >
      <div
        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white"
        aria-hidden="true"
      >
        {{
          user?.name
            ?.split(' ')
            .map((part) => part[0])
            .slice(0, 2)
            .join('')
        }}
      </div>
      <div class="min-w-0 flex-1">
        <p class="font-semibold text-slate-900">
          {{ user?.name ?? 'Student' }}
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
      <p
        v-if="academicError"
        class="w-full text-sm text-amber-800"
        role="alert"
      >
        {{ academicError }}
        <button
          type="button"
          class="min-h-11 font-medium underline"
          @click="loadAcademicContext"
        >
          Try again
        </button>
      </p>
    </section>
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
    <SkeletonLoader
      v-if="loading"
      :count="2"
    />
    <dl
      v-else
      class="grid grid-cols-3 gap-2 sm:gap-4"
    >
      <div class="card">
        <dt
          class="min-h-10 text-xs font-medium text-slate-500 sm:min-h-0 sm:text-sm"
        >
          Total requests
        </dt>
        <dd class="mt-3 text-3xl font-bold text-slate-900">
          {{ loaded ? stats.total : '—' }}
        </dd>
      </div>
      <div class="card">
        <dt
          class="min-h-10 text-xs font-medium text-slate-500 sm:min-h-0 sm:text-sm"
        >
          In progress
        </dt>
        <dd class="mt-3 text-3xl font-bold text-sky-700">
          {{ loaded ? stats.active : '—' }}
        </dd>
      </div>
      <div class="card">
        <dt
          class="min-h-10 text-xs font-medium text-slate-500 sm:min-h-0 sm:text-sm"
        >
          Ready to collect
        </dt>
        <dd class="mt-3 text-3xl font-bold text-green-700">
          {{ loaded ? stats.ready : '—' }}
        </dd>
      </div>
    </dl>
    <section
      aria-labelledby="recent-requests-title"
      class="space-y-4"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2
          id="recent-requests-title"
          class="text-lg font-semibold text-slate-900"
        >
          Recent requests
        </h2>
        <RouterLink
          :to="{ name: 'student-requests' }"
          class="btn-ghost"
          >View all requests <span aria-hidden="true">→</span></RouterLink
        >
      </div>
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
        v-else-if="!recentRequests.length"
        title="Your first request starts here"
        description="Submit an academic request and follow its progress through each department."
        ><RouterLink
          :to="{ name: 'student-new-request' }"
          class="btn-primary"
          >Create a request</RouterLink
        ></EmptyState
      >
      <StudentRequestCard
        v-for="request in recentRequests"
        v-else
        :key="request.id"
        :request="request"
        @view="viewRequest"
      />
    </section>
  </div>
</template>
