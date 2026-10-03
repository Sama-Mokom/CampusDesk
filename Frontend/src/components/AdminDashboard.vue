<template>
  <div
    id="admin-workspace"
    class="space-y-6 scroll-mt-24"
  >
    <PageHeader
      eyebrow="Super administration"
      title="Campus overview"
      description="Oversee requests, manage people and keep your institution connected."
    >
      <template #actions
        ><button
          class="btn-secondary"
          :disabled="loading"
          @click="initialize"
        >
          {{ loading ? 'Refreshing…' : 'Refresh dashboard' }}
        </button></template
      >
    </PageHeader>
    <div
      v-if="error"
      role="alert"
      class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
    >
      <span>{{ error }}</span
      ><button
        class="min-h-11 font-semibold underline"
        @click="error = ''"
      >
        Dismiss
      </button>
    </div>
    <p
      v-if="success"
      role="status"
      class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
    >
      {{ success }}
    </p>
    <section
      aria-label="System overview"
      class="space-y-4"
    >
      <SkeletonLoader
        v-if="statsLoading"
        :count="3"
      />
      <div
        v-else-if="stats"
        class="grid grid-cols-2 gap-3 lg:grid-cols-4"
      >
        <div class="card">
          <p class="text-xs font-medium text-slate-500">Total requests</p>
          <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">
            {{ stats.total }}
          </p>
          <p class="mt-1 text-xs text-slate-500">Across the institution</p>
        </div>
        <div class="card">
          <p class="text-xs font-medium text-slate-500">Submitted today</p>
          <p class="mt-3 text-3xl font-bold tracking-tight text-sky-700">
            {{ stats.requests_today }}
          </p>
          <p class="mt-1 text-xs text-slate-500">New requests today</p>
        </div>
        <div class="card">
          <p class="text-xs font-medium text-slate-500">In review</p>
          <p class="mt-3 text-3xl font-bold tracking-tight text-sky-700">
            {{ stats.by_status.in_review ?? 0 }}
          </p>
          <p class="mt-1 text-xs text-slate-500">Currently being handled</p>
        </div>
        <div class="card">
          <p class="text-xs font-medium text-slate-500">Average resolution</p>
          <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">
            {{ stats.avg_resolution_hours ?? '—'
            }}<span
              v-if="stats.avg_resolution_hours !== null"
              class="ml-1 text-sm font-medium text-slate-500"
              >hrs</span
            >
          </p>
          <p class="mt-1 text-xs text-slate-500">
            {{
              stats.avg_resolution_hours === null
                ? 'No resolved requests yet'
                : 'For resolved requests'
            }}
          </p>
        </div>
      </div>
      <div
        v-else
        class="card text-sm text-slate-600"
      >
        Overview statistics are unavailable.
        <button
          class="min-h-11 font-semibold text-sky-700 underline"
          @click="loadStats"
        >
          Try again
        </button>
      </div>
      <div
        v-if="stats"
        class="grid gap-4 xl:grid-cols-[1fr_1.5fr]"
      >
        <section class="card">
          <h2 class="text-base font-semibold text-slate-900">
            Request distribution
          </h2>
          <div class="mt-4 space-y-3">
            <div
              v-for="status in statuses"
              :key="status"
              class="flex items-center justify-between gap-3 text-sm"
            >
              <StatusBadge
                kind="request"
                :status="status"
              /><span class="font-semibold tabular-nums text-slate-700">{{
                stats.by_status[status] ?? 0
              }}</span>
            </div>
          </div>
        </section>
        <section class="card">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-slate-900">
              Recent activity
            </h2>
            <a
              href="#admin-history"
              class="inline-flex min-h-11 items-center text-sm font-medium text-sky-700"
              >View status history →</a
            >
          </div>
          <EmptyState
            v-if="!stats.recent_activity.length"
            title="No activity yet"
            description="Status changes will appear as requests move through the workflow."
          /><AdminAuditTrail
            v-else
            :rows="stats.recent_activity.slice(0, 5)"
          />
        </section>
      </div>
    </section>
    <nav
      aria-label="Administration sections"
      class="flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-2"
    >
      <a
        v-for="section in sections"
        :key="section.id"
        :href="`#${section.id}`"
        class="inline-flex min-h-11 items-center rounded-lg px-4 text-sm font-medium text-slate-600 transition-colors hover:bg-sky-50 hover:text-sky-700"
        >{{ section.label }}</a
      >
    </nav>

    <section
      id="admin-requests"
      class="card scroll-mt-24 space-y-5"
      aria-labelledby="all-requests-heading"
      :aria-busy="requestsLoading"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2
            id="all-requests-heading"
            class="text-lg font-semibold text-slate-900"
          >
            All requests
          </h2>
          <p class="mt-1 text-sm text-slate-500">
            Review requests across every faculty and department.
          </p>
        </div>
        <span class="badge bg-slate-100 text-slate-600"
          >{{ requests.meta.total }} requests</span
        >
      </div>
      <div class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-semibold text-slate-700">Filter requests</h3>
          <button
            v-if="activeRequestFilterCount"
            class="min-h-11 text-sm font-semibold text-sky-700"
            @click="clearRequestFilters"
          >
            Clear filters ({{ activeRequestFilterCount }})
          </button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <label class="text-xs font-medium text-slate-600 sm:col-span-2"
            >Search<input
              v-model="requestFilters.search"
              type="search"
              maxlength="100"
              class="input-field mt-1"
              placeholder="Student name, matricule or request ID"
          /></label>
          <label class="text-xs font-medium text-slate-600"
            >Faculty<select
              v-model.number="requestFilters.faculty_id"
              class="input-field mt-1"
            >
              <option :value="0">All faculties</option>
              <option
                v-for="faculty in faculties"
                :key="faculty.id"
                :value="faculty.id"
              >
                {{ faculty.name }}
              </option>
            </select></label
          >
          <label class="text-xs font-medium text-slate-600"
            >Department<select
              v-model.number="requestFilters.department_id"
              class="input-field mt-1"
            >
              <option :value="0">All departments</option>
              <option
                v-for="department in departments"
                :key="department.id"
                :value="department.id"
              >
                {{ department.name }}
              </option>
            </select></label
          >
          <label class="text-xs font-medium text-slate-600"
            >Request type<select
              v-model.number="requestFilters.request_type_id"
              class="input-field mt-1"
            >
              <option :value="0">All request types</option>
              <option
                v-for="type in requestTypes"
                :key="type.id"
                :value="type.id"
              >
                {{ type.name }}
              </option>
            </select></label
          >
          <label class="text-xs font-medium text-slate-600"
            >Status<select
              v-model="requestFilters.status"
              class="input-field mt-1"
            >
              <option value="">All statuses</option>
              <option
                v-for="status in statuses"
                :key="status"
                :value="status"
              >
                {{ requestStatusLabel(status) }}
              </option>
            </select></label
          >
          <label class="text-xs font-medium text-slate-600"
            >From<input
              v-model="requestFilters.date_from"
              type="date"
              class="input-field mt-1" /></label
          ><label class="text-xs font-medium text-slate-600"
            >To<input
              v-model="requestFilters.date_to"
              type="date"
              class="input-field mt-1"
              :min="requestFilters.date_from || undefined"
          /></label>
        </div>
        <label
          class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm text-slate-600"
          ><input
            v-model="requestFilters.reopened"
            type="checkbox"
          />Reopened requests only</label
        >
      </div>
      <SkeletonLoader
        v-if="requestsLoading"
        :count="3"
      />
      <div
        v-else-if="collectionErrors.requests"
        role="alert"
        class="rounded-lg bg-red-50 p-4 text-sm text-red-800"
      >
        {{ collectionErrors.requests }}
        <button
          class="min-h-11 font-semibold underline"
          @click="loadRequests"
        >
          Try again
        </button>
      </div>
      <EmptyState
        v-else-if="!requests.data.length"
        :title="
          activeRequestFilterCount ? 'No matching requests' : 'No requests yet'
        "
        :description="
          activeRequestFilterCount
            ? 'Adjust the filters to find another request.'
            : 'New student requests will appear here.'
        "
      />
      <div
        v-else
        class="divide-y divide-slate-100"
      >
        <article
          v-for="row in requests.data"
          :key="row.id"
          class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="min-w-0 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-semibold text-slate-900 break-words">
                {{ row.student?.name ?? 'Unknown student' }}
              </h3>
              <span class="text-xs text-slate-500">Request #{{ row.id }}</span
              ><StatusBadge
                kind="request"
                :status="row.status as RequestStatus"
              /><span
                v-if="row.is_reopened"
                class="badge bg-amber-50 text-amber-800"
                >Reopened</span
              >
            </div>
            <p class="text-sm text-slate-600 break-words">
              {{ row.request_type?.name ?? 'Request'
              }}<span v-if="row.student?.student_profile?.matricule">
                · {{ row.student.student_profile.matricule }}</span
              >
            </p>
            <p class="text-xs text-slate-500">
              Submitted {{ date(row.created_at) }}
            </p>
          </div>
          <button
            class="btn-secondary self-start shrink-0 sm:self-auto"
            :aria-label="`View request ${row.id}`"
            @click="openRequest(row.id)"
          >
            View
          </button>
        </article>
      </div>
      <AdminPagination
        :page="requests.meta.current_page"
        :last="requests.meta.last_page"
        :total="requests.meta.total"
        :disabled="requestsLoading"
        label="Request pages"
        @change="requestPage = $event"
      />
    </section>

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
      <div class="grid gap-3 sm:grid-cols-[1fr_220px]">
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
        :title="userSearch || userRole ? 'No matching people' : 'No users yet'"
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

    <section
      id="admin-organisation"
      class="card scroll-mt-24 space-y-5"
      aria-labelledby="organisation-heading"
      :aria-busy="referencesLoading"
    >
      <div>
        <h2
          id="organisation-heading"
          class="text-lg font-semibold text-slate-900"
        >
          Organisation & workflows
        </h2>
        <p class="mt-1 text-sm text-slate-500">
          Maintain academic structures and request routing.
        </p>
      </div>
      <div
        class="flex flex-wrap gap-2"
        aria-label="Reference categories"
      >
        <button
          v-for="tab in tabs"
          :key="tab"
          :aria-pressed="tab === activeTab"
          :class="tab === activeTab ? 'btn-primary' : 'btn-secondary'"
          @click="activeTab = tab"
        >
          {{ tabLabel(tab) }}
        </button>
      </div>
      <div class="flex flex-wrap items-end gap-3">
        <label class="min-w-0 flex-1 text-xs font-medium text-slate-600"
          >Search {{ tabLabel(activeTab).toLowerCase()
          }}<input
            v-model="refSearch"
            type="search"
            class="input-field mt-1"
            placeholder="Search records" /></label
        ><button
          class="btn-primary"
          :disabled="!choicesReady"
          @click="openReference()"
        >
          Create
        </button>
      </div>
      <SkeletonLoader
        v-if="referencesLoading"
        :count="3"
      />
      <div
        v-else-if="collectionErrors.references"
        role="alert"
        class="rounded-lg bg-red-50 p-4 text-sm text-red-800"
      >
        {{ collectionErrors.references }}
        <button
          class="min-h-11 font-semibold underline"
          @click="loadReferences"
        >
          Try again
        </button>
      </div>
      <EmptyState
        v-else-if="!references.data.length"
        :title="refSearch ? 'No matching records' : 'No records yet'"
        description="Create a record or try another search."
      />
      <div
        v-else
        class="divide-y divide-slate-100"
      >
        <article
          v-for="row in references.data"
          :key="row.id"
          class="flex flex-col justify-between gap-3 py-4 sm:flex-row sm:items-center"
        >
          <div class="min-w-0">
            <h3 class="font-semibold text-slate-900 break-words">
              {{ row.name }}
            </h3>
            <p class="mt-1 text-xs text-slate-500 break-words">
              {{
                [row.code, row.matricule_prefix, row.type, row.degree_type]
                  .filter(Boolean)
                  .join(' · ')
              }}
            </p>
            <p
              v-if="row.default_department_sequence"
              class="mt-1 text-sm text-slate-500 break-words"
            >
              {{
                row.default_department_sequence.map(routeLabel).join(' → ') ||
                'No routing steps configured'
              }}
            </p>
          </div>
          <div class="flex shrink-0 gap-2">
            <button
              class="btn-secondary"
              :disabled="!choicesReady"
              :aria-label="`Edit ${row.name}`"
              @click="openReference(row)"
            >
              Edit</button
            ><button
              class="btn-secondary text-red-700"
              :disabled="pending"
              :aria-label="`Delete ${row.name}`"
              @click="askRemove(activeTab, row.id, row.name)"
            >
              Delete
            </button>
          </div>
        </article>
      </div>
      <AdminPagination
        :page="references.meta.current_page"
        :last="references.meta.last_page"
        :total="references.meta.total"
        :disabled="referencesLoading"
        label="Reference pages"
        @change="refPage = $event"
      />
    </section>

    <section
      id="admin-history"
      class="card scroll-mt-24 space-y-5"
      aria-labelledby="history-heading"
      :aria-busy="auditLoading"
    >
      <div>
        <h2
          id="history-heading"
          class="text-lg font-semibold text-slate-900"
        >
          Request and stage status history
        </h2>
        <p class="mt-1 text-sm text-slate-500">
          Status transitions across the institution. Administrative edits are
          outside this log.
        </p>
      </div>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <label class="text-xs font-medium text-slate-600"
          >Request ID<input
            v-model="auditFilters.request_id"
            type="number"
            min="1"
            class="input-field mt-1"
            placeholder="All requests" /></label
        ><label class="text-xs font-medium text-slate-600"
          >Actor ID<input
            v-model="auditFilters.actor_id"
            type="number"
            min="1"
            class="input-field mt-1"
            placeholder="All people" /></label
        ><label class="text-xs font-medium text-slate-600"
          >New status<input
            v-model="auditFilters.new_status"
            class="input-field mt-1"
            placeholder="Any status" /></label
        ><label class="text-xs font-medium text-slate-600"
          >From<input
            v-model="auditFilters.date_from"
            type="date"
            class="input-field mt-1" /></label
        ><label class="text-xs font-medium text-slate-600"
          >To<input
            v-model="auditFilters.date_to"
            type="date"
            class="input-field mt-1"
            :min="auditFilters.date_from || undefined"
        /></label>
      </div>
      <button
        v-if="hasAuditFilters"
        class="min-h-11 text-sm font-semibold text-sky-700"
        @click="clearAuditFilters"
      >
        Clear history filters
      </button>
      <SkeletonLoader
        v-if="auditLoading"
        :count="3"
      />
      <div
        v-else-if="collectionErrors.audit"
        role="alert"
        class="rounded-lg bg-red-50 p-4 text-sm text-red-800"
      >
        {{ collectionErrors.audit }}
        <button
          class="min-h-11 font-semibold underline"
          @click="loadAudit"
        >
          Try again
        </button>
      </div>
      <EmptyState
        v-else-if="!audit.data.length"
        :title="
          hasAuditFilters ? 'No matching transitions' : 'No status history yet'
        "
        description="Status changes will appear as requests move through the workflow."
      />
      <AdminAuditTrail
        v-else
        :rows="audit.data"
      />
      <AdminPagination
        :page="audit.meta.current_page"
        :last="audit.meta.last_page"
        :total="audit.meta.total"
        :disabled="auditLoading"
        label="History pages"
        @change="auditPage = $event"
      />
    </section>

    <BaseModal
      :open="detailOpen"
      :title="
        detail
          ? `${detail.request_type?.name ?? 'Request'} · #${detail.id}`
          : 'Request details'
      "
      size="xl"
      :busy="pending"
      @close="closeDetail"
    >
      <SkeletonLoader
        v-if="detailLoading"
        :count="4"
      />
      <div
        v-else-if="detailError"
        role="alert"
        class="rounded-lg bg-red-50 p-4 text-sm text-red-800"
      >
        {{ detailError }}
        <button
          class="min-h-11 font-semibold underline"
          @click="openRequest(detailId)"
        >
          Try again
        </button>
      </div>
      <div
        v-else-if="detail"
        class="space-y-6"
      >
        <div
          class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-4"
        >
          <div>
            <p class="font-semibold text-slate-900">
              {{ detail.student?.name }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
              {{ detail.student?.student_profile?.matricule }} · Submitted
              {{ date(detail.created_at) }}
            </p>
          </div>
          <StatusBadge
            kind="request"
            :status="detail.status as RequestStatus"
          />
        </div>
        <p
          v-if="detail.is_reopened"
          class="text-sm font-medium text-amber-800"
        >
          This request has been reopened.
        </p>
        <section>
          <h3 class="mb-2 text-sm font-semibold text-slate-900">Description</h3>
          <p class="whitespace-pre-wrap break-words text-sm text-slate-600">
            {{ detail.description || 'No description provided.' }}
          </p>
        </section>
        <section>
          <h3 class="mb-3 text-sm font-semibold text-slate-900">
            Stage timeline
          </h3>
          <RequestTimeline
            v-if="detailStages.length"
            :stages="detailStages"
          />
          <p
            v-else
            class="text-sm text-slate-500"
          >
            No stages recorded.
          </p>
        </section>
        <section>
          <h3 class="mb-3 text-sm font-semibold text-slate-900">Attachments</h3>
          <DocumentViewer
            :key="detail.id"
            :attachments="detailAttachments"
          />
        </section>
        <section>
          <h3 class="mb-3 text-sm font-semibold text-slate-900">
            Status history
          </h3>
          <AdminAuditTrail
            v-if="detail.status_history?.length"
            :rows="[...detail.status_history].reverse()"
          />
          <p
            v-else
            class="text-sm text-slate-500"
          >
            No status changes recorded.
          </p>
        </section>
        <p
          v-if="formError"
          role="alert"
          class="rounded-lg bg-red-50 p-3 text-sm text-red-800"
        >
          {{ formError }}
        </p>
      </div>
      <template #footer
        ><button
          class="btn-secondary"
          :disabled="pending"
          @click="closeDetail"
        >
          Close</button
        ><button
          v-if="detail?.status === 'rejected'"
          class="btn-primary"
          :disabled="pending"
          @click="reopen"
        >
          {{ pending ? 'Reopening…' : 'Reopen request' }}
        </button></template
      >
    </BaseModal>

    <BaseModal
      :open="referenceModal"
      :title="`${refForm.id ? 'Edit' : 'Create'} ${singularTabLabel(activeTab)}`"
      size="lg"
      :busy="pending"
      @close="closeReference"
    >
      <form
        id="reference-form"
        class="space-y-4"
        @submit.prevent="saveReference"
      >
        <fieldset
          :disabled="pending"
          class="space-y-4"
        >
          <label class="block text-sm font-medium text-slate-700"
            >Name<input
              v-model="refForm.name"
              required
              class="input-field mt-1"
          /></label>
          <div class="grid gap-4 sm:grid-cols-2">
            <label
              v-if="activeTab !== 'request-types'"
              class="block text-sm font-medium text-slate-700"
              >Code<input
                v-model="refForm.code"
                required
                class="input-field mt-1" /></label
            ><label
              v-if="activeTab === 'faculties'"
              class="block text-sm font-medium text-slate-700"
              >Matricule prefix<input
                v-model="refForm.matricule_prefix"
                required
                class="input-field mt-1"
            /></label>
          </div>
          <template v-if="activeTab === 'departments'"
            ><label class="block text-sm font-medium text-slate-700"
              >Faculty<select
                v-model.number="refForm.faculty_id"
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
                  v-for="faculty in faculties"
                  :key="faculty.id"
                  :value="faculty.id"
                >
                  {{ faculty.name }}
                </option>
              </select></label
            ><label class="block text-sm font-medium text-slate-700"
              >Department type<select
                v-model="refForm.type"
                class="input-field mt-1"
              >
                <option value="academic">Academic</option>
                <option value="records">Records</option>
                <option value="admin">Administration</option>
              </select></label
            ></template
          >
          <template v-if="activeTab === 'programmes'"
            ><label class="block text-sm font-medium text-slate-700"
              >Department<select
                v-model.number="refForm.department_id"
                required
                class="input-field mt-1"
              >
                <option
                  :value="0"
                  disabled
                >
                  Choose a department
                </option>
                <option
                  v-for="department in departments"
                  :key="department.id"
                  :value="department.id"
                >
                  {{ department.name }}
                </option>
              </select></label
            ><label class="block text-sm font-medium text-slate-700"
              >Degree type<select
                v-model="refForm.degree_type"
                class="input-field mt-1"
              >
                <option
                  v-for="degree in degrees"
                  :key="degree"
                >
                  {{ degree }}
                </option>
              </select></label
            ></template
          >
          <template v-if="activeTab === 'request-types'"
            ><label class="block text-sm font-medium text-slate-700"
              >Description<textarea
                v-model="refForm.description"
                class="input-field mt-1"
                rows="3"
              />
            </label>
            <div>
              <h3 class="text-sm font-semibold text-slate-900">
                Routing sequence
              </h3>
              <p class="mt-1 text-xs text-slate-500">
                Arrange the departments in the order a request should follow.
              </p>
            </div>
            <div
              v-for="(_step, index) in refForm.default_department_sequence"
              :key="index"
              class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3"
            >
              <label class="block text-xs font-medium text-slate-600"
                >Step {{ index + 1
                }}<select
                  v-model="refForm.default_department_sequence[index]"
                  class="input-field mt-1"
                >
                  <option value="STUDENT_DEPARTMENT">Student department</option>
                  <option value="FACULTY_RECORDS">Faculty records</option>
                  <option
                    v-for="department in departments"
                    :key="department.id"
                    :value="department.id"
                  >
                    {{ department.name }}
                  </option>
                </select></label
              >
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  class="btn-secondary text-xs"
                  :disabled="index === 0"
                  :aria-label="`Move step ${index + 1} up`"
                  @click="moveStep(index, -1)"
                >
                  Move up</button
                ><button
                  type="button"
                  class="btn-secondary text-xs"
                  :disabled="
                    index === refForm.default_department_sequence.length - 1
                  "
                  :aria-label="`Move step ${index + 1} down`"
                  @click="moveStep(index, 1)"
                >
                  Move down</button
                ><button
                  type="button"
                  class="btn-secondary text-xs text-red-700"
                  :aria-label="`Remove step ${index + 1}`"
                  @click="refForm.default_department_sequence.splice(index, 1)"
                >
                  Remove
                </button>
              </div>
            </div>
            <button
              type="button"
              class="btn-secondary"
              @click="
                refForm.default_department_sequence.push('STUDENT_DEPARTMENT')
              "
            >
              Add step
            </button></template
          >
        </fieldset>
        <p
          v-if="formError"
          role="alert"
          class="rounded-lg bg-red-50 p-3 text-sm text-red-800"
        >
          {{ formError }}
        </p>
      </form>
      <template #footer
        ><button
          class="btn-secondary"
          :disabled="pending"
          @click="closeReference"
        >
          Cancel</button
        ><button
          type="submit"
          form="reference-form"
          class="btn-primary"
          :disabled="pending"
        >
          {{ pending ? 'Saving…' : 'Save' }}
        </button></template
      >
    </BaseModal>
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
      :open="!!deleteTarget"
      title="Delete record"
      size="sm"
      :busy="pending"
      @close="closeDelete"
    >
      <p class="text-sm text-slate-600">
        Delete
        <strong class="break-words text-slate-900">{{
          deleteTarget?.name
        }}</strong
        >? This cannot be undone. Records used by other parts of CampusDesk
        cannot be deleted.
      </p>
      <p
        v-if="formError"
        role="alert"
        class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800"
      >
        {{ formError }}
      </p>
      <template #footer
        ><button
          class="btn-secondary"
          :disabled="pending"
          @click="closeDelete"
        >
          Cancel</button
        ><button
          class="btn-primary !bg-red-700 hover:!bg-red-800"
          :disabled="pending"
          @click="remove"
        >
          {{ pending ? 'Deleting…' : 'Delete record' }}
        </button></template
      >
    </BaseModal>
  </div>
</template>
<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import {
  adminRequest,
  adminStats,
  createAdmin,
  deleteAdmin,
  listAdmin,
  setAdminLevel,
  updateAdmin
} from '@/services/admin'
import type {
  AdminRequest,
  AdminUser,
  AuditRow,
  Department,
  Faculty,
  Page,
  Programme,
  RequestType,
  Stats
} from '@/services/admin'
import { reopenRequest } from '@/services/requests'
import type {
  Attachment,
  RequestStage,
  RequestStatus,
  StageStatus
} from '@/types'
import { requestStatusLabel } from '@/types'
import StatusBadge from './StatusBadge.vue'
import RequestTimeline from './RequestTimeline.vue'
import DocumentViewer from './DocumentViewer.vue'
import BaseModal from './ui/BaseModal.vue'
import EmptyState from './ui/EmptyState.vue'
import SkeletonLoader from './ui/SkeletonLoader.vue'
import PageHeader from './ui/PageHeader.vue'
import AdminPagination from './admin/AdminPagination.vue'
import AdminAuditTrail from './admin/AdminAuditTrail.vue'
import AdminUserFields, { type UserForm } from './admin/AdminUserFields.vue'

const emptyPage = <T,>(): Page<T> => ({
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
  links: { next: null, prev: null }
})
const tabs = [
  'faculties',
  'departments',
  'programmes',
  'request-types'
] as const
type Tab = (typeof tabs)[number]
type Reference = {
  id: number
  name: string
  code?: string
  matricule_prefix?: string
  type?: string
  degree_type?: string
  default_department_sequence?: (number | string)[]
  description?: string | null
  faculty_id?: number
  department_id?: number
}
const tabLabels: Record<Tab, string> = {
  faculties: 'Faculties',
  departments: 'Departments',
  programmes: 'Programmes',
  'request-types': 'Request types'
}
const singularLabels: Record<Tab, string> = {
  faculties: 'faculty',
  departments: 'department',
  programmes: 'programme',
  'request-types': 'request type'
}
const tabLabel = (tab: Tab) => tabLabels[tab]
const singularTabLabel = (tab: Tab) => singularLabels[tab]
const sections = [
  { id: 'admin-requests', label: 'Requests' },
  { id: 'admin-users', label: 'People & access' },
  { id: 'admin-organisation', label: 'Organisation' },
  { id: 'admin-history', label: 'Status history' }
]
const statuses: RequestStatus[] = [
  'draft',
  'pending',
  'in_review',
  'forwarded',
  'ready',
  'collected',
  'rejected'
]
const degrees = ['BACHELOR', 'CERTIFICATE', 'MASTER', 'PHD']
const error = ref(''),
  formError = ref(''),
  success = ref(''),
  loading = ref(true),
  pending = ref(false)
const statsLoading = ref(true),
  requestsLoading = ref(true),
  usersLoading = ref(true),
  referencesLoading = ref(true),
  auditLoading = ref(true),
  choicesReady = ref(false)
const collectionErrors = reactive({
  requests: '',
  users: '',
  references: '',
  audit: ''
})
const stats = ref<Stats | null>(null),
  faculties = ref<Faculty[]>([]),
  departments = ref<Department[]>([]),
  programmes = ref<Programme[]>([]),
  requestTypes = ref<RequestType[]>([])
const requests = ref<Page<AdminRequest>>(emptyPage()),
  users = ref<Page<AdminUser>>(emptyPage()),
  references = ref<Page<Reference>>(emptyPage()),
  audit = ref<Page<AuditRow>>(emptyPage())
const requestPage = ref(1),
  userPage = ref(1),
  refPage = ref(1),
  auditPage = ref(1),
  activeTab = ref<Tab>('faculties')
const userSearch = ref(''),
  userRole = ref(''),
  refSearch = ref('')
const requestFilters = reactive({
  search: '',
  faculty_id: 0,
  department_id: 0,
  request_type_id: 0,
  status: '',
  reopened: false,
  date_from: '',
  date_to: ''
})
const auditFilters = reactive({
  request_id: '',
  actor_id: '',
  new_status: '',
  date_from: '',
  date_to: ''
})
const detail = ref<AdminRequest | null>(null),
  detailOpen = ref(false),
  detailLoading = ref(false),
  detailId = ref(0),
  detailError = ref('')
const referenceModal = ref(false),
  userModal = ref(false)
const deleteTarget = ref<{ kind: string; id: number; name: string } | null>(
  null
)
const activeRequestFilterCount = computed(
  () =>
    Object.values(requestFilters).filter(
      (value) => value !== '' && value !== 0 && value !== false
    ).length
)
const hasAuditFilters = computed(() =>
  Object.values(auditFilters).some(Boolean)
)
const detailAttachments = computed<Attachment[]>(() =>
  (detail.value?.attachments ?? []).map((file) => ({ ...file, file_path: '' }))
)
const detailStages = computed<RequestStage[]>(() =>
  (detail.value?.stages ?? []).map((stage) => ({
    id: stage.id,
    request_id: detail.value!.id,
    department_name: stage.department?.name ?? 'Unknown department',
    sequence_order: stage.sequence_order,
    status: stage.status as StageStatus,
    handled_by: stage.handled_by ? `#${stage.handled_by}` : null,
    staff_note: stage.staff_note ?? null,
    updated_at: stage.updated_at ?? null
  }))
)
const refForm = reactive({
  id: 0,
  name: '',
  code: '',
  matricule_prefix: '',
  faculty_id: 0,
  department_id: 0,
  type: 'academic',
  degree_type: 'BACHELOR',
  description: '',
  default_department_sequence: [] as (number | string)[]
})
const emptyUserForm = (): UserForm => ({
  id: 0,
  role: 'student',
  name: '',
  email: '',
  password: '',
  matricule: '',
  faculty_id: 0,
  department_id: 0,
  programme_id: 0,
  level: '100',
  staff_id: '',
  department_ids: [],
  primary_department_id: 0
})
const userForm = ref<UserForm>(emptyUserForm())
const date = (value: string) =>
  value
    ? new Date(value).toLocaleString('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short'
      })
    : 'Not recorded'
function routeLabel(step: number | string) {
  return step === 'STUDENT_DEPARTMENT'
    ? 'Student department'
    : step === 'FACULTY_RECORDS'
      ? 'Faculty records'
      : (departments.value.find((department) => department.id === Number(step))
          ?.name ?? `Department #${step}`)
}
function clearRequestFilters() {
  Object.assign(requestFilters, {
    search: '',
    faculty_id: 0,
    department_id: 0,
    request_type_id: 0,
    status: '',
    reopened: false,
    date_from: '',
    date_to: ''
  })
}
function clearAuditFilters() {
  Object.assign(auditFilters, {
    request_id: '',
    actor_id: '',
    new_status: '',
    date_from: '',
    date_to: ''
  })
}
function message(e: unknown) {
  const response = (
    e as {
      response?: {
        data?: { message?: string; errors?: Record<string, string[]> }
      }
    }
  )?.response?.data
  return response?.errors
    ? Object.values(response.errors).flat().join(' ')
    : (response?.message ?? 'The action failed. Please try again.')
}
let referenceVersion = 0,
  requestVersion = 0,
  userVersion = 0,
  auditVersion = 0,
  detailVersion = 0
async function loadReferences() {
  const version = ++referenceVersion
  referencesLoading.value = true
  collectionErrors.references = ''
  try {
    const result = await listAdmin<Reference>(activeTab.value, {
      page: refPage.value,
      search: refSearch.value
    })
    if (version === referenceVersion) references.value = result
  } catch (e) {
    if (version === referenceVersion) collectionErrors.references = message(e)
  } finally {
    if (version === referenceVersion) referencesLoading.value = false
  }
}
async function loadRequests() {
  const version = ++requestVersion
  requestsLoading.value = true
  collectionErrors.requests = ''
  try {
    const result = await listAdmin<AdminRequest>('requests', {
      ...requestFilters,
      reopened: requestFilters.reopened ? 1 : undefined,
      page: requestPage.value
    })
    if (version === requestVersion) requests.value = result
  } catch (e) {
    if (version === requestVersion) collectionErrors.requests = message(e)
  } finally {
    if (version === requestVersion) requestsLoading.value = false
  }
}
async function loadUsers() {
  const version = ++userVersion
  usersLoading.value = true
  collectionErrors.users = ''
  try {
    const result = await listAdmin<AdminUser>('users', {
      page: userPage.value,
      search: userSearch.value,
      role: userRole.value
    })
    if (version === userVersion) users.value = result
  } catch (e) {
    if (version === userVersion) collectionErrors.users = message(e)
  } finally {
    if (version === userVersion) usersLoading.value = false
  }
}
async function loadAudit() {
  const version = ++auditVersion
  auditLoading.value = true
  collectionErrors.audit = ''
  try {
    const result = await listAdmin<AuditRow>('audit-log', {
      ...auditFilters,
      page: auditPage.value
    })
    if (version === auditVersion) audit.value = result
  } catch (e) {
    if (version === auditVersion) collectionErrors.audit = message(e)
  } finally {
    if (version === auditVersion) auditLoading.value = false
  }
}
async function loadStats() {
  statsLoading.value = true
  try {
    stats.value = await adminStats()
  } catch (e) {
    error.value = message(e)
  } finally {
    statsLoading.value = false
  }
}
async function allChoices<T>(kind: string): Promise<T[]> {
  const result: T[] = []
  let page = 1
  while (true) {
    const batch = await listAdmin<T>(kind, { page, per_page: 100 })
    result.push(...batch.data)
    if (page >= batch.meta.last_page) return result
    page++
  }
}
async function loadChoices() {
  choicesReady.value = false
  try {
    // Keep startup reads ordered for PHP's single-worker development server.
    faculties.value = await allChoices<Faculty>('faculties')
    departments.value = await allChoices<Department>('departments')
    programmes.value = await allChoices<Programme>('programmes')
    requestTypes.value = await allChoices<RequestType>('request-types')
    choicesReady.value = true
  } catch (e) {
    error.value = `Unable to load form choices. ${message(e)} Refresh the dashboard to retry.`
  }
}
let initializing = false
async function initialize() {
  if (initializing) return
  initializing = true
  loading.value = true
  error.value = ''
  await loadStats()
  await loadChoices()
  await loadRequests()
  await loadUsers()
  await loadReferences()
  await loadAudit()
  loading.value = false
  initializing = false
}
onMounted(initialize)
watch(
  [requestPage, () => JSON.stringify(requestFilters)],
  ([, filters], [, oldFilters]) => {
    if (filters !== oldFilters && requestPage.value !== 1) {
      requestPage.value = 1
      return
    }
    void loadRequests()
  }
)
watch(
  [userPage, userSearch, userRole],
  ([, search, role], [, oldSearch, oldRole]) => {
    if ((search !== oldSearch || role !== oldRole) && userPage.value !== 1) {
      userPage.value = 1
      return
    }
    void loadUsers()
  }
)
watch(
  [refPage, refSearch, activeTab],
  ([, search, tab], [, oldSearch, oldTab]) => {
    if ((search !== oldSearch || tab !== oldTab) && refPage.value !== 1) {
      refPage.value = 1
      return
    }
    void loadReferences()
  }
)
watch(
  [auditPage, () => JSON.stringify(auditFilters)],
  ([, filters], [, oldFilters]) => {
    if (filters !== oldFilters && auditPage.value !== 1) {
      auditPage.value = 1
      return
    }
    void loadAudit()
  }
)
async function openRequest(id: number) {
  const version = ++detailVersion
  detailId.value = id
  detailOpen.value = true
  detailLoading.value = true
  detailError.value = ''
  formError.value = ''
  detail.value = null
  try {
    const result = await adminRequest(id)
    if (version === detailVersion) detail.value = result
  } catch (e) {
    if (version === detailVersion) detailError.value = message(e)
  } finally {
    if (version === detailVersion) detailLoading.value = false
  }
}
function closeDetail() {
  if (!pending.value) {
    detailOpen.value = false
    detailVersion++
    detail.value = null
  }
}
async function reopen() {
  if (!detail.value || pending.value) return
  const id = detail.value.id
  pending.value = true
  formError.value = ''
  success.value = ''
  try {
    await reopenRequest(id)
    success.value = `Request #${id} reopened.`
    await openRequest(id)
    await loadRequests()
    await loadStats()
    await loadAudit()
  } catch (e) {
    formError.value = message(e)
  } finally {
    pending.value = false
  }
}
function openReference(row?: Reference) {
  formError.value = ''
  Object.assign(refForm, {
    id: 0,
    name: '',
    code: '',
    matricule_prefix: '',
    faculty_id: 0,
    department_id: 0,
    type: 'academic',
    degree_type: 'BACHELOR',
    description: '',
    default_department_sequence: []
  })
  if (row)
    Object.assign(refForm, row, {
      default_department_sequence: [...(row.default_department_sequence ?? [])]
    })
  referenceModal.value = true
}
function closeReference() {
  if (!pending.value) referenceModal.value = false
}
function moveStep(index: number, delta: number) {
  const next = index + delta
  if (next < 0 || next >= refForm.default_department_sequence.length) return
  ;[
    refForm.default_department_sequence[index],
    refForm.default_department_sequence[next]
  ] = [
    refForm.default_department_sequence[next]!,
    refForm.default_department_sequence[index]!
  ]
}
async function saveReference() {
  if (pending.value) return
  formError.value = ''
  if (!refForm.name.trim()) {
    formError.value = 'Enter a name.'
    return
  }
  if (
    (activeTab.value === 'departments' && !refForm.faculty_id) ||
    (activeTab.value === 'programmes' && !refForm.department_id)
  ) {
    formError.value = 'Choose the academic unit this record belongs to.'
    return
  }
  if (
    activeTab.value === 'request-types' &&
    !refForm.default_department_sequence.length
  ) {
    formError.value = 'Add at least one routing step.'
    return
  }
  const body =
    activeTab.value === 'faculties'
      ? {
          name: refForm.name,
          code: refForm.code,
          matricule_prefix: refForm.matricule_prefix
        }
      : activeTab.value === 'departments'
        ? {
            name: refForm.name,
            code: refForm.code,
            faculty_id: refForm.faculty_id,
            type: refForm.type
          }
        : activeTab.value === 'programmes'
          ? {
              name: refForm.name,
              code: refForm.code,
              department_id: refForm.department_id,
              degree_type: refForm.degree_type
            }
          : {
              name: refForm.name,
              description: refForm.description,
              default_department_sequence: refForm.default_department_sequence
            }
  pending.value = true
  success.value = ''
  try {
    if (refForm.id) await updateAdmin(activeTab.value, refForm.id, body)
    else await createAdmin(activeTab.value, body)
    success.value = 'Record saved.'
    referenceModal.value = false
    await loadReferences()
    await loadChoices()
  } catch (e) {
    formError.value = message(e)
  } finally {
    pending.value = false
  }
}
function openUser(user?: AdminUser) {
  formError.value = ''
  userForm.value = emptyUserForm()
  if (user)
    Object.assign(userForm.value, {
      id: user.id,
      role: user.role,
      name: user.name,
      email: user.email,
      password: '',
      matricule: user.student_profile?.matricule ?? '',
      faculty_id: user.student_profile?.faculty_id ?? 0,
      department_id: user.student_profile?.department_id ?? 0,
      programme_id: user.student_profile?.programme_id ?? 0,
      level: user.student_profile?.level ?? '100',
      staff_id: user.staff_profile?.staff_id ?? '',
      department_ids:
        user.staff_profile?.departments.map((department) => department.id) ??
        [],
      primary_department_id:
        user.staff_profile?.departments.find(
          (department) => department.is_primary
        )?.id ?? 0
    })
  userModal.value = true
}
function closeUser() {
  if (!pending.value) userModal.value = false
}
async function saveUser() {
  if (pending.value) return
  formError.value = ''
  const form = userForm.value
  if (!form.name.trim() || !form.email.trim()) {
    formError.value = 'Name and email are required.'
    return
  }
  if ((!form.id || form.password) && form.password.length < 8) {
    formError.value = 'Use a password with at least 8 characters.'
    return
  }
  if (
    form.role === 'student' &&
    (!form.matricule.trim() ||
      !form.faculty_id ||
      !form.department_id ||
      !form.programme_id)
  ) {
    formError.value =
      'Complete the matricule, faculty, department and programme.'
    return
  }
  if (
    form.role === 'staff' &&
    (!form.staff_id.trim() ||
      !form.department_ids.length ||
      !form.department_ids.includes(form.primary_department_id))
  ) {
    formError.value =
      'Enter a staff ID and choose department memberships with one primary department.'
    return
  }
  const body: Record<string, unknown> = { name: form.name, email: form.email }
  if (!form.id) body.role = form.role
  if (form.password) body.password = form.password
  if (form.role === 'student')
    Object.assign(body, {
      matricule: form.matricule,
      faculty_id: form.faculty_id,
      department_id: form.department_id,
      programme_id: form.programme_id,
      level: form.level
    })
  else
    Object.assign(body, {
      staff_id: form.staff_id,
      department_ids: form.department_ids,
      primary_department_id: form.primary_department_id
    })
  pending.value = true
  success.value = ''
  try {
    if (form.id) await updateAdmin('users', form.id, body)
    else await createAdmin('users', body)
    userModal.value = false
    success.value = 'User saved.'
    await loadUsers()
  } catch (e) {
    formError.value = message(e)
  } finally {
    pending.value = false
  }
}
async function changeLevel(user: AdminUser, level: string) {
  if (pending.value) return
  pending.value = true
  error.value = ''
  success.value = ''
  try {
    await setAdminLevel(
      user.id,
      (level || null) as 'dept_admin' | 'super_admin' | null
    )
    success.value = `Admin access updated for ${user.name}.`
  } catch (e) {
    error.value = message(e)
  } finally {
    await loadUsers()
    pending.value = false
  }
}
function askRemove(kind: string, id: number, name: string) {
  formError.value = ''
  deleteTarget.value = { kind, id, name }
}
function closeDelete() {
  if (!pending.value) deleteTarget.value = null
}
async function remove() {
  if (pending.value || !deleteTarget.value) return
  const { kind, id } = deleteTarget.value
  pending.value = true
  formError.value = ''
  success.value = ''
  try {
    await deleteAdmin(kind, id)
    deleteTarget.value = null
    success.value = 'Record deleted.'
    if (kind === 'users') await loadUsers()
    else {
      await loadReferences()
      await loadChoices()
    }
  } catch (e) {
    formError.value = message(e)
  } finally {
    pending.value = false
  }
}
</script>
