const API_BASE = import.meta.env?.VITE_API_BASE || '/api';

export type DjScratch = {
  id: number;
  title: string;
  description: string | null;
  genre: string | null;
  url: string;
  cover_image_url: string | null;
  source_type?: string | null;
  external_provider?: string | null;
  external_url?: string | null;
  embed_url?: string | null;
  thumbnail_url?: string | null;
  mime_type: string | null;
  duration_seconds: number;
  formatted_size: string;
  created_at: string | null;
  dj: {
    id: number | null;
    name: string;
    handle: string | null;
    headline: string | null;
    avatar_url: string | null;
  };
};

export type DjScratchStats = {
  scratch_count: number;
  dj_count: number;
  genre_count: number;
};

export type DjScratchesResponse = {
  scratches: DjScratch[];
  stats: DjScratchStats;
  genres: string[];
};

export type DjScratchesQuery = {
  search?: string;
  genre?: string;
};

async function parseJson<T>(response: Response): Promise<T> {
  const body = await response.json();

  if (!response.ok) {
    throw new Error(body?.message || 'Unable to load Scratch Routines.');
  }

  return body as T;
}

function queryString(query: DjScratchesQuery): string {
  const params = new URLSearchParams();

  if (query.search) params.set('search', query.search);
  if (query.genre) params.set('genre', query.genre);

  return params.toString();
}

export async function getDjScratches(query: DjScratchesQuery = {}): Promise<DjScratchesResponse> {
  const params = queryString(query);
  const response = await fetch(`${API_BASE}/dj-scratches${params ? `?${params}` : ''}`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });

  return parseJson(response);
}
