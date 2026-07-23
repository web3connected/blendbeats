type RequestOptions = RequestInit & {
  json?: unknown;
};

export interface AgoraLiveToken {
  appId: string;
  channelName: string;
  expiresAt: string;
  role: 'host' | 'audience';
  token: string;
  uid: number;
}

export interface LiveViewerPresence {
  count: number;
  viewers: Array<{
    user_id: number | null;
    name: string;
    is_guest: boolean;
  }>;
}

export interface LiveDj {
  id: number | null;
  name: string | null;
  dj_name: string | null;
  handle: string | null;
}

export interface LiveStream {
  id: number;
  live_channel_id: number;
  user_id: number;
  agora_channel_name: string;
  title: string;
  status: 'live' | 'ended';
  max_duration_minutes: number | null;
  started_at: string | null;
  ended_at: string | null;
  recording_enabled: boolean;
  recording_status: string | null;
  recording_started_at: string | null;
  recording_ended_at: string | null;
  recording_storage_path: string | null;
  channel?: {
    id: number;
    username_slug: string;
    title: string;
  } | null;
  dj?: LiveDj | null;
}

export interface LiveChannel {
  id: number;
  username_slug: string;
  title: string;
  description: string | null;
  is_enabled: boolean;
  dj: LiveDj;
  active_stream: LiveStream | null;
}

export interface LiveStudioState {
  can_go_live: boolean;
  limits: {
    tier: string;
    can_go_live: boolean;
    max_stream_minutes: number | null;
    monthly_stream_limit: number | null;
    can_record_live_streams: boolean;
  };
  monthly_usage: {
    used: number;
    limit: number | null;
    remaining: number | null;
  };
  channel: LiveChannel | null;
  active_stream: LiveStream | null;
}

interface ApiErrorBody {
  message?: string;
  error?: string;
  errors?: Record<string, string[]>;
}

export class LiveApiError extends Error {
  errors: Record<string, string[]>;
  status: number;

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'LiveApiError';
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

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { json, headers, ...requestOptions } = options;
  const response = await fetch(path, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(json !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...headers,
    },
    body: json !== undefined ? JSON.stringify(json) : requestOptions.body,
    ...requestOptions,
  });
  const body = await parseJson<ApiErrorBody | T>(response);

  if (!response.ok) {
    const errorBody = body as ApiErrorBody | null;

    throw new LiveApiError(
      errorBody?.message || errorBody?.error || 'Live request failed.',
      response.status,
      errorBody?.errors || {},
    );
  }

  return body as T;
}

export function getLiveDirectory(): Promise<{ streams: LiveStream[] }> {
  return request('/api/live');
}

export function getLiveChannel(usernameSlug: string): Promise<{ channel: LiveChannel }> {
  return request(`/api/live/${encodeURIComponent(usernameSlug)}`);
}

export function getLiveStudio(): Promise<LiveStudioState> {
  return request('/api/live/studio');
}

export function getLiveToken(payload: {
  role: 'host' | 'audience';
  live_stream_id?: number;
  username_slug?: string;
}): Promise<AgoraLiveToken> {
  return request('/api/live/token', {
    method: 'POST',
    json: payload,
  });
}

export function heartbeatLiveViewer(liveStreamId: number, viewerId: string): Promise<LiveViewerPresence> {
  return request(`/api/live/${liveStreamId}/viewers`, {
    method: 'POST',
    json: { viewer_id: viewerId },
  });
}

export function leaveLiveViewer(liveStreamId: number, viewerId: string): Promise<LiveViewerPresence> {
  return request(`/api/live/${liveStreamId}/viewers`, {
    method: 'DELETE',
    json: { viewer_id: viewerId },
    keepalive: true,
  });
}

export function startLive(
  title?: string,
  recordingEnabled = false,
): Promise<{ stream: LiveStream; token: AgoraLiveToken }> {
  return request('/api/live/start', {
    method: 'POST',
    json: {
      title: title?.trim() || undefined,
      recording_enabled: recordingEnabled,
    },
  });
}

export function endLive(): Promise<{ ended: boolean; stream: LiveStream | null }> {
  return request('/api/live/end', {
    method: 'POST',
  });
}
