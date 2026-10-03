<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    size?: 'sm' | 'md' | 'lg' | 'xl'
    busy?: boolean
  }>(),
  { size: 'lg', busy: false }
)
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLDialogElement | null>(null)
const titleId = useId()
let previousFocus: HTMLElement | null = null
let previousOverflow = ''
let locked = false
const widths = {
  sm: 'max-w-md',
  md: 'max-w-xl',
  lg: 'max-w-3xl',
  xl: 'max-w-5xl'
}

function restore() {
  if (!locked) return
  document.body.style.overflow = previousOverflow
  locked = false
  if (previousFocus?.isConnected) previousFocus.focus()
}
function close() {
  if (!props.busy) emit('close')
}
function trapFocus(event: KeyboardEvent) {
  if (event.key !== 'Tab') return
  const nodes = Array.from(
    dialog.value?.querySelectorAll<HTMLElement>(
      'button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]'
    ) ?? []
  ).filter((el) => !el.hidden && el.getAttribute('aria-hidden') !== 'true')
  const first = nodes[0]
  const last = nodes[nodes.length - 1]
  if (!first || !last) {
    event.preventDefault()
    dialog.value?.focus()
    return
  }
  if (
    event.shiftKey &&
    (document.activeElement === first ||
      document.activeElement === dialog.value)
  ) {
    event.preventDefault()
    last.focus()
  } else if (
    !event.shiftKey &&
    (document.activeElement === last || document.activeElement === dialog.value)
  ) {
    event.preventDefault()
    first.focus()
  }
}
watch(
  () => props.open,
  async (open) => {
    if (open) {
      previousFocus = document.activeElement as HTMLElement | null
      previousOverflow = document.body.style.overflow
      locked = true
      document.body.style.overflow = 'hidden'
      await nextTick()
      if (!props.open || !dialog.value) return
      // Native dialogs make the application inert; open is a jsdom fallback.
      if (typeof dialog.value.showModal === 'function') dialog.value.showModal()
      else dialog.value.setAttribute('open', '')
      dialog.value.focus()
    } else {
      if (dialog.value?.open && typeof dialog.value.close === 'function')
        dialog.value.close()
      restore()
    }
  },
  { immediate: true }
)
onBeforeUnmount(restore)
</script>

<template>
  <Teleport to="body">
    <dialog
      v-if="open"
      ref="dialog"
      role="dialog"
      :aria-labelledby="titleId"
      aria-modal="true"
      :aria-busy="busy"
      tabindex="-1"
      class="base-modal"
      :class="widths[size]"
      @cancel.prevent="close"
      @keydown="trapFocus"
      @click.self="close"
    >
      <div class="modal-surface">
        <header
          class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6"
        >
          <h2
            :id="titleId"
            class="min-w-0 break-words"
          >
            {{ title }}
          </h2>
          <button
            class="icon-button"
            type="button"
            aria-label="Close dialog"
            :disabled="busy"
            @click="close"
          >
            <svg
              aria-hidden="true"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
            >
              <path d="m6 6 12 12M6 18 18 6" />
            </svg>
          </button>
        </header>
        <div class="min-h-0 overflow-y-auto overscroll-contain p-5 sm:p-6">
          <slot />
        </div>
        <footer
          v-if="$slots.footer"
          class="flex shrink-0 flex-wrap justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6"
        >
          <slot name="footer" />
        </footer>
      </div>
    </dialog>
  </Teleport>
</template>

<style scoped>
.base-modal {
  width: calc(100% - 2rem);
  max-height: calc(100dvh - 2rem);
  margin: auto;
  padding: 0;
  overflow: hidden;
  color: #0f172a;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 1rem;
  box-shadow: 0 24px 80px #0f172a33;
}
.base-modal::backdrop {
  background: #0f172a99;
  backdrop-filter: blur(3px);
}
.modal-surface {
  display: flex;
  flex-direction: column;
  max-height: calc(100dvh - 2rem);
}
@media (max-width: 639px) {
  .base-modal {
    width: 100%;
    max-width: 100%;
    max-height: calc(100dvh - 1rem);
    margin: auto 0 0;
    border-radius: 1rem 1rem 0 0;
  }
  .modal-surface {
    max-height: calc(100dvh - 1rem);
  }
}
</style>
