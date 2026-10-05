<template>
  <BaseModal
    :open="!!target"
    title="Delete record"
    size="sm"
    :busy="busy"
    @close="emit('close')"
  >
    <p class="text-sm text-slate-600">
      Delete
      <strong class="break-words text-slate-900">{{ target?.name }}</strong
      >? This cannot be undone. Records used by other parts of CampusDesk cannot
      be deleted.
    </p>
    <p
      v-if="error"
      role="alert"
      class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800"
    >
      {{ error }}
    </p>
    <template #footer
      ><button
        class="btn-secondary"
        :disabled="busy"
        @click="emit('close')"
      >
        Cancel</button
      ><button
        class="btn-primary !bg-red-700 hover:!bg-red-800"
        :disabled="busy"
        @click="emit('confirm')"
      >
        {{ busy ? 'Deleting…' : 'Delete record' }}
      </button></template
    >
  </BaseModal>
</template>

<script setup lang="ts">
import BaseModal from '@/components/ui/BaseModal.vue'
defineProps<{ target: { name: string } | null; busy: boolean; error: string }>()
const emit = defineEmits<{ close: []; confirm: [] }>()
</script>
