import axios from 'axios';

// Create the axios instance
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  // The local Laravel development server handles requests serially. The admin
  // dashboard needs several initial API calls, so allow a queued request enough
  // time to reach the server rather than cancelling it after ten seconds.
  timeout: 30000,
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
    if (error.response?.status === 401 && localStorage.getItem('token')) {
      // A request made with a stale token invalidates the persisted session.
      // Do not redirect guest requests: doing so remounts the login page and
      // can create a request/reload loop.
      localStorage.removeItem('token');
      localStorage.removeItem('user');

      if (window.location.pathname !== '/login') {
        window.location.replace('/login');
      }
    }
    
    // Pass the error back to the calling function so it can still be handled locally if needed
    return Promise.reject(error);
  }
);

export default api;
