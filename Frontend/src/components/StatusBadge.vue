<template>
  <AppBadge :tone="tone">{{ label }}</AppBadge>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AppBadge from './ui/AppBadge.vue'
import type { RequestStatus, StageStatus } from '@/types'
import { requestStatusLabel, stageStatusLabel } from '@/types'

type Tone = 'neutral' | 'success' | 'warning' | 'info' | 'purple' | 'danger' | 'orange'

const props = defineProps<{
  kind: 'request' | 'stage'
  status: RequestStatus | StageStatus
}>()

const label = computed(() =>
  props.kind === 'request'
    ? requestStatusLabel(props.status as RequestStatus)
    : stageStatusLabel(props.status as StageStatus)
)

const tone = computed<Tone>(() => {
  if (props.kind === 'stage') {
    const s = props.status as StageStatus
    if (s === 'approved') return 'success'
    if (s === 'rejected') return 'danger'
    if (s === 'in_review') return 'info'
    return 'warning'
  }
  const map: Record<RequestStatus, Tone> = {
    draft: 'neutral',
    awaiting_payment: 'warning',
    pending: 'warning',
    in_review: 'info',
    forwarded: 'purple',
    ready: 'success',
    collected: 'success',
    rejected: 'danger'
  }
  return map[props.status as RequestStatus] ?? 'neutral'
})
</script>
