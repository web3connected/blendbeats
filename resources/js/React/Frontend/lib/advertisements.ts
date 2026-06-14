import apiClient from '@/lib/api-client';

export type UniversalAdvertisement = {
  id: number;
  type: string;
  title: string | null;
  subtitle: string | null;
  description: string | null;
  image_url: string | null;
  url: string;
  campaign: {
    group: string;
    group_number: number;
    slot: number;
    placement_score: number;
    started_at: string | null;
    ends_at: string | null;
  };
};

export async function getDisplayAdvertisement(placement: string): Promise<UniversalAdvertisement | null> {
  const response = await apiClient.get<{ ad: UniversalAdvertisement | null }>('/ads/display', {
    params: { placement },
  });

  return response.data.ad;
}
