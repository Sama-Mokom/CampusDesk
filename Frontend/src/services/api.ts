import axios from 'axios';

// Create the axios instance
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  timeout: 10000,
  headers: {
    Accept: 'application/json',
    // Remove Content-Type — let Axios set it per request
    // JSON requests will default to application/json automatically
    // FormData requests will set multipart/form-data with boundary automatically
  },
});

//Request interceptor
api.interceptors.request.use(
  (config) => {
    // Retrieve your token from localStorage (adjust the key 'token' if yours is named differently)
    const token = localStorage.getItem('token');
    
    // If a token exists, inject it into the Authorization header
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    
    return config;
  },
  (error) => {
    // Handle request errors here
    return Promise.reject(error);
  }
);


//Response interceptor
api.interceptors.response.use(
  (response) => {
    // If the request succeeded, just pass the response through
    return response;
  },
  (error) => {
    // Check if the server returned a 401 Unauthorized error
    if (error.response && error.response.status === 401) {
      // Clear token (and any other auth data) from localStorage
      localStorage.removeItem('token');
      // Force a page reload or redirect to the login page
       window.location.href = '/login';
    }
    
    // Pass the error back to the calling function so it can still be handled locally if needed
    return Promise.reject(error);
  }
);

export default api;