<template>
  <div class="space-y-8">
    <section class="space-y-4">
      <h2 class="text-lg text-primary font-semibold">System overview</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
        <div class="card">
          <p class="text-sm text-neutral-600">Total requests</p>
          <p class="text-3xl font-bold text-primary">{{ systemStats.total }}</p>
        </div>
        <div class="card">
          <p class="text-sm text-neutral-600">Avg resolution (h)</p>
          <p class="text-3xl font-bold text-primary-light">{{ avgHours }}</p>
        </div>
        <div class="card">
          <p class="text-sm text-neutral-600">Today</p>
          <p class="text-3xl font-bold text-teal-600">{{ systemStats.requests_today }}</p>
        </div>
        <div class="card col-span-2 md:col-span-2 lg:col-span-2">
          <p class="text-sm text-neutral-600 mb-2">By status</p>
          <div class="flex flex-wrap gap-2 text-xs">
            <span v-for="st in statusKeys" :key="st" class="badge bg-neutral-100 text-neutral-800">
              {{ requestStatusLabel(st) }}: {{ systemStats.by_status[st] }}
            </span>
          </div>
        </div>
      </div>
    </section>

    <section class="card">
      <h2 class="text-lg text-primary font-semibold mb-4">Recent activity</h2>
      <div class="space-y-2 max-h-64 overflow-y-auto text-sm">
        <div
          v-for="row in recentActivity"
          :key="`${row.request_id}-${row.id}`"
          class="border-b border-neutral-100 pb-2 last:border-0"
        >
          <p>
            <span class="font-medium">{{ row.changed_by.name }}</span>
            · Req #{{ row.request_id }}
          </p>
          <p class="text-xs text-neutral-600">
            {{ formatHist(row.old_status) }} → {{ formatHist(row.new_status) }} ·
            {{ formatDateTime(row.changed_at) }}
          </p>
          <p v-if="row.note" class="text-xs text-neutral-700 mt-1">{{ row.note }}</p>
        </div>
      </div>
    </section>

    <section class="card overflow-x-auto">
      <h2 class="text-lg text-primary font-semibold mb-4">All requests</h2>
      <div class="flex flex-col xl:flex-row flex-wrap gap-3 mb-4">
        <input v-model="reqSearch" type="text" class="input-field flex-1 min-w-[12rem]" placeholder="Search student name, ID…" />
        <select v-model.number="filterFaculty" class="input-field max-w-[14rem]">
          <option :value="0">All faculties</option>
          <option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option>
        </select>
        <select v-model.number="filterDepartment" class="input-field max-w-[14rem]">
          <option :value="0">All departments</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select v-model.number="filterRequestType" class="input-field max-w-[14rem]">
          <option :value="0">All request types</option>
          <option v-for="t in requestTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <select v-model="filterReqStatus" class="input-field max-w-[12rem]">
          <option value="">All statuses</option>
          <option v-for="st in statusKeys" :key="st" :value="st">{{ requestStatusLabel(st) }}</option>
        </select>
        <label class="flex items-center gap-2 text-sm text-neutral-700 whitespace-nowrap">
          <input v-model="filterReopened" type="checkbox" class="rounded" />
          Reopened only
        </label>
        <input v-model="filterDateFrom" type="date" class="input-field max-w-[11rem]" />
        <input v-model="filterDateTo" type="date" class="input-field max-w-[11rem]" />
      </div>
      <table class="w-full text-sm">
        <thead class="border-b border-neutral-300 bg-neutral-50">
          <tr>
            <th class="text-left py-2 px-2 font-semibold text-primary">ID</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Student</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Type</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Status</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Reopened</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Created</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-200">
          <tr v-if="filteredRequests.length === 0">
            <td colspan="7" class="py-4 text-center text-neutral-500">No requests match.</td>
          </tr>
          <tr v-for="r in filteredRequests" :key="r.id" class="hover:bg-neutral-50">
            <td class="py-2 px-2 font-mono text-xs">{{ r.id }}</td>
            <td class="py-2 px-2">{{ studentLabel(r.student_id) }}</td>
            <td class="py-2 px-2">{{ r.request_type.name }}</td>
            <td class="py-2 px-2">
              <StatusBadge kind="request" :status="r.status" />
            </td>
            <td class="py-2 px-2">{{ r.is_reopened ? 'Yes' : '—' }}</td>
            <td class="py-2 px-2 text-xs">{{ formatDateTime(r.created_at) }}</td>
            <td class="py-2 px-2">
              <button type="button" class="text-primary font-semibold text-sm" @click="openRequest(r)">View</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="card">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
        <h2 class="text-lg text-primary font-semibold">Users</h2>
        <div class="flex flex-wrap gap-2">
          <select v-model="userRoleFilter" class="input-field max-w-xs">
            <option value="">All roles</option>
            <option value="student">Student</option>
            <option value="staff">Staff</option>
          </select>
          <button type="button" class="btn-primary text-sm" @click="openUserModal('create')">Create user</button>
        </div>
      </div>
      <div class="space-y-2">
        <div
          v-for="u in filteredUsers"
          :key="u.id"
          class="flex items-center justify-between p-3 bg-neutral-50 rounded-md"
        >
          <div>
            <p class="font-semibold text-primary">{{ u.name }}</p>
            <p class="text-xs text-neutral-600">
              {{ u.role }}
              <span v-if="u.staff_profile?.admin_level"> · {{ u.staff_profile.admin_level }}</span>
              · {{ u.email }}
            </p>
          </div>
          <div class="flex gap-2">
            <button type="button" class="text-xs btn-secondary" @click="openUserModal('edit', u)">Edit</button>
            <button type="button" class="text-xs text-red-600" @click="removeUser(u.id)">Delete</button>
          </div>
        </div>
      </div>
    </section>

    <section class="space-y-4">
      <h2 class="text-lg text-primary font-semibold">Organisational management</h2>
      <div class="border-b border-neutral-200 flex gap-1 flex-wrap">
        <button
          v-for="tab in orgTabs"
          :key="tab.id"
          type="button"
          :class="[
            'px-4 py-2 font-medium text-sm transition-colors',
            orgTab === tab.id ? 'text-primary border-b-2 border-primary' : 'text-neutral-600 hover:text-foreground'
          ]"
          @click="orgTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <div v-if="orgTab === 'faculties'" class="card space-y-4">
        <div class="flex gap-2 flex-wrap">
          <input v-model="newFaculty.name" type="text" class="input-field max-w-xs" placeholder="Name" />
          <input v-model="newFaculty.code" type="text" class="input-field max-w-[8rem]" placeholder="Code" />
          <button type="button" class="btn-primary text-sm" @click="addFaculty">Add</button>
        </div>
        <table class="w-full text-sm">
          <thead class="border-b bg-neutral-50">
            <tr>
              <th class="text-left py-2 px-2">Code</th>
              <th class="text-left py-2 px-2">Name</th>
              <th class="text-left py-2 px-2">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in faculties" :key="f.id" class="border-b border-neutral-100">
              <td class="py-2 px-2">{{ f.code }}</td>
              <td class="py-2 px-2">{{ f.name }}</td>
              <td class="py-2 px-2">
                <button type="button" class="text-xs text-red-600" @click="removeFaculty(f.id)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="orgTab === 'departments'" class="card space-y-4">
        <div class="flex gap-2 flex-wrap items-end">
          <div>
            <label class="text-xs text-neutral-600">Faculty</label>
            <select v-model.number="newDept.faculty_id" class="input-field max-w-xs">
              <option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <input v-model="newDept.name" type="text" class="input-field max-w-xs" placeholder="Name" />
          <input v-model="newDept.code" type="text" class="input-field max-w-[8rem]" placeholder="Code" />
          <button type="button" class="btn-primary text-sm" @click="addDepartment">Add</button>
        </div>
        <table class="w-full text-sm">
          <thead class="border-b bg-neutral-50">
            <tr>
              <th class="text-left py-2 px-2">Code</th>
              <th class="text-left py-2 px-2">Name</th>
              <th class="text-left py-2 px-2">Faculty</th>
              <th class="text-left py-2 px-2">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in departments" :key="d.id" class="border-b border-neutral-100">
              <td class="py-2 px-2">{{ d.code }}</td>
              <td class="py-2 px-2">{{ d.name }}</td>
              <td class="py-2 px-2">{{ facultyName(d.faculty_id) }}</td>
              <td class="py-2 px-2">
                <button type="button" class="text-xs text-red-600" @click="removeDepartment(d.id)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="orgTab === 'programmes'" class="card space-y-4">
        <div class="flex gap-2 flex-wrap items-end">
          <div>
            <label class="text-xs text-neutral-600">Faculty</label>
            <select v-model.number="newProg.faculty_id" class="input-field max-w-xs">
              <option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <input v-model="newProg.name" type="text" class="input-field max-w-xs" placeholder="Name" />
          <input v-model="newProg.code" type="text" class="input-field max-w-[8rem]" placeholder="Code" />
          <select v-model="newProg.degree_type" class="input-field max-w-[8rem]">
            <option v-for="dt in degreeTypes" :key="dt" :value="dt">{{ dt }}</option>
          </select>
          <button type="button" class="btn-primary text-sm" @click="addProgramme">Add</button>
        </div>
        <table class="w-full text-sm">
          <thead class="border-b bg-neutral-50">
            <tr>
              <th class="text-left py-2 px-2">Code</th>
              <th class="text-left py-2 px-2">Name</th>
              <th class="text-left py-2 px-2">Degree</th>
              <th class="text-left py-2 px-2">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in programmes" :key="p.id" class="border-b border-neutral-100">
              <td class="py-2 px-2">{{ p.code }}</td>
              <td class="py-2 px-2">{{ p.name }}</td>
              <td class="py-2 px-2">{{ p.degree_type }}</td>
              <td class="py-2 px-2">
                <button type="button" class="text-xs text-red-600" @click="removeProgramme(p.id)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="orgTab === 'request_types'" class="card space-y-4">
        <div class="space-y-2">
          <input v-model="newRt.name" type="text" class="input-field max-w-md" placeholder="Name" />
          <textarea v-model="newRt.description" class="input-field" rows="2" placeholder="Description" />
          <p class="text-sm text-neutral-600">Department sequence (add departments in order):</p>
          <div class="flex flex-wrap gap-2">
            <select v-model.number="newRtDeptPick" class="input-field max-w-xs">
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <button type="button" class="btn-secondary text-sm" @click="pushNewRtDept">Add to sequence</button>
          </div>
          <ul class="text-sm space-y-1">
            <li v-for="(id, i) in newRt.sequence" :key="`${id}-${i}`" class="flex items-center gap-2">
              {{ i + 1 }}. {{ deptLabel(id) }}
              <button type="button" class="text-xs text-red-600" @click="newRt.sequence.splice(i, 1)">Remove</button>
            </li>
          </ul>
          <button type="button" class="btn-primary text-sm" @click="addRequestType">Create request type</button>
        </div>

        <div v-for="rt in requestTypes" :key="rt.id" class="border border-neutral-200 rounded-lg p-4 space-y-2">
          <div class="flex justify-between items-start gap-4">
            <div>
              <p class="font-semibold text-primary">{{ rt.name }}</p>
              <p class="text-xs text-neutral-600">{{ rt.description }}</p>
            </div>
            <button type="button" class="text-xs text-red-600" @click="removeRequestType(rt.id)">Delete</button>
          </div>
          <p class="text-sm text-neutral-600">Drag to reorder departments in the workflow:</p>
          <ul class="space-y-1">
            <li
              v-for="(deptId, idx) in rt.default_department_sequence"
              :key="`${rt.id}-${idx}`"
              draggable="true"
              class="flex items-center justify-between gap-2 p-2 bg-neutral-50 rounded border border-neutral-200 cursor-grab active:cursor-grabbing"
              @dragstart="onRtDragStart(rt.id, idx)"
              @dragover.prevent
              @drop="onRtDrop(rt.id, idx)"
            >
              <span>{{ idx + 1 }}. {{ deptLabel(deptId) }}</span>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <section class="card overflow-x-auto">
      <h2 class="text-lg text-primary font-semibold mb-4">Audit log</h2>
      <div class="flex flex-wrap gap-3 mb-4">
        <input v-model.number="auditFilterRequestId" type="number" class="input-field max-w-[8rem]" placeholder="Request ID" />
        <input v-model.number="auditFilterUserId" type="number" class="input-field max-w-[8rem]" placeholder="User ID" />
        <input v-model="auditDateFrom" type="date" class="input-field max-w-[11rem]" />
        <input v-model="auditDateTo" type="date" class="input-field max-w-[11rem]" />
        <select v-model="auditTransition" class="input-field max-w-[14rem]">
          <option value="">Any transition</option>
          <option v-for="st in statusKeys" :key="`to-${st}`" :value="st">To: {{ requestStatusLabel(st) }}</option>
        </select>
      </div>
      <table class="w-full text-sm">
        <thead class="border-b border-neutral-300 bg-neutral-50">
          <tr>
            <th class="text-left py-2 px-2 font-semibold text-primary">Request</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Transition</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">By</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">Note</th>
            <th class="text-left py-2 px-2 font-semibold text-primary">When</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-200">
          <tr v-for="row in auditPageRows" :key="`${row.request_id}-${row.id}`">
            <td class="py-2 px-2 font-mono">#{{ row.request_id }}</td>
            <td class="py-2 px-2">{{ formatHist(row.old_status) }} → {{ formatHist(row.new_status) }}</td>
            <td class="py-2 px-2">{{ row.changed_by.name }} ({{ row.changed_by.id }})</td>
            <td class="py-2 px-2 text-xs">{{ row.note ?? '—' }}</td>
            <td class="py-2 px-2 text-xs whitespace-nowrap">{{ formatDateTime(row.changed_at) }}</td>
          </tr>
        </tbody>
      </table>
      <div class="flex justify-between items-center mt-4 text-sm">
        <button type="button" class="btn-secondary text-sm" :disabled="auditPage <= 0" @click="auditPage--">Previous</button>
        <span>Page {{ auditPage + 1 }}</span>
        <button type="button" class="btn-secondary text-sm" :disabled="auditPage >= auditMaxPage" @click="auditPage++">Next</button>
      </div>
    </section>

    <div
      v-if="selectedRequest"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="selectedRequest = null"
    >
      <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-neutral-200 p-6 flex justify-between items-start">
          <h2 class="text-2xl font-bold">Request #{{ selectedRequest.id }}</h2>
          <button type="button" class="text-2xl text-neutral-500" @click="selectedRequest = null">×</button>
        </div>
        <div class="p-6 space-y-4">
          <p class="text-sm text-neutral-600">
            {{ studentLabel(selectedRequest.student_id) }} · {{ selectedRequest.request_type.name }}
          </p>
          <StatusBadge kind="request" :status="selectedRequest.status" />
          <p class="text-foreground">{{ selectedRequest.description }}</p>
          <RequestTimeline :stages="selectedRequest.stages" />
          <div class="border border-amber-200 bg-amber-50 rounded-md p-4 space-y-2">
            <p class="text-sm font-semibold text-primary">Admin override</p>
            <select v-model="overrideStatus" class="input-field">
              <option v-for="st in statusKeys" :key="st" :value="st">{{ requestStatusLabel(st) }}</option>
            </select>
            <textarea v-model="overrideNote" class="input-field" rows="2" placeholder="Note (optional)" />
            <button type="button" class="btn-primary text-sm" @click="applyOverride">Apply status</button>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="userModal.open"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto"
      @click.self="userModal.open = false"
    >
      <div class="bg-white rounded-lg max-w-lg w-full my-8 p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-primary">{{ userModal.mode === 'create' ? 'Create user' : 'Edit user' }}</h3>
        <template v-if="userForm.role === 'student'">
          <input v-model="userForm.name" class="input-field" placeholder="Name" />
          <input v-model="userForm.email" class="input-field" placeholder="Email" />
          <input v-model="userForm.password" type="password" class="input-field" placeholder="Password" />
          <input v-model="userForm.matricule" class="input-field" placeholder="Matricule" />
          <select v-model.number="userForm.faculty_id" class="input-field" @change="syncUserProg">
            <option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
          <select v-model.number="userForm.department_id" class="input-field">
            <option v-for="d in deptsForUserFaculty" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
          <select v-model.number="userForm.programme_id" class="input-field">
            <option v-for="p in progsForUserFaculty" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <select v-model="userForm.level" class="input-field">
            <option v-for="lv in levels" :key="lv" :value="lv">{{ lv }}</option>
          </select>
        </template>
        <template v-else>
          <input v-model="userForm.name" class="input-field" placeholder="Name" />
          <input v-model="userForm.email" class="input-field" placeholder="Email" />
          <input v-model="userForm.password" type="password" class="input-field" placeholder="Password" />
          <input v-model="userForm.staff_id" class="input-field" placeholder="Staff ID" />
          <label class="text-sm text-neutral-600">Admin level</label>
          <select v-model="userForm.admin_level" class="input-field">
            <option :value="null">Plain staff</option>
            <option value="dept_admin">Department admin</option>
            <option value="super_admin">Super admin</option>
          </select>
          <p class="text-sm text-neutral-600">Departments (check + set primary):</p>
          <div v-for="d in departments" :key="d.id" class="flex items-center gap-2 text-sm">
            <input
              :id="`du-${d.id}`"
              v-model="userForm.dept_ids"
              type="checkbox"
              :value="d.id"
              class="rounded"
            />
            <label :for="`du-${d.id}`">{{ d.name }}</label>
            <input v-model.number="userForm.primary_dept_id" type="radio" class="ml-2" :value="d.id" />
          </div>
        </template>
        <div v-if="userModal.mode === 'create'" class="flex gap-2">
          <span class="text-sm text-neutral-600">Role:</span>
          <label class="text-sm"><input v-model="userForm.role" type="radio" value="student" /> Student</label>
          <label class="text-sm"><input v-model="userForm.role" type="radio" value="staff" /> Staff</label>
        </div>
        <div class="flex gap-2 justify-end">
          <button type="button" class="btn-secondary" @click="userModal.open = false">Cancel</button>
          <button type="button" class="btn-primary" @click="saveUser">Save</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { useMockData } from '@/composables/useMockData'
import type { Request, User, RequestStatus, DegreeType, StudentLevel } from '@/types'
import { requestStatusLabel } from '@/types'
import type { StaffAdminLevel } from '@/types'
import StatusBadge from './StatusBadge.vue'
import RequestTimeline from './RequestTimeline.vue'

const {
  sessionUser,
  mockRequests,
  mockUsers,
  faculties,
  departments,
  programmes,
  requestTypes,
  systemStats,
  recentActivity,
  getUserById,
  allStatusHistoryFlat,
  adminOverrideRequestStatus,
  createFaculty,
  createDepartment,
  createProgramme,
  createRequestTypeRow,
  deleteFaculty,
  deleteDepartment,
  deleteProgramme,
  deleteRequestTypeEntity,
  saveRequestTypeEntity,
  saveUserEntity,
  deleteUserEntity,
  deptSummary
} = useMockData()

const statusKeys: RequestStatus[] = [
  'draft',
  'pending',
  'in_review',
  'forwarded',
  'ready',
  'collected',
  'rejected'
]

const degreeTypes: DegreeType[] = ['BSc', 'BEng', 'MEng', 'MSc', 'PhD']
const levels = ['L100', 'L200', 'L300', 'L400', 'L500', 'L600'] as const

const avgHours = computed(() => systemStats.value.avg_resolution_hours.toFixed(1))

const orgTabs = [
  { id: 'faculties', label: 'Faculties' },
  { id: 'departments', label: 'Departments' },
  { id: 'programmes', label: 'Programmes' },
  { id: 'request_types', label: 'Request types' }
] as const
const orgTab = ref<(typeof orgTabs)[number]['id']>('faculties')

const newFaculty = reactive({ name: '', code: '' })
const newDept = reactive({ faculty_id: 1, name: '', code: '' })
const newProg = reactive({
  faculty_id: 1,
  name: '',
  code: '',
  degree_type: 'BEng' as DegreeType
})
const newRt = reactive({ name: '', description: '', sequence: [] as number[] })
const newRtDeptPick = ref(1)

const rtDrag = ref<{ rtId: number; fromIdx: number } | null>(null)

function onRtDragStart(rtId: number, idx: number) {
  rtDrag.value = { rtId, fromIdx: idx }
}

function onRtDrop(rtId: number, toIdx: number) {
  const drag = rtDrag.value
  if (!drag || drag.rtId !== rtId) return
  const rt = requestTypes.value.find(t => t.id === rtId)
  if (!rt) return
  const seq = [...rt.default_department_sequence]
  const [moved] = seq.splice(drag.fromIdx, 1)!
  seq.splice(toIdx, 0, moved!)
  rt.default_department_sequence = seq
  saveRequestTypeEntity({ ...rt })
  rtDrag.value = null
}

function deptLabel(id: number) {
  return deptSummary(id).name
}

function facultyName(id: number) {
  return faculties.value.find(f => f.id === id)?.name ?? ''
}

function studentLabel(studentId: number) {
  const u = getUserById(studentId)
  if (!u) return `#${studentId}`
  const m = u.student_profile?.matricule
  return m ? `${u.name} (${m})` : u.name
}

function formatHist(s: RequestStatus | null) {
  if (s === null) return '—'
  return requestStatusLabel(s)
}

function formatDateTime(iso: string) {
  return new Date(iso).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const reqSearch = ref('')
const filterFaculty = ref(0)
const filterDepartment = ref(0)
const filterRequestType = ref(0)
const filterReqStatus = ref('')
const filterReopened = ref(false)
const filterDateFrom = ref('')
const filterDateTo = ref('')

const filteredRequests = computed(() => {
  return mockRequests.value.filter(r => {
    if (reqSearch.value) {
      const q = reqSearch.value.toLowerCase()
      const name = getUserById(r.student_id)?.name?.toLowerCase() ?? ''
      if (!String(r.id).includes(q) && !name.includes(q)) return false
    }
    if (filterFaculty.value) {
      const fac = getUserById(r.student_id)?.student_profile?.faculty.id
      if (fac !== filterFaculty.value) return false
    }
    if (filterDepartment.value) {
      const matchStage = r.stages.some(s => s.department.id === filterDepartment.value)
      const matchStudent = getUserById(r.student_id)?.student_profile?.department.id === filterDepartment.value
      if (!matchStage && !matchStudent) return false
    }
    if (filterRequestType.value && r.request_type.id !== filterRequestType.value) return false
    if (filterReqStatus.value && r.status !== filterReqStatus.value) return false
    if (filterReopened.value && !r.is_reopened) return false
    if (filterDateFrom.value) {
      if (new Date(r.created_at) < new Date(filterDateFrom.value)) return false
    }
    if (filterDateTo.value) {
      const t = new Date(filterDateTo.value)
      t.setHours(23, 59, 59, 999)
      if (new Date(r.created_at) > t) return false
    }
    return true
  })
})

const selectedRequest = ref<Request | null>(null)
const overrideStatus = ref<RequestStatus>('pending')
const overrideNote = ref('')

function openRequest(r: Request) {
  selectedRequest.value = r
  overrideStatus.value = r.status
  overrideNote.value = ''
}

function applyOverride() {
  const admin = sessionUser.value
  const r = selectedRequest.value
  if (!admin || !r) return
  adminOverrideRequestStatus(r.id, overrideStatus.value, overrideNote.value.trim() || null, admin)
  selectedRequest.value = null
}

const userRoleFilter = ref('')
const filteredUsers = computed(() => {
  let list = mockUsers.value
  if (userRoleFilter.value === 'student') list = list.filter(u => u.role === 'student')
  else if (userRoleFilter.value === 'staff') list = list.filter(u => u.role === 'staff')
  return list
})

const userModal = reactive({ open: false, mode: 'create' as 'create' | 'edit' })
const userForm = reactive({
  id: 0,
  name: '',
  email: '',
  password: '',
  role: 'student' as 'student' | 'staff',
  matricule: '',
  faculty_id: 1,
  department_id: 1,
  programme_id: 1,
  level: 'L100',
  staff_id: '',
  admin_level: null as StaffAdminLevel,
  dept_ids: [] as number[],
  primary_dept_id: 1 as number
})

const deptsForUserFaculty = computed(() =>
  departments.value.filter(d => d.faculty_id === userForm.faculty_id)
)
const progsForUserFaculty = computed(() =>
  programmes.value.filter(p => p.faculty_id === userForm.faculty_id)
)

function syncUserProg() {
  const ds = deptsForUserFaculty.value
  if (ds.length && !ds.some(d => d.id === userForm.department_id)) {
    userForm.department_id = ds[0]!.id
  }
  const ps = progsForUserFaculty.value
  if (ps.length && !ps.some(p => p.id === userForm.programme_id)) {
    userForm.programme_id = ps[0]!.id
  }
}

watch(() => userForm.faculty_id, syncUserProg)

function openUserModal(mode: 'create' | 'edit', u?: User) {
  userModal.mode = mode
  userModal.open = true
  if (mode === 'create') {
    Object.assign(userForm, {
      id: 0,
      name: '',
      email: '',
      password: '',
      role: 'student',
      matricule: '',
      faculty_id: faculties.value[0]?.id ?? 1,
      department_id: 1,
      programme_id: 1,
      level: 'L100',
      staff_id: '',
      admin_level: null,
      dept_ids: [],
      primary_dept_id: departments.value[0]?.id ?? 1
    })
  } else if (u) {
    userForm.id = u.id
    userForm.name = u.name
    userForm.email = u.email
    userForm.password = u.password
    userForm.role = u.role
    if (u.student_profile) {
      userForm.matricule = u.student_profile.matricule
      userForm.faculty_id = u.student_profile.faculty.id
      userForm.department_id = u.student_profile.department.id
      userForm.programme_id = u.student_profile.programme.id
      userForm.level = u.student_profile.level
    }
    if (u.staff_profile) {
      userForm.staff_id = u.staff_profile.staff_id
      userForm.admin_level = u.staff_profile.admin_level
      userForm.dept_ids = u.staff_profile.departments.map(d => d.id)
      const prim = u.staff_profile.departments.find(d => d.is_primary)
      userForm.primary_dept_id = prim?.id ?? u.staff_profile.departments[0]?.id ?? 1
    }
  }
  syncUserProg()
}

function saveUser() {
  const fac = faculties.value.find(f => f.id === userForm.faculty_id)
  const dep = departments.value.find(d => d.id === userForm.department_id)
  const prog = programmes.value.find(p => p.id === userForm.programme_id)
  if (userForm.role === 'student' && (!fac || !dep || !prog)) return

  if (userForm.role === 'student') {
    const existing = userModal.mode === 'edit' ? getUserById(userForm.id) : undefined
    const row: User = {
      id: userForm.id || Date.now(),
      name: userForm.name,
      email: userForm.email,
      password: userForm.password,
      role: 'student',
      created_at: existing?.created_at ?? new Date().toISOString(),
      student_profile: {
        matricule: userForm.matricule,
        level: userForm.level as StudentLevel,
        status: 'active',
        faculty: { ...fac! },
        department: { ...dep! },
        programme: { ...prog! }
      }
    }
    if (userModal.mode === 'create') {
      row.id = Math.floor(Math.random() * 100000) + 200
    }
    saveUserEntity(row)
  } else {
    const existingStaff = userModal.mode === 'edit' ? getUserById(userForm.id) : undefined
    const deptAssignments = userForm.dept_ids.map(id => {
      const d = departments.value.find(x => x.id === id)
      return {
        id,
        name: d?.name ?? '',
        code: d?.code ?? '',
        is_primary: id === userForm.primary_dept_id
      }
    })
    const row: User = {
      id: userForm.id,
      name: userForm.name,
      email: userForm.email,
      password: userForm.password,
      role: 'staff',
      created_at: existingStaff?.created_at ?? new Date().toISOString(),
      staff_profile: {
        staff_id: userForm.staff_id || `UB-STF-${userForm.id}`,
        admin_level: userForm.admin_level,
        departments: deptAssignments
      }
    }
    if (userModal.mode === 'create') {
      row.id = Math.floor(Math.random() * 100000) + 200
    }
    saveUserEntity(row)
  }
  userModal.open = false
}

function removeUser(id: number) {
  if (confirm('Remove this user from the mock directory?')) deleteUserEntity(id)
}

function addFaculty() {
  const a = sessionUser.value
  if (!a || !newFaculty.name.trim() || !newFaculty.code.trim()) return
  createFaculty(newFaculty.name.trim(), newFaculty.code.trim().toUpperCase(), a)
  newFaculty.name = ''
  newFaculty.code = ''
}

function removeFaculty(id: number) {
  deleteFaculty(id)
}

function addDepartment() {
  const a = sessionUser.value
  if (!a || !newDept.name.trim() || !newDept.code.trim()) return
  createDepartment(newDept.faculty_id, newDept.name.trim(), newDept.code.trim().toUpperCase(), a)
  newDept.name = ''
  newDept.code = ''
}

function removeDepartment(id: number) {
  deleteDepartment(id)
}

function addProgramme() {
  const a = sessionUser.value
  if (!a || !newProg.name.trim() || !newProg.code.trim()) return
  createProgramme(newProg.faculty_id, newProg.name.trim(), newProg.code.trim(), newProg.degree_type, a)
  newProg.name = ''
  newProg.code = ''
}

function removeProgramme(id: number) {
  deleteProgramme(id)
}

function pushNewRtDept() {
  if (!newRt.sequence.includes(newRtDeptPick.value)) newRt.sequence.push(newRtDeptPick.value)
}

function addRequestType() {
  const a = sessionUser.value
  if (!a || !newRt.name.trim()) return
  createRequestTypeRow(newRt.name.trim(), newRt.description.trim(), newRt.sequence.length ? newRt.sequence : [2], a)
  newRt.name = ''
  newRt.description = ''
  newRt.sequence = []
}

function removeRequestType(id: number) {
  deleteRequestTypeEntity(id)
}

const auditFilterRequestId = ref<number | null>(null)
const auditFilterUserId = ref<number | null>(null)
const auditDateFrom = ref('')
const auditDateTo = ref('')
const auditTransition = ref('')
const auditPage = ref(0)
const auditPageSize = 15

const filteredAudit = computed(() => {
  let rows = allStatusHistoryFlat()
  if (auditFilterRequestId.value) {
    rows = rows.filter(r => r.request_id === auditFilterRequestId.value)
  }
  if (auditFilterUserId.value) {
    rows = rows.filter(r => r.changed_by.id === auditFilterUserId.value)
  }
  if (auditDateFrom.value) {
    const from = new Date(auditDateFrom.value).getTime()
    rows = rows.filter(r => new Date(r.changed_at).getTime() >= from)
  }
  if (auditDateTo.value) {
    const to = new Date(auditDateTo.value)
    to.setHours(23, 59, 59, 999)
    rows = rows.filter(r => new Date(r.changed_at).getTime() <= to.getTime())
  }
  if (auditTransition.value) {
    rows = rows.filter(r => r.new_status === auditTransition.value)
  }
  return rows
})

const auditMaxPage = computed(() =>
  Math.max(0, Math.ceil(filteredAudit.value.length / auditPageSize) - 1)
)

const auditPageRows = computed(() => {
  const start = auditPage.value * auditPageSize
  return filteredAudit.value.slice(start, start + auditPageSize)
})

watch([auditFilterRequestId, auditFilterUserId, auditDateFrom, auditDateTo, auditTransition], () => {
  auditPage.value = 0
})
</script>
