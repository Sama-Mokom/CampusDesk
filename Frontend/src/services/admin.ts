import api from './api'

export interface Page<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
  links: { next: string | null; prev: string | null }
}

export interface Faculty { id: number; name: string; code: string; matricule_prefix: string }
export interface Department { id: number; faculty_id: number; name: string; code: string; type: 'academic' | 'records' | 'admin' }
export interface Programme { id: number; department_id: number; faculty_id: number; name: string; code: string; degree_type: string }
export interface RequestType { id: number; name: string; description: string | null; default_department_sequence: (number | string)[] }
export interface AdminUser {
  id: number; name: string; email: string; role: 'student' | 'staff'
  student_profile: { matricule: string; faculty_id: number; department_id: number; programme_id: number; level: string } | null
  staff_profile: { staff_id: string; admin_level: 'dept_admin' | 'super_admin' | null; departments: { id: number; name: string; is_primary: boolean }[] } | null
}
export interface AdminRequest {
  id: number; status: string; description: string | null; is_reopened: boolean; created_at: string
  request_type: { id: number; name: string }; student: { id: number; name: string; student_profile?: { matricule: string } }
  stages?: { id: number; sequence_order: number; status: string; department: { name: string } | null; handled_by?: number | null; staff_note?: string | null; updated_at?: string | null }[]
  attachments?: { id: number; original_name: string; mime_type?: string }[]; status_history?: AuditRow[]
}
export interface AuditRow { id: number; request_id: number; request_stage_id: number | null; old_status: string | null; new_status: string; changed_by: { id: number; name: string } | null; note: string | null; changed_at: string }
export interface Stats { total: number; requests_today: number; by_status: Record<string, number>; avg_resolution_hours: number | null; recent_activity: AuditRow[] }

const params = (input: Record<string, unknown>) => Object.fromEntries(Object.entries(input).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== 0))
export async function listAdmin<T>(kind: string, query: Record<string, unknown> = {}): Promise<Page<T>> {
  return (await api.get(`/admin/${kind}`, { params: params(query) })).data
}
export async function createAdmin<T>(kind: string, body: unknown): Promise<T> { return (await api.post(`/admin/${kind}`, body)).data.data }
export async function updateAdmin<T>(kind: string, id: number, body: unknown): Promise<T> { return (await api.patch(`/admin/${kind}/${id}`, body)).data.data }
export async function deleteAdmin(kind: string, id: number): Promise<void> { await api.delete(`/admin/${kind}/${id}`) }
export async function adminStats(): Promise<Stats> { return (await api.get('/admin/stats')).data.data }
export async function adminRequest(id: number): Promise<AdminRequest> { return (await api.get(`/admin/requests/${id}`)).data.data }
export async function setAdminLevel(id: number, level: 'dept_admin' | 'super_admin' | null): Promise<AdminUser> { return (await api.patch(`/admin/users/${id}/admin-level`, { admin_level: level })).data.data }
