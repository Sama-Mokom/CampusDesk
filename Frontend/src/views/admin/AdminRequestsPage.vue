<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Super administration"
      title="All requests"
      description="Review and track requests across every faculty and department."
    >
      <template #actions>
        <button
          class="btn-secondary"
          :disabled="loading"
          @click="initialize"
        >
          {{ loading ? 'Refreshing…' : 'Refresh page' }}
        </button>
      </template>
    </PageHeader>

    <div
      v-if="error || choicesError"
      role="alert"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
    >
      {{ error || choicesError }}
    </div>
    <p
      v-if="success"
      role="status"
      class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
    >
      {{ success }}
    </p>
    <section
      id="admin-requests"
      class="card scroll-mt-24 space-y-5"
      aria-labelledby="all-requests-heading"
      :aria-busy="requestsLoading"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2
            id="all-requests-heading"
            class="text-lg font-semibold text-slate-900"
          >
            All requests
          </h2>
          <p class="mt-1 text-sm text-slate-500">
            Review requests across every faculty and department.
          </p>
        </div>
        <span class="badge bg-slate-100 text-slate-600"
          >{{ requests.meta.total }} requests</span
        >
      </div>
      <div class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-semibold text-slate-700">Filter requests</h3>
          <button
            v-if="activeRequestFilterCount"
            class="min-h-11 text-sm font-semibold text-sky-700"
            @click="clearRequestFilters"
          >
            Clear filters ({{ activeRequestFilterCount }})
          </button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <label class="text-xs font-medium text-slate-600 sm:col-span-2"
            >Search<input
              v-model="requestFilters.search"
              type="search"
              maxlength="100"
              class="input-field mt-1"
              placeholder="Student name, matricule or request ID"
          /></label>
          <label class="text-xs font-medium text-slate-600"
            >Faculty<select
              v-model.number="requestFilters.faculty_id"
              class="input-field mt-1"
            >
              <option :value="0">All faculties</option>
              <option
                v-for="faculty in faculties"
                :key="faculty.id"
                :value="faculty.id"
              >
                {{ faculty.name }}
              </option>
            </select></label
          >
          <label class="text-xs font-medium text-slate-600"
            >Department<select
              v-model.number="requestFilters.department_id"
              class="input-field mt-1"
            >
              <option :value="0">All departments</option>
              <option
                v-for="department in departments"
                :key="department.id"
                :value="department.id"
              >
                {{ department.name }}
              </option>
            </select></label
          >
          <label class="text-xs font-medium text-slate-600"
            >Request type<select
              v-model.number="requestFilters.request_type_id"
              class="input-field mt-1"
            >
              <option :value="0">All request types</option>
              <option
                v-for="type in requestTypes"
                :key="type.id"
                :value="type.id"
              >
                {{ type.name }}
              </option>
            </select></label
          >
          <label class="text-xs font-medium text-slate-600"
            >Status<select
              v-model="requestFilters.status"
              class="input-field mt-1"
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
          <label class="text-xs font-medium text-slate-600"
            >From<input
              v-model="requestFilters.date_from"
              type="date"
              class="input-field mt-1" /></label
          ><label class="text-xs font-medium text-slate-600"
            >To<input
              v-model="requestFilters.date_to"
              type="date"
              class="input-field mt-1"
              :min="requestFilters.date_from || undefined"
          /></label>
        </div>
        <label
          class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm text-slate-600"
          ><input
            v-model="requestFilters.reopened"
            type="checkbox"
          />Reopened requests only</label
        >
      </div>
      <SkeletonLoader
        v-if="requestsLoading"
        :count="3"
      />
      <div
        v-else-if="collectionErrors.requests"
        role="alert"
        class="rounded-lg bg-red-50 p-4 text-sm text-red-800"
      >
        {{ collectionErrors.requests }}
        <button
          class="min-h-11 font-semibold underline"
          @click="loadRequests"
        >
          Try again
        </button>
      </div>
      <EmptyState
        v-else-if="!requests.data.length"
        :title="
          activeRequestFilterCount ? 'No matching requests' : 'No requests yet'
        "
        :description="
          activeRequestFilterCount
            ? 'Adjust the filters to find another request.'
            : 'New student requests will appear here.'
        "
      />
      <div
        v-else
        class="divide-y divide-slate-100"
      >
        <article
          v-for="row in requests.data"
          :key="row.id"
          class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="min-w-0 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-semibold text-slate-900 break-words">
                {{ row.student?.name ?? 'Unknown student' }}
              </h3>
              <span class="text-xs text-slate-500">Request #{{ row.id }}</span
              ><StatusBadge
                kind="request"
                :status="row.status as RequestStatus"
              /><span
                v-if="row.is_reopened"
                class="badge bg-amber-50 text-amber-800"
                >Reopened</span
              >
            </div>
            <p class="text-sm text-slate-600 break-words">
              {{ row.request_type?.name ?? 'Request'
              }}<span v-if="row.student?.student_profile?.matricule">
                · {{ row.student.student_profile.matricule }}</span
              >
            </p>
            <p class="text-xs text-slate-500">
              Submitted {{ date(row.created_at) }}
            </p>
          </div>
          <button
            class="btn-secondary self-start shrink-0 sm:self-auto"
            :aria-label="`View request ${row.id}`"
            @click="openRequest(row.id)"
          >
            View
          </button>
        </article>
      </div>
      <AdminPagination
        :page="requests.meta.current_page"
        :last="requests.meta.last_page"
        :total="requests.meta.total"
        :disabled="requestsLoading"
        label="Request pages"
        @change="requestPage = $event"
      />
    </section>
    <BaseModal
      :open="detailOpen"
      :title="
        detail
          ? `${detail.request_type?.name ?? 'Request'} · #${detail.id}`
          : 'Request details'
      "
      size="xl"
      :busy="pending"
      @close="closeDetail"
    >
      <SkeletonLoader
        v-if="detailLoading"
        :count="4"
      />
      <div
        v-else-if="detailError"
        role="alert"
        class="rounded-lg bg-red-50 p-4 text-sm text-red-800"
      >
        {{ detailError }}
        <button
          class="min-h-11 font-semibold underline"
          @click="openRequest(detailId)"
        >
          Try again
        </button>
      </div>
      <div
        v-else-if="detail"
        class="space-y-6"
      >
        <div
          class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-4"
        >
          <div>
            <p class="font-semibold text-slate-900">
              {{ detail.student?.name }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
              {{ detail.student?.student_profile?.matricule }} · Submitted
              {{ date(detail.created_at) }}
            </p>
          </div>
          <StatusBadge
            kind="request"
            :status="detail.status as RequestStatus"
          />
        </div>
        <p
          v-if="detail.is_reopened"
          class="text-sm font-medium text-amber-800"
        >
          This request has been reopened.
        </p>
        <section>
          <h3 class="mb-2 text-sm font-semibold text-slate-900">Description</h3>
          <p class="whitespace-pre-wrap break-words text-sm text-slate-600">
            {{ detail.description || 'No description provided.' }}
          </p>
        </section>
        <section>
          <h3 class="mb-3 text-sm font-semibold text-slate-900">
            Stage timeline
          </h3>
          <RequestTimeline
            v-if="detailStages.length"
            :stages="detailStages"
          />
          <p
            v-else
            class="text-sm text-slate-500"
          >
            No stages recorded.
          </p>
        </section>
        <section>
          <h3 class="mb-3 text-sm font-semibold text-slate-900">Attachments</h3>
          <DocumentViewer
            :key="detail.id"
            :attachments="detailAttachments"
          />
        </section>
        <section>
          <h3 class="mb-3 text-sm font-semibold text-slate-900">
            Status history
          </h3>
          <AdminAuditTrail
            v-if="detail.status_history?.length"
            :rows="[...detail.status_history].reverse()"
          />
          <p
            v-else
            class="text-sm text-slate-500"
          >
            No status changes recorded.
          </p>
        </section>
        <p
          v-if="formError"
          role="alert"
          class="rounded-lg bg-red-50 p-3 text-sm text-red-800"
        >
          {{ formError }}
        </p>
      </div>
      <template #footer
        ><button
          class="btn-secondary"
          :disabled="pending"
          @click="closeDetail"
        >
          Close</button
        ><button
          v-if="detail?.status === 'rejected'"
          class="btn-primary"
          :disabled="pending"
          @click="reopen"
        >
          {{ pending ? 'Reopening…' : 'Reopen request' }}
        </button></template
      >
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue'
import RequestTimeline from '@/components/RequestTimeline.vue'
import DocumentViewer from '@/components/DocumentViewer.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import AdminAuditTrail from '@/components/admin/AdminAuditTrail.vue'

import { requestStatusLabel } from '@/types'
import type { RequestStatus } from '@/types'
import { useAdminRequests } from '@/composables/admin/useAdminRequests'

const {
  requests,
  requestPage,
  requestsLoading,
  collectionErrors,
  requestFilters,
  activeRequestFilterCount,
  clearRequestFilters,
  loadRequests,
  initialize,
  loading,
  error,
  success,
  pending,
  formError,
  choicesError,
  faculties,
  departments,
  requestTypes,
  statuses,
  date,
  detail,
  detailOpen,
  detailLoading,
  detailId,
  detailError,
  detailAttachments,
  detailStages,
  openRequest,
  closeDetail,
  reopen
} = useAdminRequests()
</script>
