<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import type { Attachment } from '../types'
import api from '../services/api'
import EmptyState from './ui/EmptyState.vue'
import SkeletonLoader from './ui/SkeletonLoader.vue'
import AppIcon from './ui/AppIcon.vue'

const props = defineProps<{ attachments: Attachment[] }>()
const activeFile = ref<Attachment | null>(null)
const blobUrl = ref<string | null>(null)
const loadingFile = ref(false)
const error = ref('')
let requestVersion = 0
function releaseUrl() {
  if (blobUrl.value) URL.revokeObjectURL(blobUrl.value)
  blobUrl.value = null
}
function close() {
  requestVersion++
  activeFile.value = null
  loadingFile.value = false
  error.value = ''
  releaseUrl()
}
async function load(file: Attachment) {
  const version = ++requestVersion
  releaseUrl()
  loadingFile.value = true
  error.value = ''
  try {
    const response = await api.get(`/attachments/${file.id}`, {
      responseType: 'blob'
    })
    if (version !== requestVersion) return
    const mime =
      response.data instanceof Blob && response.data.type
        ? response.data.type
        : file.mime_type
    blobUrl.value = URL.createObjectURL(
      new Blob([response.data], { type: mime })
    )
  } catch {
    if (version === requestVersion)
      error.value =
        'This document could not be loaded. Check your connection and try again.'
  } finally {
    if (version === requestVersion) loadingFile.value = false
  }
}
function select(file: Attachment) {
  if (activeFile.value?.id === file.id) {
    close()
    return
  }
  activeFile.value = file
  void load(file)
}
function openInNewTab() {
  if (blobUrl.value) window.open(blobUrl.value, '_blank', 'noopener,noreferrer')
}
function isImage(file: Attachment) {
  return (
    /^image\/(jpeg|png|gif|webp|svg\+xml)$/.test(file.mime_type ?? '') ||
    /\.(jpe?g|png|gif|webp|svg)$/i.test(file.original_name)
  )
}
function isPdf(file: Attachment) {
  return (
    file.mime_type === 'application/pdf' || /\.pdf$/i.test(file.original_name)
  )
}
watch(
  () => props.attachments,
  (files) => {
    if (
      activeFile.value &&
      !files.some((file) => file.id === activeFile.value?.id)
    )
      close()
  }
)
onBeforeUnmount(close)
</script>

<template>
  <div class="min-w-0 space-y-4">
    <div class="grid gap-3 sm:grid-cols-2">
      <button
        v-for="file in attachments"
        :key="file.id"
        type="button"
        class="flex min-w-0 items-center justify-between gap-3 rounded-lg border p-3 text-left transition-colors"
        :aria-pressed="activeFile?.id === file.id"
        :class="
          activeFile?.id === file.id
            ? 'border-sky-600 bg-sky-50'
            : 'border-slate-200 bg-white hover:bg-slate-50'
        "
        @click="select(file)"
      >
        <span class="flex min-w-0 items-center gap-3"
          ><span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"
            ><AppIcon name="requests" /></span
          ><span class="min-w-0"
            ><span class="block break-all text-sm font-medium">{{
              file.original_name
            }}</span
            ><span class="mt-1 block text-xs text-slate-500">{{
              isPdf(file)
                ? 'PDF document'
                : isImage(file)
                  ? 'Image'
                  : 'Attachment'
            }}</span></span
          ></span
        >
        <span class="shrink-0 text-xs font-semibold text-sky-800">{{
          activeFile?.id === file.id ? 'Viewing' : 'View'
        }}</span>
      </button>
    </div>
    <section
      v-if="activeFile"
      class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
      aria-label="Document preview"
      :aria-busy="loadingFile"
    >
      <div
        class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-white p-3"
      >
        <p class="min-w-0 flex-1 break-all text-sm font-semibold">
          {{ activeFile.original_name }}
        </p>
        <div class="flex flex-wrap items-center gap-1">
          <a
            v-if="blobUrl"
            class="btn-ghost"
            :href="blobUrl"
            :download="activeFile.original_name"
            >Download</a
          >
          <button
            type="button"
            class="btn-ghost"
            :disabled="!blobUrl"
            @click="openInNewTab"
          >
            Open in new tab ↗
          </button>
          <button
            type="button"
            class="icon-button"
            aria-label="Close document preview"
            @click="close"
          >
            <AppIcon name="close" />
          </button>
        </div>
      </div>
      <div
        v-if="loadingFile"
        class="p-5"
      >
        <SkeletonLoader :count="2" />
      </div>
      <div
        v-else-if="error"
        class="m-4 feedback-error"
        role="alert"
      >
        <p>{{ error }}</p>
        <button
          type="button"
          class="btn-secondary mt-3"
          @click="load(activeFile)"
        >
          Retry document
        </button>
      </div>
      <div
        v-else-if="blobUrl && isImage(activeFile)"
        class="p-4"
      >
        <img
          :src="blobUrl"
          :alt="activeFile.original_name"
          class="mx-auto max-h-[65vh] max-w-full object-contain"
        />
      </div>
      <iframe
        v-else-if="blobUrl && isPdf(activeFile)"
        :src="blobUrl"
        :title="activeFile.original_name"
        class="h-[60vh] min-h-72 w-full border-0"
      />
      <EmptyState
        v-else
        title="Preview not available for this file type."
        description="Use Download or Open in new tab to view this attachment."
      />
    </section>
    <EmptyState
      v-if="!attachments.length"
      title="No documents"
      description="No attachments uploaded for this request."
    />
  </div>
</template>
