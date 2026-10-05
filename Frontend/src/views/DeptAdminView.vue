<template>
  <router-view v-slot="{ Component, route }">
    <component
      :is="Component"
      :key="`${user?.id}:${route.path}`"
    />
  </router-view>
</template>

<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
const { user } = useAuth()
</script>
