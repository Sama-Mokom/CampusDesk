<template>
  <span :class="['badge', badgeClass]">{{ label }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { RequestStatus, StageStatus } from '@/types'
import { requestStatusLabel, stageStatusLabel } from '@/types'

const props = defineProps<{
  kind: 'request' | 'stage'
  status: RequestStatus | StageStatus
}>()

const label = computed(() =>
  props.kind === 'request'
    ? requestStatusLabel(props.status as RequestStatus)
    : stageStatusLabel(props.status as StageStatus)
)

const badgeClass = computed(() => {
  if (props.kind === 'stage') {
    const s = props.status as StageStatus
    if (s === 'approved') return 'badge-stage-approved'
    if (s === 'rejected') return 'badge-rejected'
    if (s === 'in_review') return 'badge-in-review'
    return 'badge-pending'
  }
  const map: Record<RequestStatus, string> = {
    draft: 'badge-draft',
    pending: 'badge-pending',
    in_review: 'badge-in-review',
    forwarded: 'badge-forwarded',
    ready: 'badge-ready',
    collected: 'badge-collected',
    rejected: 'badge-rejected'
  }
  return map[props.status as RequestStatus] ?? 'badge-draft'
})
</script>
