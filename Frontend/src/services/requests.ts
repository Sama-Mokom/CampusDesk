import api from './api'; 
import {CreateRequestPayload, Request as DocumentRequest} from '../types';
import type { AxiosResponse } from 'axios'


/**
 * GET /requests
 * Fetch all requests for the currently authenticated student.
 */
export const fetchRequests = async (): Promise<DocumentRequest[]> => {
  const response: AxiosResponse<{ data: DocumentRequest[] }> = await api.get('/requests')
  return response.data.data
}

/**
 * GET /requests/{id}
 * Fetch a single request with its full stage progression and status history.
 */
export const fetchRequestById = async (id: number): Promise<DocumentRequest> => {
  const response: AxiosResponse<{ data: DocumentRequest }> = await api.get(`/requests/${id}`)
  return response.data.data
}

/**
 * POST /requests
 * Submit a new student request. Handles both simple JSON and file uploads.
 */
export const createRequest = async (payload: CreateRequestPayload): Promise<DocumentRequest> => {
  // If the user included file attachments, we must use FormData
  if (payload.attachments && payload.attachments.length > 0) {
    const formData = new FormData();
    formData.append('request_type_id', payload.request_type_id.toString());
    formData.append('description', payload.description);

    payload.attachments.forEach((file) => {
      formData.append('attachments[]', file);
    });
    // Log what FormData actually contains
    for (const [key, value] of formData.entries()) {
    }
   const response: AxiosResponse<{ data: DocumentRequest }> = await api.post('/requests', formData, {
  //  headers: {
  //   'Content-Type': 'multipart/form-data',
  // },
   })
    return response.data.data
  }

  // Otherwise, send a clean JSON request
   const response: AxiosResponse<{ data: DocumentRequest }> = await api.post('/requests', {
    request_type_id: payload.request_type_id,
    description: payload.description,
  })
  return response.data.data
};