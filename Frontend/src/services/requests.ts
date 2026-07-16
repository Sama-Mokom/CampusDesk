import api from './api'; 
import {CreateRequestPayload} from '../types';


/**
 * GET /requests
 * Fetch all requests for the currently authenticated student.
 */
export const fetchRequests = async (): Promise<Request[]> => {
  const response = await api.get<Request[]>('/requests');
  return response.data;
};

/**
 * GET /requests/{id}
 * Fetch a single request with its full stage progression and status history.
 */
export const fetchRequestById = async (id: number): Promise<Request> => {
  const response = await api.get<Request>(`/requests/${id}`);
  return response.data;
};

/**
 * POST /requests
 * Submit a new student request. Handles both simple JSON and file uploads.
 */
export const createRequest = async (payload: CreateRequestPayload): Promise<Request> => {
  // If the user included file attachments, we must use FormData
  if (payload.attachments && payload.attachments.length > 0) {
    const formData = new FormData();
    formData.append('request_type_id', payload.request_type_id.toString());
    formData.append('description', payload.description);

    payload.attachments.forEach((file) => {
      formData.append('attachments[]', file);
    });

    const response = await api.post<Request>('/requests', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  }

  // Otherwise, send a clean JSON request
  const response = await api.post<Request>('/requests', {
    request_type_id: payload.request_type_id,
    description: payload.description,
  });
  return response.data;
};