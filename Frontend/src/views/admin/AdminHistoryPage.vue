<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Super administration"
      title="Status history"
      description="Review request and stage transitions across the institution."
    >
      <template #actions>
        <button
          class="btn-secondary"
          :disabled="auditLoading"
          @click="loadAudit"
        >
          {{ auditLoading ? 'Refreshing…' : 'Refresh page' }}
        </button>
      </template>
    </PageHeader>

    <section
      id="admin-history"
      class="card scroll-mt-24 space-y-5"
      aria-labelledby="history-heading"
      :aria-busy="auditLoading"
    >
      <div>
        <h2
          id="history-heading"
          class="text-lg font-semibold text-slate-900"
        >
          Request and stage status history
        </h2>
        <p class="mt-1 text-sm text-slate-500">
          Status transitions across the institution. Administrative edits are
          outside this log.
        </p>
      </div>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <label class="text-xs font-medium text-slate-600"
          >Request ID<input
            v-model="auditFilters.request_id"
            type="number"
            min="1"
            class="input-field mt-1"
            placeholder="All requests" /></label
        ><label class="text-xs font-medium text-slate-600"
          >Actor ID<input
            v-model="auditFilters.actor_id"
            type="number"
            min="1"
            class="input-field mt-1"
            placeholder="All people" /></label
        ><label class="text-xs font-medium text-slate-600"
          >New status<input
            v-model="auditFilters.new_status"
            class="input-field mt-1"
            placeholder="Any status" /></label
        ><label class="text-xs font-medium text-slate-600"
          >From<input
            v-model="auditFilters.date_from"
            type="date"
            class="input-field mt-1" /></label
        ><label class="text-xs font-medium text-slate-600"
          >To<input
            v-model="auditFilters.date_to"
            type="date"
            class="input-field mt-1"
            :min="auditFilters.date_from || undefined"
        /></label>
      </div>
      <button
        v-if="hasAuditFilters"
        class="min-h-11 text-sm font-semibold text-sky-700"
        @click="clearAuditFilters"
      >
        Clear history filters
      </button>
      <SkeletonLoader
        v-if="auditLoading"
        :count="3"
      />
      <div
        v-else-if="collectionErrors.audit"
        role="alert"
        class="rounded-lg bg-red-50 p-4 text-sm text-red-800"
      >
        {{ collectionErrors.audit }}
        <button
          class="min-h-11 font-semibold underline"
          @click="loadAudit"
        >
          Try again
        </button>
      </div>
      <EmptyState
        v-else-if="!audit.data.length"
        :title="
          hasAuditFilters ? 'No matching transitions' : 'No status history yet'
        "
        description="Status changes will appear as requests move through the workflow."
      />
      <AdminAuditTrail
        v-else
        :rows="audit.data"
      />
      <AdminPagination
        :page="audit.meta.current_page"
        :last="audit.meta.last_page"
        :total="audit.meta.total"
        :disabled="auditLoading"
        label="History pages"
        @change="auditPage = $event"
      />
    </section>
  </div>
</template>

<script setup lang="ts">
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import AdminAuditTrail from '@/components/admin/AdminAuditTrail.vue'

import { useAdminHistory } from '@/composables/admin/useAdminHistory'

const {
  audit,
  auditPage,
  auditLoading,
  collectionErrors,
  auditFilters,
  hasAuditFilters,
  clearAuditFilters,
  loadAudit
} = useAdminHistory()
</script>
