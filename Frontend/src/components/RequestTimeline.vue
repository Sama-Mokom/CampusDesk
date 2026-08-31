<template>
  <div class="space-y-0">
    <div
      v-for="(stage, index) in ordered"
      :key="stage.id"
      class="flex gap-3"
    >
      <div class="flex flex-col items-center w-8 shrink-0">
        <div
          :class="[
            'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 shrink-0',
            nodeClass(stage, index)
          ]"
        >
          <span v-if="stage.status === 'approved'" class="text-green-700">✓</span>
          <span v-else-if="stage.status === 'rejected'" class="text-red-700">✕</span>
          <span v-else-if="isActive(stage)" class="text-primary">{{ stage.sequence_order }}</span>
          <span v-else class="text-neutral-500">{{ stage.sequence_order }}</span>
        </div>
        <div v-if="index < ordered.length - 1" class="w-0.5 flex-1 min-h-6 bg-neutral-300 my-1" />
      </div>
      <div class="flex-1 pb-6 pt-0.5">
        <div class="flex flex-wrap items-center gap-2">
          <p class="font-semibold text-sm text-foreground">{{ stage.department_name }}</p>
          <StatusBadge kind="stage" :status="stage.status" />
        </div>
        <p v-if="stage.staff_note" class="text-sm text-neutral-700 mt-1">{{ stage.staff_note }}</p>
        <p v-if="stage.handled_by" class="text-xs text-neutral-600 mt-1">
          Handled by {{ stage.handled_by }}
        </p>
        <p v-if="stage.updated_at" class="text-xs text-neutral-500 mt-1">{{ formatTime(stage.updated_at) }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { RequestStage } from '../types'
import StatusBadge from './StatusBadge.vue'
import { format } from 'date-fns'

const props = defineProps<{ stages: RequestStage[] }>()

const ordered = computed(() => {
  const list = Array.isArray(props.stages) ? props.stages : []
  return [...list].sort((a, b) => a.sequence_order - b.sequence_order)
})

function isActive(stage: RequestStage): boolean {
  if (stage.status === 'rejected' || stage.status === 'approved') return false
  const ord = ordered.value
  const i = ord.findIndex(s => s.id === stage.id)
  if (i < 0) return false
  for (let j = 0; j < i; j++) {
    if (ord[j]!.status !== 'approved') return false
  }
  return true
}

function nodeClass(stage: RequestStage, index: number) {
  if (stage.status === 'approved') return 'bg-green-50 border-green-500'
  if (stage.status === 'rejected') return 'bg-red-50 border-red-500'
  if (isActive(stage)) return 'bg-white border-primary ring-2 ring-primary/30'
  return 'bg-neutral-50 border-neutral-300'
}

function formatTime(iso: string) {
  try {
    return format(new Date(iso), 'MMM d, yyyy · HH:mm')
  } catch {
    return iso
  }
}
</script>
