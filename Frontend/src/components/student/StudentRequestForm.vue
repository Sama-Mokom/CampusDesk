<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import type {
  CreateRequestPayload,
  Request as DocumentRequest,
  RequestTypeEntity,
  RequestTypeTier
} from '../../types'
import SkeletonLoader from '../ui/SkeletonLoader.vue'
import StudentPaymentPanel from './StudentPaymentPanel.vue'
import { formatXAF } from '../TierBadge.vue'

const props = defineProps<{
  requestTypes: RequestTypeEntity[]
  loading: boolean
  submitting: boolean
  error: string
  confirmation: DocumentRequest | null
}>()
const emit = defineEmits<{
  submit: [payload: CreateRequestPayload]
  reset: []
  view: [request: DocumentRequest]
  updated: [request: DocumentRequest]
}>()
const typeControl = ref<HTMLSelectElement | null>(null)
const form = reactive({
  request_type_id: '',
  request_type_tier_id: '',
  description: ''
})
const files = ref<File[]>([])
const validation = ref('')
const attempted = ref(false)
const dragging = ref(false)

const selectedType = computed(() =>
  props.requestTypes.find((type) => type.id === Number(form.request_type_id))
)

const activeTiers = computed<RequestTypeTier[]>(() =>
  (selectedType.value?.tiers ?? []).filter((t) => t.is_active !== false)
)

const selectedTier = computed<RequestTypeTier | undefined>(() =>
  activeTiers.value.find(
    (tier) => String(tier.id) === String(form.request_type_tier_id)
  )
)

const confirmationType = computed(() =>
  props.requestTypes.find(
    (type) =>
      type.id === props.confirmation?.request_type_id ||
      type.name === props.confirmation?.request_type
  )
)

const confirmationTiers = computed<RequestTypeTier[]>(() =>
  (confirmationType.value?.tiers ?? []).filter((t) => t.is_active !== false)
)

watch(
  () => form.request_type_id,
  () => {
    if (activeTiers.value.length === 1 && activeTiers.value[0]) {
      form.request_type_tier_id = String(activeTiers.value[0].id)
    } else {
      form.request_type_tier_id = ''
    }
  }
)

const orderedDepartments = computed(() =>
  [...(props.confirmation?.stages ?? [])].sort(
    (a, b) => a.sequence_order - b.sequence_order
  )
)

watch(
  () => props.confirmation,
  (confirmation) => {
    if (!confirmation) return
    form.request_type_id = ''
    form.request_type_tier_id = ''
    form.description = ''
    files.value = []
    validation.value = ''
    attempted.value = false
  }
)

async function focus() {
  if (props.confirmation) emit('reset')
  await nextTick()
  typeControl.value?.focus()
  typeControl.value?.scrollIntoView?.({ behavior: 'auto', block: 'center' })
}
defineExpose({ focus })

function addFiles(newFiles: File[]) {
  validation.value = ''
  for (const file of newFiles) {
    if (!/\.(pdf|docx|jpe?g|png)$/i.test(file.name)) {
      validation.value = 'Choose PDF, DOCX, JPG, or PNG files.'
      continue
    }
    if (file.size > 5 * 1024 * 1024) {
      validation.value = `${file.name} exceeds the 5 MB limit.`
      continue
    }
    if (
      !files.value.some(
        (existing) =>
          existing.name === file.name &&
          existing.size === file.size &&
          existing.lastModified === file.lastModified
      )
    )
      files.value.push(file)
  }
}
function onFiles(event: Event) {
  const input = event.target as HTMLInputElement
  addFiles(Array.from(input.files ?? []))
  input.value = ''
}
function onDrop(event: DragEvent) {
  dragging.value = false
  if (!props.submitting) addFiles(Array.from(event.dataTransfer?.files ?? []))
}
function submit() {
  if (props.submitting) return
  attempted.value = true
  validation.value = ''
  if (!selectedType.value) {
    validation.value = 'Choose a request type to continue.'
    typeControl.value?.focus()
    return
  }
  if (activeTiers.value.length > 1 && !selectedTier.value) {
    validation.value = 'Choose a processing tier to continue.'
    return
  }
  if (!form.description.trim()) {
    validation.value =
      'Add a description so your department knows what you need.'
    return
  }
  if (form.description.trim().length > 1000) {
    validation.value = 'Keep your description to 1,000 characters or fewer.'
    return
  }
  emit('submit', {
    request_type_id: selectedType.value.id,
    request_type_tier_id: selectedTier.value ? selectedTier.value.id : undefined,
    description: form.description.trim(),
    attachments: [...files.value]
  })
}
function reset() {
  form.request_type_id = ''
  form.request_type_tier_id = ''
  form.description = ''
  files.value = []
  validation.value = ''
  attempted.value = false
  emit('reset')
  void focus()
}
function onConfirmationUpdated(updated: DocumentRequest) {
  emit('updated', updated)
}
</script>

<template>
  <section
    class="card"
    aria-labelledby="new-request-heading"
  >
    <template v-if="confirmation">
      <!-- Awaiting Payment State: Directly render StudentPaymentPanel -->
      <div
        v-if="confirmation.status === 'awaiting_payment'"
        class="space-y-5"
        data-testid="confirmation-awaiting-payment"
      >
        <div class="flex items-center gap-3">
          <div
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-status-warning-bg text-status-warning-text"
            aria-hidden="true"
          >
            <svg
              class="h-6 w-6"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <rect x="2" y="5" width="20" height="14" rx="2" />
              <line x1="2" y1="10" x2="22" y2="10" />
            </svg>
          </div>
          <div>
            <h2
              id="new-request-heading"
              class="text-lg font-semibold text-heading"
            >
              Request submitted — Fee payment required
            </h2>
            <p class="text-xs text-muted">
              Request #{{ confirmation.id }}
              <span v-if="confirmation.request_type">· {{ confirmation.request_type }}</span>
            </p>
          </div>
        </div>

        <StudentPaymentPanel
          :request="confirmation"
          :available-tiers="confirmationTiers"
          @updated="onConfirmationUpdated"
        />

        <div class="flex flex-col sm:flex-row gap-2 pt-1">
          <button
            type="button"
            class="btn-secondary flex-1"
            @click="emit('view', confirmation)"
          >
            View request details
          </button>
          <button
            type="button"
            class="btn-ghost"
            @click="reset"
          >
            Submit another
          </button>
        </div>
      </div>

      <!-- Paid or Standard Completed Confirmation -->
      <div
        v-else
        data-testid="confirmation-submitted-standard"
      >
        <div
          class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-status-success-bg text-status-success-text"
          aria-hidden="true"
        >
          <svg
            class="h-6 w-6"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path d="m5 12 4 4L19 6" />
          </svg>
        </div>
        <h2
          id="new-request-heading"
          class="text-lg font-semibold text-heading"
        >
          Request submitted
        </h2>
        <p class="mt-2 text-sm leading-6 text-body">
          Request #{{ confirmation.id }}
          <span v-if="confirmation.tier_name" class="font-medium text-heading">
            ({{ confirmation.tier_name }}
            <span v-if="confirmation.fee_amount">· {{ formatXAF(confirmation.fee_amount) }}</span>)
          </span>
          is in the queue. Track its progress as each department reviews it.
        </p>
        <ol
          v-if="orderedDepartments.length"
          class="my-5 space-y-3"
          aria-label="Processing departments"
        >
          <li
            v-for="(stage, index) in orderedDepartments"
            :key="stage.id"
            class="flex items-center gap-3 text-sm text-body"
          >
            <span
              class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent-soft-text"
              >{{ index + 1 }}</span
            >{{ stage.department_name }}
          </li>
        </ol>
        <div class="mt-5 flex flex-col gap-2">
          <button
            type="button"
            class="btn-primary w-full"
            @click="emit('view', confirmation)"
          >
            Track this request
          </button>
          <button
            type="button"
            class="btn-secondary w-full"
            @click="reset"
          >
            Submit another
          </button>
        </div>
      </div>
    </template>
    <template v-else>
      <div class="mb-5 flex items-start gap-3">
        <span
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent-soft text-accent-soft-text"
          aria-hidden="true"
          ><svg
            class="h-5 w-5"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
          >
            <path d="M12 5v14M5 12h14" /></svg
        ></span>
        <div>
          <h2
            id="new-request-heading"
            class="text-lg font-semibold text-heading"
          >
            New request
          </h2>
          <p class="mt-1 text-sm text-muted">Start with what you need.</p>
        </div>
      </div>
      <SkeletonLoader
        v-if="loading"
        :count="3"
      />
      <p
        v-else-if="requestTypes.length === 0"
        class="rounded-xl bg-surface-muted p-4 text-sm text-body"
      >
        No request types are currently available. Please try refreshing the
        dashboard.
      </p>
      <form
        v-else
        class="space-y-5"
        novalidate
        :aria-busy="submitting"
        @submit.prevent="submit"
      >
        <p
          v-if="validation || error"
          id="student-form-error"
          role="alert"
          class="rounded-lg border border-status-danger-border bg-status-danger-bg p-3 text-sm text-status-danger-text"
        >
          {{ validation || error }}
        </p>
        <div>
          <label
            for="student-request-type"
            class="mb-2 block text-sm font-medium text-body"
            >Request type
            <span
              class="text-status-danger-text"
              aria-hidden="true"
              >*</span
            ></label
          >
          <select
            id="student-request-type"
            ref="typeControl"
            v-model="form.request_type_id"
            class="input-field"
            required
            :disabled="submitting"
            :aria-invalid="attempted && !selectedType"
            :aria-describedby="
              attempted && !selectedType
                ? 'student-form-error'
                : selectedType?.description
                  ? 'request-type-description'
                  : undefined
            "
          >
            <option
              value=""
              disabled
            >
              Select a request type
            </option>
            <option
              v-for="type in requestTypes"
              :key="type.id"
              :value="String(type.id)"
            >
              {{ type.name }}
            </option>
          </select>
          <p
            v-if="selectedType?.description"
            id="request-type-description"
            class="mt-2 text-xs leading-5 text-muted"
          >
            {{ selectedType.description }}
          </p>

          <!-- Single Tier Summary Card -->
          <div
            v-if="selectedType && activeTiers.length === 1 && activeTiers[0]"
            class="mt-3 rounded-xl border border-accent-soft-border bg-accent-soft p-3.5"
            data-testid="tier-summary-card"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-accent-soft-text">
                  Processing Fee
                </span>
                <p class="mt-0.5 text-base font-bold text-heading">
                  {{ formatXAF(activeTiers[0].fee_amount) }}
                </p>
              </div>
              <div class="text-right">
                <span class="text-xs font-semibold uppercase tracking-wider text-accent-soft-text">
                  Target Turnaround
                </span>
                <p class="mt-0.5 text-sm font-medium text-body">
                  ~{{ activeTiers[0].turnaround_days }} days
                </p>
              </div>
            </div>
          </div>

          <!-- Multiple Tiers Radio Cards -->
          <fieldset
            v-else-if="selectedType && activeTiers.length > 1"
            class="mt-4 space-y-2"
            data-testid="tier-selector-fieldset"
          >
            <legend class="mb-2 block text-sm font-medium text-body">
              Processing tier & fee
              <span class="text-status-danger-text" aria-hidden="true">*</span>
            </legend>
            <div class="grid gap-3 sm:grid-cols-3">
              <label
                v-for="tier in activeTiers"
                :key="tier.id"
                :class="[
                  'relative flex cursor-pointer flex-col justify-between rounded-xl border p-3.5 transition-all',
                  String(form.request_type_tier_id) === String(tier.id)
                    ? 'border-accent-text bg-accent-soft shadow-sm ring-2 ring-accent-soft-border'
                    : 'border-subtle bg-surface-card hover:border-strong'
                ]"
              >
                <div class="flex items-start justify-between gap-1.5">
                  <span class="text-sm font-semibold text-heading">{{ tier.name }}</span>
                  <input
                    type="radio"
                    name="request-tier"
                    :value="String(tier.id)"
                    v-model="form.request_type_tier_id"
                    class="sr-only"
                    :disabled="submitting"
                  />
                  <span
                    v-if="tier.priority === 3"
                    class="shrink-0 rounded-full bg-status-purple-bg px-2 py-0.5 text-[10px] font-bold text-status-purple-text"
                  >
                    Top Priority
                  </span>
                  <span
                    v-else-if="tier.priority === 2"
                    class="shrink-0 rounded-full bg-accent-soft px-2 py-0.5 text-[10px] font-bold text-accent-soft-text"
                  >
                    Priority
                  </span>
                </div>
                <div class="mt-2.5">
                  <p class="text-base font-bold text-heading">{{ formatXAF(tier.fee_amount) }}</p>
                  <p class="mt-0.5 text-xs text-muted">~{{ tier.turnaround_days }} days turnaround</p>
                </div>
                <p v-if="tier.description" class="mt-2 text-[11px] leading-4 text-muted">
                  {{ tier.description }}
                </p>
              </label>
            </div>
          </fieldset>
        </div>

        <div>
          <label
            for="student-description"
            class="mb-2 block text-sm font-medium text-body"
            >Description
            <span
              class="text-status-danger-text"
              aria-hidden="true"
              >*</span
            ></label
          ><textarea
            id="student-description"
            v-model="form.description"
            class="input-field resize-y"
            rows="5"
            maxlength="1000"
            placeholder="Tell us what you need and any details that will help your department."
            required
            :disabled="submitting"
            :aria-invalid="attempted && !form.description.trim()"
            :aria-describedby="
              attempted && !form.description.trim()
                ? 'student-form-error description-count'
                : 'description-count'
            "
          />
          <p
            id="description-count"
            class="mt-1 text-right text-xs text-muted"
          >
            {{ form.description.length }} / 1,000
          </p>
        </div>
        <div>
          <label
            for="student-attachments"
            class="mb-2 block text-sm font-medium text-body"
            >Supporting documents
            <span class="font-normal text-muted">(optional)</span></label
          >
          <div
            class="rounded-xl border-2 border-dashed p-4 text-center transition-colors"
            :class="
              dragging
                ? 'border-accent-text bg-accent-soft'
                : 'border-strong bg-surface-muted'
            "
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
          >
            <svg
              class="mx-auto mb-2 h-6 w-6 text-muted"
              aria-hidden="true"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.7"
            >
              <path d="M12 16V4m-4 4 4-4 4 4M4 16v4h16v-4" />
            </svg>
            <p class="text-sm text-body">Drop files here, or browse</p>
            <input
              id="student-attachments"
              type="file"
              multiple
              accept=".pdf,.docx,.jpg,.jpeg,.png"
              class="mt-3 block min-h-11 w-full min-w-0 text-xs text-body file:mr-2 file:rounded-md file:border file:border-strong file:bg-surface-muted file:px-3 file:py-2 file:text-xs file:font-semibold file:text-accent-text"
              :disabled="submitting"
              aria-describedby="attachment-help"
              @change="onFiles"
            />
            <p
              id="attachment-help"
              class="mt-2 text-xs leading-5 text-muted"
            >
              PDF, DOCX, JPG, PNG · Up to 5 MB per file
            </p>
          </div>
          <ul
            v-if="files.length"
            class="mt-3 space-y-2"
            aria-label="Selected attachments"
          >
            <li
              v-for="(file, index) in files"
              :key="`${file.name}-${file.lastModified}`"
              class="flex items-center gap-2 rounded-lg border border-subtle bg-surface-muted pl-3"
            >
              <span class="min-w-0 flex-1 break-all text-xs text-body">{{
                file.name
              }}</span
              ><button
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-muted hover:bg-status-danger-bg hover:text-status-danger-text"
                :disabled="submitting"
                :aria-label="`Remove ${file.name}`"
                @click="files.splice(index, 1)"
              >
                <span aria-hidden="true">×</span>
              </button>
            </li>
          </ul>
        </div>
        <button
          type="submit"
          class="btn-primary w-full"
          :disabled="submitting"
        >
          {{
            submitting
              ? 'Submitting request…'
              : selectedTier
                ? `Submit request (${formatXAF(selectedTier.fee_amount)})`
                : 'Submit request'
          }}<span
            v-if="!submitting"
            aria-hidden="true"
            >→</span
          >
        </button>
        <p class="text-center text-xs leading-5 text-muted">
          Your request will be routed to the right departments.
        </p>
      </form>
    </template>
  </section>
</template>
