import apiClient from './api-client';
import type { AuthUser } from './auth';

export type SaveAccountAvatarPayload = {
  useGravatar: boolean;
  avatarUrl: string;
  avatarFile: File | null;
  removeAvatar: boolean;
};

export type SaveAccountProfilePayload = {
  first_name: string;
  last_name: string;
  name: string;
  email: string;
  contact_email: string;
  phone: string;
  birthdate: string;
  timezone: string;
  city: string;
  state: string;
  country: string;
  postal_code: string;
  website_url: string;
  instagram_url: string;
  youtube_url: string;
  soundcloud_url: string;
  spotify_url: string;
  bio: string;
  marketing_opt_in: boolean;
};

export async function saveAccountProfile(payload: SaveAccountProfilePayload): Promise<AuthUser> {
  const response = await apiClient.patch<{ user: AuthUser }>('/auth/account', payload);

  return response.data.user;
}

export async function saveAccountAvatar(payload: SaveAccountAvatarPayload): Promise<AuthUser> {
  const formData = new FormData();

  formData.append('is_gravatar', payload.useGravatar ? '1' : '0');
  formData.append('remove_avatar', payload.removeAvatar ? '1' : '0');

  if (payload.avatarFile) {
    formData.append('avatar', payload.avatarFile);
  } else if (payload.avatarUrl.trim()) {
    formData.append('avatar_url', payload.avatarUrl.trim());
  }

  // Indicate that the client intends the uploaded file to be stored under the media accounts path
  // so the server can place it under media/accounts/{account_slug}/avatar
  formData.append('store_in_media', '1');

  const response = await apiClient.post<{ user: AuthUser }>('/auth/avatar', formData);

  return response.data.user;
}
