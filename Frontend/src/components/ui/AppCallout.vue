<script setup lang="ts">
import { computed } from 'vue'

type CalloutTone = 'info' | 'success' | 'warning' | 'danger' | 'neutral'

const props = withDefaults(defineProps<{ tone?: CalloutTone }>(), {
  tone: 'info'
})

// Literal classes keep these rules in Tailwind's generated component layer.
const TONE_CLASS: Record<CalloutTone, string> = {
  info: 'callout-info',
  success: 'callout-success',
  warning: 'callout-warning',
  danger: 'callout-danger',
  neutral: 'callout-neutral'
}

const toneClass = computed(() => TONE_CLASS[props.tone])
</script>

<template>
  <div
    :class="['callout', toneClass]"
    role="note"
  >
    <slot />
  </div>
</template>
