<template>
  <div class="space-y-6">
    <section class="card">
      <h2 class="text-xl font-semibold text-primary">Department oversight</h2>
      <p class="text-sm text-neutral-600 mt-1">{{ overview.department?.name ?? 'Loading department…' }}</p>
    </section>

    <p v-if="error" class="p-4 bg-red-100 text-red-700 rounded-lg">{{ error }}</p>
    <p v-if="success" class="p-4 bg-green-100 text-green-700 rounded-lg">{{ success }}</p>

    <section class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <div v-for="metric in metrics" :key="metric.label" class="card">
        <p class="text-sm text-neutral-600">{{ metric.label }}</p><p class="text-2xl font-bold text-primary">{{ metric.value }}</p>
      </div>
    </section>

    <section class="card overflow-x-auto">
      <div class="flex justify-between items-center mb-4"><h3 class="text-lg font-semibold text-primary">All department stages</h3><button class="btn-secondary text-sm" @click="load">Refresh</button></div>
      <p v-if="loading" class="text-neutral-500">Loading department work…</p>
      <p v-else-if="!overview.stages.length" class="text-neutral-500">No stages have passed through this department.</p>
      <table v-else class="w-full text-sm">
        <thead class="border-b text-left text-neutral-600"><tr><th class="p-2">Student / request</th><th class="p-2">Status</th><th class="p-2">Availability</th><th class="p-2">Handler</th><th class="p-2">Updated</th><th class="p-2">Action</th></tr></thead>
        <tbody><tr v-for="stage in overview.stages" :key="stage.id" class="border-b border-neutral-100">
          <td class="p-2"><p class="font-medium">{{ stage.request.student_name }}</p><p class="text-neutral-600">{{ stage.request.request_type }} · #{{ stage.request_id }}</p></td>
          <td class="p-2"><span class="badge">{{ stage.status.replace('_', ' ') }}</span></td>
          <td class="p-2"><span v-if="stage.is_claimable" class="text-green-700 font-medium">Claimable now</span><span v-else-if="stage.blocked_reason" class="text-amber-700" :title="stage.blocked_reason">Blocked: {{ stage.blocked_reason }}</span><span v-else class="text-neutral-400">Not applicable</span></td>
          <td class="p-2">{{ stage.handler?.name ?? 'Unclaimed' }}</td><td class="p-2">{{ formatDate(stage.updated_at) }}</td>
          <td class="p-2"><button v-if="stage.is_claimable" class="btn-primary text-xs" @click="pickUp(stage)">Pick up</button><button v-else-if="stage.status === 'in_review'" class="btn-secondary text-xs" @click="openReassign(stage)">Reassign</button><span v-else class="text-neutral-400">—</span></td>
        </tr></tbody>
      </table>
    </section>

    <div v-if="modal.stage" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="closeModal">
      <form class="bg-white rounded-lg p-6 w-full max-w-md space-y-4" @submit.prevent="submitReassign">
        <div><h3 class="text-lg font-semibold text-primary">Reassign active case</h3><p class="text-sm text-neutral-600">{{ modal.stage.request.request_type }} for {{ modal.stage.request.student_name }}</p></div>
        <label class="block text-sm font-medium">Assign to<select v-model.number="modal.recipientId" class="input-field mt-1" required><option :value="0" disabled>Select staff member</option><option v-for="staff in eligibleStaff" :key="staff.id" :value="staff.id">{{ staff.name }} ({{ staff.staff_id }})</option></select></label>
        <p v-if="modal.error" class="text-sm text-red-600">{{ modal.error }}</p>
        <div class="flex justify-end gap-2"><button type="button" class="btn-secondary" @click="closeModal">Cancel</button><button class="btn-primary" :disabled="modal.submitting">{{ modal.submitting ? 'Reassigning…' : 'Reassign' }}</button></div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { fetchDepartmentAdminRequests, reassignStage, type DepartmentAdminOverview, type DepartmentAdminStage } from '../services/deptAdmin'
import { claimStage } from '../services/stages'

const loading = ref(false); const error = ref(''); const success = ref('')
const overview = reactive<Partial<DepartmentAdminOverview>>({ stages: [], stats: { total: 0, unclaimed: 0, claimable: 0, blocked: 0, in_review: 0, completed: 0 }, staff: [] })
const modal = reactive({ stage: null as DepartmentAdminStage | null, recipientId: 0, submitting: false, error: '' })
const metrics = computed(() => [{ label: 'All stages', value: overview.stats?.total ?? 0 }, { label: 'Claimable now', value: overview.stats?.claimable ?? 0 }, { label: 'Blocked', value: overview.stats?.blocked ?? 0 }, { label: 'In review', value: overview.stats?.in_review ?? 0 }, { label: 'Completed', value: overview.stats?.completed ?? 0 }])
const eligibleStaff = computed(() => (overview.staff ?? []).filter(s => s.id !== modal.stage?.handled_by))
async function load() { loading.value = true; error.value = ''; try { Object.assign(overview, await fetchDepartmentAdminRequests()) } catch (e: any) { error.value = e.response?.data?.message ?? 'Failed to load department work.' } finally { loading.value = false } }
function openReassign(stage: DepartmentAdminStage) { modal.stage = stage; modal.recipientId = 0; modal.error = '' }
function closeModal() { modal.stage = null; modal.error = '' }
async function submitReassign() { if (!modal.stage || !modal.recipientId) return; modal.submitting = true; modal.error = ''; try { await reassignStage(modal.stage.id, modal.recipientId); success.value = 'Stage reassigned and the receiving staff member notified.'; closeModal(); await load() } catch (e: any) { modal.error = e.response?.data?.message ?? 'Unable to reassign this stage.' } finally { modal.submitting = false } }
async function pickUp(stage: DepartmentAdminStage) { error.value = ''; try { await claimStage(stage.request_id, stage.id); success.value = 'Stage claimed. You can process it through the standard staff workflow.'; await load() } catch (e: any) { error.value = e.response?.data?.message ?? 'Unable to claim this stage.' } }
function formatDate(value: string) { return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }
onMounted(load)
</script>
