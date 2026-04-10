<template>
  <div class="space-y-6">
    <!-- Queue Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="card">
        <p class="text-sm text-neutral-600">Total Queue</p>
        <p class="text-3xl font-bold text-primary">{{ staffQueue.length }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">Pending</p>
        <p class="text-3xl font-bold text-yellow-600">{{ pendingCount }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">In Progress</p>
        <p class="text-3xl font-bold text-blue-600">{{ inProgressCount }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">Approved</p>
        <p class="text-3xl font-bold text-green-600">{{ approvedCount }}</p>
      </div>
    </div>

    <!-- Filter Section -->
    <div class="card">
      <div class="flex flex-col md:flex-row gap-4">
        <select v-model="filterStatus" class="input-field flex-1">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="in_progress">In Progress</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
        <input v-model="searchQuery" type="text" class="input-field flex-1" placeholder="Search by student name or request ID..." />
      </div>
    </div>

    <!-- Requests Queue -->
    <div class="space-y-3">
      <h2 class="text-xl font-semibold">Request Queue</h2>
      <div v-if="filteredRequests.length === 0" class="card text-center text-neutral-500">
        <p>No requests found.</p>
      </div>
      <div v-else v-for="request in filteredRequests" :key="request.id" class="card">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-3">
              <h3 class="font-semibold text-foreground">{{ request.studentName }}</h3>
              <span :class="['badge', getStatusBadgeClass(request.status)]">
                {{ formatStatus(request.status) }}
              </span>
            </div>
            <p class="text-sm text-neutral-600 mt-1">{{ request.title }}</p>
            <p class="text-xs text-neutral-500 mt-1">{{ request.studentEmail }} • ID: {{ request.id }}</p>
            <p class="text-sm text-foreground mt-2">{{ request.description }}</p>
            <div class="flex items-center gap-4 mt-3">
              <span class="text-xs font-medium">{{ formatType(request.type) }}</span>
              <span :class="['text-xs font-medium', getPriorityColor(request.priority)]">
                {{ request.priority.toUpperCase() }} PRIORITY
              </span>
              <span class="text-xs text-neutral-500">{{ formatDate(request.createdAt) }}</span>
            </div>
          </div>
          <div class="flex gap-2">
            <button
              @click="openStatusModal(request)"
              class="btn-secondary text-sm px-3 py-1"
            >
              Update
            </button>
            <button
              @click="selectedRequest = request"
              class="btn-secondary text-sm px-3 py-1"
            >
              Details
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Status Update Modal -->
    <div v-if="statusModal.open" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-md w-full">
        <div class="border-b border-neutral-200 p-4">
          <h2 class="text-lg font-bold">Update Status</h2>
        </div>
        <div class="p-4 space-y-4">
          <div v-if="statusModal.request">
            <p class="text-sm text-neutral-600 mb-2">Request: {{ statusModal.request.title }}</p>
            <p class="text-sm text-neutral-600 mb-4">Student: {{ statusModal.request.studentName }}</p>
            
            <label class="block text-sm font-medium mb-2">New Status</label>
            <select v-model="statusModal.newStatus" class="input-field mb-4">
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>

            <label class="block text-sm font-medium mb-2">Message</label>
            <textarea v-model="statusModal.message" class="input-field mb-4" rows="3" placeholder="Add a note about this status update..."></textarea>
          </div>
          <div class="flex gap-2 justify-end">
            <button @click="statusModal.open = false" class="btn-secondary">Cancel</button>
            <button @click="updateStatus" class="btn-primary">Update</button>
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
              <p class="text-sm text-neutral-600">Student</p>
              <p class="font-semibold">{{ selectedRequest.studentName }}</p>
              <p class="text-xs text-neutral-600">{{ selectedRequest.studentEmail }}</p>
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
            <p class="text-sm text-neutral-600 mb-2">Notes</p>
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
import { ref, computed, reactive } from 'vue'
import { useMockData } from '@/composables/useMockData'
import type { Request } from '@/types'

const { staffQueue, updateRequestStatus } = useMockData()
const selectedRequest = ref<Request | null>(null)
const filterStatus = ref('')
const searchQuery = ref('')

const statusModal = reactive({
  open: false,
  request: null as Request | null,
  newStatus: 'in_progress',
  message: ''
})

const pendingCount = computed(() => staffQueue.value.filter(r => r.status === 'pending').length)
const inProgressCount = computed(() => staffQueue.value.filter(r => r.status === 'in_progress').length)
const approvedCount = computed(() => staffQueue.value.filter(r => r.status === 'approved').length)

const filteredRequests = computed(() => {
  return staffQueue.value.filter(request => {
    const matchStatus = !filterStatus.value || request.status === filterStatus.value
    const matchSearch = !searchQuery.value || 
      request.studentName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      request.id.toLowerCase().includes(searchQuery.value.toLowerCase())
    return matchStatus && matchSearch
  })
})

const openStatusModal = (request: Request) => {
  statusModal.request = request
  statusModal.newStatus = request.status
  statusModal.message = ''
  statusModal.open = true
}

const updateStatus = () => {
  if (statusModal.request) {
    updateRequestStatus(
      statusModal.request.id,
      statusModal.newStatus as any,
      statusModal.message || `Status updated to ${formatStatus(statusModal.newStatus)}`
    )
    statusModal.open = false
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

const getPriorityColor = (priority: string) => {
  const map: Record<string, string> = {
    low: 'text-neutral-600',
    medium: 'text-yellow-600',
    high: 'text-red-600'
  }
  return map[priority] || ''
}
</script>
