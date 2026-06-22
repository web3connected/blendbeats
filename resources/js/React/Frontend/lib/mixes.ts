import { incrementCounter } from '@/lib/counters';

const API_BASE = import.meta.env?.VITE_API_BASE || '/api';

export type PublicMix = {
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  genre?: string | null;
  audio_url?: string | null;
  cover_image_url?: string | null;
  duration?: number | null;
  is_featured: boolean;
  play_count: number;
  rating_average: number;
  rating_count: number;
  published_at?: string | null;
  created_at?: string | null;
  dj: {
    id?: number | null;
    name: string;
  };
};

export type GenreMixRow = {
  genre: string;
  mixes: PublicMix[];
};

export type MixesStats = {
  featured_mixes: number;
  total_plays: number;
  average_rating: number;
  genre_count: number;
};

export type MixesIndexResponse = {
  stats: MixesStats;
  featured: PublicMix[];
  mixes: PublicMix[];
  genres: GenreMixRow[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
    has_more_pages: boolean;
  };
};

async function parseJson<T>(response: Response): Promise<T> {
  const body = await response.json();

  if (!response.ok) {
    throw new Error(body?.message || 'Unable to load mixes.');
  }

  return body as T;
}

export async function getMixesIndex(page = 1): Promise<MixesIndexResponse> {
  const params = new URLSearchParams({
    page: String(page),
    per_page: '25',
  });
  const response = await fetch(`${API_BASE}/mixes?${params.toString()}`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });

  return parseJson<MixesIndexResponse>(response);
}

export async function trackMixPlay(slug: string): Promise<number> {
  const data = await incrementCounter('mixes', slug, 'play');
  return data.count;
}
