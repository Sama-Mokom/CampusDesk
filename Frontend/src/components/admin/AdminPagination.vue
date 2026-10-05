<template>
  <nav
    class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4 text-sm"
    :aria-label="label"
  >
    <span class="text-slate-500"
      >Page {{ page }} of {{ Math.max(1, last)
      }}<span
        v-if="total !== undefined"
        class="hidden sm:inline"
      >
        · {{ total }} results</span
      ></span
    >
    <div class="flex gap-2">
      <button
        class="btn-secondary"
        :disabled="disabled || page <= 1"
        @click="$emit('change', page - 1)"
      >
        Previous</button
      ><button
        class="btn-secondary"
        :disabled="disabled || page >= last"
        @click="$emit('change', page + 1)"
      >
        Next
      </button>
    </div>
  </nav>
</template>
<script setup lang="ts">
withDefaults(
  defineProps<{
    page: number
    last: number
    total?: number
    disabled?: boolean
    label?: string
  }>(),
  { disabled: false, label: 'Result pages' }
)
defineEmits<{ change: [page: number] }>()
</script>
