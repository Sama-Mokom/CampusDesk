<script setup lang="ts">
import AppIcon from '../ui/AppIcon.vue'
defineProps<{ title: string; description: string }>()
</script>
<template>
  <div
    class="mx-auto grid max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[0.85fr_1.15fr]"
  >
    <aside
      class="relative hidden flex-col overflow-hidden bg-slate-900 p-10 text-white lg:flex"
    >
      <div
        aria-hidden="true"
        class="absolute -right-32 -top-32 h-96 w-96 rounded-full border border-sky-300/10"
      />
      <div class="relative">
        <span
          class="mb-8 flex h-12 w-12 items-center justify-center rounded-xl border border-sky-400/30 bg-sky-500/10 text-sky-300"
          ><AppIcon name="building"
        /></span>
        <p
          class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-300"
        >
          Your campus, connected
        </p>
        <h2 class="mt-5 text-4xl font-semibold leading-tight tracking-tight">
          Less paperwork.<br />More possibility.
        </h2>
        <p class="mt-5 leading-relaxed text-slate-300">
          A simpler way to request documents and follow every step of your
          university journey.
        </p>
      </div>
      <svg
        aria-hidden="true"
        class="my-12 w-full text-sky-300/40"
        viewBox="0 0 300 150"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
      >
        <path
          d="M20 140h260M40 140V60l110-45 110 45v80M35 60h230M60 75h180M60 120h180M70 75v45m40-45v45m40-45v45m40-45v45m40-45v45M130 140v-20h40v20M140 40h20M150 30v20"
        />
        <path d="M60 140v-20m180 20v-20M110 15l40-12 40 12" />
      </svg>
      <div
        class="mt-auto space-y-4 border-t border-slate-700 pt-6 text-sm text-slate-300"
      >
        <p class="flex items-center gap-3">
          <AppIcon name="shield" />Secure document access
        </p>
        <p class="flex items-center gap-3">
          <AppIcon name="requests" />A clear view of every request
        </p>
        <p class="flex items-center gap-3">
          <AppIcon name="users" />Connected across departments
        </p>
      </div>
    </aside>
    <section class="px-5 py-8 sm:p-10 lg:p-12">
      <p class="eyebrow mb-3">Welcome to CampusDesk</p>
      <h1 class="text-3xl">{{ title }}</h1>
      <p class="mb-8 mt-3 text-slate-500">{{ description }}</p>
      <slot />
    </section>
  </div>
</template>
