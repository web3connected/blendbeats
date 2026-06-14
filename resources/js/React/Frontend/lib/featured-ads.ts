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
  campaign_title?: string | null;
  campaign_slot_id?: number | null;
  group_slot_number?: number | null;
  group: string;
  campaign_option_id: number | null;
  option_name: string | null;
  duration_days: number | null;
  amount_cents: number;
  amount_label: string;
  currency: string;
  payment_provider: string | null;
  payment_status: string;
  status: string;
  is_mine: boolean;
  approval_url: string | null;
  start_date: string | null;
  end_date: string | null;
  dj?: {
    name: string;
    handle: string;
  } | null;
};

export type FeaturedCampaignSlot = {
  id: number;
  campaign_id: number;
  group: string;
  group_number: number;
  group_slot_number: number;
  template_slot_number: number;
  daily_price_cents: number;
  daily_price_label: string;
  exposure_percent: number;
  rotation_weight: number;
  claim_status: string;
  is_unlocked: boolean;
  is_available: boolean;
  active_campaign: FeaturedAdCampaign | null;
  options: FeaturedAdOption[];
};

export type FeaturedMarketplaceCampaign = {
  id: number;
  title: string;
  description: string | null;
  status: string;
  group: string;
  group_name: string;
  group_number: number;
  template_type: string;
  slot_count: number;
  daily_price_cents: number;
  daily_price_label: string;
  daily_price_range_label: string;
  is_unlocked: boolean;
  slots: FeaturedCampaignSlot[];
};

export type FeaturedAdsPlacementsResponse = {
  membership: {
    tier: string;
    groups: string[];
  };
  campaigns: FeaturedMarketplaceCampaign[];
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
  campaignSlotId: number,
  campaignOptionId: number,
  startDate: string,
): Promise<FeaturedAdCheckoutResponse> {
  try {
    const response = await apiClient.post<FeaturedAdCheckoutResponse>('/featured-ads/checkout', {
      campaign_slot_id: campaignSlotId,
      campaign_option_id: campaignOptionId,
      start_date: startDate,
    });

    return response.data;
  } catch (error) {
    toFeaturedAdsError(error);
  }
}

export async function restartFeaturedAdCheckout(
  campaignId: number,
  campaignOptionId: number,
  startDate: string,
): Promise<FeaturedAdCheckoutResponse> {
  try {
    const response = await apiClient.post<FeaturedAdCheckoutResponse>(`/featured-ads/campaigns/${campaignId}/checkout`, {
      campaign_option_id: campaignOptionId,
      start_date: startDate,
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
