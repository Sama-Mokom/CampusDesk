<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import type {
  CreateRequestPayload,
  Request as DocumentRequest,
  RequestTypeEntity
} from '../../types'
import SkeletonLoader from '../ui/SkeletonLoader.vue'

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
}>()
const typeControl = ref<HTMLSelectElement | null>(null)
const form = reactive({ request_type_id: '', description: '' })
const files = ref<File[]>([])
const validation = ref('')
const attempted = ref(false)
const dragging = ref(false)
const selectedType = computed(() =>
  props.requestTypes.find((type) => type.id === Number(form.request_type_id))
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
    description: form.description.trim(),
    attachments: [...files.value]
  })
}
function reset() {
  form.request_type_id = ''
  form.description = ''
  files.value = []
  validation.value = ''
  attempted.value = false
  emit('reset')
  void focus()
}
</script>

<template>
  <section
    id="new-request"
    class="card scroll-mt-24"
    aria-labelledby="new-request-heading"
  >
    <template v-if="confirmation">
      <div
        class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-green-700"
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
        class="text-lg font-semibold text-slate-900"
      >
        Request submitted
      </h2>
      <p class="mt-2 text-sm leading-6 text-slate-600">
        Request #{{ confirmation.id }} is in the queue. Track its progress as
        each department reviews it.
      </p>
      <ol
        v-if="orderedDepartments.length"
        class="my-5 space-y-3"
        aria-label="Processing departments"
      >
        <li
          v-for="(stage, index) in orderedDepartments"
          :key="stage.id"
          class="flex items-center gap-3 text-sm text-slate-700"
        >
          <span
            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sky-50 text-xs font-semibold text-sky-700"
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
          Track this request</button
        ><button
          type="button"
          class="btn-secondary w-full"
          @click="reset"
        >
          Submit another
        </button>
      </div>
    </template>
    <template v-else>
      <div class="mb-5 flex items-start gap-3">
        <span
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700"
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
            class="text-lg font-semibold text-slate-900"
          >
            New request
          </h2>
          <p class="mt-1 text-sm text-slate-500">Start with what you need.</p>
        </div>
      </div>
      <SkeletonLoader
        v-if="loading"
        :count="3"
      />
      <p
        v-else-if="requestTypes.length === 0"
        class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600"
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
          class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"
        >
          {{ validation || error }}
        </p>
        <div>
          <label
            for="student-request-type"
            class="mb-2 block text-sm font-medium text-slate-700"
            >Request type
            <span
              class="text-red-600"
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
            class="mt-2 text-xs leading-5 text-slate-500"
          >
            {{ selectedType.description }}
          </p>
        </div>
        <div>
          <label
            for="student-description"
            class="mb-2 block text-sm font-medium text-slate-700"
            >Description
            <span
              class="text-red-600"
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
            class="mt-1 text-right text-xs text-slate-500"
          >
            {{ form.description.length }} / 1,000
          </p>
        </div>
        <div>
          <label
            for="student-attachments"
            class="mb-2 block text-sm font-medium text-slate-700"
            >Supporting documents
            <span class="font-normal text-slate-500">(optional)</span></label
          >
          <div
            class="rounded-xl border-2 border-dashed p-4 text-center transition-colors"
            :class="
              dragging
                ? 'border-sky-500 bg-sky-50'
                : 'border-slate-200 bg-slate-50/60'
            "
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
          >
            <svg
              class="mx-auto mb-2 h-6 w-6 text-slate-400"
              aria-hidden="true"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.7"
            >
              <path d="M12 16V4m-4 4 4-4 4 4M4 16v4h16v-4" />
            </svg>
            <p class="text-sm text-slate-600">Drop files here, or browse</p>
            <input
              id="student-attachments"
              type="file"
              multiple
              accept=".pdf,.docx,.jpg,.jpeg,.png"
              class="mt-3 block min-h-11 w-full min-w-0 text-xs text-slate-600 file:mr-2 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-2 file:text-xs file:font-semibold file:text-sky-700"
              :disabled="submitting"
              aria-describedby="attachment-help"
              @change="onFiles"
            />
            <p
              id="attachment-help"
              class="mt-2 text-xs leading-5 text-slate-500"
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
              class="flex items-center gap-2 rounded-lg border border-slate-200 pl-3"
            >
              <span class="min-w-0 flex-1 break-all text-xs text-slate-700">{{
                file.name
              }}</span
              ><button
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-700"
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
          {{ submitting ? 'Submitting request…' : 'Submit request'
          }}<span
            v-if="!submitting"
            aria-hidden="true"
            >→</span
          >
        </button>
        <p class="text-center text-xs leading-5 text-slate-500">
          Your request will be routed to the right departments.
        </p>
      </form>
    </template>
  </section>
</template>
