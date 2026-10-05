<template>
  <div class="space-y-5">
    <label
      v-if="!userForm.id"
      class="block text-sm font-medium text-slate-700"
      >Role<select
        v-model="userForm.role"
        class="input-field mt-1"
      >
        <option value="student">Student</option>
        <option value="staff">Staff</option>
      </select></label
    >
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium text-slate-700"
        >Name<input
          v-model="userForm.name"
          required
          class="input-field mt-1"
          placeholder="Full name"
      /></label>
      <label class="block text-sm font-medium text-slate-700"
        >Email<input
          v-model="userForm.email"
          type="email"
          required
          class="input-field mt-1"
          placeholder="name@example.com"
      /></label>
      <label class="block text-sm font-medium text-slate-700"
        >Password<input
          v-model="userForm.password"
          type="password"
          autocomplete="new-password"
          minlength="8"
          :required="!userForm.id"
          class="input-field mt-1"
          :placeholder="
            userForm.id
              ? 'Leave blank to keep current password'
              : 'At least 8 characters'
          "
      /></label>
      <label
        v-if="userForm.role === 'staff'"
        class="block text-sm font-medium text-slate-700"
        >Staff ID<input
          v-model="userForm.staff_id"
          required
          class="input-field mt-1"
          placeholder="Staff ID"
      /></label>
    </div>

    <div
      v-if="userForm.role === 'student'"
      class="grid gap-4 sm:grid-cols-2"
    >
      <label class="block text-sm font-medium text-slate-700"
        >Matricule<input
          v-model="userForm.matricule"
          required
          class="input-field mt-1"
          placeholder="Matricule"
      /></label>
      <label class="block text-sm font-medium text-slate-700"
        >Faculty<select
          v-model.number="userForm.faculty_id"
          required
          class="input-field mt-1"
        >
          <option
            :value="0"
            disabled
          >
            Choose a faculty
          </option>
          <option
            v-for="f in faculties"
            :key="f.id"
            :value="f.id"
          >
            {{ f.name }}
          </option>
        </select></label
      >
      <label class="block text-sm font-medium text-slate-700"
        >Department<select
          v-model.number="userForm.department_id"
          required
          :disabled="!userForm.faculty_id"
          class="input-field mt-1"
        >
          <option
            :value="0"
            disabled
          >
            Choose a department
          </option>
          <option
            v-for="d in departments.filter(
              (d) => d.faculty_id === userForm.faculty_id
            )"
            :key="d.id"
            :value="d.id"
          >
            {{ d.name }}
          </option>
        </select></label
      >
      <label class="block text-sm font-medium text-slate-700"
        >Programme<select
          v-model.number="userForm.programme_id"
          required
          :disabled="!userForm.department_id"
          class="input-field mt-1"
        >
          <option
            :value="0"
            disabled
          >
            Choose a programme
          </option>
          <option
            v-for="p in programmes.filter(
              (p) => p.department_id === userForm.department_id
            )"
            :key="p.id"
            :value="p.id"
          >
            {{ p.name }}
          </option>
        </select></label
      >
      <label class="block text-sm font-medium text-slate-700"
        >Level<select
          v-model="userForm.level"
          class="input-field mt-1"
        >
          <option
            v-for="level in levels"
            :key="level"
          >
            {{ level }}
          </option>
        </select></label
      >
    </div>

    <section
      v-else
      class="space-y-4"
      aria-labelledby="department-memberships-title"
    >
      <div>
        <h3
          id="department-memberships-title"
          class="text-base font-semibold text-primary"
        >
          Department memberships
        </h3>
        <p class="text-sm text-slate-600">
          Find departments, select memberships, then choose one primary
          department.
        </p>
      </div>
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h4 class="text-sm font-semibold text-primary">
            Selected departments
          </h4>
          <span class="badge bg-white text-slate-700"
            >{{ userForm.department_ids.length }} selected</span
          >
        </div>
        <p
          v-if="!selectedStaffDepartments.length"
          class="text-sm text-slate-500"
        >
          No departments selected yet. Choose at least one below.
        </p>
        <div
          v-else
          class="flex flex-wrap gap-2"
        >
          <button
            v-for="d in selectedStaffDepartments"
            :key="d.id"
            type="button"
            class="inline-flex max-w-full items-center gap-2 rounded-full border border-primary/20 bg-white px-3 py-2 min-h-11 text-left text-xs font-medium text-primary hover:bg-slate-100"
            :aria-label="`Remove ${d.name} from memberships`"
            @click="toggleStaffDepartment(d.id, false)"
          >
            {{ d.name }}
            <span class="text-slate-500"
              >· {{ facultyNameForDepartment(d) }}</span
            ><span aria-hidden="true">×</span>
          </button>
        </div>
        <label class="block text-sm font-medium text-slate-700"
          >Primary department<select
            v-model.number="userForm.primary_department_id"
            class="input-field mt-1 bg-white"
            :disabled="!selectedStaffDepartments.length"
          >
            <option
              :value="0"
              disabled
            >
              Choose a primary department
            </option>
            <option
              v-for="d in selectedStaffDepartments"
              :key="d.id"
              :value="d.id"
            >
              {{ d.name }} — {{ facultyNameForDepartment(d) }}
            </option>
          </select></label
        >
        <p class="text-xs text-slate-600">
          The primary department determines department admin scope.
        </p>
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700"
          >Search departments<input
            v-model="departmentSearch"
            type="search"
            class="input-field mt-1"
            placeholder="Search name, code or faculty" /></label
        ><label class="block text-sm font-medium text-slate-700"
          >Faculty<select
            v-model.number="departmentFacultyFilter"
            class="input-field mt-1"
          >
            <option :value="0">All faculties</option>
            <option
              v-for="f in faculties"
              :key="f.id"
              :value="f.id"
            >
              {{ f.name }}
            </option>
          </select></label
        >
      </div>
      <div
        class="max-h-60 overflow-y-auto rounded-lg border border-slate-200"
        aria-label="Available departments"
      >
        <div
          v-for="group in filteredDepartmentGroups"
          :key="group.facultyId"
          class="border-b border-slate-200 last:border-b-0"
        >
          <h4
            class="sticky top-0 bg-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600"
          >
            {{ group.facultyName }}
          </h4>
          <label
            v-for="d in group.departments"
            :key="d.id"
            class="flex cursor-pointer items-center gap-3 border-t border-slate-100 px-4 py-2.5 text-sm hover:bg-slate-50"
            ><input
              type="checkbox"
              :checked="userForm.department_ids.includes(d.id)"
              class="rounded border-slate-300 text-primary focus:ring-primary"
              :aria-label="`Assign ${d.name}`"
              @change="
                toggleStaffDepartment(
                  d.id,
                  ($event.target as HTMLInputElement).checked
                )
              "
            /><span class="min-w-0 flex-1 font-medium text-foreground">{{
              d.name
            }}</span
            ><span class="text-xs text-slate-500">{{ d.code }}</span></label
          >
        </div>
        <p
          v-if="!filteredDepartmentGroups.length"
          class="px-4 py-6 text-center text-sm text-slate-500"
        >
          No departments match your search.
        </p>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { Department, Faculty, Programme } from '@/services/admin'

export interface UserForm {
  id: number
  role: 'student' | 'staff'
  name: string
  email: string
  password: string
  matricule: string
  faculty_id: number
  department_id: number
  programme_id: number
  level: string
  staff_id: string
  department_ids: number[]
  primary_department_id: number
}
const props = defineProps<{
  faculties: Faculty[]
  departments: Department[]
  programmes: Programme[]
}>()
const userForm = defineModel<UserForm>({ required: true })
const levels = ['100', '200', '300', '400', '500', '600']
const departmentSearch = ref('')
const departmentFacultyFilter = ref(0)
const selectedStaffDepartments = computed(() =>
  props.departments
    .filter((department) =>
      userForm.value.department_ids.includes(department.id)
    )
    .sort((a, b) => a.name.localeCompare(b.name))
)
function facultyNameForDepartment(department: Department): string {
  return (
    props.faculties.find((faculty) => faculty.id === department.faculty_id)
      ?.name ?? 'Other faculty'
  )
}
const filteredDepartmentGroups = computed(() => {
  const term = departmentSearch.value.trim().toLocaleLowerCase()
  const groups = new Map<number, Department[]>()
  for (const department of props.departments) {
    if (
      departmentFacultyFilter.value &&
      department.faculty_id !== departmentFacultyFilter.value
    )
      continue
    if (
      term &&
      !`${department.name} ${department.code} ${facultyNameForDepartment(department)}`
        .toLocaleLowerCase()
        .includes(term)
    )
      continue
    const group = groups.get(department.faculty_id) ?? []
    group.push(department)
    groups.set(department.faculty_id, group)
  }
  return [...groups]
    .map(([facultyId, departments]) => ({
      facultyId,
      facultyName:
        props.faculties.find((faculty) => faculty.id === facultyId)?.name ??
        'Other departments',
      departments: departments.sort((a, b) => a.name.localeCompare(b.name))
    }))
    .sort((a, b) => a.facultyName.localeCompare(b.facultyName))
})
function toggleStaffDepartment(id: number, selected: boolean) {
  const form = userForm.value
  if (selected && !form.department_ids.includes(id))
    form.department_ids.push(id)
  if (!selected)
    form.department_ids = form.department_ids.filter(
      (departmentId) => departmentId !== id
    )
  if (!form.department_ids.includes(form.primary_department_id))
    form.primary_department_id = form.department_ids[0] ?? 0
}
watch(
  () => userForm.value.faculty_id,
  () => {
    if (
      !props.departments.some(
        (item) =>
          item.id === userForm.value.department_id &&
          item.faculty_id === userForm.value.faculty_id
      )
    ) {
      userForm.value.department_id = 0
      userForm.value.programme_id = 0
    }
  }
)
watch(
  () => userForm.value.department_id,
  () => {
    if (
      !props.programmes.some(
        (item) =>
          item.id === userForm.value.programme_id &&
          item.department_id === userForm.value.department_id
      )
    )
      userForm.value.programme_id = 0
  }
)
</script>
