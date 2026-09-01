import api from './api'; // Adjust the path to your api.ts file
import { 
  Faculty, 
  Department, 
  Programme, 
  RequestTypeEntity 
} from '../types'; // Adjust the path to your index.ts types file

/**
 * GET /faculties
 * Fetch all available faculties.
 */
export const fetchFaculties = async (): Promise<Faculty[]> => {
  const response = await api.get<Faculty[]>('/faculties');
  return response.data;
};

/**
 * GET /departments
 * Fetch all available departments.
 */
export const fetchDepartments = async (): Promise<Department[]> => {
  const response = await api.get<Department[]>('/departments');
  return response.data;
};

/**
 * GET /programmes
 * Fetch all available academic programmes.
 */
export const fetchProgrammes = async (): Promise<Programme[]> => {
  const response = await api.get<Programme[]>('/programmes');
  return response.data;
};

/**
 * GET /request-types
 * Fetch all student request types along with their sequence paths.
 */
export const fetchRequestTypes = async (): Promise<RequestTypeEntity[]> => {
  const response = await api.get<RequestTypeEntity[]>('/request-types');
  return response.data;
};