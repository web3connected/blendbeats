const API_BASE = import.meta.env?.VITE_AUTH_API_BASE || '/api';

export interface AuthUser {
  id: number;
  name: string;
  first_name?: string | null;
  last_name?: string | null;
  email: string;
  avatar?: string | null;
  avatar_url?: string | null;
  gravatar_url?: string | null;
  custom_avatar_url?: string | null;
  generated_avatar_url?: string | null;
  avatar_source?: 'gravatar' | 'url' | 'uploaded' | 'generated' | string | null;
  is_gravatar?: boolean | null;
  use_gravatar?: boolean | null;
  media_storage_tier?: string | null;
  dj_profile?: {
    id: number;
    dj_name: string;
    handle: string;
    profile_status: string;
    visibility: string;
  } | null;
  profile?: {
    contact_email?: string | null;
    phone?: string | null;
    city?: string | null;
    state?: string | null;
    country?: string | null;
    postal_code?: string | null;
    timezone?: string | null;
    website_url?: string | null;
    instagram_url?: string | null;
    youtube_url?: string | null;
    soundcloud_url?: string | null;
    spotify_url?: string | null;
    bio?: string | null;
    birthdate?: string | null;
    marketing_opt_in?: boolean | null;
  } | null;
}

interface AuthResponse {
  user: AuthUser | null;
}

interface ApiErrorBody {
  message?: string;
  error?: string;
  errors?: Record<string, string[]>;
}

export class ApiAuthError extends Error {
  errors: Record<string, string[]>;
  status: number;

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'ApiAuthError';
    this.status = status;
    this.errors = errors;
  }
}

async function parseJson<T>(response: Response): Promise<T | null> {
  const text = await response.text();
  if (!text) return null;
  try {
    return JSON.parse(text) as T;
  } catch {
    return null;
  }
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API_BASE}${path}`, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...options.headers,
    },
    ...options,
  });

  const body = await parseJson<ApiErrorBody | T>(response);

  if (!response.ok) {
    const errorBody = body as ApiErrorBody | null;
    throw new ApiAuthError(
      errorBody?.message || errorBody?.error || 'Something went wrong. Please try again.',
      response.status,
      errorBody?.errors || {},
    );
  }

  return body as T;
}

export async function getCurrentUser(): Promise<AuthUser | null> {
  try {
    const data = await request<AuthResponse>('/auth/me');
    return data.user;
  } catch (error) {
    if (error instanceof ApiAuthError && [401, 404].includes(error.status)) return null;
    throw error;
  }
}

export async function loginUser(email: string, password: string): Promise<AuthUser> {
  const data = await request<AuthResponse>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });

  if (!data.user) throw new ApiAuthError('Login succeeded, but no user was returned.', 200);
  return data.user;
}

export async function registerUser(
  name: string,
  email: string,
  password: string,
  passwordConfirmation: string,
): Promise<AuthUser> {
  const data = await request<AuthResponse>('/auth/register', {
    method: 'POST',
    body: JSON.stringify({
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    }),
  });

  if (!data.user) throw new ApiAuthError('Registration succeeded, but no user was returned.', 201);
  return data.user;
}

export async function logoutUser(): Promise<void> {
  await request<{ ok: boolean }>('/auth/logout', { method: 'POST' });
}

export async function requestPasswordReset(email: string): Promise<void> {
  try {
    await request<{ ok?: boolean; message?: string }>('/auth/forgot-password', {
      method: 'POST',
      body: JSON.stringify({ email }),
    });
  } catch (error) {
    if (error instanceof ApiAuthError && error.status === 404) return;
    throw error;
  }
}

export async function resetPassword(
  token: string,
  email: string,
  password: string,
  passwordConfirmation: string,
): Promise<void> {
  await request<{ ok?: boolean; message?: string }>('/auth/reset-password', {
    method: 'POST',
    body: JSON.stringify({
      token,
      email,
      password,
      password_confirmation: passwordConfirmation,
    }),
  });
}
