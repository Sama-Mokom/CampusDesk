<script setup lang="ts">
import { computed } from 'vue'
import type { Request as DocumentRequest } from '../../types'
import StatusBadge from '../StatusBadge.vue'

const props = defineProps<{ request: DocumentRequest }>()
const emit = defineEmits<{ view: [request: DocumentRequest] }>()
const stages = computed(() =>
  [...(props.request.stages ?? [])].sort(
    (a, b) => a.sequence_order - b.sequence_order
  )
)
const completed = computed(
  () => stages.value.filter((stage) => stage.status === 'approved').length
)
const current = computed(() =>
  stages.value.find((stage) => stage.status !== 'approved')
)
const date = computed(() =>
  new Date(props.request.created_at).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  })
)
</script>

<template>
  <article
    class="rounded-xl border border-slate-200 bg-white p-4 transition-colors hover:border-sky-200 sm:p-5"
  >
    <div class="flex items-start gap-3">
      <span
        class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500 sm:flex"
        aria-hidden="true"
        ><svg
          class="h-5 w-5"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.7"
        >
          <path d="M14 3H5v18h14V8Zm0 0v5h5M8 12h8M8 16h5" /></svg
      ></span>
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="text-xs font-medium text-slate-500">
              Request #{{ request.id }}
            </p>
            <h3
              class="mt-1 break-words text-[15px] font-semibold text-slate-900"
            >
              {{ request.request_type }}
            </h3>
          </div>
          <StatusBadge
            kind="request"
            :status="request.status"
          />
        </div>
        <p
          class="mt-2 line-clamp-2 break-words text-sm leading-6 text-slate-500"
        >
          {{ request.description }}
        </p>
        <div
          v-if="stages.length"
          class="mt-4"
        >
          <div
            class="mb-2 flex flex-wrap justify-between gap-1 text-xs text-slate-500"
          >
            <span class="break-words">{{
              current ? current.department_name : 'All departments completed'
            }}</span
            ><span>{{ completed }} of {{ stages.length }} stages</span>
          </div>
          <div
            class="flex gap-1"
            role="img"
            :aria-label="`${completed} of ${stages.length} departments completed`"
          >
            <span
              v-for="stage in stages"
              :key="stage.id"
              class="h-1 flex-1 rounded-full"
              :class="
                stage.status === 'approved'
                  ? 'bg-green-500'
                  : stage.status === 'in_review'
                    ? 'bg-sky-500'
                    : stage.status === 'rejected'
                      ? 'bg-red-400'
                      : 'bg-slate-100'
              "
            />
          </div>
        </div>
        <div
          class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3"
        >
          <time
            :datetime="request.created_at"
            class="text-xs text-slate-500"
            >{{ date }}</time
          ><button
            type="button"
            class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-sky-700 transition-colors hover:bg-sky-50"
            :aria-label="`View ${request.request_type}, request ${request.id}`"
            @click="emit('view', request)"
          >
            View details <span aria-hidden="true">→</span>
          </button>
        </div>
      </div>
    </div>
  </article>
</template>
