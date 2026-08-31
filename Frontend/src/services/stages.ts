import api from './api'; 
import type { RequestStage, ResolveStagePayload } from '../types';


// API Endpoints
/**
 * GET /requests/{id}/stages
 * Fetch the stages progress / queue history for a given request.
 */
// GET /requests/{id}/stages — stages for a specific request
export const fetchRequestStages = async (requestId: number): Promise<RequestStage[]> => {
  const response = await api.get(`/requests/${requestId}/stages`)
  // Laravel ResourceCollection wraps results in { data: [...] }
  return response.data.data ?? response.data
}

export const fetchMyCases = async (): Promise<RequestStage[]> => {
  const response = await api.get('/stages/my-cases')
  return response.data.data ?? response.data
}

// GET /stages — staff queue (no requestId needed)
// services/stages.ts

export const fetchStaffQueue = async (): Promise<RequestStage[]> => {
  const response = await api.get('/stages')
  // Axios returns { data: { data: [...] } } when Laravel wraps resources
  return response.data.data ?? response.data
}

/**
 * POST /requests/{requestId}/stages/{stageId}/claim
 * Marks a stage as claimed by the currently authenticated staff member.
 * Returns the updated stage details.
 */
export const claimStage = async (requestId: number, stageId: number): Promise<void> => {
   await api.post<RequestStage>(
    `/requests/${requestId}/stages/${stageId}/claim`
  );
};

/**
 * PATCH /requests/{requestId}/stages/{stageId}/resolve
 * Resolves a stage (either 'approve' or 'reject') with a staff note.
 * Maps action → status ('approve' → 'approved', 'reject' → 'rejected').
 */
export const resolveStage = async (
  requestId: number, 
  stageId: number, 
  payload: ResolveStagePayload
): Promise<void> => {
  // const statusMap: Record<'approved' | 'rejected', 'approved' | 'rejected'> = {
  //   approved: 'approved',
  //   rejected: 'rejected',
  // }
  await api.patch<RequestStage>(
    `/requests/${requestId}/stages/${stageId}/resolve`, 
    {
      status: payload.status,
      staff_note: payload.staff_note,
    }
  );
};