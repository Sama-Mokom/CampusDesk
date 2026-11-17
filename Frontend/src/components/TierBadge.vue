<template>
  <AppBadge
    :tone="tone"
    :title="tooltip"
  >
    {{ name }}
  </AppBadge>
</template>

<script lang="ts">
export function formatXAF(amount: number | null | undefined): string {
  if (amount == null) return ''
  return `${amount.toLocaleString('en-US')} XAF`
}
</script>

<script setup lang="ts">
import { computed } from 'vue'
import AppBadge from './ui/AppBadge.vue'

type Tone = 'neutral' | 'purple'

const props = withDefaults(
  defineProps<{
    name: string
    priority?: number
    turnaroundDays?: number
    feeAmount?: number
  }>(),
  {
    priority: 1,
    turnaroundDays: undefined,
    feeAmount: undefined
  }
)

const tone = computed<Tone>(() => {
  if (
    props.priority === 3 ||
    props.priority === 2 ||
    /fast/i.test(props.name)
  ) {
    return 'purple'
  }
  return 'neutral'
})

const tooltip = computed(() => {
  const parts: string[] = []
  if (props.feeAmount != null) parts.push(formatXAF(props.feeAmount))
  if (props.turnaroundDays != null) parts.push(`~${props.turnaroundDays} days`)
  return parts.length ? parts.join(' · ') : undefined
})
</script>
