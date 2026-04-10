import { ref, computed } from 'vue'
import type { Request, Student, StaffMember, AdminStats } from '@/types'
import { RequestStatus, RequestType } from '@/types'

const mockRequests = ref<Request[]>([
  {
    id: 'req-001',
    studentId: 'stu-001',
    studentName: 'Alice Johnson',
    studentEmail: 'alice.johnson@university.edu',
    type: 'transcript',
    title: 'Official Transcript Request',
    description: 'Need official transcripts for graduate school applications',
    status: 'approved',
    priority: 'high',
    createdAt: new Date('2024-03-15'),
    updatedAt: new Date('2024-03-17'),
    assignedTo: 'staff-001',
    timeline: [
      {
        status: 'pending',
        timestamp: new Date('2024-03-15'),
        message: 'Request submitted',
        actor: 'alice.johnson@university.edu'
      },
      {
        status: 'in_progress',
        timestamp: new Date('2024-03-16'),
        message: 'Processing transcript',
        actor: 'john.doe@university.edu'
      },
      {
        status: 'approved',
        timestamp: new Date('2024-03-17'),
        message: 'Transcript approved and ready for pickup',
        actor: 'john.doe@university.edu'
      }
    ]
  },
  {
    id: 'req-002',
    studentId: 'stu-002',
    studentName: 'Bob Smith',
    studentEmail: 'bob.smith@university.edu',
    type: 'financial_aid',
    title: 'Financial Aid Appeal',
    description: 'Requesting review of financial aid eligibility',
    status: 'pending',
    priority: 'high',
    createdAt: new Date('2024-03-20'),
    updatedAt: new Date('2024-03-20'),
    timeline: [
      {
        status: 'pending',
        timestamp: new Date('2024-03-20'),
        message: 'Request submitted',
        actor: 'bob.smith@university.edu'
      }
    ]
  },
  {
    id: 'req-003',
    studentId: 'stu-003',
    studentName: 'Carol Davis',
    studentEmail: 'carol.davis@university.edu',
    type: 'accommodation',
    title: 'Disability Accommodation Request',
    description: 'Request for exam accommodations due to learning disability',
    status: 'approved',
    priority: 'high',
    createdAt: new Date('2024-03-10'),
    updatedAt: new Date('2024-03-12'),
    assignedTo: 'staff-002',
    timeline: [
      {
        status: 'pending',
        timestamp: new Date('2024-03-10'),
        message: 'Request submitted',
        actor: 'carol.davis@university.edu'
      },
      {
        status: 'in_progress',
        timestamp: new Date('2024-03-11'),
        message: 'Documentation under review',
        actor: 'jane.smith@university.edu'
      },
      {
        status: 'approved',
        timestamp: new Date('2024-03-12'),
        message: 'Accommodation approved',
        actor: 'jane.smith@university.edu'
      }
    ]
  },
  {
    id: 'req-004',
    studentId: 'stu-004',
    studentName: 'David Wilson',
    studentEmail: 'david.wilson@university.edu',
    type: 'enrollment',
    title: 'Course Enrollment Exception',
    description: 'Request to enroll in course without prerequisite',
    status: 'rejected',
    priority: 'medium',
    createdAt: new Date('2024-03-05'),
    updatedAt: new Date('2024-03-08'),
    notes: 'Missing required prerequisites. Please complete them first.',
    timeline: [
      {
        status: 'pending',
        timestamp: new Date('2024-03-05'),
        message: 'Request submitted',
        actor: 'david.wilson@university.edu'
      },
      {
        status: 'in_progress',
        timestamp: new Date('2024-03-06'),
        message: 'Reviewing prerequisite requirements',
        actor: 'prof.williams@university.edu'
      },
      {
        status: 'rejected',
        timestamp: new Date('2024-03-08'),
        message: 'Missing required prerequisites',
        actor: 'prof.williams@university.edu'
      }
    ]
  },
  {
    id: 'req-005',
    studentId: 'stu-005',
    studentName: 'Emma Brown',
    studentEmail: 'emma.brown@university.edu',
    type: 'other',
    title: 'Tuition Payment Plan Request',
    description: 'Requesting extended payment plan for tuition',
    status: 'in_progress',
    priority: 'high',
    createdAt: new Date('2024-03-18'),
    updatedAt: new Date('2024-03-19'),
    assignedTo: 'staff-001',
    timeline: [
      {
        status: 'pending',
        timestamp: new Date('2024-03-18'),
        message: 'Request submitted',
        actor: 'emma.brown@university.edu'
      },
      {
        status: 'in_progress',
        timestamp: new Date('2024-03-19'),
        message: 'Processing payment plan options',
        actor: 'finance.officer@university.edu'
      }
    ]
  }
])

export function useMockData() {
  const currentUser = ref<{ id: string; role: 'student' | 'staff' | 'admin'; name: string }>({
    id: 'user-001',
    role: 'student',
    name: 'Current User'
  })

  const studentRequests = computed(() => {
    return mockRequests.value.filter(r => r.studentId === 'stu-001')
  })

  const staffQueue = computed(() => {
    return mockRequests.value.filter(r => !['rejected', 'completed'].includes(r.status))
  })

  const adminStats = computed(() => {
    const total = mockRequests.value.length
    const pending = mockRequests.value.filter(r => r.status === 'pending').length
    const approved = mockRequests.value.filter(r => r.status === 'approved').length
    const rejected = mockRequests.value.filter(r => r.status === 'rejected').length
    const avgProcessingTime = 3.2 // days

    return {
      totalRequests: total,
      pendingRequests: pending,
      approvedRequests: approved,
      rejectedRequests: rejected,
      avgProcessingTime
    }
  })

  const createRequest = (data: Partial<Request>) => {
    const newRequest: Request = {
      id: `req-${Date.now()}`,
      studentId: data.studentId || 'stu-001',
      studentName: data.studentName || 'Student Name',
      studentEmail: data.studentEmail || 'student@university.edu',
      type: data.type || 'other',
      title: data.title || 'New Request',
      description: data.description || '',
      status: 'pending',
      priority: data.priority || 'medium',
      createdAt: new Date(),
      updatedAt: new Date(),
      timeline: [
        {
          status: 'pending',
          timestamp: new Date(),
          message: 'Request submitted',
          actor: data.studentEmail || 'student@university.edu'
        }
      ]
    }
    mockRequests.value.push(newRequest)
    return newRequest
  }

  const updateRequestStatus = (requestId: string, newStatus: RequestStatus, message: string) => {
    const request = mockRequests.value.find(r => r.id === requestId)
    if (request) {
      request.status = newStatus
      request.updatedAt = new Date()
      request.timeline.push({
        status: newStatus,
        timestamp: new Date(),
        message,
        actor: currentUser.value.name
      })
    }
  }

  const assignRequest = (requestId: string, staffId: string) => {
    const request = mockRequests.value.find(r => r.id === requestId)
    if (request) {
      request.assignedTo = staffId
      request.updatedAt = new Date()
    }
  }

  const addNote = (requestId: string, note: string) => {
    const request = mockRequests.value.find(r => r.id === requestId)
    if (request) {
      request.notes = note
      request.updatedAt = new Date()
    }
  }

  const switchRole = (role: 'student' | 'staff' | 'admin') => {
    currentUser.value.role = role
    if (role === 'student') {
      currentUser.value.name = 'Alice Johnson'
    } else if (role === 'staff') {
      currentUser.value.name = 'John Doe (Staff)'
    } else {
      currentUser.value.name = 'Admin User'
    }
  }

  return {
    mockRequests,
    currentUser,
    studentRequests,
    staffQueue,
    adminStats,
    createRequest,
    updateRequestStatus,
    assignRequest,
    addNote,
    switchRole
  }
}
