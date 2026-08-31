<template>
  <div class="space-y-4">
    <!-- File list -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <button
        v-for="file in attachments"
        :key="file.id"
        type="button"
        class="p-3 border rounded-lg flex items-center justify-between transition-colors text-left"
        :class="
          activeFile?.id === file.id
            ? 'border-primary bg-primary/5'
            : 'border-neutral-200 hover:bg-neutral-50'
        "
        @click="select(file)"
      >
        <div class="flex items-center gap-2 overflow-hidden">
          <span class="text-lg shrink-0">{{ fileIcon(file) }}</span>
          <span class="text-sm font-medium text-foreground truncate">{{ file.original_name }}</span>
        </div>
        <span class="text-xs text-primary font-semibold shrink-0 ml-2">
          {{ activeFile?.id === file.id ? 'Viewing' : 'View' }}
        </span>
      </button>
    </div>

    <!-- Inline viewer -->
    <div
      v-if="activeFile"
      class="border border-neutral-200 rounded-lg overflow-hidden bg-neutral-50"
    >
      <!-- Toolbar -->
      <div class="flex items-center justify-between px-4 py-2 bg-white border-b border-neutral-200">
        <span class="text-sm font-medium text-foreground truncate max-w-xs">
          {{ activeFile.original_name }}
        </span>
        <div class="flex items-center gap-2 shrink-0">
          <a
            :href="activeFile.file_path"
            target="_blank"
            rel="noopener noreferrer"
            class="text-xs text-primary font-semibold hover:underline"
          >
            Open in new tab ↗
          </a>
          <button
            type="button"
            class="text-neutral-400 hover:text-neutral-600 text-lg leading-none ml-2"
            @click="activeFile = null"
          >
            ×
          </button>
        </div>
      </div>

      <!-- Image viewer -->
      <div v-if="isImage(activeFile)" class="flex items-center justify-center p-4 min-h-48 max-h-[60vh] overflow-auto">
        <img
          :src="activeFile.file_path"
          :alt="activeFile.original_name"
          class="max-w-full max-h-[55vh] object-contain rounded"
        />
      </div>

      <!-- PDF viewer -->
      <div v-else-if="isPdf(activeFile)" class="w-full" style="height: 60vh;">
        <iframe
          :src="activeFile.file_path"
          :title="activeFile.original_name"
          class="w-full h-full border-0"
        />
      </div>

      <!-- Unsupported type fallback -->
      <div v-else class="flex flex-col items-center justify-center py-10 gap-3 text-neutral-500">
        <span class="text-4xl">📎</span>
        <p class="text-sm">Preview not available for this file type.</p>
        <a
          :href="activeFile.file_path"
          target="_blank"
          rel="noopener noreferrer"
          class="btn-primary text-sm"
        >
          Download file
        </a>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!attachments.length" class="text-center py-8 text-neutral-400">
      No attachments uploaded for this request.
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { Attachment } from '../types'

const props = defineProps<{
  attachments: Attachment[]
}>()

const activeFile = ref<Attachment | null>(null)

function select(file: Attachment) {
  // Toggle: clicking the active file collapses the viewer
  activeFile.value = activeFile.value?.id === file.id ? null : file
}

const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']
const PDF_TYPES   = ['application/pdf']

function isImage(file: Attachment): boolean {
  if (file.mime_type && IMAGE_TYPES.includes(file.mime_type)) return true
  // Fall back to extension check when mime_type is absent
  return /\.(jpe?g|png|gif|webp|svg)$/i.test(file.original_name)
}

function isPdf(file: Attachment): boolean {
  if (file.mime_type && PDF_TYPES.includes(file.mime_type)) return true
  return /\.pdf$/i.test(file.original_name)
}

function fileIcon(file: Attachment): string {
  if (isImage(file)) return '🖼️'
  if (isPdf(file))   return '📄'
  return '📎'
}
</script>
