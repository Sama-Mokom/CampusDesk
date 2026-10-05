<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Super administration"
      title="Campus overview"
      description="See request activity across your institution."
    >
      <template #actions>
        <button
          class="btn-secondary"
          :disabled="statsLoading"
          @click="loadStats"
        >
          {{ statsLoading ? 'Refreshing…' : 'Refresh page' }}
        </button>
      </template>
    </PageHeader>

    <div
      v-if="error"
      role="alert"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
    >
      {{ error }}
    </div>

    <section
      aria-label="System overview"
      class="space-y-4"
    >
      <SkeletonLoader
        v-if="statsLoading"
        :count="3"
      />
      <div
        v-else-if="stats"
        class="grid grid-cols-2 gap-3 lg:grid-cols-4"
      >
        <div class="card">
          <p class="text-xs font-medium text-slate-500">Total requests</p>
          <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">
            {{ stats.total }}
          </p>
          <p class="mt-1 text-xs text-slate-500">Across the institution</p>
        </div>
        <div class="card">
          <p class="text-xs font-medium text-slate-500">Submitted today</p>
          <p class="mt-3 text-3xl font-bold tracking-tight text-sky-700">
            {{ stats.requests_today }}
          </p>
          <p class="mt-1 text-xs text-slate-500">New requests today</p>
        </div>
        <div class="card">
          <p class="text-xs font-medium text-slate-500">In review</p>
          <p class="mt-3 text-3xl font-bold tracking-tight text-sky-700">
            {{ stats.by_status.in_review ?? 0 }}
          </p>
          <p class="mt-1 text-xs text-slate-500">Currently being handled</p>
        </div>
        <div class="card">
          <p class="text-xs font-medium text-slate-500">Average resolution</p>
          <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">
            {{ stats.avg_resolution_hours ?? '—'
            }}<span
              v-if="stats.avg_resolution_hours !== null"
              class="ml-1 text-sm font-medium text-slate-500"
              >hrs</span
            >
          </p>
          <p class="mt-1 text-xs text-slate-500">
            {{
              stats.avg_resolution_hours === null
                ? 'No resolved requests yet'
                : 'For resolved requests'
            }}
          </p>
        </div>
      </div>
      <div
        v-else
        class="card text-sm text-slate-600"
      >
        Overview statistics are unavailable.
        <button
          class="min-h-11 font-semibold text-sky-700 underline"
          @click="loadStats"
        >
          Try again
        </button>
      </div>
      <div
        v-if="stats"
        class="grid gap-4 xl:grid-cols-[1fr_1.5fr]"
      >
        <section class="card">
          <h2 class="text-base font-semibold text-slate-900">
            Request distribution
          </h2>
          <div class="mt-4 space-y-3">
            <div
              v-for="status in statuses"
              :key="status"
              class="flex items-center justify-between gap-3 text-sm"
            >
              <StatusBadge
                kind="request"
                :status="status"
              /><span class="font-semibold tabular-nums text-slate-700">{{
                stats.by_status[status] ?? 0
              }}</span>
            </div>
          </div>
        </section>
        <section class="card">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-slate-900">
              Recent activity
            </h2>
            <RouterLink
              to="/admin/history"
              class="inline-flex min-h-11 items-center text-sm font-medium text-sky-700"
              >View status history →</RouterLink
            >
          </div>
          <EmptyState
            v-if="!stats.recent_activity.length"
            title="No activity yet"
            description="Status changes will appear as requests move through the workflow."
          /><AdminAuditTrail
            v-else
            :rows="stats.recent_activity.slice(0, 5)"
          />
        </section>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import StatusBadge from '@/components/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import AdminAuditTrail from '@/components/admin/AdminAuditTrail.vue'
import { RouterLink } from 'vue-router'

import { useAdminOverview } from '@/composables/admin/useAdminOverview'

const { stats, statsLoading, error, statuses, loadStats } = useAdminOverview()
</script>
