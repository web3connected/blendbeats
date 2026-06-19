import apiClient from './api-client';
import type { PublicMix } from './mixes';

export type UserPlaylistItem = {
  id: number;
  added_at: string | null;
  mix: PublicMix;
};

export type UserPlaylistResponse = {
  playlist: UserPlaylistItem[];
};

export class UserPlaylistApiError extends Error {
  constructor(message: string, public readonly status?: number) {
    super(message);
    this.name = 'UserPlaylistApiError';
  }
}

function toPlaylistError(error: unknown): never {
  if (error instanceof Error && 'response' in error) {
    const response = (error as Error & { response?: { status: number; data?: { message?: string } } }).response;
    throw new UserPlaylistApiError(response?.data?.message || error.message, response?.status);
  }

  throw new UserPlaylistApiError(error instanceof Error ? error.message : 'Playlist request failed.');
}

export async function getUserPlaylist(): Promise<UserPlaylistResponse> {
  try {
    const response = await apiClient.get<UserPlaylistResponse>('/user-playlist');
    return response.data;
  } catch (error) {
    toPlaylistError(error);
  }
}

export async function savePlaylistMix(mixId: number): Promise<{ item: UserPlaylistItem }> {
  try {
    const response = await apiClient.post<{ item: UserPlaylistItem }>(`/user-playlist/mixes/${mixId}`);
    return response.data;
  } catch (error) {
    toPlaylistError(error);
  }
}

export async function removePlaylistMix(mixId: number): Promise<{ ok: boolean }> {
  try {
    const response = await apiClient.delete<{ ok: boolean }>(`/user-playlist/mixes/${mixId}`);
    return response.data;
  } catch (error) {
    toPlaylistError(error);
  }
}
