<template>
  <RouterView v-slot="{ Component, route }">
    <component
      :is="Component"
      :key="`${user?.id ?? 'guest'}:${route.path}`"
    />
  </RouterView>
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const { user } = useAuth()
</script>
