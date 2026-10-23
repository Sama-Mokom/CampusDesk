<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Super administration"
      :title="tabLabel(activeTab)"
      description="Maintain academic structures and request routing."
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
          {{ tabLabel(activeTab) }} records
        </h2>
        <p class="mt-1 text-sm text-slate-500">
          Maintain academic structures and request routing.
        </p>
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
            <div
              v-if="activeTab === 'request-types' && row.tiers?.length"
              class="mt-2 flex flex-wrap gap-1.5"
            >
              <span
                v-for="tier in row.tiers"
                :key="tier.id || tier.code"
                class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                :class="{
                  'bg-purple-50 text-purple-700 ring-1 ring-purple-600/20': tier.priority === 3,
                  'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20': tier.priority === 2,
                  'bg-slate-100 text-slate-700': tier.priority === 1,
                  'opacity-50 line-through': !tier.is_active
                }"
              >
                <span>{{ tier.name }}:</span>
                <span class="font-semibold">{{ Number(tier.fee_amount).toLocaleString('en-US') }} {{ tier.currency || 'XAF' }}</span>
                <span class="text-slate-400">({{ tier.turnaround_days }}d)</span>
              </span>
            </div>
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
            </button>

            <!-- Processing Tiers Section -->
            <div class="space-y-3 pt-3 border-t border-slate-200">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-sm font-semibold text-slate-900">
                    Processing tiers & fees
                  </h3>
                  <p class="mt-0.5 text-xs text-slate-500">
                    Configure service levels, turnaround times, queue priorities, and processing fees.
                  </p>
                </div>
                <button
                  type="button"
                  class="btn-secondary text-xs"
                  @click="addTier"
                >
                  + Add tier
                </button>
              </div>

              <div
                v-if="!refForm.tiers.length"
                class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-500"
              >
                No tiers configured. A default standard tier will apply if none are defined.
              </div>

              <div
                v-for="(tier, tIndex) in refForm.tiers"
                :key="tIndex"
                class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3"
              >
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-bold text-slate-700">Tier #{{ tIndex + 1 }}</span>
                  <div class="flex items-center gap-2">
                    <label class="flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
                      <input
                        v-model="tier.is_active"
                        type="checkbox"
                        class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                      />
                      Active
                    </label>
                    <button
                      type="button"
                      class="btn-secondary text-xs"
                      :disabled="tIndex === 0"
                      :aria-label="`Move tier ${tIndex + 1} up`"
                      @click="moveTier(tIndex, -1)"
                    >
                      ↑
                    </button>
                    <button
                      type="button"
                      class="btn-secondary text-xs"
                      :disabled="tIndex === refForm.tiers.length - 1"
                      :aria-label="`Move tier ${tIndex + 1} down`"
                      @click="moveTier(tIndex, 1)"
                    >
                      ↓
                    </button>
                    <button
                      type="button"
                      class="btn-secondary text-xs text-red-700"
                      :aria-label="`Remove tier ${tIndex + 1}`"
                      @click="removeTier(tIndex)"
                    >
                      Remove
                    </button>
                  </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                  <label class="block text-xs font-medium text-slate-700">
                    Name
                    <input
                      v-model="tier.name"
                      placeholder="e.g. Fast Mode"
                      required
                      class="input-field mt-1"
                    />
                  </label>
                  <label class="block text-xs font-medium text-slate-700">
                    Code
                    <input
                      v-model="tier.code"
                      placeholder="e.g. fast"
                      required
                      class="input-field mt-1"
                    />
                  </label>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                  <label class="block text-xs font-medium text-slate-700">
                    Fee (XAF)
                    <input
                      v-model.number="tier.fee_amount"
                      type="number"
                      min="100"
                      step="100"
                      required
                      class="input-field mt-1"
                    />
                  </label>
                  <label class="block text-xs font-medium text-slate-700">
                    Turnaround (days)
                    <input
                      v-model.number="tier.turnaround_days"
                      type="number"
                      min="1"
                      required
                      class="input-field mt-1"
                    />
                  </label>
                  <label class="block text-xs font-medium text-slate-700">
                    Priority
                    <select
                      v-model.number="tier.priority"
                      class="input-field mt-1"
                    >
                      <option :value="1">Priority 1 (Normal)</option>
                      <option :value="2">Priority 2 (Fast)</option>
                      <option :value="3">Priority 3 (Super Fast / Urgent)</option>
                    </select>
                  </label>
                </div>

                <label class="block text-xs font-medium text-slate-700">
                  Description (optional)
                  <input
                    v-model="tier.description"
                    placeholder="Brief description for students"
                    class="input-field mt-1"
                  />
                </label>
              </div>
            </div>
          </template>
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
import AdminDeleteDialog from '@/components/admin/AdminDeleteDialog.vue'

import { useAdminReferences } from '@/composables/admin/useAdminReferences'
import type { ReferenceKind } from '@/composables/admin/shared'
const props = defineProps<{ kind: ReferenceKind }>()
const {
  deleteTarget,
  closeDelete,
  remove,
  references,
  refPage,
  referencesLoading,
  collectionErrors,
  refSearch,
  loadReferences,
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
  activeTab,
  tabLabel,
  singularTabLabel,
  degrees,
  routeLabel,
  referenceModal,
  refForm,
  openReference,
  closeReference,
  saveReference,
  moveStep,
  addTier,
  removeTier,
  moveTier,
  askRemove
} = useAdminReferences(props.kind)
</script>
