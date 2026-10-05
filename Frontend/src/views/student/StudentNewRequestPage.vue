<script setup lang="ts">
import { onMounted, onScopeDispose, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { studentApiMessage } from '@/composables/useStudentRequests'
import { createRequest } from '@/services/requests'
import { fetchRequestTypes } from '@/services/reference'
import type {
  CreateRequestPayload,
  Request as DocumentRequest,
  RequestTypeEntity
} from '@/types'
import PageHeader from '@/components/ui/PageHeader.vue'
import StudentRequestForm from '@/components/student/StudentRequestForm.vue'

const router = useRouter()
const { user } = useAuth()
const requestTypes = ref<RequestTypeEntity[]>([])
const loading = ref(true)
const loadError = ref('')
const submitting = ref(false)
const submitError = ref('')
const confirmation = ref<DocumentRequest | null>(null)
let active = true
onScopeDispose(() => {
  active = false
})

async function loadTypes() {
  loading.value = true
  loadError.value = ''
  try {
    const result = await fetchRequestTypes()
    if (active) requestTypes.value = result
  } catch (cause) {
    if (active)
      loadError.value = studentApiMessage(
        cause,
        'Request types could not be loaded. Please try again.'
      )
  } finally {
    if (active) loading.value = false
  }
}
async function submitRequest(payload: CreateRequestPayload) {
  if (submitting.value || user.value?.role !== 'student') return
  submitting.value = true
  submitError.value = ''
  try {
    const result = await createRequest(payload)
    if (active) confirmation.value = result
  } catch (cause) {
    if (active)
      submitError.value = studentApiMessage(
        cause,
        'Failed to submit the request. Please try again.'
      )
  } finally {
    if (active) submitting.value = false
  }
}
function resetRequest() {
  confirmation.value = null
  submitError.value = ''
}
function viewRequest(request: DocumentRequest) {
  void router.push({
    name: 'student-request-detail',
    params: { id: request.id }
  })
}
onMounted(loadTypes)
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Student portal"
      title="New request"
      description="Tell us what you need and attach any supporting documents."
    >
      <template #actions
        ><RouterLink
          :to="{ name: 'student-requests' }"
          class="btn-secondary"
          >Back to my requests</RouterLink
        ></template
      >
    </PageHeader>
    <div
      v-if="loadError"
      role="alert"
      class="feedback-error flex flex-wrap items-center justify-between gap-3"
    >
      <span>{{ loadError }}</span
      ><button
        type="button"
        class="btn-secondary"
        :disabled="loading"
        @click="loadTypes"
      >
        Try again
      </button>
    </div>
    <p
      v-if="confirmation"
      role="status"
      class="feedback-success"
    >
      Request #{{ confirmation.id }} submitted successfully.
    </p>
    <div
      class="grid items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
    >
      <StudentRequestForm
        v-if="!loadError"
        :request-types="requestTypes"
        :loading="loading"
        :submitting="submitting"
        :error="submitError"
        :confirmation="confirmation"
        @submit="submitRequest"
        @reset="resetRequest"
        @view="viewRequest"
      />
      <aside class="rounded-xl border border-sky-100 bg-sky-50/60 p-5">
        <h2 class="text-base font-semibold text-sky-900">What happens next?</h2>
        <p class="mt-3 text-sm leading-6 text-sky-800">
          After submission, your request is routed to the relevant departments.
          You can check its status, review staff notes, and open your documents
          from the request details page.
        </p>
        <RouterLink
          :to="{ name: 'student-requests' }"
          class="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-sky-800"
          >Go to my requests
          <span
            aria-hidden="true"
            class="ml-2"
            >→</span
          ></RouterLink
        >
      </aside>
    </div>
  </div>
</template>
