export type DegreeType = 'BSc' | 'BEng' | 'MEng' | 'MSc' | 'PhD'

export type StudentLevel = '100' | '200' | '300' | '400' | '500' | '600'

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

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterCredentials {
  name: string
  email: string
  password: string
  password_confirmation: string
  matricule: string
  faculty_id: number
  department_id: number
  programme_id: number
  level: 'L100' | 'L200' | 'L300' | 'L400' | 'L500' | 'L600'
}

export interface AuthResponse {
  token: string
  user: User
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

export interface CreateRequestPayload {
  request_type_id: number;
  description: string;
  attachments?: File[]; // For handling file uploads if needed
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
  request_id: number
  department_name: string 
  sequence_order: number
  status: StageStatus
  handled_by: string | null
  staff_note: string | null
  updated_at: string | null
  request?: {
    id: number
    description: string
    request_type?: string
    created_at: string
    student_name?: string
    student_matricule?: string
    student_level?: StudentLevel
  }
}

export interface ResolveStagePayload {
  /**
   * The action to perform on this stage.
   */
  action: 'approve' | 'reject';
  
  /**
   * A staff comment/note documenting the decision.
   */
  staff_note: string;
  
  /**
   * Optional: If the overall request needs to transition to a new status 
   * (e.g. 'rejected' if rejecting, or 'ready' if this is the final stage).
   */
  request_status?: RequestStatus;
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
  request_type: string 
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
