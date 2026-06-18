import apiClient from '@/lib/api-client';

const DISPLAY_AD_CACHE_TTL_MS = 60 * 1000;
const DISPLAY_AD_CACHE_PREFIX = 'blendbeats.display_ad.v2.';

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

type CachedDisplayAdvertisement = {
  expires_at: number;
  ad: UniversalAdvertisement;
};

function cacheKey(placement: string) {
  return `${DISPLAY_AD_CACHE_PREFIX}${placement}`;
}

function readCachedDisplayAdvertisement(placement: string): UniversalAdvertisement | null {
  try {
    const raw = window.localStorage.getItem(cacheKey(placement));
    if (!raw) return null;

    const cached = JSON.parse(raw) as Partial<CachedDisplayAdvertisement>;

    if (!cached.ad || !cached.expires_at || cached.expires_at <= Date.now() || !isDisplayAdvertisementCurrent(cached.ad)) {
      window.localStorage.removeItem(cacheKey(placement));
      return null;
    }

    return cached.ad;
  } catch {
    return null;
  }
}

function writeCachedDisplayAdvertisement(placement: string, ad: UniversalAdvertisement | null) {
  if (!ad || !isDisplayAdvertisementCurrent(ad)) return;

  try {
    window.localStorage.setItem(
      cacheKey(placement),
      JSON.stringify({
        expires_at: Date.now() + DISPLAY_AD_CACHE_TTL_MS,
        ad,
      } satisfies CachedDisplayAdvertisement),
    );
  } catch {
    // Local storage is a display optimization only.
  }
}

function isDisplayAdvertisementCurrent(ad: UniversalAdvertisement): boolean {
  const now = Date.now();
  const startsAt = ad.campaign.started_at ? Date.parse(ad.campaign.started_at) : null;
  const endsAt = ad.campaign.ends_at ? Date.parse(ad.campaign.ends_at) : null;

  if (startsAt && startsAt > now) return false;
  if (endsAt && endsAt <= now) return false;

  return true;
}

export async function getDisplayAdvertisement(placement: string): Promise<UniversalAdvertisement | null> {
  const cachedAd = readCachedDisplayAdvertisement(placement);
  if (cachedAd) return cachedAd;

  const response = await apiClient.get<{ ad: UniversalAdvertisement | null }>('/ads/display', {
    params: { placement },
  });

  writeCachedDisplayAdvertisement(placement, response.data.ad);

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
