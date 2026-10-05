<script setup lang="ts">
import { onScopeDispose, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import axios from 'axios'
import { studentApiMessage } from '@/composables/useStudentRequests'
import {
  fetchRequestById,
  markRequestCollected,
  reopenRequest
} from '@/services/requests'
import type { Request as DocumentRequest } from '@/types'
import { requestStatusLabel } from '@/types'
import PageHeader from '@/components/ui/PageHeader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import RequestTimeline from '@/components/RequestTimeline.vue'
import DocumentViewer from '@/components/DocumentViewer.vue'

const route = useRoute()
const request = ref<DocumentRequest | null>(null)
const loading = ref(true)
const error = ref('')
const unavailable = ref(false)
const actionError = ref('')
const success = ref('')
const pending = ref<'reopen' | 'collect' | null>(null)
let version = 0
onScopeDispose(() => {
  version++
})

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
function formatDate(value: string) {
  return new Date(value).toLocaleString('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short'
  })
}
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
            </dl>
            <div>
              <h3 class="text-sm font-semibold">Description</h3>
              <p
                class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-600"
              >
                {{ request.description || 'No description provided.' }}
              </p>
            </div>
            <div
              v-if="request.status === 'rejected' || request.status === 'ready'"
              class="flex flex-wrap gap-3 border-t border-slate-100 pt-4"
            >
              <button
                v-if="request.status === 'rejected'"
                type="button"
                class="btn-primary"
                data-testid="reopen-request"
                :disabled="!!pending"
                @click="performAction('reopen')"
              >
                {{ pending === 'reopen' ? 'Reopening…' : 'Reopen request' }}
              </button>
              <button
                v-if="request.status === 'ready'"
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
