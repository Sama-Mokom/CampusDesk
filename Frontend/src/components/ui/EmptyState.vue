<script setup lang="ts">
defineProps<{ title: string; description?: string }>()
</script>
<template>
  <div class="flex flex-col items-center px-4 py-10 text-center">
    <div
      class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400"
    >
      <svg
        aria-hidden="true"
        width="24"
        height="24"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
      >
        <path d="M8 3h8l4 4v14H4V3h4Zm7 0v5h5M8 12h8M8 16h5" />
      </svg>
    </div>
    <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
    <p
      v-if="description"
      class="mt-2 max-w-sm text-sm text-slate-500"
    >
      {{ description }}
    </p>
    <div
      v-if="$slots.default"
      class="mt-5"
    >
      <slot />
    </div>
  </div>
</template>
