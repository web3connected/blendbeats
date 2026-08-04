import apiClient from '@/lib/api-client';

export const MAX_MEDIA_UPLOAD_BYTES = 50 * 1024 * 1024;
export const MAX_COVER_UPLOAD_BYTES = 10 * 1024 * 1024;

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
  metadata?: Record<string, unknown> | null;
  source_type?: string | null;
  external_provider?: string | null;
  external_url?: string | null;
  embed_url?: string | null;
  thumbnail_url?: string | null;
  portfolio_title?: string | null;
  portfolio_description?: string | null;
  portfolio_genre?: string | null;
  portfolio_visibility?: string | null;
  portfolio_kind?: string | null;
  duration_seconds?: number | null;
  portfolio_cover_image_path?: string | null;
  portfolio_cover_image_url?: string | null;
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

export type MediaUpdateResponse = {
  file: MediaFileRecord;
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

export type MediaUploadDetails = {
  title: string;
  description: string;
  genre: string;
  visibility: string;
  mediaKind: string;
  sourceType?: 'upload' | 'youtube' | 'instagram';
  externalUrl?: string | null;
  durationSeconds?: number | null;
  coverImage?: File | null;
};

function toMediaManagerError(error: unknown): never {
  if (error instanceof Error && 'response' in error) {
    const response = error.response as { status?: number; data?: { message?: string; errors?: Record<string, string[]> } };

    const status = response.status || 500;
    const fallbackMessage = status === 401
      ? 'Your session has expired. Please sign in again and retry the upload.'
      : status === 413
        ? 'This upload is too large for the server. Media files must be 50 MB or smaller.'
        : status === 422
          ? 'Please correct the highlighted upload details and try again.'
          : status >= 500
            ? 'The upload service encountered a problem. Please try again in a few minutes.'
            : 'The media request could not be completed.';

    throw new MediaManagerApiError(
      response.data?.message || fallbackMessage,
      status,
      response.data?.errors || {},
    );
  }

  if (error instanceof TypeError) {
    throw new MediaManagerApiError(
      'The upload could not reach BlendBeats. Check your internet connection and try again.',
      0,
    );
  }

  throw error;
}

export function mediaManagerErrorMessage(error: unknown, fallback = 'Unable to complete the media request.'): string {
  if (!(error instanceof MediaManagerApiError)) return fallback;

  return Object.values(error.errors).flat().find(Boolean) || error.message || fallback;
}

function assertUploadSizes(file: File, coverImage?: File | null): void {
  if (file.size > MAX_MEDIA_UPLOAD_BYTES) {
    throw new MediaManagerApiError(
      `“${file.name}” is too large (${formatFileSize(file.size)}). Media files must be 50 MB or smaller.`,
      422,
      { file: ['Choose a media file that is 50 MB or smaller.'] },
    );
  }

  if (coverImage && coverImage.size > MAX_COVER_UPLOAD_BYTES) {
    throw new MediaManagerApiError(
      `“${coverImage.name}” is too large (${formatFileSize(coverImage.size)}). Cover images must be 10 MB or smaller.`,
      422,
      { cover_image: ['Choose a cover image that is 10 MB or smaller.'] },
    );
  }
}

function assertCoverSize(coverImage?: File | null): void {
  if (coverImage && coverImage.size > MAX_COVER_UPLOAD_BYTES) {
    throw new MediaManagerApiError(
      `“${coverImage.name}” is too large (${formatFileSize(coverImage.size)}). Cover images must be 10 MB or smaller.`,
      422,
      { cover_image: ['Choose a cover image that is 10 MB or smaller.'] },
    );
  }
}

function formatFileSize(bytes: number): string {
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
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

export async function uploadMediaFile(
  file: File,
  collection = 'dj_media',
  details?: MediaUploadDetails,
): Promise<MediaUploadResponse> {
  assertUploadSizes(file, details?.coverImage);
  const formData = new FormData();
  formData.append('file', file);
  formData.append('disk', 'public');
  formData.append('collection', collection);
  if (details) {
    formData.append('title', details.title);
    formData.append('description', details.description);
    formData.append('genre', details.genre);
    formData.append('visibility', details.visibility);
    formData.append('media_kind', details.mediaKind);
    if (typeof details.durationSeconds === 'number') {
      formData.append('duration_seconds', String(details.durationSeconds));
    }
    if (details.coverImage) {
      formData.append('cover_image', details.coverImage);
    }
  }

  try {
    const response = await apiClient.post<MediaUploadResponse>('/media/files', formData);
    return response.data;
  } catch (error) {
    toMediaManagerError(error);
  }
}

export async function linkYoutubeMediaFile(
  collection = 'dj_media',
  details: MediaUploadDetails & { externalUrl: string },
): Promise<MediaUploadResponse> {
  assertCoverSize(details.coverImage);
  const formData = new FormData();
  formData.append('external_url', details.externalUrl);
  formData.append('source_type', 'youtube');
  formData.append('disk', 'public');
  formData.append('collection', collection);
  formData.append('title', details.title);
  formData.append('description', details.description);
  formData.append('genre', details.genre);
  formData.append('visibility', details.visibility);
  formData.append('media_kind', details.mediaKind);

  if (typeof details.durationSeconds === 'number') {
    formData.append('duration_seconds', String(details.durationSeconds));
  }

  if (details.coverImage) {
    formData.append('cover_image', details.coverImage);
  }

  try {
    const response = await apiClient.post<MediaUploadResponse>('/media/files', formData);
    return response.data;
  } catch (error) {
    toMediaManagerError(error);
  }
}

export async function linkInstagramMediaFile(
  collection = 'dj_media',
  details: MediaUploadDetails & { externalUrl: string },
): Promise<MediaUploadResponse> {
  assertCoverSize(details.coverImage);
  const formData = new FormData();
  formData.append('external_url', details.externalUrl);
  formData.append('source_type', 'instagram');
  formData.append('disk', 'public');
  formData.append('collection', collection);
  formData.append('title', details.title);
  formData.append('description', details.description);
  formData.append('genre', details.genre);
  formData.append('visibility', details.visibility);
  formData.append('media_kind', details.mediaKind);

  if (details.coverImage) {
    formData.append('cover_image', details.coverImage);
  }

  try {
    const response = await apiClient.post<MediaUploadResponse>('/media/files', formData);
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

export async function updateMediaFile(
  fileId: number,
  details: Partial<MediaUploadDetails>,
): Promise<MediaUpdateResponse> {
  try {
    assertCoverSize(details.coverImage);
    if (details.coverImage) {
      const formData = new FormData();

      if (details.title !== undefined) formData.append('title', details.title);
      if (details.description !== undefined) formData.append('description', details.description);
      if (details.genre !== undefined) formData.append('genre', details.genre);
      if (details.visibility !== undefined) formData.append('visibility', details.visibility);
      if (details.mediaKind !== undefined) formData.append('media_kind', details.mediaKind);
      if (details.durationSeconds !== undefined && details.durationSeconds !== null) {
        formData.append('duration_seconds', String(details.durationSeconds));
      }
      if (details.externalUrl !== undefined && details.externalUrl !== null) {
        formData.append('external_url', details.externalUrl);
      }
      if (details.sourceType !== undefined && details.sourceType !== null) {
        formData.append('source_type', details.sourceType);
      }
      formData.append('cover_image', details.coverImage);

      const response = await apiClient.post<MediaUpdateResponse>(`/media/files/${fileId}`, formData);
      return response.data;
    }

    const response = await apiClient.patch<MediaUpdateResponse>(`/media/files/${fileId}`, {
      title: details.title,
      description: details.description,
      genre: details.genre,
      visibility: details.visibility,
      media_kind: details.mediaKind,
      duration_seconds: details.durationSeconds,
      external_url: details.externalUrl,
      source_type: details.sourceType,
    });

    return response.data;
  } catch (error) {
    toMediaManagerError(error);
  }
}
