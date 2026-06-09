import axios from 'axios';

const apiClient = axios.create({
  baseURL: import.meta.env?.VITE_AUTH_API_BASE || '/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
});

apiClient.interceptors.request.use((config) => {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (token) config.headers['X-CSRF-TOKEN'] = token;
  return config;
});

export async function checkHealth() {
  const response = await apiClient.get('/health');
  return response.data;
}

export default apiClient;
