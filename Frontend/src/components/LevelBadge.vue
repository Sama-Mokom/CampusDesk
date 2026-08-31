<template>
  <span
    class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-primary/10 text-primary border border-primary/20"
  >
    {{ level }}
  </span>
</template>

<script setup lang="ts">
import type { StudentLevel } from '../types'

defineProps<{ level: StudentLevel }>()
</script>

