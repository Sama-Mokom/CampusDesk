<script setup lang="ts">
defineProps<{
  name:
    | 'home'
    | 'requests'
    | 'plus'
    | 'logout'
    | 'menu'
    | 'close'
    | 'users'
    | 'building'
    | 'arrow'
    | 'shield'
    | 'check'
}>()
const paths = {
  home: 'm3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z',
  requests: 'M14 3H5v18h14V8Zm0 0v5h5M8 12h8M8 16h5',
  plus: 'M12 5v14M5 12h14',
  logout: 'M9 4H4v16h5M9 12h12m-4-4 4 4-4 4',
  menu: 'M4 6h16M4 12h16M4 18h16',
  close: 'm6 6 12 12M6 18 18 6',
  users:
    'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 4a4 4 0 0 1 0 8m6 9v-2a4 4 0 0 0-3-3.87M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
  building:
    'M3 21h18M5 21V7l7-4 7 4v14M9 21v-4h6v4M9 8v2m6-2v2m-6 3h.01M15 13h.01',
  arrow: 'M5 12h14m-5-5 5 5-5 5',
  shield: 'm12 3 8 4v5c0 5-8 9-8 9s-8-4-8-9V7Zm-4 9 3 3 5-5',
  check: 'm5 12 4 4L19 6'
}
</script>
<template>
  <svg
    aria-hidden="true"
    class="h-5 w-5 shrink-0"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <path :d="paths[name]" />
  </svg>
</template>
