import axios from 'axios';

import apiClient from '@/lib/api-client';

export type DjProfilePayload = {
  dj_name: string;
  handle: string;
  profile_headline?: string;
  bio: string;
  banner_url?: string;
  primary_genre: string;
  secondary_genres: string[];
  dj_type?: string;
  city?: string;
  state?: string;
  country?: string;
  website?: string;
  instagram?: string;
  tiktok?: string;
  youtube?: string;
  soundcloud?: string;
  mixcloud?: string;
  twitch?: string;
  spotify?: string;
  available_for_bookings: boolean;
  booking_email?: string;
  visibility: string;
};

export type DjProfileResponse = DjProfilePayload & {
  id: number;
  profile_status: string;
};

export class DjProfileApiError extends Error {
  errors: Record<string, string[]>;
  status: number;

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'DjProfileApiError';
    this.status = status;
    this.errors = errors;
  }
}

export async function getDjProfile(): Promise<DjProfileResponse> {
  try {
    const response = await apiClient.get<{ dj_profile: DjProfileResponse }>('/dj/profile');
    return response.data.dj_profile;
  } catch (error) {
    if (axios.isAxiosError(error)) {
      const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
      throw new DjProfileApiError(
        data?.message || 'Unable to load DJ profile right now.',
        error.response?.status || 500,
        data?.errors || {},
      );
    }

    throw error;
  }
}

export async function saveDjProfile(payload: DjProfilePayload): Promise<void> {
  try {
    await apiClient.post('/dj/profile', payload);
  } catch (error) {
    if (axios.isAxiosError(error)) {
      const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
      throw new DjProfileApiError(
        data?.message || 'Unable to save DJ profile right now.',
        error.response?.status || 500,
        data?.errors || {},
      );
    }

    throw error;
  }
}
