const API_BASE = import.meta.env?.VITE_AUTH_API_BASE || '/api';

export type DjLoungePostType = 'text' | 'mix_update' | 'battle_callout' | 'question';

export interface DjLoungePost {
  id: string;
  authorName: string;
  handle: string;
  avatarInitial: string;
  role: string;
  timestamp: string;
  body: string;
  genre: string;
  mediaTitle?: string | null;
  mediaUrl?: string | null;
  mediaMeta?: string | null;
  likes: number;
  comments: number;
  reposts: number;
  bookmarks: number;
  isLive?: boolean;
  liked?: boolean;
  reposted?: boolean;
  bookmarked?: boolean;
}

interface PostsResponse {
  posts: DjLoungePost[];
}

interface PostResponse {
  post: DjLoungePost;
}

interface ApiErrorBody {
  message?: string;
  errors?: Record<string, string[]>;
}

export class DjLoungeApiError extends Error {
  errors: Record<string, string[]>;
  status: number;

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'DjLoungeApiError';
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

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API_BASE}${path}`, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...options.headers,
    },
    ...options,
  });

  const body = await parseJson<ApiErrorBody | T>(response);

  if (!response.ok) {
    const errorBody = body as ApiErrorBody | null;
    throw new DjLoungeApiError(
      errorBody?.message || 'DJLounge is not available right now.',
      response.status,
      errorBody?.errors || {},
    );
  }

  return body as T;
}

export async function getDjLoungePosts(): Promise<DjLoungePost[]> {
  const data = await request<PostsResponse>('/dj-lounge/posts');
  return data.posts;
}

export async function createDjLoungePost(body: string): Promise<DjLoungePost> {
  const data = await request<PostResponse>('/dj-lounge/posts', {
    method: 'POST',
    body: JSON.stringify({ body }),
  });

  return data.post;
}

export async function toggleDjLoungePostReaction(postId: string): Promise<{ liked: boolean; like_count: number }> {
  return request(`/dj-lounge/posts/${postId}/reaction`, { method: 'POST' });
}

export async function toggleDjLoungePostRepost(postId: string): Promise<{ reposted: boolean; repost_count: number }> {
  return request(`/dj-lounge/posts/${postId}/repost`, { method: 'POST' });
}

export async function toggleDjLoungePostBookmark(
  postId: string,
): Promise<{ bookmarked: boolean; bookmark_count: number }> {
  return request(`/dj-lounge/posts/${postId}/bookmark`, { method: 'POST' });
}
