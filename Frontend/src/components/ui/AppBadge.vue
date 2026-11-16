<script setup lang="ts">
export type BadgeTone =
  | 'neutral'
  | 'success'
  | 'warning'
  | 'info'
  | 'purple'
  | 'danger'
  | 'orange'

withDefaults(
  defineProps<{
    tone?: BadgeTone
    dot?: boolean
  }>(),
  {
    tone: 'neutral',
    dot: true
  }
)
</script>

<template>
  <span :class="['badge', `badge-${tone}`]">
    <span
      v-if="dot"
      aria-hidden="true"
      class="h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-70"
    />
    <slot />
  </span>
</template>
