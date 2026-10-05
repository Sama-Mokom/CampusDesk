<template>
  <ol class="divide-y divide-slate-100">
    <li
      v-for="row in rows"
      :key="row.id"
      class="py-3 text-sm"
    >
      <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
        <p class="font-medium text-slate-800 break-words">
          {{ row.changed_by?.name ?? 'System' }}
          <span class="font-normal text-slate-500"
            >· Request #{{ row.request_id
            }}<span v-if="row.request_stage_id">
              · Stage #{{ row.request_stage_id }}</span
            ></span
          >
        </p>
        <time
          :datetime="row.changed_at"
          class="text-xs text-slate-500"
          >{{ formatDate(row.changed_at) }}</time
        >
      </div>
      <p class="mt-1 text-slate-600">
        {{ label(row.old_status) }} <span aria-label="changed to">→</span>
        <span class="font-medium text-slate-800">{{
          label(row.new_status)
        }}</span>
      </p>
      <p
        v-if="row.note"
        class="mt-2 whitespace-pre-wrap break-words text-slate-500"
      >
        {{ row.note }}
      </p>
    </li>
  </ol>
</template>
<script setup lang="ts">
import type { AuditRow } from '@/services/admin'
defineProps<{ rows: AuditRow[] }>()
const label = (value: string | null) =>
  value
    ? value.replace(/_/g, ' ').replace(/^\w/, (letter) => letter.toUpperCase())
    : 'Created'
const formatDate = (value: string) =>
  value
    ? new Date(value).toLocaleString('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short'
      })
    : 'Not recorded'
</script>
