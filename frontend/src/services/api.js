import axios from 'axios';
import Swal from 'sweetalert2';

const api = axios.create({
  baseURL: 'http://localhost:8000/api', // Laravel standard API endpoint
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// Request interceptor to attach token
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor to handle auth errors
api.interceptors.response.use(
  response => response,
  error => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    } else if (error.response && error.response.status === 403) {
      Swal.fire({
        icon: 'warning',
        title: 'Límite de Plan',
        text: error.response.data.message || 'No tienes acceso para realizar esta acción.',
        confirmButtonText: 'Membresías',
        showCancelButton: true,
        cancelButtonText: 'Entendido',
        confirmButtonColor: 'var(--accent)',
        cancelButtonColor: 'var(--surface-2)',
        background: 'var(--surface)',
        color: 'var(--text)',
        customClass: { 
          popup: 'swal-custom-popup', 
          title: 'swal-custom-title', 
          htmlContainer: 'swal-custom-content', 
          confirmButton: 'swal-custom-confirm',
          cancelButton: 'swal-custom-cancel'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          window.dispatchEvent(new CustomEvent('open-membership-modal'));
        }
      });
    }
    return Promise.reject(error);
  }
);

export default api;
