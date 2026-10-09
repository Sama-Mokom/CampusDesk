<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Super administration"
      title="People & access"
      description="Manage accounts, department memberships and admin access."
    >
      <template #actions>
        <button
          class="btn-secondary"
          :disabled="loading"
          @click="initialize"
        >
          {{ loading ? 'Refreshing…' : 'Refresh page' }}
        </button>
      </template>
    </PageHeader>

    <div
      v-if="error || choicesError"
      role="alert"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
    >
      {{ error || choicesError }}
    </div>
    <p
      v-if="success"
      role="status"
      class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
    >
      {{ success }}
    </p>
    <section
      id="admin-users"
      class="card scroll-mt-24 space-y-5"
      aria-labelledby="users-heading"
      :aria-busy="usersLoading"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2
            id="users-heading"
            class="text-lg font-semibold text-slate-900"
          >
            People & access
          </h2>
          <p class="mt-1 text-sm text-slate-500">
            Manage accounts, department memberships and admin access.
          </p>
        </div>
        <button
          class="btn-primary"
          :disabled="!choicesReady"
          @click="openUser()"
        >
          Create user
        </button>
      </div>
      <div class="grid gap-3 sm:grid-cols-[1fr_220px_220px]">
        <label class="text-xs font-medium text-slate-600"
          >Search users<input
            v-model="userSearch"
            type="search"
            class="input-field mt-1"
            placeholder="Search users" /></label
        ><label class="text-xs font-medium text-slate-600"
          >Role<select
            v-model="userRole"
            class="input-field mt-1"
          >
            <option value="">All roles</option>
            <option value="student">Student</option>
            <option value="staff">Staff</option>
          </select></label
        ><label class="text-xs font-medium text-slate-600"
          >Account status<select
            v-model="userStatus"
            class="input-field mt-1"
          >
            <option value="">All accounts</option>
            <option value="enabled">Enabled</option>
            <option value="disabled">Disabled</option>
          </select></label
        >
      </div>
      <SkeletonLoader
        v-if="usersLoading"
        :count="3"
      />
      <div
        v-else-if="collectionErrors.users"
        role="alert"
        class="rounded-lg bg-red-50 p-4 text-sm text-red-800"
      >
        {{ collectionErrors.users }}
        <button
          class="min-h-11 font-semibold underline"
          @click="loadUsers"
        >
          Try again
        </button>
      </div>
      <EmptyState
        v-else-if="!users.data.length"
        :title="userSearch || userRole || userStatus ? 'No matching people' : 'No users yet'"
        description="Create an account or adjust your search."
      />
      <div
        v-else
        class="divide-y divide-slate-100"
      >
        <article
          v-for="user in users.data"
          :key="user.id"
          class="flex flex-col justify-between gap-4 py-4 xl:flex-row xl:items-center"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-semibold text-slate-900 break-words">
                {{ user.name }}
              </h3>
              <span class="badge bg-slate-100 text-slate-600 capitalize">{{
                user.role
              }}</span>
              <span
                class="badge"
                :class="user.is_disabled ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
              >{{ user.is_disabled ? 'Disabled' : 'Enabled' }}</span>
            </div>
            <p class="mt-1 break-all text-sm text-slate-500">
              {{ user.email }}
            </p>
            <p
              v-if="user.staff_profile"
              class="mt-1 text-xs text-slate-500 break-words"
            >
              {{
                user.staff_profile.departments
                  .map((department) => department.name)
                  .join(' · ') || 'No departments assigned'
              }}
            </p>
          </div>
          <div class="flex flex-wrap items-end gap-2">
            <label
              v-if="user.role === 'staff'"
              class="min-w-0 flex-1 text-xs font-medium text-slate-500 sm:flex-none"
              >Admin access<select
                :value="user.staff_profile?.admin_level ?? ''"
                class="input-field mt-1"
                :aria-label="`Admin access for ${user.name}`"
                :disabled="pending"
                @change="
                  changeLevel(user, ($event.target as HTMLSelectElement).value)
                "
              >
                <option value="">Staff</option>
                <option value="dept_admin">Department admin</option>
                <option value="super_admin">Super admin</option>
              </select></label
            ><button
              class="btn-secondary"
              :disabled="!choicesReady"
              :aria-label="`Edit ${user.name}`"
              @click="openUser(user)"
            >
              Edit</button
            ><button
              v-if="user.is_disabled"
              class="btn-secondary text-emerald-700"
              :disabled="pending"
              :aria-label="`Re-enable ${user.name}`"
              @click="enableAccount(user)"
            >
              Re-enable</button
            ><button
              v-else-if="signedInUser?.id !== user.id"
              class="btn-secondary text-amber-700"
              :disabled="pending"
              :aria-label="`Disable ${user.name}`"
              @click="askDisable(user)"
            >
              Disable</button
            ><button
              class="btn-secondary text-red-700"
              :disabled="pending"
              :aria-label="`Delete ${user.name}`"
              @click="askRemove('users', user.id, user.name)"
            >
              Delete
            </button>
          </div>
        </article>
      </div>
      <AdminPagination
        :page="users.meta.current_page"
        :last="users.meta.last_page"
        :total="users.meta.total"
        :disabled="usersLoading"
        label="User pages"
        @change="userPage = $event"
      />
    </section>
    <BaseModal
      :open="userModal"
      :title="userForm.id ? 'Edit user' : 'Create user'"
      size="lg"
      :busy="pending"
      @close="closeUser"
    >
      <form
        id="admin-user-form"
        @submit.prevent="saveUser"
      >
        <fieldset :disabled="pending">
          <AdminUserFields
            v-if="userModal"
            v-model="userForm"
            :faculties="faculties"
            :departments="departments"
            :programmes="programmes"
          />
        </fieldset>
        <p
          v-if="formError"
          role="alert"
          class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800"
        >
          {{ formError }}
        </p>
      </form>
      <template #footer
        ><button
          class="btn-secondary"
          :disabled="pending"
          @click="closeUser"
        >
          Cancel</button
        ><button
          type="submit"
          form="admin-user-form"
          class="btn-primary"
          :disabled="pending"
        >
          {{ pending ? 'Saving…' : 'Save user' }}
        </button></template
      >
    </BaseModal>
    <BaseModal
      :open="!!disableTarget"
      title="Disable account"
      size="sm"
      :busy="pending"
      @close="closeDisable"
    >
      <p class="text-sm text-slate-700">
        Disable <strong>{{ disableTarget?.name }}</strong>? Their active sessions and password-reset links will be revoked immediately.
      </p>
      <label class="mt-4 block text-sm font-medium text-slate-700">
        Reason (optional)
        <textarea
          v-model="disableReason"
          class="input-field mt-1"
          rows="3"
          maxlength="500"
          placeholder="Why is this account being disabled?"
        />
      </label>
      <p
        v-if="disableError"
        role="alert"
        class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800"
      >
        {{ disableError }}
      </p>
      <template #footer>
        <button
          class="btn-secondary"
          :disabled="pending"
          @click="closeDisable"
        >
          Cancel
        </button>
        <button
          class="btn-primary bg-red-700 hover:bg-red-800"
          :disabled="pending"
          @click="confirmDisable"
        >
          {{ pending ? 'Disabling…' : 'Disable account' }}
        </button>
      </template>
    </BaseModal>
    <AdminDeleteDialog
      :target="deleteTarget"
      :busy="pending"
      :error="formError"
      @close="closeDelete"
      @confirm="remove"
    />
  </div>
</template>

<script setup lang="ts">
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import AdminUserFields from '@/components/admin/AdminUserFields.vue'
import AdminDeleteDialog from '@/components/admin/AdminDeleteDialog.vue'

import { useAdminUsers } from '@/composables/admin/useAdminUsers'

const {
  deleteTarget,
  closeDelete,
  remove,
  users,
  userPage,
  usersLoading,
  collectionErrors,
  userSearch,
  userRole,
  userStatus,
  signedInUser,
  loadUsers,
  initialize,
  loading,
  error,
  success,
  pending,
  formError,
  choicesReady,
  choicesError,
  faculties,
  departments,
  programmes,
  userModal,
  userForm,
  openUser,
  closeUser,
  saveUser,
  changeLevel,
  disableTarget,
  disableReason,
  disableError,
  askDisable,
  closeDisable,
  confirmDisable,
  enableAccount,
  askRemove
} = useAdminUsers()
</script>
