import api from './api'
import type {
  CreateRequestPayload,
  Payment,
  Request as DocumentRequest
} from '../types'
import type { AxiosResponse } from 'axios'

/**
 * GET /requests
 * Fetch all requests for the currently authenticated student.
 */
export const fetchRequests = async (): Promise<DocumentRequest[]> => {
  const response: AxiosResponse<{ data: DocumentRequest[] }> =
    await api.get('/requests')
  return response.data.data
}

/**
 * GET /requests/{id}
 * Fetch a single request with its full stage progression and status history.
 */
export async function fetchRequestById(id: number): Promise<DocumentRequest> {
  const response = await api.get(`/requests/${id}`)
  const payload = response.data.data ?? response.data
  return Array.isArray(payload) ? payload[0] : payload
}

/**
 * POST /requests/{id}/reopen
 * Restore a rejected request or request tier upgrade.
 */
export async function reopenRequest(
  id: number,
  payload?: { request_type_tier_id?: number; cancel_upgrade?: boolean }
): Promise<DocumentRequest> {
  const response = await api.post(`/requests/${id}/reopen`, payload ?? {})
  return response.data.data ?? response.data
}

/**
 * POST /requests/{id}/collect
 * Confirm physical collection of a completed document request.
 */
export async function markRequestCollected(id: number): Promise<DocumentRequest> {
  const response = await api.patch(`/requests/${id}/collect`)
  return response.data.data ?? response.data
}

/**
 * PATCH /requests/{id}/tier
 * Switch tier while awaiting payment.
 */
export async function switchRequestTier(
  requestId: number,
  tierId: number
): Promise<DocumentRequest> {
  const response = await api.patch(`/requests/${requestId}/tier`, {
    request_type_tier_id: tierId
  })
  return response.data.data ?? response.data
}

/**
 * POST /requests/{id}/payments
 * Initiate a payment session.
 */
export async function initiatePayment(
  requestId: number,
  payload: {
    checkout_mode: 'hosted' | 'direct_push'
    payment_method?: string
    phone?: string
  }
): Promise<Payment> {
  const response = await api.post(`/requests/${requestId}/payments`, payload)
  return response.data.data ?? response.data
}

/**
 * POST /requests/{id}/payments/{paymentId}/verify
 * Poll / verify status of payment session.
 */
export async function verifyPayment(
  requestId: number,
  paymentId: number,
  fakeOutcome?: string
): Promise<{ payment: Payment; request: DocumentRequest }> {
  const response = await api.post(
    `/requests/${requestId}/payments/${paymentId}/verify`,
    fakeOutcome ? { fake_outcome: fakeOutcome } : {}
  )
  return response.data.data ?? response.data
}

/**
 * POST /requests
 * Submit a new student request. Handles both simple JSON and file uploads.
 */
export const createRequest = async (
  payload: CreateRequestPayload
): Promise<DocumentRequest> => {
  // If the user included file attachments, use FormData
  if (payload.attachments && payload.attachments.length > 0) {
    const formData = new FormData()
    formData.append('request_type_id', payload.request_type_id.toString())
    if (
      payload.request_type_tier_id !== undefined &&
      payload.request_type_tier_id !== null
    ) {
      formData.append(
        'request_type_tier_id',
        payload.request_type_tier_id.toString()
      )
    }
    formData.append('description', payload.description)

    payload.attachments.forEach((file) => {
      formData.append('attachments[]', file)
    })
    const response: AxiosResponse<{ data: DocumentRequest }> = await api.post(
      '/requests',
      formData
    )
    return response.data.data
  }

  // Otherwise, send a clean JSON request
  const body: Record<string, unknown> = {
    request_type_id: payload.request_type_id,
    description: payload.description
  }
  if (
    payload.request_type_tier_id !== undefined &&
    payload.request_type_tier_id !== null
  ) {
    body.request_type_tier_id = payload.request_type_tier_id
  }
  const response: AxiosResponse<{ data: DocumentRequest }> = await api.post(
    '/requests',
    body
  )
  return response.data.data
}
