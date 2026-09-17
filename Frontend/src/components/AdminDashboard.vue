<template>
  <main class="space-y-8">
    <div v-if="error" role="alert" class="card text-red-700 flex justify-between gap-4"><span>{{ error }}</span><button @click="error = ''">Dismiss</button></div>
    <section class="space-y-4">
      <h2 class="text-lg text-primary font-semibold">System overview</h2>
      <p v-if="loading" class="text-neutral-600">Loading dashboard…</p>
      <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card"><p>Total requests</p><strong class="text-3xl text-primary">{{ stats?.total ?? 0 }}</strong></div>
        <div class="card"><p>Today</p><strong class="text-3xl text-primary">{{ stats?.requests_today ?? 0 }}</strong></div>
        <div class="card"><p>Average resolution (hours)</p><strong class="text-3xl text-primary">{{ stats?.avg_resolution_hours ?? '—' }}</strong></div>
        <div class="card"><p>By status</p><div v-for="(count, status) in stats?.by_status" :key="status">{{ status }}: {{ count }}</div></div>
      </div>
      <div class="card"><h3 class="font-semibold mb-2">Recent status activity</h3><p v-if="!stats?.recent_activity.length">No activity yet.</p><p v-for="row in stats?.recent_activity" :key="row.id" class="text-sm py-1 border-b">{{ row.changed_by?.name ?? 'System' }} · Request #{{ row.request_id }} · {{ row.old_status ?? '—' }} → {{ row.new_status }} · {{ date(row.changed_at) }}</p></div>
    </section>

    <section class="card space-y-5" aria-labelledby="all-requests-heading">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 id="all-requests-heading" class="text-lg text-primary font-semibold">All requests</h2>
          <p class="text-sm text-neutral-600">Review requests across every faculty and department.</p>
        </div>
        <span class="badge bg-neutral-100 text-neutral-700">{{ requests.meta.total }} {{ requests.meta.total === 1 ? 'request' : 'requests' }}</span>
      </div>

      <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-semibold text-primary">Filter requests</h3>
          <button v-if="activeRequestFilterCount" type="button" class="text-sm font-medium text-primary hover:underline" @click="clearRequestFilters">Clear filters ({{ activeRequestFilterCount }})</button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <label class="block sm:col-span-2 xl:col-span-2"><span class="block text-xs font-medium text-neutral-600 mb-1">Search</span><input v-model="requestFilters.search" type="search" class="input-field bg-white" placeholder="Student name, matricule or request ID" /></label>
          <label class="block"><span class="block text-xs font-medium text-neutral-600 mb-1">Faculty</span><select v-model.number="requestFilters.faculty_id" class="input-field bg-white"><option :value="0">All faculties</option><option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option></select></label>
          <label class="block"><span class="block text-xs font-medium text-neutral-600 mb-1">Department</span><select v-model.number="requestFilters.department_id" class="input-field bg-white"><option :value="0">All departments</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select></label>
          <label class="block"><span class="block text-xs font-medium text-neutral-600 mb-1">Request type</span><select v-model.number="requestFilters.request_type_id" class="input-field bg-white"><option :value="0">All request types</option><option v-for="t in requestTypes" :key="t.id" :value="t.id">{{ t.name }}</option></select></label>
          <label class="block"><span class="block text-xs font-medium text-neutral-600 mb-1">Status</span><select v-model="requestFilters.status" class="input-field bg-white"><option value="">All statuses</option><option v-for="status in statuses" :key="status" :value="status">{{ requestStatusLabel(status) }}</option></select></label>
          <label class="block"><span class="block text-xs font-medium text-neutral-600 mb-1">From</span><input v-model="requestFilters.date_from" type="date" class="input-field bg-white" /></label>
          <label class="block"><span class="block text-xs font-medium text-neutral-600 mb-1">To</span><input v-model="requestFilters.date_to" type="date" class="input-field bg-white" :min="requestFilters.date_from || undefined" /></label>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-neutral-700 cursor-pointer"><input v-model="requestFilters.reopened" type="checkbox" class="rounded border-neutral-300 text-primary focus:ring-primary" /> Reopened requests only</label>
      </div>

      <p v-if="requestsLoading" class="text-sm text-neutral-600 py-6 text-center" role="status">Loading requests…</p>
      <div v-else-if="!requests.data.length" class="rounded-lg border border-dashed border-neutral-300 p-8 text-center"><p class="font-medium text-neutral-700">No requests found</p><p class="text-sm text-neutral-500 mt-1">Try adjusting the filters.</p></div>
      <div v-else class="space-y-2">
        <article v-for="row in requests.data" :key="row.id" class="flex flex-col gap-3 rounded-lg border border-neutral-200 bg-neutral-50 p-4 transition-colors hover:bg-white sm:flex-row sm:items-center sm:justify-between">
          <div class="min-w-0 space-y-1.5">
            <div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-primary">{{ row.student?.name ?? 'Unknown student' }}</h3><span class="text-xs text-neutral-500">Request #{{ row.id }}</span><StatusBadge kind="request" :status="row.status as RequestStatus" /><span v-if="row.is_reopened" class="badge bg-amber-100 text-amber-800">Reopened</span></div>
            <p class="text-sm text-neutral-700">{{ row.request_type?.name ?? 'Request' }}<span v-if="row.student?.student_profile?.matricule"> · {{ row.student.student_profile.matricule }}</span></p>
            <p class="text-xs text-neutral-500">Submitted {{ date(row.created_at) }}</p>
          </div>
          <button type="button" class="btn-secondary self-start text-sm font-medium shrink-0 sm:self-auto" :aria-label="`View request ${row.id}`" @click="openRequest(row.id)">View</button>
        </article>
      </div>
      <Pagination :page="requests.meta.current_page" :last="requests.meta.last_page" @change="requestPage = $event" />
    </section>

    <section class="card space-y-4">
      <div class="flex justify-between"><h2 class="text-lg text-primary font-semibold">Users</h2><button class="btn-primary" @click="openUser()">Create user</button></div>
      <div class="flex gap-2"><input v-model="userSearch" class="input-field" placeholder="Search users" /><select v-model="userRole" class="input-field"><option value="">All roles</option><option>student</option><option>staff</option></select></div>
      <p v-if="usersLoading">Loading users…</p><p v-else-if="!users.data.length">No users match.</p>
      <div v-for="u in users.data" :key="u.id" class="flex flex-wrap items-center justify-between gap-2 bg-neutral-50 rounded-md p-3"><div><strong>{{ u.name }}</strong><p class="text-xs">{{ u.email }} · {{ u.role }} <span v-if="u.staff_profile?.admin_level">· {{ u.staff_profile.admin_level }}</span></p></div><div class="flex gap-2"><select v-if="u.role === 'staff'" :value="u.staff_profile?.admin_level ?? ''" class="input-field" :disabled="pending" @change="changeLevel(u, ($event.target as HTMLSelectElement).value)"><option value="">Plain staff</option><option value="dept_admin">Department admin</option><option value="super_admin">Super Admin</option></select><button class="btn-secondary" @click="openUser(u)">Edit</button><button class="text-red-600" :disabled="pending" @click="remove('users', u.id)">Delete</button></div></div>
      <Pagination :page="users.meta.current_page" :last="users.meta.last_page" @change="userPage = $event" />
    </section>

    <section class="space-y-4"><h2 class="text-lg text-primary font-semibold">Organisational management</h2>
      <div class="flex gap-2 flex-wrap"><button v-for="tab in tabs" :key="tab" :class="tab === activeTab ? 'btn-primary' : 'btn-secondary'" @click="activeTab = tab; refPage = 1">{{ tab }}</button></div>
      <div class="card space-y-4"><div class="flex gap-2"><input v-model="refSearch" class="input-field" placeholder="Search" /><button class="btn-primary" @click="openReference()">Create</button></div>
        <p v-if="referencesLoading">Loading…</p><p v-else-if="!references.data.length">No records match.</p>
        <div v-for="row in references.data" :key="row.id" class="flex justify-between items-center border-b py-2"><div><strong>{{ row.name }}</strong><p class="text-xs">{{ row.code ?? '' }} <span v-if="row.matricule_prefix">· {{ row.matricule_prefix }}</span><span v-if="row.type">· {{ row.type }}</span><span v-if="row.degree_type">· {{ row.degree_type }}</span><span v-if="row.default_department_sequence">· {{ row.default_department_sequence.join(' → ') }}</span></p></div><div class="flex gap-2"><button class="btn-secondary" @click="openReference(row)">Edit</button><button class="text-red-600" :disabled="pending" @click="remove(activeTab, row.id)">Delete</button></div></div>
        <Pagination :page="references.meta.current_page" :last="references.meta.last_page" @change="refPage = $event" />
      </div>
    </section>

    <section class="card space-y-4"><h2 class="text-lg text-primary font-semibold">Request and stage status history</h2><p class="text-sm text-neutral-600">This log records status transitions. Administrative edits are outside this log.</p>
      <div class="flex flex-wrap gap-2"><input v-model="auditFilters.request_id" type="number" class="input-field" placeholder="Request ID" /><input v-model="auditFilters.actor_id" type="number" class="input-field" placeholder="Actor ID" /><input v-model="auditFilters.new_status" class="input-field" placeholder="New status" /><input v-model="auditFilters.date_from" type="date" class="input-field" aria-label="Audit from" /><input v-model="auditFilters.date_to" type="date" class="input-field" aria-label="Audit to" /></div>
      <p v-if="auditLoading">Loading history…</p><p v-else-if="!audit.data.length">No status transitions match.</p>
      <div v-for="row in audit.data" :key="row.id" class="border-b py-2 text-sm"><strong>{{ row.changed_by?.name ?? 'System' }}</strong> · Request #{{ row.request_id }}<span v-if="row.request_stage_id"> · Stage #{{ row.request_stage_id }}</span> · {{ row.old_status ?? '—' }} → {{ row.new_status }} · {{ date(row.changed_at) }}<p v-if="row.note">{{ row.note }}</p></div>
      <Pagination :page="audit.meta.current_page" :last="audit.meta.last_page" @change="auditPage = $event" />
    </section>

    <div v-if="detail" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="detail = null">
      <div role="dialog" aria-modal="true" :aria-labelledby="`request-detail-title-${detail.id}`" class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-lg bg-white shadow-xl">
        <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-neutral-200 bg-white p-6">
          <div><p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Request #{{ detail.id }}</p><h2 :id="`request-detail-title-${detail.id}`" class="text-2xl font-bold text-primary">{{ detail.request_type?.name ?? 'Request details' }}</h2><p class="mt-1 text-sm text-neutral-600">{{ detail.student?.name }}<span v-if="detail.student?.student_profile?.matricule"> · {{ detail.student.student_profile.matricule }}</span></p></div>
          <button type="button" class="text-2xl leading-none text-neutral-500 hover:text-foreground" aria-label="Close request details" @click="detail = null">×</button>
        </div>
        <div class="p-6 space-y-6">
          <div class="grid grid-cols-2 gap-4 rounded-lg bg-neutral-50 p-4"><div><p class="text-xs font-medium text-neutral-600 mb-1">Status</p><StatusBadge kind="request" :status="detail.status as RequestStatus" /><p v-if="detail.is_reopened" class="mt-2 text-xs font-medium text-amber-700">Reopened request</p></div><div><p class="text-xs font-medium text-neutral-600 mb-1">Submitted</p><p class="text-sm font-semibold text-foreground">{{ date(detail.created_at) }}</p></div></div>
          <section><h3 class="text-sm font-semibold text-primary mb-2">Description</h3><p class="text-sm text-foreground whitespace-pre-wrap">{{ detail.description || 'No description provided.' }}</p></section>
          <section><h3 class="text-sm font-semibold text-primary mb-3">Stage timeline</h3><RequestTimeline v-if="detailStages.length" :stages="detailStages" /><p v-else class="text-sm text-neutral-500">No stages recorded.</p></section>
          <section><h3 class="text-sm font-semibold text-primary mb-3">Attachments</h3><DocumentViewer :key="detail.id" :attachments="detailAttachments" /></section>
          <section><h3 class="text-sm font-semibold text-primary mb-3">Status history</h3><div v-if="detail.status_history?.length" class="space-y-2 rounded-lg border border-neutral-200 bg-neutral-50 p-3"><div v-for="row in [...detail.status_history].reverse()" :key="row.id" class="border-b border-neutral-200 pb-2 text-sm last:border-0 last:pb-0"><p class="font-medium">{{ row.old_status ?? '—' }} → {{ row.new_status }}</p><p class="text-xs text-neutral-600">{{ row.changed_by?.name ?? 'System' }} · {{ date(row.changed_at) }}</p><p v-if="row.note" class="mt-1 text-neutral-700">{{ row.note }}</p></div></div><p v-else class="text-sm text-neutral-500">No status changes recorded.</p></section>
          <div v-if="detail.status === 'rejected'" class="border-t border-neutral-200 pt-4"><button type="button" class="btn-primary" :disabled="pending" @click="reopen">{{ pending ? 'Reopening…' : 'Reopen request' }}</button></div>
        </div>
      </div>
    </div>

    <div v-if="referenceModal" class="fixed inset-0 z-50 bg-black/50 grid place-items-center p-4" @click.self="referenceModal = false"><form class="card bg-white w-full max-w-lg max-h-[90vh] overflow-y-auto space-y-3" @submit.prevent="saveReference"><h2 class="text-xl font-semibold">{{ refForm.id ? 'Edit' : 'Create' }} {{ activeTab }}</h2><input v-model="refForm.name" required class="input-field w-full" placeholder="Name" /><input v-if="activeTab !== 'request-types'" v-model="refForm.code" required class="input-field w-full" placeholder="Code" /><input v-if="activeTab === 'faculties'" v-model="refForm.matricule_prefix" required class="input-field w-full" placeholder="Matricule prefix" /><select v-if="activeTab === 'departments'" v-model.number="refForm.faculty_id" required class="input-field w-full"><option :value="0" disabled>Faculty</option><option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option></select><select v-if="activeTab === 'departments'" v-model="refForm.type" class="input-field w-full"><option>academic</option><option>records</option><option>admin</option></select><select v-if="activeTab === 'programmes'" v-model.number="refForm.department_id" required class="input-field w-full"><option :value="0" disabled>Department</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select><select v-if="activeTab === 'programmes'" v-model="refForm.degree_type" class="input-field w-full"><option v-for="degree in degrees" :key="degree">{{ degree }}</option></select><template v-if="activeTab === 'request-types'"><textarea v-model="refForm.description" class="input-field w-full" placeholder="Description" /><p class="text-sm">Routing sequence</p><div v-for="(step, index) in refForm.default_department_sequence" :key="index" class="flex gap-2"><select v-model="refForm.default_department_sequence[index]" class="input-field flex-1" :aria-label="`Routing step ${index + 1}: ${step}`"><option value="STUDENT_DEPARTMENT">Student department</option><option value="FACULTY_RECORDS">Faculty records</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select><button type="button" @click="moveStep(index, -1)">↑</button><button type="button" @click="moveStep(index, 1)">↓</button><button type="button" @click="refForm.default_department_sequence.splice(index, 1)">Remove</button></div><button type="button" class="btn-secondary" @click="refForm.default_department_sequence.push('STUDENT_DEPARTMENT')">Add step</button></template><div class="flex justify-end gap-2"><button type="button" class="btn-secondary" @click="referenceModal = false">Cancel</button><button class="btn-primary" :disabled="pending">Save</button></div></form></div>

    <div v-if="userModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="userModal = false">
      <form role="dialog" aria-modal="true" aria-labelledby="user-modal-title" class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl" @submit.prevent="saveUser">
        <div class="flex items-start justify-between gap-4 border-b border-neutral-200 px-6 py-4">
          <div><h2 id="user-modal-title" class="text-xl font-semibold text-primary">{{ userForm.id ? 'Edit user' : 'Create user' }}</h2><p class="text-sm text-neutral-600">{{ userForm.role === 'staff' ? 'Account and department assignments' : 'Account and academic information' }}</p></div>
          <button type="button" class="text-2xl leading-none text-neutral-500 hover:text-foreground" aria-label="Close user form" @click="userModal = false">×</button>
        </div>
        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-5">
          <label v-if="!userForm.id" class="block text-sm font-medium text-neutral-700">Role<select v-model="userForm.role" class="input-field mt-1"><option value="student">Student</option><option value="staff">Staff</option></select></label>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-neutral-700">Name<input v-model="userForm.name" required class="input-field mt-1" placeholder="Full name" /></label>
            <label class="block text-sm font-medium text-neutral-700">Email<input v-model="userForm.email" type="email" required class="input-field mt-1" placeholder="name@example.com" /></label>
            <label class="block text-sm font-medium text-neutral-700">Password<input v-model="userForm.password" type="password" :required="!userForm.id" class="input-field mt-1" :placeholder="userForm.id ? 'Leave blank to keep current password' : 'At least 8 characters'" /></label>
            <label v-if="userForm.role === 'staff'" class="block text-sm font-medium text-neutral-700">Staff ID<input v-model="userForm.staff_id" required class="input-field mt-1" placeholder="Staff ID" /></label>
          </div>

          <div v-if="userForm.role === 'student'" class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-neutral-700">Matricule<input v-model="userForm.matricule" required class="input-field mt-1" placeholder="Matricule" /></label>
            <label class="block text-sm font-medium text-neutral-700">Faculty<select v-model.number="userForm.faculty_id" class="input-field mt-1"><option :value="0" disabled>Choose a faculty</option><option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option></select></label>
            <label class="block text-sm font-medium text-neutral-700">Department<select v-model.number="userForm.department_id" class="input-field mt-1"><option :value="0" disabled>Choose a department</option><option v-for="d in departments.filter(d => d.faculty_id === userForm.faculty_id)" :key="d.id" :value="d.id">{{ d.name }}</option></select></label>
            <label class="block text-sm font-medium text-neutral-700">Programme<select v-model.number="userForm.programme_id" class="input-field mt-1"><option :value="0" disabled>Choose a programme</option><option v-for="p in programmes.filter(p => p.department_id === userForm.department_id)" :key="p.id" :value="p.id">{{ p.name }}</option></select></label>
            <label class="block text-sm font-medium text-neutral-700">Level<select v-model="userForm.level" class="input-field mt-1"><option v-for="level in levels" :key="level">{{ level }}</option></select></label>
          </div>

          <section v-else class="space-y-4" aria-labelledby="department-memberships-title">
            <div><h3 id="department-memberships-title" class="text-base font-semibold text-primary">Department memberships</h3><p class="text-sm text-neutral-600">Find departments, select memberships, then choose one primary department.</p></div>
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 space-y-3">
              <div class="flex flex-wrap items-center justify-between gap-2"><h4 class="text-sm font-semibold text-primary">Selected departments</h4><span class="badge bg-white text-neutral-700">{{ userForm.department_ids.length }} selected</span></div>
              <p v-if="!selectedStaffDepartments.length" class="text-sm text-neutral-500">No departments selected yet. Choose at least one below.</p>
              <div v-else class="flex flex-wrap gap-2"><button v-for="d in selectedStaffDepartments" :key="d.id" type="button" class="inline-flex max-w-full items-center gap-2 rounded-full border border-primary/20 bg-white px-3 py-1 text-left text-xs font-medium text-primary hover:bg-neutral-100" :aria-label="`Remove ${d.name} from memberships`" @click="toggleStaffDepartment(d.id, false)">{{ d.name }} <span class="text-neutral-500">· {{ facultyNameForDepartment(d) }}</span><span aria-hidden="true">×</span></button></div>
              <label class="block text-sm font-medium text-neutral-700">Primary department<select v-model.number="userForm.primary_department_id" class="input-field mt-1 bg-white" :disabled="!selectedStaffDepartments.length"><option :value="0" disabled>Choose a primary department</option><option v-for="d in selectedStaffDepartments" :key="d.id" :value="d.id">{{ d.name }} — {{ facultyNameForDepartment(d) }}</option></select></label>
              <p class="text-xs text-neutral-600">The primary department determines department admin scope.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2"><label class="block text-sm font-medium text-neutral-700">Search departments<input v-model="departmentSearch" type="search" class="input-field mt-1" placeholder="Search name, code or faculty" /></label><label class="block text-sm font-medium text-neutral-700">Faculty<select v-model.number="departmentFacultyFilter" class="input-field mt-1"><option :value="0">All faculties</option><option v-for="f in faculties" :key="f.id" :value="f.id">{{ f.name }}</option></select></label></div>
            <div class="max-h-60 overflow-y-auto rounded-lg border border-neutral-200" aria-label="Available departments">
              <div v-for="group in filteredDepartmentGroups" :key="group.facultyId" class="border-b border-neutral-200 last:border-b-0"><h4 class="sticky top-0 bg-neutral-100 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-neutral-600">{{ group.facultyName }}</h4><label v-for="d in group.departments" :key="d.id" class="flex cursor-pointer items-center gap-3 border-t border-neutral-100 px-4 py-2.5 text-sm hover:bg-neutral-50"><input type="checkbox" :checked="userForm.department_ids.includes(d.id)" class="rounded border-neutral-300 text-primary focus:ring-primary" :aria-label="`Assign ${d.name}`" @change="toggleStaffDepartment(d.id, ($event.target as HTMLInputElement).checked)" /><span class="min-w-0 flex-1 font-medium text-foreground">{{ d.name }}</span><span class="text-xs text-neutral-500">{{ d.code }}</span></label></div>
              <p v-if="!filteredDepartmentGroups.length" class="px-4 py-6 text-center text-sm text-neutral-500">No departments match your search.</p>
            </div>
          </section>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-neutral-200 bg-white px-6 py-4"><button type="button" class="btn-secondary" @click="userModal = false">Cancel</button><button type="submit" class="btn-primary" :disabled="pending || (userForm.role === 'staff' && !userForm.department_ids.length)">{{ pending ? 'Saving…' : 'Save user' }}</button></div>
      </form>
    </div>
  </main>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onMounted, reactive, ref, watch } from 'vue'
import { adminRequest, adminStats, createAdmin, deleteAdmin, listAdmin, setAdminLevel, updateAdmin } from '@/services/admin'
import type { AdminRequest, AdminUser, AuditRow, Department, Faculty, Page, Programme, RequestType, Stats } from '@/services/admin'
import { reopenRequest } from '@/services/requests'
import type { Attachment, RequestStage, RequestStatus, StageStatus } from '@/types'
import { requestStatusLabel } from '@/types'
import StatusBadge from './StatusBadge.vue'
import RequestTimeline from './RequestTimeline.vue'
import DocumentViewer from './DocumentViewer.vue'

const Pagination = defineComponent({ props: { page: { type: Number, required: true }, last: { type: Number, required: true } }, emits: ['change'], setup(props, { emit }) { return () => h('div', { class: 'flex gap-3 items-center text-sm' }, [h('button', { disabled: props.page <= 1, onClick: () => emit('change', props.page - 1) }, 'Previous'), h('span', `Page ${props.page} of ${props.last}`), h('button', { disabled: props.page >= props.last, onClick: () => emit('change', props.page + 1) }, 'Next')]) } })
const emptyPage = <T,>(): Page<T> => ({ data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 }, links: { next: null, prev: null } })
const tabs = ['faculties', 'departments', 'programmes', 'request-types'] as const
type Tab = typeof tabs[number]
type Reference = { id: number; name: string; code?: string; matricule_prefix?: string; type?: string; degree_type?: string; default_department_sequence?: (number | string)[]; description?: string | null; faculty_id?: number; department_id?: number }
const statuses: RequestStatus[] = ['draft', 'pending', 'in_review', 'forwarded', 'ready', 'collected', 'rejected']
const degrees = ['BACHELOR', 'CERTIFICATE', 'MASTER', 'PHD']
const levels = ['100', '200', '300', '400', '500', '600']
const error = ref(''), loading = ref(true), pending = ref(false)
const requestsLoading = ref(false), usersLoading = ref(false), referencesLoading = ref(false), auditLoading = ref(false)
const stats = ref<Stats | null>(null), faculties = ref<Faculty[]>([]), departments = ref<Department[]>([]), programmes = ref<Programme[]>([]), requestTypes = ref<RequestType[]>([])
const requests = ref<Page<AdminRequest>>(emptyPage()), users = ref<Page<AdminUser>>(emptyPage()), references = ref<Page<Reference>>(emptyPage()), audit = ref<Page<AuditRow>>(emptyPage())
const requestPage = ref(1), userPage = ref(1), refPage = ref(1), auditPage = ref(1), activeTab = ref<Tab>('faculties')
const userSearch = ref(''), userRole = ref(''), refSearch = ref('')
const requestFilters = reactive({ search: '', faculty_id: 0, department_id: 0, request_type_id: 0, status: '', reopened: false, date_from: '', date_to: '' })
const auditFilters = reactive({ request_id: '', actor_id: '', new_status: '', date_from: '', date_to: '' })
const detail = ref<AdminRequest | null>(null), referenceModal = ref(false), userModal = ref(false)
const activeRequestFilterCount = computed(() => Object.values(requestFilters).filter(value => value !== '' && value !== 0 && value !== false).length)
const detailAttachments = computed<Attachment[]>(() => (detail.value?.attachments ?? []).map(file => ({ ...file, file_path: '' })))
const detailStages = computed<RequestStage[]>(() => (detail.value?.stages ?? []).map(stage => ({
  id: stage.id,
  request_id: detail.value!.id,
  department_name: stage.department?.name ?? 'Unknown department',
  sequence_order: stage.sequence_order,
  status: stage.status as StageStatus,
  handled_by: stage.handled_by ? `#${stage.handled_by}` : null,
  staff_note: stage.staff_note ?? null,
  updated_at: stage.updated_at ?? null,
})))
const refForm = reactive({ id: 0, name: '', code: '', matricule_prefix: '', faculty_id: 0, department_id: 0, type: 'academic', degree_type: 'BACHELOR', description: '', default_department_sequence: [] as (number | string)[] })
const userForm = reactive({ id: 0, role: 'student' as 'student' | 'staff', name: '', email: '', password: '', matricule: '', faculty_id: 0, department_id: 0, programme_id: 0, level: '100', staff_id: '', department_ids: [] as number[], primary_department_id: 0 })
const departmentSearch = ref('')
const departmentFacultyFilter = ref(0)
const selectedStaffDepartments = computed(() => userForm.department_ids
  .map(id => departments.value.find(department => department.id === id))
  .filter((department): department is Department => department !== undefined)
  .sort((a, b) => a.name.localeCompare(b.name)))
function facultyNameForDepartment(department: Department): string {
  return faculties.value.find(faculty => faculty.id === department.faculty_id)?.name ?? 'Other faculty'
}
const filteredDepartmentGroups = computed(() => {
  const term = departmentSearch.value.trim().toLocaleLowerCase()
  const groups = new Map<number, Department[]>()
  for (const department of departments.value) {
    if (departmentFacultyFilter.value && department.faculty_id !== departmentFacultyFilter.value) continue
    const facultyName = facultyNameForDepartment(department)
    if (term && !`${department.name} ${department.code} ${facultyName}`.toLocaleLowerCase().includes(term)) continue
    const group = groups.get(department.faculty_id) ?? []
    group.push(department)
    groups.set(department.faculty_id, group)
  }
  return [...groups].map(([facultyId, group]) => ({
    facultyId,
    facultyName: faculties.value.find(faculty => faculty.id === facultyId)?.name ?? 'Other departments',
    departments: group.sort((a, b) => a.name.localeCompare(b.name)),
  })).sort((a, b) => a.facultyName.localeCompare(b.facultyName))
})
function toggleStaffDepartment(id: number, selected: boolean) {
  if (selected && !userForm.department_ids.includes(id)) userForm.department_ids.push(id)
  if (!selected) userForm.department_ids = userForm.department_ids.filter(departmentId => departmentId !== id)
  if (!userForm.department_ids.includes(userForm.primary_department_id)) {
    userForm.primary_department_id = userForm.department_ids[0] ?? 0
  }
}
const date = (value: string) => new Date(value).toLocaleString()
function clearRequestFilters() { Object.assign(requestFilters, { search: '', faculty_id: 0, department_id: 0, request_type_id: 0, status: '', reopened: false, date_from: '', date_to: '' }) }
function fail(e: unknown) { const response = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response?.data; error.value = response?.message ?? (response?.errors ? Object.values(response.errors).flat().join(' ') : 'The action failed. Please try again.') }
async function loadReferences() { referencesLoading.value = true; try { references.value = await listAdmin<Reference>(activeTab.value, { page: refPage.value, search: refSearch.value }) } catch (e) { fail(e) } finally { referencesLoading.value = false } }
async function loadRequests() { requestsLoading.value = true; try { requests.value = await listAdmin<AdminRequest>('requests', { ...requestFilters, reopened: requestFilters.reopened ? 1 : undefined, page: requestPage.value }) } catch (e) { fail(e) } finally { requestsLoading.value = false } }
async function loadUsers() { usersLoading.value = true; try { users.value = await listAdmin<AdminUser>('users', { page: userPage.value, search: userSearch.value, role: userRole.value }) } catch (e) { fail(e) } finally { usersLoading.value = false } }
async function loadAudit() { auditLoading.value = true; try { audit.value = await listAdmin<AuditRow>('audit-log', { ...auditFilters, page: auditPage.value }) } catch (e) { fail(e) } finally { auditLoading.value = false } }
async function loadStats() { try { stats.value = await adminStats() } catch (e) { fail(e) } }
async function allChoices<T>(kind: string): Promise<T[]> { const result: T[] = []; let page = 1; while (true) { const batch = await listAdmin<T>(kind, { page, per_page: 100 }); result.push(...batch.data); if (page >= batch.meta.last_page) return result; page++ } }
async function loadChoices() { try { const [f, d, p, t] = await Promise.all([allChoices<Faculty>('faculties'), allChoices<Department>('departments'), allChoices<Programme>('programmes'), allChoices<RequestType>('request-types')]); faculties.value = f; departments.value = d; programmes.value = p; requestTypes.value = t } catch (e) { fail(e) } }
onMounted(async () => { await Promise.all([loadStats(), loadChoices(), loadRequests(), loadUsers(), loadReferences(), loadAudit()]); loading.value = false })
watch([requestPage, () => ({ ...requestFilters })], loadRequests, { deep: true })
watch(() => ({ ...requestFilters }), () => { requestPage.value = 1 }, { deep: true })
watch([userPage, userSearch, userRole], loadUsers)
watch([userSearch, userRole], () => { userPage.value = 1 })
watch([refPage, refSearch, activeTab], loadReferences)
watch([refSearch, activeTab], () => { refPage.value = 1 })
watch([auditPage, () => ({ ...auditFilters })], loadAudit, { deep: true })
watch(() => ({ ...auditFilters }), () => { auditPage.value = 1 }, { deep: true })
async function openRequest(id: number) { try { detail.value = await adminRequest(id) } catch (e) { fail(e) } }
async function reopen() { if (!detail.value) return; pending.value = true; try { await reopenRequest(detail.value.id); await Promise.all([openRequest(detail.value.id), loadRequests(), loadStats(), loadAudit()]) } catch (e) { fail(e) } finally { pending.value = false } }
function openReference(row?: Reference) { Object.assign(refForm, { id: 0, name: '', code: '', matricule_prefix: '', faculty_id: 0, department_id: 0, type: 'academic', degree_type: 'BACHELOR', description: '', default_department_sequence: [] }); if (row) Object.assign(refForm, row, { default_department_sequence: [...(row.default_department_sequence ?? [])] }); referenceModal.value = true }
function moveStep(index: number, delta: number) { const next = index + delta; if (next < 0 || next >= refForm.default_department_sequence.length) return; [refForm.default_department_sequence[index], refForm.default_department_sequence[next]] = [refForm.default_department_sequence[next]!, refForm.default_department_sequence[index]!] }
async function saveReference() { const body = activeTab.value === 'faculties' ? { name: refForm.name, code: refForm.code, matricule_prefix: refForm.matricule_prefix } : activeTab.value === 'departments' ? { name: refForm.name, code: refForm.code, faculty_id: refForm.faculty_id, type: refForm.type } : activeTab.value === 'programmes' ? { name: refForm.name, code: refForm.code, department_id: refForm.department_id, degree_type: refForm.degree_type } : { name: refForm.name, description: refForm.description, default_department_sequence: refForm.default_department_sequence }; pending.value = true; try { if (refForm.id) await updateAdmin(activeTab.value, refForm.id, body); else await createAdmin(activeTab.value, body); referenceModal.value = false; await Promise.all([loadReferences(), loadChoices()]) } catch (e) { fail(e) } finally { pending.value = false } }
function openUser(u?: AdminUser) { departmentSearch.value = ''; departmentFacultyFilter.value = 0; Object.assign(userForm, { id: 0, role: 'student', name: '', email: '', password: '', matricule: '', faculty_id: 0, department_id: 0, programme_id: 0, level: '100', staff_id: '', department_ids: [], primary_department_id: 0 }); if (u) Object.assign(userForm, { id: u.id, role: u.role, name: u.name, email: u.email, password: '', ...(u.student_profile ?? {}), staff_id: u.staff_profile?.staff_id ?? '', department_ids: u.staff_profile?.departments.map(d => d.id) ?? [], primary_department_id: u.staff_profile?.departments.find(d => d.is_primary)?.id ?? 0 }); userModal.value = true }
async function saveUser() { const body: Record<string, unknown> = { name: userForm.name, email: userForm.email }; if (!userForm.id) body.role = userForm.role; if (userForm.password) body.password = userForm.password; if (userForm.role === 'student') Object.assign(body, { matricule: userForm.matricule, faculty_id: userForm.faculty_id, department_id: userForm.department_id, programme_id: userForm.programme_id, level: userForm.level }); else Object.assign(body, { staff_id: userForm.staff_id, department_ids: userForm.department_ids, primary_department_id: userForm.primary_department_id }); pending.value = true; try { if (userForm.id) await updateAdmin('users', userForm.id, body); else await createAdmin('users', body); userModal.value = false; await loadUsers() } catch (e) { fail(e) } finally { pending.value = false } }
async function changeLevel(u: AdminUser, level: string) { pending.value = true; try { await setAdminLevel(u.id, (level || null) as 'dept_admin' | 'super_admin' | null); await loadUsers() } catch (e) { fail(e); await loadUsers() } finally { pending.value = false } }
async function remove(kind: string, id: number) { if (!window.confirm('Delete this unused record?')) return; pending.value = true; try { await deleteAdmin(kind, id); if (kind === 'users') await loadUsers(); else await Promise.all([loadReferences(), loadChoices()]) } catch (e) { fail(e) } finally { pending.value = false } }
</script>
