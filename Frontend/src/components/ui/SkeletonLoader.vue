<script setup lang="ts">
withDefaults(defineProps<{ count?: number }>(), { count: 3 })
</script>
<template>
  <div
    role="status"
    aria-label="Loading content"
    aria-busy="true"
    class="space-y-3"
  >
    <span class="sr-only">Loading content…</span>
    <div
      v-for="n in count"
      :key="n"
      aria-hidden="true"
      class="animate-pulse rounded-xl border border-slate-200 bg-white p-5"
    >
      <div class="flex justify-between gap-6">
        <div class="h-4 w-2/5 rounded bg-slate-200" />
        <div class="h-5 w-20 rounded-full bg-slate-100" />
      </div>
      <div class="mt-4 h-3 w-3/5 rounded bg-slate-100" />
      <div class="mt-3 h-3 w-1/3 rounded bg-slate-100" />
    </div>
  </div>
</template>
