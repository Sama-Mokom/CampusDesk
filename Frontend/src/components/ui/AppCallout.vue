<script setup lang="ts">
type CalloutTone = 'info' | 'success' | 'warning' | 'danger' | 'neutral'

withDefaults(defineProps<{ tone?: CalloutTone }>(), { tone: 'info' })
</script>

<template>
  <div
    :class="['callout', `callout-${tone}`]"
    role="note"
  >
    <slot />
  </div>
</template>
