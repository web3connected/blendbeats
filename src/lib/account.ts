import apiClient from './api-client';
import type { AuthUser } from './auth';

export type SaveAccountAvatarPayload = {
  useGravatar: boolean;
  avatarUrl: string;
  avatarFile: File | null;
  removeAvatar: boolean;
};

export async function saveAccountAvatar(payload: SaveAccountAvatarPayload): Promise<AuthUser> {
  const formData = new FormData();

  formData.append('is_gravatar', payload.useGravatar ? '1' : '0');
  formData.append('remove_avatar', payload.removeAvatar ? '1' : '0');

  if (payload.avatarFile) {
    formData.append('avatar', payload.avatarFile);
  } else if (payload.avatarUrl.trim()) {
    formData.append('avatar_url', payload.avatarUrl.trim());
  }

  const response = await apiClient.post<{ user: AuthUser }>('/api/auth/avatar', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });

  return response.data.user;
}
