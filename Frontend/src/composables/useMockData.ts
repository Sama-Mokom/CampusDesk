import { ref, computed } from 'vue'
import type {
  User,
  Request,
  RequestStage,
  StatusHistoryEntry,
  Notification,
  Faculty,
  Department,
  Programme,
  RequestTypeEntity,
  Attachment,
  StageQueueItem,
  RequestStatus,
  StageStatus,
  StudentLevel
} from '@/types'

const created = (d: string) => d

const faculties = ref<Faculty[]>([
  { id: 1, name: 'Faculty of Engineering & Technology', code: 'FET', created_at: created('2024-01-01T00:00:00Z') }
])

const departments = ref<Department[]>([
  { id: 1, faculty_id: 1, name: "Dean's Office", code: 'DEAN', created_at: created('2024-01-01T00:00:00Z') },
  { id: 2, faculty_id: 1, name: 'Computer Engineering', code: 'CE', created_at: created('2024-01-01T00:00:00Z') },
  { id: 3, faculty_id: 1, name: 'Electrical Engineering', code: 'EE', created_at: created('2024-01-01T00:00:00Z') },
  { id: 4, faculty_id: 1, name: "Registrar's Office", code: 'REG', created_at: created('2024-01-01T00:00:00Z') }
])

const programmes = ref<Programme[]>([
  {
    id: 3,
    faculty_id: 1,
    name: 'BEng Computer Engineering',
    code: 'BENG-CE',
    degree_type: 'BEng'
  },
  {
    id: 1,
    faculty_id: 1,
    name: 'BEng Electrical Engineering',
    code: 'BENG-EE',
    degree_type: 'BEng'
  }
])

const requestTypes = ref<RequestTypeEntity[]>([
  {
    id: 2,
    name: 'Official Transcript',
    description: 'Official academic transcript',
    default_department_sequence: [2, 4, 1]
  },
  {
    id: 1,
    name: 'Attestation of Enrollment',
    description: 'Proof of enrollment',
    default_department_sequence: [2, 4]
  }
])

const fet = faculties.value[0]!
const ceDept = departments.value.find(d => d.id === 2)!
const eeDept = departments.value.find(d => d.id === 3)!
const progCe = programmes.value.find(p => p.id === 3)!
const progEe = programmes.value.find(p => p.id === 1)!

const mockUsers = ref<User[]>([
  {
    id: 1,
    name: 'Nkeng Sama Mokom',
    email: 'sama@ub.cm',
    password: 'student',
    role: 'student',
    created_at: created('2024-09-01T00:00:00Z'),
    student_profile: {
      matricule: 'FE23A118',
      level: 'L400',
      status: 'active',
      faculty: { ...fet },
      department: { ...ceDept },
      programme: { ...progCe }
    }
  },
  {
    id: 2,
    name: 'Jane Student',
    email: 'jane@ub.cm',
    password: 'student',
    role: 'student',
    created_at: created('2024-09-01T00:00:00Z'),
    student_profile: {
      matricule: 'FE22B045',
      level: 'L300',
      status: 'active',
      faculty: { ...fet },
      department: { ...eeDept },
      programme: { ...progEe }
    }
  },
  {
    id: 5,
    name: 'Dr. Mbah John',
    email: 'mbah@ub.cm',
    password: 'staff',
    role: 'staff',
    created_at: created('2020-01-01T00:00:00Z'),
    staff_profile: {
      staff_id: 'UB-STF-0042',
      admin_level: 'dept_admin',
      departments: [
        { id: 2, name: 'Computer Engineering', code: 'CE', is_primary: true },
        { id: 3, name: 'Electrical Engineering', code: 'EE', is_primary: false }
      ]
    }
  },
  {
    id: 7,
    name: 'Mrs. Enoh Grace',
    email: 'enoh@ub.cm',
    password: 'staff',
    role: 'staff',
    created_at: created('2020-01-01T00:00:00Z'),
    staff_profile: {
      staff_id: 'UB-STF-0101',
      admin_level: null,
      departments: [{ id: 4, name: "Registrar's Office", code: 'REG', is_primary: true }]
    }
  },
  {
    id: 8,
    name: 'Mr. Tabi Paul',
    email: 'tabi@ub.cm',
    password: 'staff',
    role: 'staff',
    created_at: created('2021-01-01T00:00:00Z'),
    staff_profile: {
      staff_id: 'UB-STF-0088',
      admin_level: null,
      departments: [{ id: 2, name: 'Computer Engineering', code: 'CE', is_primary: true }]
    }
  },
  {
    id: 99,
    name: 'Super Admin',
    email: 'admin@ub.cm',
    password: 'admin',
    role: 'staff',
    created_at: created('2019-01-01T00:00:00Z'),
    staff_profile: {
      staff_id: 'UB-ADM-0001',
      admin_level: 'super_admin',
      departments: [{ id: 1, name: "Dean's Office", code: 'DEAN', is_primary: true }]
    }
  }
])

let nextRequestId = 20
let nextStageId = 100
let nextHistoryId = 200
let nextNotifId = 50
let nextAttachId = 300
let nextUserId = 100

function deptSummary(id: number): { id: number; name: string } {
  const d = departments.value.find(x => x.id === id)
  return d ? { id: d.id, name: d.name } : { id, name: `Department ${id}` }
}

function userSummary(u: User): { id: number; name: string } {
  return { id: u.id, name: u.name }
}

function cloneRequestType(rt: RequestTypeEntity): RequestTypeEntity {
  return {
    id: rt.id,
    name: rt.name,
    description: rt.description,
    default_department_sequence: [...rt.default_department_sequence]
  }
}

const transcriptType: RequestTypeEntity = {
  id: 2,
  name: 'Official Transcript',
  description: 'Official academic transcript',
  default_department_sequence: [2, 4, 1]
}

const attestationType: RequestTypeEntity = {
  id: 1,
  name: 'Attestation of Enrollment',
  description: 'Proof of enrollment',
  default_department_sequence: [2, 4]
}

const mockRequests = ref<Request[]>([
  {
    id: 12,
    student_id: 1,
    request_type: cloneRequestType(transcriptType),
    description: 'Needed for MSc application abroad.',
    status: 'in_review',
    is_reopened: false,
    created_at: '2026-03-15T09:00:00Z',
    attachments: [],
    stages: [
      {
        id: 1,
        department: { id: 2, name: 'Computer Engineering' },
        sequence_order: 1,
        status: 'approved',
        handled_by: { id: 5, name: 'Dr. Mbah John' },
        staff_note: 'Verified academic records.',
        updated_at: '2026-03-16T11:00:00Z'
      },
      {
        id: 2,
        department: { id: 4, name: "Registrar's Office" },
        sequence_order: 2,
        status: 'in_review',
        handled_by: { id: 7, name: 'Mrs. Enoh Grace' },
        staff_note: null,
        updated_at: '2026-03-17T08:30:00Z'
      },
      {
        id: 3,
        department: { id: 1, name: "Dean's Office" },
        sequence_order: 3,
        status: 'pending',
        handled_by: null,
        staff_note: null,
        updated_at: null
      }
    ],
    status_history: [
      {
        id: 1,
        old_status: null,
        new_status: 'pending',
        changed_by: { id: 1, name: 'Nkeng Sama Mokom' },
        note: 'Request submitted.',
        changed_at: '2026-03-15T09:00:00Z'
      },
      {
        id: 2,
        old_status: 'pending',
        new_status: 'in_review',
        changed_by: { id: 5, name: 'Dr. Mbah John' },
        note: null,
        changed_at: '2026-03-15T10:00:00Z'
      },
      {
        id: 3,
        old_status: 'in_review',
        new_status: 'forwarded',
        changed_by: { id: 5, name: 'Dr. Mbah John' },
        note: 'Approved at dept. level.',
        changed_at: '2026-03-16T11:00:00Z'
      }
    ]
  },
  {
    id: 13,
    student_id: 1,
    request_type: cloneRequestType(attestationType),
    description: 'For internship placement.',
    status: 'pending',
    is_reopened: false,
    created_at: '2026-04-01T10:00:00Z',
    attachments: [],
    stages: [
      {
        id: 10,
        department: { id: 2, name: 'Computer Engineering' },
        sequence_order: 1,
        status: 'pending',
        handled_by: null,
        staff_note: null,
        updated_at: null
      },
      {
        id: 11,
        department: { id: 4, name: "Registrar's Office" },
        sequence_order: 2,
        status: 'pending',
        handled_by: null,
        staff_note: null,
        updated_at: null
      }
    ],
    status_history: [
      {
        id: 10,
        old_status: null,
        new_status: 'pending',
        changed_by: { id: 1, name: 'Nkeng Sama Mokom' },
        note: 'Request submitted.',
        changed_at: '2026-04-01T10:00:00Z'
      }
    ]
  },
  {
    id: 14,
    student_id: 2,
    request_type: cloneRequestType(transcriptType),
    description: 'Grad school application.',
    status: 'rejected',
    is_reopened: false,
    created_at: '2026-02-01T09:00:00Z',
    attachments: [],
    stages: [
      {
        id: 20,
        department: { id: 2, name: 'Computer Engineering' },
        sequence_order: 1,
        status: 'rejected',
        handled_by: { id: 8, name: 'Mr. Tabi Paul' },
        staff_note: 'Incomplete documentation.',
        updated_at: '2026-02-05T12:00:00Z'
      }
    ],
    status_history: [
      {
        id: 20,
        old_status: null,
        new_status: 'pending',
        changed_by: { id: 2, name: 'Jane Student' },
        note: 'Request submitted.',
        changed_at: '2026-02-01T09:00:00Z'
      },
      {
        id: 21,
        old_status: 'pending',
        new_status: 'in_review',
        changed_by: { id: 8, name: 'Mr. Tabi Paul' },
        note: null,
        changed_at: '2026-02-02T09:00:00Z'
      },
      {
        id: 22,
        old_status: 'in_review',
        new_status: 'rejected',
        changed_by: { id: 8, name: 'Mr. Tabi Paul' },
        note: 'Missing transcript pages.',
        changed_at: '2026-02-05T12:00:00Z'
      }
    ]
  }
])

const notifications = ref<Notification[]>([
  {
    id: 3,
    user_id: 1,
    type: 'stage_update',
    message: "Your transcript request has been forwarded to the Registrar's Office.",
    read: false,
    read_at: null,
    created_at: '2026-03-16T11:05:00Z'
  },
  {
    id: 4,
    user_id: 1,
    type: 'request_submitted',
    message: 'Your attestation request was submitted successfully.',
    read: true,
    read_at: '2026-04-01T10:01:00Z',
    created_at: '2026-04-01T10:00:00Z'
  }
])

const sessionUser = ref<User | null>(null)

const currentDepartmentId = ref<number | null>(null)

function staffPrimaryDepartmentId(u: User): number | null {
  const sp = u.staff_profile
  if (!sp?.departments?.length) return null
  const primary = sp.departments.find(d => d.is_primary)
  return primary?.id ?? sp.departments[0]!.id
}

function ensureStaffDepartmentContext(u: User) {
  if (u.role !== 'staff' || !u.staff_profile) return
  const primary = staffPrimaryDepartmentId(u)
  if (currentDepartmentId.value === null && primary !== null) {
    currentDepartmentId.value = primary
  }
}

function login(email: string, password: string): User | null {
  const u = mockUsers.value.find(x => x.email === email && x.password === password)
  if (!u) return null
  sessionUser.value = u
  if (u.role === 'staff') {
    currentDepartmentId.value = staffPrimaryDepartmentId(u)
  } else {
    currentDepartmentId.value = null
  }
  return u
}

function logout() {
  sessionUser.value = null
  currentDepartmentId.value = null
}

export interface RegisterStudentPayload {
  name: string
  email: string
  password: string
  matricule: string
  faculty_id: number
  department_id: number
  programme_id: number
  level: StudentLevel
}

function registerStudent(payload: RegisterStudentPayload): void {
  const fac = faculties.value.find(f => f.id === payload.faculty_id)
  const dep = departments.value.find(d => d.id === payload.department_id)
  const prog = programmes.value.find(p => p.id === payload.programme_id)
  if (!fac || !dep || !prog) return
  const id = nextUserId++
  mockUsers.value.push({
    id,
    name: payload.name,
    email: payload.email,
    password: payload.password,
    role: 'student',
    created_at: new Date().toISOString(),
    student_profile: {
      matricule: payload.matricule,
      level: payload.level,
      status: 'active',
      faculty: { ...fac },
      department: { ...dep },
      programme: { ...prog }
    }
  })
}

function getUserById(id: number): User | undefined {
  return mockUsers.value.find(u => u.id === id)
}

function pushHistory(
  req: Request,
  oldStatus: RequestStatus | null,
  newStatus: RequestStatus,
  by: User,
  note: string | null
) {
  req.status_history.push({
    id: ++nextHistoryId,
    old_status: oldStatus,
    new_status: newStatus,
    changed_by: userSummary(by),
    note,
    changed_at: new Date().toISOString()
  })
}

function notifyStudent(studentId: number, type: string, message: string) {
  notifications.value.push({
    id: ++nextNotifId,
    user_id: studentId,
    type,
    message,
    read: false,
    read_at: null,
    created_at: new Date().toISOString()
  })
}

function markNotificationRead(notifId: number) {
  const n = notifications.value.find(x => x.id === notifId)
  if (n && !n.read) {
    n.read = true
    n.read_at = new Date().toISOString()
  }
}

function buildStagesFromSequence(seq: number[]): RequestStage[] {
  return seq.map((deptId, i) => ({
    id: ++nextStageId,
    department: deptSummary(deptId),
    sequence_order: i + 1,
    status: 'pending' as StageStatus,
    handled_by: null,
    staff_note: null,
    updated_at: null
  }))
}

function pickUpStage(requestId: number, stageId: number, staff: User): boolean {
  const req = mockRequests.value.find(r => r.id === requestId)
  if (!req || !staff.staff_profile) return false
  const stage = req.stages.find(s => s.id === stageId)
  if (!stage || stage.status !== 'pending' || stage.handled_by !== null) return false
  if (stage.department.id !== currentDepartmentId.value) return false

  const oldReqStatus = req.status
  stage.handled_by = userSummary(staff)
  stage.status = 'in_review'
  stage.updated_at = new Date().toISOString()

  if (req.status === 'pending') {
    req.status = 'in_review'
    pushHistory(req, oldReqStatus, 'in_review', staff, null)
  }

  notifyStudent(
    req.student_id,
    'stage_update',
    `A staff member is reviewing your ${req.request_type.name} request at ${stage.department.name}.`
  )
  return true
}

function updateStageResolution(
  requestId: number,
  stageId: number,
  resolution: 'approved' | 'rejected',
  staffNote: string | null,
  staff: User
): boolean {
  const req = mockRequests.value.find(r => r.id === requestId)
  if (!req || !staff.staff_profile) return false
  const stage = req.stages.find(s => s.id === stageId)
  if (!stage || stage.status !== 'in_review' || stage.handled_by?.id !== staff.id) return false

  const oldReq = req.status
  stage.status = resolution
  stage.staff_note = staffNote
  stage.updated_at = new Date().toISOString()

  if (resolution === 'rejected') {
    req.status = 'rejected'
    pushHistory(req, oldReq, 'rejected', staff, staffNote)
    notifyStudent(req.student_id, 'stage_update', `Your ${req.request_type.name} request was rejected.`)
    return true
  }

  const ordered = [...req.stages].sort((a, b) => a.sequence_order - b.sequence_order)
  const idx = ordered.findIndex(s => s.id === stageId)
  const hasNext = idx >= 0 && idx < ordered.length - 1

  if (hasNext) {
    req.status = 'forwarded'
    pushHistory(req, oldReq, 'forwarded', staff, staffNote)
    const nextStage = ordered[idx + 1]!
    notifyStudent(
      req.student_id,
      'stage_update',
      `Your ${req.request_type.name} request has been forwarded to ${nextStage.department.name}.`
    )
  } else {
    req.status = 'ready'
    pushHistory(req, oldReq, 'ready', staff, staffNote)
    notifyStudent(req.student_id, 'stage_update', `Your ${req.request_type.name} request is ready for collection.`)
  }
  return true
}

function reopenRequest(requestId: number, student: User): boolean {
  const req = mockRequests.value.find(r => r.id === requestId)
  if (!req || req.student_id !== student.id || req.status !== 'rejected') return false

  const old = req.status
  req.is_reopened = true
  req.status = 'pending'
  req.stages = buildStagesFromSequence(req.request_type.default_department_sequence)
  pushHistory(req, old, 'pending', student, 'Request reopened by student.')
  notifyStudent(req.student_id, 'request_reopened', 'Your request was reopened and resubmitted for processing.')
  return true
}

function markCollected(requestId: number, actor: User): boolean {
  const req = mockRequests.value.find(r => r.id === requestId)
  if (!req || req.status !== 'ready') return false
  if (actor.role === 'student' && req.student_id !== actor.id) return false

  const old = req.status
  req.status = 'collected'
  pushHistory(req, old, 'collected', actor, 'Marked as collected.')
  notifyStudent(req.student_id, 'collected', `Your ${req.request_type.name} request was marked as collected.`)
  return true
}

function reassignStage(requestId: number, stageId: number, newStaffId: number, admin: User): boolean {
  const req = mockRequests.value.find(r => r.id === requestId)
  if (!req || !admin.staff_profile || admin.staff_profile.admin_level !== 'dept_admin') return false
  const primary = staffPrimaryDepartmentId(admin)
  if (primary === null) return false

  const stage = req.stages.find(s => s.id === stageId)
  if (!stage || stage.department.id !== primary) return false
  if (stage.status !== 'in_review' || !stage.handled_by) return false

  const nu = getUserById(newStaffId)
  if (!nu?.staff_profile?.departments.some(d => d.id === primary)) return false

  stage.handled_by = userSummary(nu)
  stage.updated_at = new Date().toISOString()
  pushHistory(req, req.status, req.status, admin, `Stage reassigned to ${nu.name}.`)
  return true
}

function adminOverrideRequestStatus(
  requestId: number,
  newStatus: RequestStatus,
  note: string | null,
  admin: User
): boolean {
  if (admin.staff_profile?.admin_level !== 'super_admin') return false
  const req = mockRequests.value.find(r => r.id === requestId)
  if (!req) return false
  const old = req.status
  req.status = newStatus
  pushHistory(req, old, newStatus, admin, note)
  return true
}

function createStudentRequest(
  student: User,
  requestTypeId: number,
  description: string,
  files: { name: string; type: string }[]
): Request {
  const rt = requestTypes.value.find(t => t.id === requestTypeId)
  if (!rt || !student.student_profile) throw new Error('Invalid request')

  const id = nextRequestId++
  const stages = buildStagesFromSequence(rt.default_department_sequence)
  const now = new Date().toISOString()
  const attachments: Attachment[] = files.map((f, i) => ({
    id: nextAttachId++,
    request_id: id,
    file_path: `/uploads/mock/${id}/${f.name}`,
    original_name: f.name,
    mime_type: f.type || 'application/octet-stream'
  }))

  const req: Request = {
    id,
    student_id: student.id,
    request_type: cloneRequestType(rt),
    description,
    status: 'pending',
    is_reopened: false,
    created_at: now,
    attachments,
    stages,
    status_history: [
      {
        id: ++nextHistoryId,
        old_status: null,
        new_status: 'pending',
        changed_by: userSummary(student),
        note: 'Request submitted.',
        changed_at: now
      }
    ]
  }
  mockRequests.value.push(req)
  notifyStudent(student.id, 'request_submitted', `Your ${rt.name} request was submitted successfully.`)
  return req
}

function allStatusHistoryFlat() {
  const rows: Array<StatusHistoryEntry & { request_id: number }> = []
  for (const r of mockRequests.value) {
    for (const h of r.status_history) {
      rows.push({ ...h, request_id: r.id })
    }
  }
  return rows.sort((a, b) => new Date(b.changed_at).getTime() - new Date(a.changed_at).getTime())
}

function staffInDepartment(deptId: number): User[] {
  return mockUsers.value.filter(
    u =>
      u.role === 'staff' &&
      u.staff_profile?.admin_level !== 'super_admin' &&
      u.staff_profile?.departments.some(d => d.id === deptId)
  )
}

function assertSuperAdmin(u: User | null): u is User {
  return !!u && u.staff_profile?.admin_level === 'super_admin'
}

function saveFaculty(row: Faculty) {
  const i = faculties.value.findIndex(f => f.id === row.id)
  if (i >= 0) faculties.value[i] = { ...row }
  else faculties.value.push({ ...row, created_at: row.created_at || new Date().toISOString() })
}

function deleteFaculty(id: number) {
  faculties.value = faculties.value.filter(f => f.id !== id)
}

function saveDepartment(row: Department) {
  const i = departments.value.findIndex(d => d.id === row.id)
  if (i >= 0) departments.value[i] = { ...row }
  else
    departments.value.push({
      ...row,
      created_at: row.created_at || new Date().toISOString()
    })
}

function deleteDepartment(id: number) {
  departments.value = departments.value.filter(d => d.id !== id)
}

function saveProgramme(row: Programme) {
  const i = programmes.value.findIndex(p => p.id === row.id)
  if (i >= 0) programmes.value[i] = { ...row }
  else programmes.value.push({ ...row })
}

function deleteProgramme(id: number) {
  programmes.value = programmes.value.filter(p => p.id !== id)
}

function saveRequestTypeEntity(row: RequestTypeEntity) {
  const i = requestTypes.value.findIndex(t => t.id === row.id)
  if (i >= 0) requestTypes.value[i] = { ...row, default_department_sequence: [...row.default_department_sequence] }
  else requestTypes.value.push({ ...row, default_department_sequence: [...row.default_department_sequence] })
}

function deleteRequestTypeEntity(id: number) {
  requestTypes.value = requestTypes.value.filter(t => t.id !== id)
}

function saveUserEntity(row: User) {
  const i = mockUsers.value.findIndex(u => u.id === row.id)
  if (i >= 0) mockUsers.value[i] = { ...row }
  else mockUsers.value.push({ ...row })
}

function deleteUserEntity(id: number) {
  if (id === sessionUser.value?.id) return
  mockUsers.value = mockUsers.value.filter(u => u.id !== id)
}

let nextFacultyId = 10
let nextDeptId = 10
let nextProgId = 10
let nextRtId = 10

function createFaculty(name: string, code: string, admin: User): Faculty | null {
  if (!assertSuperAdmin(admin)) return null
  const f: Faculty = { id: nextFacultyId++, name, code, created_at: new Date().toISOString() }
  faculties.value.push(f)
  return f
}

function createDepartment(faculty_id: number, name: string, code: string, admin: User): Department | null {
  if (!assertSuperAdmin(admin)) return null
  const d: Department = {
    id: nextDeptId++,
    faculty_id,
    name,
    code,
    created_at: new Date().toISOString()
  }
  departments.value.push(d)
  return d
}

function createProgramme(
  faculty_id: number,
  name: string,
  code: string,
  degree_type: Programme['degree_type'],
  admin: User
): Programme | null {
  if (!assertSuperAdmin(admin)) return null
  const p: Programme = { id: nextProgId++, faculty_id, name, code, degree_type }
  programmes.value.push(p)
  return p
}

function createRequestTypeRow(
  name: string,
  description: string,
  sequence: number[],
  admin: User
): RequestTypeEntity | null {
  if (!assertSuperAdmin(admin)) return null
  const rt: RequestTypeEntity = {
    id: nextRtId++,
    name,
    description,
    default_department_sequence: [...sequence]
  }
  requestTypes.value.push(rt)
  return rt
}

export function useMockData() {
  const isAuthenticated = computed(() => sessionUser.value !== null)

  const sessionNotifications = computed(() => {
    const u = sessionUser.value
    if (!u) return []
    return notifications.value.filter(n => n.user_id === u.id).sort(
      (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    )
  })

  const unreadNotificationCount = computed(
    () => sessionNotifications.value.filter(n => !n.read).length
  )

  const studentRequests = computed(() => {
    const u = sessionUser.value
    if (!u || u.role !== 'student') return []
    return mockRequests.value.filter(r => r.student_id === u.id)
  })

  const studentStats = computed(() => {
    const list = studentRequests.value
    const pending = list.filter(r =>
      ['draft', 'pending', 'in_review', 'forwarded'].includes(r.status)
    ).length
    const ready = list.filter(r => r.status === 'ready').length
    return { total: list.length, pending, ready_for_collection: ready }
  })

  const unclaimedStages = computed((): StageQueueItem[] => {
    const u = sessionUser.value
    const deptId = currentDepartmentId.value
    if (!u?.staff_profile || deptId === null) return []
    if (!u.staff_profile.departments.some(d => d.id === deptId)) return []

    const items: StageQueueItem[] = []
    for (const r of mockRequests.value) {
      for (const s of r.stages) {
        if (s.department.id === deptId && s.status === 'pending' && s.handled_by === null) {
          items.push({ request: r, stage: s })
        }
      }
    }
    return items
  })

  const myActiveStages = computed((): StageQueueItem[] => {
    const u = sessionUser.value
    if (!u?.staff_profile) return []
    const items: StageQueueItem[] = []
    for (const r of mockRequests.value) {
      for (const s of r.stages) {
        if (s.handled_by?.id === u.id && s.status === 'in_review') {
          items.push({ request: r, stage: s })
        }
      }
    }
    return items
  })

  const resolvedTodayCount = computed(() => {
    const u = sessionUser.value
    if (!u?.staff_profile) return 0
    const start = new Date()
    start.setHours(0, 0, 0, 0)
    let n = 0
    for (const r of mockRequests.value) {
      for (const s of r.stages) {
        if (s.handled_by?.id !== u.id) continue
        if (s.status !== 'approved' && s.status !== 'rejected') continue
        if (!s.updated_at) continue
        if (new Date(s.updated_at) >= start) n++
      }
    }
    return n
  })

  const primaryDepartmentId = computed(() => {
    const u = sessionUser.value
    if (!u?.staff_profile) return null
    return staffPrimaryDepartmentId(u)
  })

  const deptAdminOverviewStages = computed(() => {
    const u = sessionUser.value
    const primary = staffPrimaryDepartmentId(u!)
    if (!u?.staff_profile || u.staff_profile.admin_level !== 'dept_admin' || primary === null) {
      return [] as StageQueueItem[]
    }
    const items: StageQueueItem[] = []
    for (const r of mockRequests.value) {
      for (const s of r.stages) {
        if (s.department.id === primary) items.push({ request: r, stage: s })
      }
    }
    return items
  })

  const systemStats = computed(() => {
    const list = mockRequests.value
    const byStatus = {} as Record<RequestStatus, number>
    for (const st of [
      'draft',
      'pending',
      'in_review',
      'forwarded',
      'ready',
      'collected',
      'rejected'
    ] as RequestStatus[]) {
      byStatus[st] = list.filter(r => r.status === st).length
    }
    const today = new Date().toDateString()
    const todayCount = list.filter(r => new Date(r.created_at).toDateString() === today).length
    let totalHours = 0
    let resolved = 0
    for (const r of list) {
      const done = r.status_history.filter(h => ['ready', 'collected', 'rejected', 'forwarded'].includes(h.new_status))
      if (done.length === 0) continue
      const first = new Date(r.created_at).getTime()
      const last = new Date(done[done.length - 1]!.changed_at).getTime()
      totalHours += (last - first) / 3600000
      resolved++
    }
    const avgResolutionHours = resolved ? totalHours / resolved : 0
    return {
      total: list.length,
      by_status: byStatus,
      requests_today: todayCount,
      avg_resolution_hours: avgResolutionHours
    }
  })

  const recentActivity = computed(() => allStatusHistoryFlat().slice(0, 20))

  const departmentStats = computed(() => {
    const u = sessionUser.value
    const primary = staffPrimaryDepartmentId(u!)
    if (!u?.staff_profile || u.staff_profile.admin_level !== 'dept_admin' || primary === null) {
      return { total_through_dept: 0, avg_resolution_hours: 0, rejection_rate: 0 }
    }
    let total = 0
    let rejected = 0
    let sumHours = 0
    let counted = 0
    for (const r of mockRequests.value) {
      const stagesHere = r.stages.filter(s => s.department.id === primary)
      if (stagesHere.length === 0) continue
      total++
      const st = stagesHere[0]!
      if (st.status === 'rejected') rejected++
      if (st.updated_at && st.status !== 'pending') {
        const sub = new Date(r.created_at).getTime()
        const end = new Date(st.updated_at).getTime()
        sumHours += (end - sub) / 3600000
        counted++
      }
    }
    return {
      total_through_dept: total,
      avg_resolution_hours: counted ? sumHours / counted : 0,
      rejection_rate: total ? rejected / total : 0
    }
  })

  function setCurrentDepartmentId(id: number) {
    const u = sessionUser.value
    if (!u?.staff_profile?.departments.some(d => d.id === id)) return
    currentDepartmentId.value = id
  }

  return {
    faculties,
    departments,
    programmes,
    requestTypes,
    mockUsers,
    mockRequests,
    notifications,
    sessionUser,
    currentDepartmentId,
    isAuthenticated,
    login,
    logout,
    registerStudent,
    ensureStaffDepartmentContext,
    getUserById,
    pickUpStage,
    updateStageResolution,
    reopenRequest,
    markCollected,
    reassignStage,
    adminOverrideRequestStatus,
    createStudentRequest,
    markNotificationRead,
    staffInDepartment,
    allStatusHistoryFlat,
    studentRequests,
    studentStats,
    sessionNotifications,
    unreadNotificationCount,
    unclaimedStages,
    myActiveStages,
    resolvedTodayCount,
    primaryDepartmentId,
    deptAdminOverviewStages,
    systemStats,
    recentActivity,
    departmentStats,
    setCurrentDepartmentId,
    staffPrimaryDepartmentId,
    deptSummary,
    saveFaculty,
    deleteFaculty,
    saveDepartment,
    deleteDepartment,
    saveProgramme,
    deleteProgramme,
    saveRequestTypeEntity,
    deleteRequestTypeEntity,
    saveUserEntity,
    deleteUserEntity,
    createFaculty,
    createDepartment,
    createProgramme,
    createRequestTypeRow,
    assertSuperAdmin
  }
}
