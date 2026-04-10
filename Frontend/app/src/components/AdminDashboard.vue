<template>
  <div class="space-y-6">
    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
      <div class="card">
        <p class="text-sm text-neutral-600">Total Requests</p>
        <p class="text-3xl font-bold text-primary">{{ adminStats.totalRequests }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">Pending</p>
        <p class="text-3xl font-bold text-yellow-600">{{ adminStats.pendingRequests }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">Approved</p>
        <p class="text-3xl font-bold text-green-600">{{ adminStats.approvedRequests }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">Rejected</p>
        <p class="text-3xl font-bold text-red-600">{{ adminStats.rejectedRequests }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-neutral-600">Avg Processing</p>
        <p class="text-3xl font-bold text-primary-light">{{ adminStats.avgProcessingTime }}d</p>
      </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="card">
      <div class="flex flex-col md:flex-row gap-4">
        <input v-model="searchQuery" type="text" class="input-field flex-1" placeholder="Search by student name, email, or request ID..." />
        <select v-model="filterStatus" class="input-field flex-1">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="in_progress">In Progress</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
        <select v-model="filterType" class="input-field flex-1">
          <option value="">All Types</option>
          <option value="transcript">Transcript</option>
          <option value="financial_aid">Financial Aid</option>
          <option value="accommodation">Accommodation</option>
          <option value="enrollment">Enrollment</option>
          <option value="other">Other</option>
        </select>
      </div>
    </div>

    <!-- Requests Table -->
    <div class="card overflow-x-auto">
      <h2 class="text-lg font-semibold mb-4">All Requests</h2>
      <table class="w-full text-sm">
        <thead class="border-b border-neutral-300 bg-neutral-50">
          <tr>
            <th class="text-left py-3 px-4 font-semibold">Request ID</th>
            <th class="text-left py-3 px-4 font-semibold">Student</th>
            <th class="text-left py-3 px-4 font-semibold">Title</th>
            <th class="text-left py-3 px-4 font-semibold">Type</th>
            <th class="text-left py-3 px-4 font-semibold">Status</th>
            <th class="text-left py-3 px-4 font-semibold">Priority</th>
            <th class="text-left py-3 px-4 font-semibold">Created</th>
            <th class="text-left py-3 px-4 font-semibold">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-200">
          <tr v-if="filteredRequests.length === 0" class="hover:bg-neutral-50">
            <td colspan="8" class="py-4 px-4 text-center text-neutral-500">No requests found.</td>
          </tr>
          <tr v-for="request in filteredRequests" :key="request.id" class="hover:bg-neutral-50">
            <td class="py-3 px-4 font-mono text-xs">{{ request.id }}</td>
            <td class="py-3 px-4">
              <div>
                <p class="font-semibold">{{ request.studentName }}</p>
                <p class="text-xs text-neutral-600">{{ request.studentEmail }}</p>
              </div>
            </td>
            <td class="py-3 px-4">{{ request.title }}</td>
            <td class="py-3 px-4">{{ formatType(request.type) }}</td>
            <td class="py-3 px-4">
              <span :class="['badge', getStatusBadgeClass(request.status)]">
                {{ formatStatus(request.status) }}
              </span>
            </td>
            <td class="py-3 px-4">
              <span :class="getPriorityBadgeClass(request.priority)">
                {{ request.priority.toUpperCase() }}
              </span>
            </td>
            <td class="py-3 px-4 text-xs">{{ formatDate(request.createdAt) }}</td>
            <td class="py-3 px-4">
              <button @click="selectedRequest = request" class="text-primary hover:text-primary-light font-semibold">
                View
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- User Management Section -->
    <div class="card">
      <h2 class="text-lg font-semibold mb-4">System Users</h2>
      <div class="space-y-2">
        <div class="flex items-center justify-between p-3 bg-neutral-50 rounded-md">
          <div>
            <p class="font-semibold">Alice Johnson</p>
            <p class="text-xs text-neutral-600">Student • alice.johnson@university.edu</p>
          </div>
          <button class="text-xs btn-secondary">Manage</button>
        </div>
        <div class="flex items-center justify-between p-3 bg-neutral-50 rounded-md">
          <div>
            <p class="font-semibold">John Doe</p>
            <p class="text-xs text-neutral-600">Staff • john.doe@university.edu</p>
          </div>
          <button class="text-xs btn-secondary">Manage</button>
        </div>
        <div class="flex items-center justify-between p-3 bg-neutral-50 rounded-md">
          <div>
            <p class="font-semibold">Jane Smith</p>
            <p class="text-xs text-neutral-600">Staff • jane.smith@university.edu</p>
          </div>
          <button class="text-xs btn-secondary">Manage</button>
        </div>
        <div class="flex items-center justify-between p-3 bg-neutral-50 rounded-md">
          <div>
            <p class="font-semibold">Admin User</p>
            <p class="text-xs text-neutral-600">Admin • admin@university.edu</p>
          </div>
          <button class="text-xs btn-secondary">Manage</button>
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
              <p class="text-sm text-neutral-600">Request ID</p>
              <p class="font-mono text-sm">{{ selectedRequest.id }}</p>
            </div>
            <div>
              <p class="text-sm text-neutral-600">Status</p>
              <div :class="['badge', 'mt-1', getStatusBadgeClass(selectedRequest.status)]">
                {{ formatStatus(selectedRequest.status) }}
              </div>
            </div>
            <div>
              <p class="text-sm text-neutral-600">Student</p>
              <p class="font-semibold">{{ selectedRequest.studentName }}</p>
              <p class="text-xs text-neutral-600">{{ selectedRequest.studentEmail }}</p>
            </div>
            <div>
              <p class="text-sm text-neutral-600">Type</p>
              <p class="font-semibold">{{ formatType(selectedRequest.type) }}</p>
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
import { ref, computed } from 'vue'
import { useMockData } from '@/composables/useMockData'
import type { Request } from '@/types'

const { mockRequests, adminStats } = useMockData()
const selectedRequest = ref<Request | null>(null)
const searchQuery = ref('')
const filterStatus = ref('')
const filterType = ref('')

const filteredRequests = computed(() => {
  return mockRequests.value.filter(request => {
    const matchSearch = !searchQuery.value || 
      request.studentName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      request.studentEmail.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      request.id.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchStatus = !filterStatus.value || request.status === filterStatus.value
    const matchType = !filterType.value || request.type === filterType.value
    return matchSearch && matchStatus && matchType
  })
})

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

const getPriorityBadgeClass = (priority: string) => {
  const map: Record<string, string> = {
    low: 'badge bg-neutral-100 text-neutral-800',
    medium: 'badge bg-yellow-100 text-yellow-800',
    high: 'badge bg-red-100 text-red-800'
  }
  return map[priority] || ''
}
</script>
