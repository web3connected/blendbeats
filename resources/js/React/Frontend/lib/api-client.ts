const API_BASE = import.meta.env?.VITE_AUTH_API_BASE || '/api';

type RequestOptions = RequestInit & {
  params?: Record<string, string | number | boolean | undefined | null>;
};

function buildUrl(path: string, params?: RequestOptions['params']) {
  const normalizedPath = path.startsWith('/api/') ? path.replace(/^\/api/, '') : path;
  const url = new URL(`${API_BASE}${normalizedPath}`, window.location.origin);

  Object.entries(params || {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  });

  return url.pathname + url.search;
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<{ data: T }> {
  const isFormData = options.body instanceof FormData;
  const response = await fetch(buildUrl(path, options.params), {
    credentials: 'include',
    ...options,
    headers: {
      Accept: 'application/json',
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...options.headers,
    },
  });

  const text = await response.text();
  let data: unknown = null;

  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = { message: text.trim().startsWith('<') ? '' : text.trim() };
    }
  }

  if (!response.ok) {
    const responseData = data as { message?: string } | null;
    const statusMessage = response.status === 401
      ? 'Your session has expired. Please sign in again and retry the upload.'
      : response.status === 413
        ? 'The selected file is larger than the server allows.'
        : response.status >= 500
          ? 'The server could not complete the request. Please try again in a few minutes.'
          : 'Request failed.';
    const error = new Error(responseData?.message || statusMessage) as Error & {
      response?: { status: number; data: unknown };
    };
    error.response = { status: response.status, data };
    throw error;
  }

  return { data: data as T };
}

const apiClient = {
  get: <T>(path: string, options: RequestOptions = {}) => request<T>(path, { ...options, method: 'GET' }),
  post: <T>(path: string, body?: unknown, options: RequestOptions = {}) =>
    request<T>(path, {
      ...options,
      method: 'POST',
      body: body instanceof FormData ? body : JSON.stringify(body || {}),
    }),
  patch: <T>(path: string, body?: unknown, options: RequestOptions = {}) =>
    request<T>(path, {
      ...options,
      method: 'PATCH',
      body: body instanceof FormData ? body : JSON.stringify(body || {}),
    }),
  delete: <T>(path: string, options: RequestOptions = {}) => request<T>(path, { ...options, method: 'DELETE' }),
};

export async function checkHealth() {
  const response = await apiClient.get('/health');
  return response.data;
}

export default apiClient;
