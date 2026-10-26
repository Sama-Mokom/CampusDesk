<template>
  <span
    :class="['badge', badgeClass]"
    :title="tooltip"
  >
    <span
      v-if="priority === 3"
      aria-hidden="true"
      class="text-[11px]"
      >⚡</span
    >
    <span
      v-else
      aria-hidden="true"
      class="h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-70"
    />
    {{ name }}
  </span>
</template>

<script lang="ts">
export function formatXAF(amount: number | null | undefined): string {
  if (amount == null) return ''
  return `${amount.toLocaleString('en-US')} XAF`
}
</script>

<script setup lang="ts">
import { computed } from 'vue'

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

const badgeClass = computed(() => {
  if (props.priority === 3 || /super\s*fast/i.test(props.name)) {
    return 'badge-tier-super-fast'
  }
  if (props.priority === 2 || /fast|standard/i.test(props.name)) {
    return 'badge-tier-fast'
  }
  return 'badge-tier-normal'
})

const tooltip = computed(() => {
  const parts: string[] = []
  if (props.feeAmount != null) parts.push(formatXAF(props.feeAmount))
  if (props.turnaroundDays != null) parts.push(`~${props.turnaroundDays} days`)
  return parts.length ? parts.join(' · ') : undefined
})
</script>
