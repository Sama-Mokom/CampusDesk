import api from './api'
import type { StudentLevel, StageStatus } from '../types'

export interface DepartmentAdminStaff { id: number; name: string; staff_id: string }
export interface DepartmentAdminStage {
  id: number; request_id: number; department_name: string; sequence_order: number
  status: StageStatus; handled_by: number | null; handler: { id: number; name: string } | null
  staff_note: string | null; updated_at: string; is_claimable: boolean; blocked_reason: string | null
  request: { id: number; description: string; request_type: string; created_at: string; student_name: string; student_matricule: string; student_level: StudentLevel }
  reassignments: { id: number; from_user: string | null; to_user: string; reassigned_by: string | null; created_at: string }[]
}

export interface DepartmentAdminOverview {
  stages: DepartmentAdminStage[]
  department: { id: number; name: string }
  stats: { total: number; unclaimed: number; claimable: number; blocked: number; in_review: number; completed: number }
  staff: DepartmentAdminStaff[]
}

export async function fetchDepartmentAdminRequests(): Promise<DepartmentAdminOverview> {
  const response = await api.get('/dept-admin/requests')
  return { stages: response.data.data ?? [], ...response.data.meta }
}

export async function reassignStage(stageId: number, handledBy: number): Promise<DepartmentAdminStage> {
  const response = await api.patch(`/dept-admin/stages/${stageId}/reassign`, { handled_by: handledBy })
  return response.data.data ?? response.data
}
