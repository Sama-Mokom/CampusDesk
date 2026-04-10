export type RequestStatus = 'pending' | 'approved' | 'rejected' | 'in_progress' | 'completed'
export type RequestType = 'transcript' | 'financial_aid' | 'accommodation' | 'enrollment' | 'other'
export type UserRole = 'student' | 'staff' | 'admin'

export interface Request {
  id: string
  studentId: string
  studentName: string
  studentEmail: string
  type: RequestType
  title: string
  description: string
  status: RequestStatus
  priority: 'low' | 'medium' | 'high'
  createdAt: Date
  updatedAt: Date
  assignedTo?: string
  notes?: string
  timeline: Timeline[]
}

export interface Timeline {
  status: RequestStatus
  timestamp: Date
  message: string
  actor: string
}

export interface Student {
  id: string
  name: string
  email: string
  studentId: string
  major: string
  year: 'freshman' | 'sophomore' | 'junior' | 'senior'
  enrolledAt: Date
}

export interface StaffMember {
  id: string
  name: string
  email: string
  department: string
  role: string
  assignedRequests: string[]
}

export interface AdminUser {
  id: string
  name: string
  email: string
  permissions: string[]
  createdAt: Date
}

export interface Dashboard {
  studentRequests: Request[]
  staffQueue: Request[]
  adminStats: AdminStats
}

export interface AdminStats {
  totalRequests: number
  pendingRequests: number
  approvedRequests: number
  rejectedRequests: number
  avgProcessingTime: number
}
