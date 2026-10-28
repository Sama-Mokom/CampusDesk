<script setup lang="ts">
import { computed, getCurrentScope, onScopeDispose, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import axios from 'axios'
import { studentApiMessage } from '@/composables/useStudentRequests'
import { useRealtime } from '@/composables/useRealtime'
import {
  fetchRequestById,
  markRequestCollected,
  reopenRequest
} from '@/services/requests'
import type { Request as DocumentRequest, RequestTypeTier } from '@/types'
import { requestStatusLabel } from '@/types'
import PageHeader from '@/components/ui/PageHeader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import TierBadge, { formatXAF } from '@/components/TierBadge.vue'
import RequestTimeline from '@/components/RequestTimeline.vue'
import DocumentViewer from '@/components/DocumentViewer.vue'
import StudentPaymentPanel from '@/components/student/StudentPaymentPanel.vue'

const route = useRoute()
const request = ref<DocumentRequest | null>(null)
const loading = ref(true)
const error = ref('')
const unavailable = ref(false)
const actionError = ref('')
const success = ref('')
const pending = ref<'reopen' | 'collect' | 'upgrade' | null>(null)
let version = 0
if (getCurrentScope()) {
  onScopeDispose(() => {
    version++
  })
}

const availableTiers = computed<RequestTypeTier[]>(() => {
  return request.value?.available_tiers ?? []
})

const higherTiers = computed<RequestTypeTier[]>(() => {
  if (!request.value || request.value.status !== 'rejected') return []
  const currentTierId = request.value.request_type_tier_id
  const currentPriority = request.value.tier_priority ?? 0
  const currentAmountPaid = request.value.amount_paid ?? 0

  return availableTiers.value
    .filter((tier) => {
      if (tier.id === currentTierId) return false
      return (
        tier.priority > currentPriority ||
        tier.fee_amount > currentAmountPaid
      )
    })
    .sort((a, b) => a.priority - b.priority)
})

function getUpgradeDelta(tier: RequestTypeTier): number {
  const paid = request.value?.amount_paid ?? 0
  return Math.max(0, tier.fee_amount - paid)
}

async function loadRequest() {
  const current = ++version
  request.value = null
  loading.value = true
  error.value = ''
  unavailable.value = false
  actionError.value = ''
  success.value = ''
  pending.value = null
  const value = String(route.params.id ?? '')
  const id = Number(value)
  if (!/^\d+$/.test(value) || !Number.isSafeInteger(id) || id <= 0) {
    unavailable.value = true
    loading.value = false
    return
  }
  try {
    const result = await fetchRequestById(id)
    if (current === version) {
      request.value = result ?? null
      unavailable.value = !result
    }
  } catch (cause) {
    if (current !== version) return
    if (
      axios.isAxiosError(cause) &&
      [403, 404].includes(cause.response?.status ?? 0)
    )
      unavailable.value = true
    else
      error.value = studentApiMessage(
        cause,
        'Failed to load request details. Please try again.'
      )
  } finally {
    if (current === version) loading.value = false
  }
}

async function performAction(action: 'reopen' | 'collect') {
  if (!request.value || pending.value) return
  const current = version
  const id = request.value.id
  pending.value = action
  actionError.value = ''
  success.value = ''
  try {
    const result =
      action === 'reopen'
        ? await reopenRequest(id)
        : await markRequestCollected(id)
    if (current !== version) return
    request.value = result
    success.value =
      action === 'reopen'
        ? 'Request reopened and returned to the pending queue.'
        : 'Request marked as collected.'
  } catch (cause) {
    if (current === version)
      actionError.value = studentApiMessage(
        cause,
        action === 'reopen'
          ? 'Failed to reopen the request. Please try again.'
          : 'Failed to mark the request as collected. Please try again.'
      )
  } finally {
    if (current === version) pending.value = null
  }
}

async function reopenWithUpgrade(tierId: number) {
  if (!request.value || pending.value) return
  const current = version
  const id = request.value.id
  pending.value = 'upgrade'
  actionError.value = ''
  success.value = ''
  try {
    const result = await reopenRequest(id, { request_type_tier_id: tierId })
    if (current !== version) return
    request.value = result
    success.value = `Request reopened with upgrade to ${result.pending_upgrade_tier?.name || 'higher tier'}. Please complete payment below.`
  } catch (cause) {
    if (current === version) {
      actionError.value = studentApiMessage(
        cause,
        'Failed to upgrade and reopen the request. Please try again.'
      )
    }
  } finally {
    if (current === version) pending.value = null
  }
}

function onPaymentCompleted(updated: DocumentRequest) {
  request.value = updated
  success.value = 'Payment confirmed! Your request has entered the processing queue.'
}

function onRequestUpdated(updated: DocumentRequest) {
  request.value = updated
}

function formatDate(value: string) {
  return new Date(value).toLocaleString('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short'
  })
}
const { onInvalidate, onReconnect } = useRealtime()

onInvalidate((event) => {
  if (request.value && event.request_id === request.value.id) {
    void loadRequest()
  }
})

onReconnect(() => {
  if (request.value) {
    void loadRequest()
  }
})

watch(() => route.params.id, loadRequest, { immediate: true })
</script>

<template>
  <div class="space-y-6">
    <RouterLink
      :to="{ name: 'student-requests' }"
      class="btn-ghost"
      ><span aria-hidden="true">←</span> Back to my requests</RouterLink
    >
    <PageHeader
      :eyebrow="request ? `Request #${request.id}` : 'Student portal'"
      :title="request?.request_type ?? 'Request details'"
      description="Your request information, documents, and department progress."
    >
      <template
        v-if="request"
        #actions
        ><StatusBadge
          kind="request"
          :status="request.status"
        /><button
          type="button"
          class="btn-secondary"
          :disabled="loading || !!pending"
          @click="loadRequest"
        >
          Refresh request
        </button></template
      >
    </PageHeader>
    <SkeletonLoader
      v-if="loading"
      :count="4"
    />
    <div
      v-else-if="error"
      role="alert"
      class="feedback-error space-y-3"
    >
      <p>{{ error }}</p>
      <button
        type="button"
        class="btn-secondary"
        @click="loadRequest"
      >
        Try again
      </button>
    </div>
    <EmptyState
      v-else-if="unavailable"
      title="Request unavailable"
      description="This request does not exist or is not available to your account."
      ><RouterLink
        :to="{ name: 'student-requests' }"
        class="btn-primary"
        >View my requests</RouterLink
      ></EmptyState
    >
    <template v-else-if="request">
      <!-- Prominent Payment Panel if Request is Awaiting Payment -->
      <StudentPaymentPanel
        v-if="request.status === 'awaiting_payment'"
        :request="request"
        :active-tiers="availableTiers"
        @payment-completed="onPaymentCompleted"
        @request-updated="onRequestUpdated"
      />

      <p
        v-if="actionError"
        role="alert"
        class="feedback-error"
      >
        {{ actionError }}
      </p>
      <p
        v-if="success"
        role="status"
        class="feedback-success"
      >
        {{ success }}
      </p>
      <div
        class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]"
      >
        <div class="min-w-0 space-y-6">
          <section
            class="card space-y-4"
            aria-labelledby="request-information-title"
          >
            <h2
              id="request-information-title"
              class="text-lg font-semibold"
            >
              Request information
            </h2>
            <p
              v-if="request.is_reopened"
              class="text-sm font-medium text-amber-800"
            >
              This request has been reopened.
            </p>
            <dl class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
              <div>
                <dt class="text-xs text-slate-500">Request ID</dt>
                <dd class="mt-1 text-sm font-medium">#{{ request.id }}</dd>
              </div>
              <div>
                <dt class="text-xs text-slate-500">Submitted</dt>
                <dd class="mt-1 text-sm font-medium">
                  {{ formatDate(request.created_at) }}
                </dd>
              </div>
              <div v-if="request.tier_name">
                <dt class="text-xs text-slate-500">Processing Tier</dt>
                <dd class="mt-1">
                  <TierBadge
                    :name="request.tier_name"
                    :priority="request.tier_priority"
                    :turnaround-days="request.turnaround_days"
                    :fee-amount="request.fee_amount"
                  />
                </dd>
              </div>
              <div v-if="request.fee_amount != null">
                <dt class="text-xs text-slate-500">Processing Fee</dt>
                <dd class="mt-1 text-sm font-medium">
                  {{ formatXAF(request.fee_amount) }}
                  <span
                    v-if="request.amount_paid != null && request.amount_paid >= request.fee_amount"
                    class="ml-1 text-xs text-emerald-700 font-semibold"
                  >
                    (Paid)
                  </span>
                  <span
                    v-else-if="request.amount_due != null && request.amount_due > 0"
                    class="ml-1 text-xs text-amber-700 font-semibold"
                  >
                    ({{ formatXAF(request.amount_due) }} due)
                  </span>
                </dd>
              </div>
              <div v-if="request.target_ready_at">
                <dt class="text-xs text-slate-500">Estimated Ready Date</dt>
                <dd class="mt-1 text-sm font-medium text-sky-800">
                  {{ formatDate(request.target_ready_at) }}
                </dd>
              </div>
              <div v-else-if="request.turnaround_days">
                <dt class="text-xs text-slate-500">Target Turnaround</dt>
                <dd class="mt-1 text-sm font-medium">
                  ~{{ request.turnaround_days }} days
                  <span
                    v-if="request.status === 'awaiting_payment'"
                    class="text-xs text-slate-400 block"
                  >
                    (starts after payment)
                  </span>
                </dd>
              </div>
            </dl>
            <div>
              <h3 class="text-sm font-semibold">Description</h3>
              <p
                class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-600"
              >
                {{ request.description || 'No description provided.' }}
              </p>
            </div>

            <!-- Actions Section: Reopen options when rejected, or Collect when ready -->
            <div
              v-if="request.status === 'rejected'"
              class="border-t border-slate-100 pt-5 space-y-4"
              data-testid="reopen-section"
            >
              <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-4">
                <div class="flex items-center gap-2">
                  <span class="inline-flex h-2 w-2 rounded-full bg-rose-500" />
                  <h3 class="text-sm font-bold text-rose-950">
                    Request Rejected — Reopen Options
                  </h3>
                </div>
                <p class="mt-1 text-xs text-rose-800">
                  This request was rejected during departmental review. You can return it to the review queue with your current tier or choose a faster processing tier.
                </p>

                <!-- Option A: Standard free reopen at same tier -->
                <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm">
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="text-sm font-semibold text-slate-900">
                        Reopen at current tier ({{ request.tier_name || 'Standard' }})
                      </span>
                      <span class="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">
                        Free
                      </span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">
                      Returns request to review queue with existing turnaround time at no extra fee.
                    </p>
                  </div>
                  <button
                    type="button"
                    class="btn-primary shrink-0"
                    data-testid="reopen-request"
                    :disabled="!!pending"
                    @click="performAction('reopen')"
                  >
                    {{ pending === 'reopen' ? 'Reopening…' : 'Reopen request' }}
                  </button>
                </div>

                <!-- Option B: Higher Tier Escalation (if higher tiers available) -->
                <div
                  v-if="higherTiers.length > 0"
                  class="mt-4 space-y-2.5"
                  data-testid="tier-escalation-options"
                >
                  <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                    <span>Or escalate speed with a tier upgrade:</span>
                    <span class="text-[11px] text-slate-500 font-normal">Pay delta fee top-up</span>
                  </div>
                  <div class="grid gap-2.5 sm:grid-cols-2">
                    <div
                      v-for="tier in higherTiers"
                      :key="tier.id"
                      class="flex flex-col justify-between rounded-lg border border-sky-200 bg-sky-50/70 p-3 text-xs"
                    >
                      <div>
                        <div class="flex items-center justify-between">
                          <span class="font-bold text-sky-950">{{ tier.name }}</span>
                          <span class="font-extrabold text-sky-800">
                            +{{ formatXAF(getUpgradeDelta(tier)) }}
                          </span>
                        </div>
                        <p class="mt-1 text-slate-600">
                          Turnaround: ~{{ tier.turnaround_days }} {{ tier.turnaround_days === 1 ? 'day' : 'days' }}
                        </p>
                        <p
                          v-if="tier.description"
                          class="mt-0.5 text-[11px] text-slate-500 line-clamp-2"
                        >
                          {{ tier.description }}
                        </p>
                      </div>
                      <div class="mt-3 pt-2 border-t border-sky-200/60">
                        <button
                          type="button"
                          class="btn-secondary w-full text-xs font-semibold text-sky-800 border-sky-300 hover:bg-sky-100"
                          :data-testid="'reopen-upgrade-' + tier.id"
                          :disabled="!!pending"
                          @click="reopenWithUpgrade(tier.id)"
                        >
                          {{ pending === 'upgrade' ? 'Upgrading…' : `Upgrade to ${tier.name} & Reopen` }}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-else-if="request.status === 'ready'"
              class="flex flex-wrap gap-3 border-t border-slate-100 pt-4"
            >
              <button
                type="button"
                class="btn-primary"
                :disabled="!!pending"
                @click="performAction('collect')"
              >
                {{
                  pending === 'collect'
                    ? 'Marking as collected…'
                    : 'Mark as collected'
                }}
              </button>
            </div>
          </section>
          <section
            class="card"
            aria-labelledby="request-documents-title"
          >
            <h2
              id="request-documents-title"
              class="mb-4 text-lg font-semibold"
            >
              Documents
              <span class="text-sm font-normal text-slate-500"
                >({{ request.attachments?.length ?? 0 }})</span
              >
            </h2>
            <DocumentViewer
              :key="request.id"
              :attachments="request.attachments ?? []"
            />
          </section>
          <section
            class="card"
            aria-labelledby="request-history-title"
          >
            <h2
              id="request-history-title"
              class="mb-4 text-lg font-semibold"
            >
              Status history
            </h2>
            <p
              v-if="!request.status_history?.length"
              class="text-sm text-slate-500"
            >
              No status changes recorded yet.
            </p>
            <ol
              v-else
              class="space-y-3"
            >
              <li
                v-for="entry in [...request.status_history].reverse()"
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
              </li>
            </ol>
          </section>
        </div>
        <section
          class="card min-w-0"
          aria-labelledby="request-timeline-title"
        >
          <h2
            id="request-timeline-title"
            class="mb-5 text-lg font-semibold"
          >
            Stage timeline
          </h2>
          <RequestTimeline :stages="request.stages ?? []" />
        </section>
      </div>
    </template>
  </div>
</template>
