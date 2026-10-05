<script setup lang="ts">
import type { RequestStage } from '../../types'
import LevelBadge from '../LevelBadge.vue'
import StatusBadge from '../StatusBadge.vue'

defineProps<{ stage: RequestStage; active: boolean }>()
defineEmits<{ claim: []; resolve: []; details: [] }>()
function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}
</script>

<template>
  <article class="case-card">
    <div class="flex min-w-0 flex-1 gap-3 sm:gap-4">
      <div
        class="case-icon"
        aria-hidden="true"
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.7"
          class="h-5 w-5"
        >
          <path
            d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"
          />
          <path d="M14 3v5h5M8 12h8M8 16h5" />
        </svg>
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-xs font-medium text-slate-500"
            >#{{ stage.request_id }}</span
          ><StatusBadge
            kind="stage"
            :status="stage.status"
          />
        </div>
        <h3 class="mt-2 break-words text-[15px] font-semibold text-slate-900">
          {{ stage.request?.request_type || 'Student request' }}
        </h3>
        <div
          class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500"
        >
          <span class="font-medium text-slate-700">{{
            stage.request?.student_name || 'Student'
          }}</span
          ><span v-if="stage.request?.student_matricule">{{
            stage.request.student_matricule
          }}</span
          ><LevelBadge
            v-if="stage.request?.student_level"
            :level="stage.request.student_level"
          />
        </div>
        <p
          v-if="stage.request?.description"
          class="mt-3 line-clamp-2 break-words text-sm leading-relaxed text-slate-600"
        >
          {{ stage.request.description }}
        </p>
        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
          <span
            >{{ stage.department_name }} · Stage
            {{ stage.sequence_order }}</span
          ><span v-if="stage.request?.created_at"
            >Submitted {{ formatDate(stage.request.created_at) }}</span
          ><span v-if="active && stage.updated_at"
            >Updated {{ formatDate(stage.updated_at) }}</span
          >
        </div>
      </div>
    </div>
    <div class="case-actions">
      <button
        type="button"
        class="btn-secondary"
        :aria-label="`View details for request ${stage.request_id}`"
        @click="$emit('details')"
      >
        Details</button
      ><button
        v-if="active"
        type="button"
        class="btn-primary"
        :aria-label="`Update status for request ${stage.request_id}`"
        @click="$emit('resolve')"
      >
        Update status</button
      ><button
        v-else
        type="button"
        class="btn-primary"
        :aria-label="`Claim request ${stage.request_id}`"
        @click="$emit('claim')"
      >
        Claim request
      </button>
    </div>
  </article>
</template>

<style scoped>
.case-card {
  @apply flex min-w-0 flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 transition-colors hover:border-sky-200 sm:p-5 xl:flex-row xl:items-center;
}
.case-icon {
  @apply hidden h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500 sm:flex;
}
.case-actions {
  @apply grid shrink-0 grid-cols-2 gap-2 border-t border-slate-100 pt-4 sm:flex sm:justify-end xl:border-0 xl:pt-0;
}
.case-actions button {
  @apply text-xs;
}
</style>
