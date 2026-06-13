import apiClient from '@/lib/api-client';

export type RateableType = 'mixes' | 'djs' | 'songs' | 'tracks' | 'media_files';

export type RatingSummary = {
  average: number;
  count: number;
  user_rating?: number | null;
  context: string;
  id?: number;
};

export async function getRatingSummary(
  type: RateableType,
  id: string | number,
  context = 'default',
): Promise<RatingSummary> {
  const response = await apiClient.get<{ rating: RatingSummary }>(`/ratings/${type}/${id}`, {
    params: { context },
  });

  return response.data.rating;
}

export async function rateTarget(
  type: RateableType,
  id: string | number,
  rating: number,
  options: { review?: string; context?: string } = {},
): Promise<RatingSummary> {
  const response = await apiClient.post<{ rating: RatingSummary }>(`/ratings/${type}/${id}`, {
    rating,
    review: options.review,
    context: options.context ?? 'default',
  });

  return response.data.rating;
}

export async function removeRating(
  type: RateableType,
  id: string | number,
  context = 'default',
): Promise<RatingSummary> {
  const response = await apiClient.delete<{ rating: RatingSummary }>(`/ratings/${type}/${id}`, {
    params: { context },
  });

  return response.data.rating;
}
