import api from './api';

import {LoginCredentials, RegisterCredentials, AuthResponse, User} from '../types';


//LOGIN USERS
export const login = async (credentials: LoginCredentials) : Promise<User> => {
    const response = await api.post<AuthResponse>('/login', credentials);
    const { token, user } = response.data;

   localStorage.setItem('token', token);

   return user;
}

//REGISTER USERS
export const register = async (credentials: RegisterCredentials): Promise<User> => {
  // 1. Send registration data to the backend
  const response = await api.post<AuthResponse>('/register', {
    ...credentials,
    password_confirmation: credentials.password,
});
  const { token, user } = response.data;

  // 2. Save the new token
  localStorage.setItem('token', token);

  return user;
};

//LOGOUT USERS
export const logout = async (): Promise<void> => {
  try {
    // 1. Tell your backend to revoke/expire the token
    await api.post('/logout');
  } catch (error) {
    // Even if the backend request fails (e.g., token already expired), 
    // we still want to clean up local state.
    console.error('Backend logout failed:', error);
  } finally {
    // 2. Always clear the token locally to log the user out on the frontend
    localStorage.removeItem('token');
  }
};