<template>
  <AppBadge tone="neutral">{{ level }}</AppBadge>
</template>

<script setup lang="ts">
import AppBadge from './ui/AppBadge.vue'
import type { StudentLevel } from '../types'

defineProps<{ level: StudentLevel }>()
</script>
