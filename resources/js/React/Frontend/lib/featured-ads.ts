import apiClient from '@/lib/api-client';

export type FeaturedAdOption = {
  id: number;
  name: string;
  description: string | null;
  duration_days: number;
  price_cents: number;
  price_label: string;
};

export type FeaturedAdCampaign = {
  id: number;
  slot_number: number;
  group: string;
  option_name: string | null;
  duration_days: number | null;
  amount_cents: number;
  amount_label: string;
  currency: string;
  payment_provider: string | null;
  payment_status: string;
  status: string;
  start_date: string | null;
  end_date: string | null;
  dj?: {
    name: string;
    handle: string;
  } | null;
};

export type FeaturedAdSlot = {
  number: number;
  group: string;
  group_number: number;
  position: number;
  daily_price_cents: number;
  daily_price_label: string;
  is_unlocked: boolean;
  is_available: boolean;
  active_campaign: FeaturedAdCampaign | null;
  options: FeaturedAdOption[];
};

export type FeaturedAdsPlacementsResponse = {
  membership: {
    tier: string;
    groups: string[];
  };
  slots: FeaturedAdSlot[];
  my_campaigns: FeaturedAdCampaign[];
  payment_provider: {
    provider: string;
    display_name: string;
    mode: string;
    credentials_ready: boolean;
  } | null;
};

export type FeaturedAdCheckoutResponse = {
  campaign: FeaturedAdCampaign;
  checkout_url: string | null;
};

export class FeaturedAdsApiError extends Error {
  status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = 'FeaturedAdsApiError';
    this.status = status;
  }
}

function toFeaturedAdsError(error: unknown): never {
  if (error instanceof Error && 'response' in error) {
    const response = error.response as { status?: number; data?: { message?: string } };

    throw new FeaturedAdsApiError(
      response.data?.message || 'Featured ads are not available right now.',
      response.status || 500,
    );
  }

  throw error;
}

export async function getFeaturedAdPlacements(): Promise<FeaturedAdsPlacementsResponse> {
  try {
    const response = await apiClient.get<FeaturedAdsPlacementsResponse>('/featured-ads/placements');
    return response.data;
  } catch (error) {
    toFeaturedAdsError(error);
  }
}

export async function startFeaturedAdCheckout(
  slotNumber: number,
  campaignOptionId: number,
): Promise<FeaturedAdCheckoutResponse> {
  try {
    const response = await apiClient.post<FeaturedAdCheckoutResponse>('/featured-ads/checkout', {
      slot_number: slotNumber,
      campaign_option_id: campaignOptionId,
    });

    return response.data;
  } catch (error) {
    toFeaturedAdsError(error);
  }
}

export async function captureFeaturedAdCampaign(campaignId: number): Promise<{ campaign: FeaturedAdCampaign }> {
  try {
    const response = await apiClient.post<{ campaign: FeaturedAdCampaign }>(`/featured-ads/campaigns/${campaignId}/capture`);
    return response.data;
  } catch (error) {
    toFeaturedAdsError(error);
  }
}
