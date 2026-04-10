export type DegreeType = 'BSc' | 'BEng' | 'MEng' | 'MSc' | 'PhD'

export type StudentLevel = 'L100' | 'L200' | 'L300' | 'L400' | 'L500' | 'L600'

export type StudentProfileStatus = 'active' | 'on_leave' | 'graduated' | 'suspended'

export type UserRole = 'student' | 'staff'

export type StaffAdminLevel = 'dept_admin' | 'super_admin' | null

export type RequestStatus =
  | 'draft'
  | 'pending'
  | 'in_review'
  | 'forwarded'
  | 'ready'
  | 'collected'
  | 'rejected'

export type StageStatus = 'pending' | 'in_review' | 'approved' | 'rejected'

export interface Faculty {
  id: number
  name: string
  code: string
  created_at: string
}

export interface Department {
  id: number
  faculty_id: number
  name: string
  code: string
  created_at: string
}

export interface Programme {
  id: number
  faculty_id: number
  name: string
  code: string
  degree_type: DegreeType
}

export interface DepartmentAssignment {
  id: number
  name: string
  code: string
  is_primary: boolean
}

export interface StudentProfile {
  matricule: string
  level: StudentLevel
  status: StudentProfileStatus
  faculty: Faculty
  department: Department
  programme: Programme
}

export interface StaffProfile {
  staff_id: string
  admin_level: StaffAdminLevel
  departments: DepartmentAssignment[]
}

export interface User {
  id: number
  name: string
  email: string
  password: string
  role: UserRole
  created_at: string
  student_profile?: StudentProfile
  staff_profile?: StaffProfile
}

export interface UserSummary {
  id: number
  name: string
}

export interface RequestTypeEntity {
  id: number
  name: string
  description: string
  default_department_sequence: number[]
}

export interface Attachment {
  id: number
  request_id: number
  file_path: string
  original_name: string
  mime_type: string
}

export interface RequestStage {
  id: number
  department: { id: number; name: string }
  sequence_order: number
  status: StageStatus
  handled_by: UserSummary | null
  staff_note: string | null
  updated_at: string | null
}

export interface StatusHistoryEntry {
  id: number
  old_status: RequestStatus | null
  new_status: RequestStatus
  changed_by: UserSummary
  note: string | null
  changed_at: string
}

export interface Request {
  id: number
  student_id: number
  request_type: RequestTypeEntity
  description: string
  status: RequestStatus
  is_reopened: boolean
  created_at: string
  attachments: Attachment[]
  stages: RequestStage[]
  status_history: StatusHistoryEntry[]
}

export interface Notification {
  id: number
  user_id: number
  type: string
  message: string
  read: boolean
  read_at: string | null
  created_at: string
}

/** Row for staff queue: request + one stage context */
export interface StageQueueItem {
  request: Request
  stage: RequestStage
}

export function requestStatusLabel(status: RequestStatus): string {
  const map: Record<RequestStatus, string> = {
    draft: 'Draft',
    pending: 'Pending',
    in_review: 'In review',
    forwarded: 'Forwarded',
    ready: 'Ready',
    collected: 'Collected',
    rejected: 'Rejected'
  }
  return map[status] ?? status
}

export function stageStatusLabel(status: StageStatus): string {
  const map: Record<StageStatus, string> = {
    pending: 'Pending',
    in_review: 'In review',
    approved: 'Approved',
    rejected: 'Rejected'
  }
  return map[status] ?? status
}
