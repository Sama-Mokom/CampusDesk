<template>
  <div class="space-y-6">
    <!-- Request Form Section -->
    <div class="card">
      <h2 class="text-xl font-semibold mb-4">Submit New Request</h2>
      <form @submit.prevent="submitRequest" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Request Type</label>
          <select v-model="form.type" class="input-field">
            <option value="transcript">Transcript</option>
            <option value="financial_aid">Financial Aid</option>
            <option value="accommodation">Accommodation</option>
            <option value="enrollment">Enrollment</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Title</label>
          <input v-model="form.title" type="text" class="input-field" placeholder="Brief title of your request" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Description</label>
          <textarea v-model="form.description" class="input-field" rows="4" placeholder="Detailed description of your request"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Priority</label>
          <select v-model="form.priority" class="input-field">
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>
        <button type="submit" class="btn-primary">Submit Request</button>
      </form>
    </div>

    <!-- Requests List Section -->
    <div>
      <h2 class="text-xl font-semibold mb-4">Your Requests</h2>
      <div v-if="studentRequests.length === 0" class="card text-center text-neutral-500">
        <p>You have no requests yet.</p>
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="request in studentRequests"
          :key="request.id"
          class="card cursor-pointer hover:border-primary transition-colors"
          @click="selectedRequest = request"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <h3 class="font-semibold text-foreground">{{ request.title }}</h3>
              <p class="text-sm text-neutral-600 mt-1">{{ request.description }}</p>
              <div class="flex items-center gap-2 mt-2">
                <span :class="['badge', getStatusBadgeClass(request.status)]">
                  {{ formatStatus(request.status) }}
                </span>
                <span class="text-xs text-neutral-500">{{ formatDate(request.createdAt) }}</span>
              </div>
            </div>
            <span v-if="request.type" class="text-xs font-medium text-primary ml-2">
              {{ formatType(request.type) }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Request Detail Modal -->
    <div v-if="selectedRequest" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-neutral-200 p-6 flex justify-between items-start">
          <h2 class="text-2xl font-bold">{{ selectedRequest.title }}</h2>
          <button @click="selectedRequest = null" class="text-neutral-500 hover:text-foreground text-2xl">×</button>
        </div>
        
        <div class="p-6 space-y-6">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-neutral-600">Type</p>
              <p class="font-semibold">{{ formatType(selectedRequest.type) }}</p>
            </div>
            <div>
              <p class="text-sm text-neutral-600">Status</p>
              <div :class="['badge', 'mt-1', getStatusBadgeClass(selectedRequest.status)]">
                {{ formatStatus(selectedRequest.status) }}
              </div>
            </div>
            <div>
              <p class="text-sm text-neutral-600">Priority</p>
              <p class="font-semibold capitalize">{{ selectedRequest.priority }}</p>
            </div>
            <div>
              <p class="text-sm text-neutral-600">Created</p>
              <p class="font-semibold">{{ formatDate(selectedRequest.createdAt) }}</p>
            </div>
          </div>

          <div>
            <p class="text-sm text-neutral-600 mb-2">Description</p>
            <p class="text-foreground">{{ selectedRequest.description }}</p>
          </div>

          <div v-if="selectedRequest.notes" class="bg-neutral-50 p-4 rounded-md border border-neutral-200">
            <p class="text-sm text-neutral-600 mb-2">Staff Notes</p>
            <p class="text-foreground">{{ selectedRequest.notes }}</p>
          </div>

          <div>
            <p class="text-sm text-neutral-600 mb-3">Timeline</p>
            <div class="space-y-3">
              <div v-for="(item, index) in selectedRequest.timeline" :key="index" class="flex gap-3">
                <div class="flex flex-col items-center">
                  <div :class="['w-3 h-3 rounded-full', getStatusColor(item.status)]"></div>
                  <div v-if="index < selectedRequest.timeline.length - 1" class="w-0.5 h-8 bg-neutral-300 mt-1"></div>
                </div>
                <div class="flex-1 pt-0.5">
                  <p class="font-semibold text-sm">{{ formatStatus(item.status) }}</p>
                  <p class="text-xs text-neutral-600">{{ formatDate(item.timestamp) }}</p>
                  <p class="text-sm text-foreground mt-1">{{ item.message }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useMockData } from '@/composables/useMockData'
import type { RequestType } from '@/types'

const { studentRequests, createRequest } = useMockData()
const selectedRequest = ref(null)

const form = reactive({
  type: 'other' as RequestType,
  title: '',
  description: '',
  priority: 'medium' as 'low' | 'medium' | 'high'
})

const submitRequest = () => {
  if (form.title.trim() && form.description.trim()) {
    createRequest({
      studentId: 'stu-001',
      studentName: 'Alice Johnson',
      studentEmail: 'alice.johnson@university.edu',
      ...form
    })
    form.title = ''
    form.description = ''
    form.type = 'other'
    form.priority = 'medium'
  }
}

const formatStatus = (status: string) => {
  const map: Record<string, string> = {
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
    in_progress: 'In Progress',
    completed: 'Completed'
  }
  return map[status] || status
}

const formatType = (type: string) => {
  const map: Record<string, string> = {
    transcript: 'Transcript',
    financial_aid: 'Financial Aid',
    accommodation: 'Accommodation',
    enrollment: 'Enrollment',
    other: 'Other'
  }
  return map[type] || type
}

const formatDate = (date: Date) => {
  if (!(date instanceof Date)) {
    date = new Date(date)
  }
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const getStatusBadgeClass = (status: string) => {
  const map: Record<string, string> = {
    pending: 'badge-pending',
    approved: 'badge-approved',
    rejected: 'badge-rejected',
    in_progress: 'badge-in-progress',
    completed: 'badge-approved'
  }
  return map[status] || ''
}

const getStatusColor = (status: string) => {
  const map: Record<string, string> = {
    pending: 'bg-yellow-400',
    approved: 'bg-green-500',
    rejected: 'bg-red-500',
    in_progress: 'bg-blue-500',
    completed: 'bg-green-600'
  }
  return map[status] || 'bg-gray-400'
}
</script>
