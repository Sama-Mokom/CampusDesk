<script setup lang="ts">
import { useRouter } from 'vue-router'
import StaffCaseWorkspace from '@/components/staff/StaffCaseWorkspace.vue'
const router = useRouter()
function openCases() {
  void router.push('/staff/cases')
}
</script>

<template>
  <StaffCaseWorkspace
    mode="queue"
    @claimed="openCases"
  />
</template>
