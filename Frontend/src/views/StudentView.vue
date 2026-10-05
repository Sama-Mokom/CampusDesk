<template>
  <RouterView :key="user?.id ?? 'guest'" />
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const { user } = useAuth()
</script>
