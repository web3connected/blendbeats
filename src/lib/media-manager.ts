import axios from 'axios';

import apiClient from '@/lib/api-client';

export type MediaFileRecord = {
  id: number;
  name: string;
  original_name: string | null;
  path: string;
  disk: string;
  mime_type: string | null;
  size: number;
  formatted_size: string;
  collection: string | null;
  url: string;
  is_image: boolean;
  is_video: boolean;
  is_audio: boolean;
  is_pdf: boolean;
  created_at: string;
};

export type MediaStorageQuota = {
  tier: string;
  tier_label: string;
  limit_bytes: number;
  limit_formatted: string;
  used_bytes: number;
  used_formatted: string;
  remaining_bytes: number;
  remaining_formatted: string;
  usage_percent: number;
};

export type MediaFilesResponse = {
  files: MediaFileRecord[];
  quota: MediaStorageQuota;
};

export type MediaUploadResponse = {
  file: MediaFileRecord;
  quota: MediaStorageQuota;
};

export type MediaDeleteResponse = {
  deleted: boolean;
  quota: MediaStorageQuota;
};

export class MediaManagerApiError extends Error {
  status: number;
  errors: Record<string, string[]>;

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'MediaManagerApiError';
    this.status = status;
    this.errors = errors;
  }
}

function toMediaManagerError(error: unknown): never {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;

    throw new MediaManagerApiError(
      data?.message || 'Media manager is not available right now.',
      error.response?.status || 500,
      data?.errors || {},
    );
  }

  throw error;
}

export async function listMediaFiles(collection?: string): Promise<MediaFileRecord[]> {
  try {
    const response = await apiClient.get<MediaFilesResponse>('/media/files', {
      params: {
        disk: 'public',
        collection,
      },
    });

    return response.data.files;
  } catch (error) {
    toMediaManagerError(error);
  }
}

export async function listMediaLibrary(collection?: string): Promise<MediaFilesResponse> {
  try {
    const response = await apiClient.get<MediaFilesResponse>('/media/files', {
      params: {
        disk: 'public',
        collection,
      },
    });

    return response.data;
  } catch (error) {
    toMediaManagerError(error);
  }
}

export async function uploadMediaFile(file: File, collection = 'dj_media'): Promise<MediaUploadResponse> {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('disk', 'public');
  formData.append('collection', collection);

  try {
    const response = await apiClient.post<MediaUploadResponse>('/media/files', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      timeout: 60000,
    });

    return response.data;
  } catch (error) {
    toMediaManagerError(error);
  }
}

export async function deleteMediaFile(fileId: number): Promise<MediaDeleteResponse> {
  try {
    const response = await apiClient.delete<MediaDeleteResponse>(`/media/files/${fileId}`);
    return response.data;
  } catch (error) {
    toMediaManagerError(error);
  }
}
