import apiClient from './api-client';

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
  featured_statuses: string[];
  featured_mix: {
    id: number;
    title: string;
    url: string;
    mime_type: string | null;
  } | null;
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
  sort?: 'featured' | 'new' | 'followers' | 'name';
};

export async function getDjHubDjs(query: DjHubQuery = {}): Promise<{
  djs: DjHubDj[];
  filters: DjHubFilters;
}> {
  const response = await apiClient.get('/dj-hub/djs', {
    params: {
      search: query.search || undefined,
      genre: query.genre || undefined,
      dj_type: query.dj_type || undefined,
      location: query.location || undefined,
      bookings: query.bookings || undefined,
      sort: query.sort || 'featured',
    },
  });

  return response.data;
}

export async function getDjHubDj(handle: string): Promise<DjHubDj> {
  const response = await apiClient.get(`/dj-hub/djs/${handle}`);

  return response.data.dj;
}
