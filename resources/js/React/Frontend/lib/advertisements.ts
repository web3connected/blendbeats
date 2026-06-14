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

export function trackAdvertisementEvent(ad: UniversalAdvertisement, placement: string, eventType: 'impression' | 'click') {
  const payload = JSON.stringify({
    ad_id: ad.id,
    ad_type: ad.type,
    event_type: eventType,
    placement,
    metadata: {
      group: ad.campaign.group,
      group_number: ad.campaign.group_number,
      slot: ad.campaign.slot,
      placement_score: ad.campaign.placement_score,
      path: window.location.pathname,
    },
  });

  if (navigator.sendBeacon) {
    const blob = new Blob([payload], { type: 'application/json' });
    if (navigator.sendBeacon('/api/ads/events', blob)) return;
  }

  void fetch('/api/ads/events', {
    method: 'POST',
    credentials: 'include',
    keepalive: true,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: payload,
  }).catch(() => undefined);
}
