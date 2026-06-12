import apiClient from '@/lib/api-client';
import type { MediaStorageQuota } from '@/lib/media-manager';

export type MediaAccount = {
  id: number;
  account_slug: string;
  disk: string;
  root_path: string;
  storage_tier: string;
  storage_limit_bytes: number;
  storage_used_bytes: number;
  status: 'active' | 'suspended' | 'disabled';
  activated_at: string | null;
};

export type UserFeatureActivation = {
  feature_key: string;
  status: 'active' | 'paused' | 'disabled';
  source: string | null;
  metadata: Record<string, unknown> | null;
  activated_at: string | null;
};

export type MediaSetupResponse = {
  media_account: MediaAccount | null;
  quota: MediaStorageQuota;
  features: UserFeatureActivation[];
};

export class MediaSetupApiError extends Error {
  status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = 'MediaSetupApiError';
    this.status = status;
  }
}

function toMediaSetupError(error: unknown): never {
  if (error instanceof Error && 'response' in error) {
    const response = error.response as { status?: number; data?: { message?: string } };
    throw new MediaSetupApiError(
      response.data?.message || 'Media setup is not available right now.',
      response.status || 500,
    );
  }

  throw error;
}

export async function getMediaSetup(): Promise<MediaSetupResponse> {
  try {
    const response = await apiClient.get<MediaSetupResponse>('/media/setup');
    return response.data;
  } catch (error) {
    toMediaSetupError(error);
  }
}

export async function activateMediaSetup(): Promise<MediaSetupResponse> {
  try {
    const response = await apiClient.post<MediaSetupResponse>('/media/setup');
    return response.data;
  } catch (error) {
    toMediaSetupError(error);
  }
}
