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
};

async function parseJson<T>(response: Response): Promise<T> {
  const body = await response.json();

  if (!response.ok) {
    throw new Error(body?.message || 'Unable to load mixes.');
  }

  return body as T;
}

export async function getMixesIndex(): Promise<MixesIndexResponse> {
  const response = await fetch(`${API_BASE}/mixes`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });

  return parseJson<MixesIndexResponse>(response);
}

export async function trackMixPlay(slug: string): Promise<number> {
  const response = await fetch(`${API_BASE}/mixes/${slug}/play`, {
    method: 'POST',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });

  const data = await parseJson<{ play_count: number }>(response);
  return data.play_count;
}
