import apiClient from '@/lib/api-client';

const API_BASE = import.meta.env?.VITE_API_BASE || '/api';

export type DjHubDj = {
  id: number;
  dj_name: string;
  handle: string;
  headline: string | null;
  bio: string | null;
  avatar_url: string | null;
  primary_genre: string | null;
  secondary_genres: string[];
  dj_type: string | null;
  location: string;
  city: string | null;
  state: string | null;
  country: string | null;
  open_for_bookings: boolean;
  followers_count: number;
  is_following: boolean;
  engagement_score: number;
  view_count: number;
  featured_slot: number | null;
  featured_statuses: string[];
  featured_mix: {
    id: number;
    title: string;
    url: string;
    mime_type: string | null;
  } | null;
  portfolio_media?: Array<{
    id: number;
    title: string;
    description: string | null;
    genre: string | null;
    kind: string | null;
    url: string;
    cover_image_url: string | null;
    mime_type: string | null;
    formatted_size: string;
    is_audio: boolean;
    is_video: boolean;
    is_image: boolean;
    created_at: string | null;
  }>;
  portfolio_stats?: {
    public_media_count: number;
    audio_count: number;
    video_count: number;
    genre_count: number;
  };
};

export type DjHubFilters = {
  genres: string[];
  dj_types: string[];
};

export type DjHubQuery = {
  search?: string;
  genre?: string;
  dj_type?: string;
  location?: string;
  bookings?: boolean;
  sort?: 'featured' | 'new' | 'followers' | 'top' | 'name';
};

async function parseJson<T>(response: Response): Promise<T> {
  const body = await response.json();

  if (!response.ok) {
    throw new Error(body?.message || 'Unable to load DJ Hub.');
  }

  return body as T;
}

function queryString(query: DjHubQuery): string {
  const params = new URLSearchParams();

  if (query.search) params.set('search', query.search);
  if (query.genre) params.set('genre', query.genre);
  if (query.dj_type) params.set('dj_type', query.dj_type);
  if (query.location) params.set('location', query.location);
  if (query.bookings) params.set('bookings', '1');
  params.set('sort', query.sort || 'featured');

  return params.toString();
}

export async function getDjHubDjs(query: DjHubQuery = {}): Promise<{
  djs: DjHubDj[];
  featured_djs: DjHubDj[];
  filters: DjHubFilters;
}> {
  const params = queryString(query);
  const response = await fetch(`${API_BASE}/dj-hub/djs${params ? `?${params}` : ''}`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });

  return parseJson(response);
}

export async function getDjHubDj(handle: string): Promise<DjHubDj> {
  const response = await fetch(`${API_BASE}/dj-hub/djs/${handle}`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });

  const data = await parseJson<{ dj: DjHubDj }>(response);
  return data.dj;
}

export type DjFollowResponse = {
  is_following: boolean;
  followers_count: number;
};

export async function followDj(handle: string): Promise<DjFollowResponse> {
  const response = await apiClient.post<DjFollowResponse>(`/dj-hub/djs/${handle}/follow`);
  return response.data;
}

export async function unfollowDj(handle: string): Promise<DjFollowResponse> {
  const response = await apiClient.delete<DjFollowResponse>(`/dj-hub/djs/${handle}/follow`);
  return response.data;
}
