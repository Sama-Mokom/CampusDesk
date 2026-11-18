<script setup lang="ts">
import { computed } from 'vue'

type BadgeTone =
  | 'neutral'
  | 'success'
  | 'warning'
  | 'info'
  | 'purple'
  | 'danger'
  | 'orange'

const props = withDefaults(
  defineProps<{
    tone?: BadgeTone
    /** Status badges keep a dot so colour is never the only signal. */
    dot?: boolean
  }>(),
  {
    tone: 'neutral',
    dot: true
  }
)

// Keep every tone as a literal class so Tailwind's content scanner emits the
// matching component rule. Interpolating `badge-${tone}` would be purged.
const TONE_CLASS: Record<BadgeTone, string> = {
  neutral: 'badge-neutral',
  success: 'badge-success',
  warning: 'badge-warning',
  info: 'badge-info',
  purple: 'badge-purple',
  danger: 'badge-danger',
  orange: 'badge-orange'
}

const toneClass = computed(() => TONE_CLASS[props.tone])
</script>

<template>
  <span :class="['badge', toneClass]">
    <span
      v-if="dot"
      aria-hidden="true"
      class="h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-70"
    />
    <slot />
  </span>
</template>
