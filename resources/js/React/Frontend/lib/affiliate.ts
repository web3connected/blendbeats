import apiClient from '@/lib/api-client';

export type AffiliateAccount = {
  id: number;
  status: string;
  display_name: string;
  contact_email: string | null;
  referral_code: string | null;
  referral_link: string | null;
  joined_at: string | null;
  approved_at: string | null;
  paused_at: string | null;
  banned_at: string | null;
};

export type AffiliateRegistrationOutputs = {
  affiliate_account_created: boolean;
  affiliate_status_assigned: string;
  affiliate_profile_established: boolean;
};

export type AffiliateAccountResponse = {
  affiliate_account: AffiliateAccount | null;
  outputs?: AffiliateRegistrationOutputs;
};

export type AffiliateReferralStatistics = {
  visits: number;
  signups: number;
  conversion_rate: number;
};

export type AffiliateQualificationStatistics = {
  total: number;
  pending: number;
  qualified: number;
  rejected: number;
  qualification_rate: number;
};

export type AffiliateRewardStatistics = {
  total: number;
  pending: number;
  approved: number;
  issued: number;
  paid: number;
  redeemed: number;
  expired: number;
  cancelled: number;
  voided: number;
  membership_credits_available: number;
  membership_credit_days_available: number;
  total_amount_cents: number;
  total_amount_label: string;
  total_points: number;
};

export type AffiliatePayoutBalance = {
  amount_cents: number;
  amount_label: string;
  currency: string;
  reward_count: number;
  can_request_payout: boolean;
};

export type AffiliatePayoutStatistics = {
  total: number;
  requested: number;
  approved: number;
  processing: number;
  paid: number;
  rejected: number;
  cancelled: number;
  total_requested_cents: number;
  total_paid_cents: number;
};

export type AffiliatePayoutRecord = {
  id: number;
  status: string;
  amount_cents: number;
  amount_label: string;
  currency: string;
  reward_count: number;
  payment_method: string | null;
  payout_reference: string | null;
  requested_at: string | null;
  approved_at: string | null;
  processing_at: string | null;
  paid_at: string | null;
  rejected_at: string | null;
  cancelled_at: string | null;
  rejection_reason: string | null;
  notes: string | null;
};

export type AffiliateActivityItem = {
  type: string;
  label: string;
  description: string;
  occurred_at: string | null;
  metadata: Record<string, unknown>;
};

export type AffiliateSummaryResponse = {
  affiliate_account: AffiliateAccount | null;
  referral_statistics: AffiliateReferralStatistics;
  qualification_statistics: AffiliateQualificationStatistics;
  reward_statistics: AffiliateRewardStatistics;
  payout_balance: AffiliatePayoutBalance;
  payout_statistics: AffiliatePayoutStatistics;
  payout_history: AffiliatePayoutRecord[];
  referral_activity: AffiliateActivityItem[];
  reward_activity: AffiliateActivityItem[];
};

export type AffiliateReferralRecord = {
  id: number;
  status: string;
  attribution_type: string;
  referral_code: string | null;
  referred_user: {
    id: number;
    name: string;
    email: string;
  } | null;
  attributed_at: string | null;
  qualified_at: string | null;
  qualified_transaction_type: string | null;
  qualified_transaction_id: string | null;
  rejected_at: string | null;
  rejection_reason: string | null;
  is_suspicious: boolean;
  fraud_reason: string | null;
  fraud_flags: string[];
  visit: {
    id: number;
    visited_at: string | null;
    landing_url: string | null;
    is_suspicious: boolean;
    suspicious_reason: string | null;
  } | null;
};

export type AffiliateReferralsResponse = {
  referrals: AffiliateReferralRecord[];
  statistics: AffiliateQualificationStatistics;
};

export type AffiliateRewardRecord = {
  id: number;
  affiliate_referral_id: number;
  reward_type: string;
  source: string;
  status: string;
  amount_cents: number | null;
  amount_label: string | null;
  currency: string;
  points: number | null;
  quantity: number;
  membership_credit_days: number | null;
  available_at: string | null;
  expires_at: string | null;
  approved_at: string | null;
  issued_at: string | null;
  paid_at: string | null;
  redeemed_at: string | null;
  issued_reference: string | null;
  redeemed_membership_expires_at: string | null;
  can_redeem: boolean;
  is_expired: boolean;
  referred_user: {
    id: number;
    name: string;
    email: string;
  } | null;
  audits: {
    id: number;
    action: string;
    from_status: string | null;
    to_status: string | null;
    occurred_at: string | null;
  }[];
};

export type AffiliateRewardsResponse = {
  rewards: AffiliateRewardRecord[];
  statistics: AffiliateRewardStatistics;
  activity: AffiliateActivityItem[];
};

export type AffiliatePayoutsResponse = {
  balance: AffiliatePayoutBalance;
  statistics: AffiliatePayoutStatistics;
  payouts: AffiliatePayoutRecord[];
};

export type AffiliatePayoutRequestResponse = {
  message: string;
  balance: AffiliatePayoutBalance;
  payout: AffiliatePayoutRecord;
};

export type AffiliateRewardRedemptionResponse = {
  message: string;
  reward: AffiliateRewardRecord;
  subscription: {
    plan: string;
    billing_provider: string | null;
    expires_at: string | null;
    reason: string | null;
  };
};

export type AffiliateRegistrationInput = {
  display_name?: string;
  contact_email?: string;
};

export type AffiliatePayoutRequestInput = {
  payment_method: string;
  notes?: string;
};

export class AffiliateApiError extends Error {
  status: number;
  errors: Record<string, string[]>;

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'AffiliateApiError';
    this.status = status;
    this.errors = errors;
  }
}

function toAffiliateError(error: unknown): never {
  if (error instanceof Error && 'response' in error) {
    const response = error.response as {
      status?: number;
      data?: { message?: string; errors?: Record<string, string[]> };
    };

    throw new AffiliateApiError(
      response.data?.message || 'Affiliate registration is not available right now.',
      response.status || 500,
      response.data?.errors || {},
    );
  }

  throw error;
}

export async function getAffiliateAccount(): Promise<AffiliateAccountResponse> {
  try {
    const response = await apiClient.get<AffiliateAccountResponse>('/account/affiliate');
    return response.data;
  } catch (error) {
    toAffiliateError(error);
  }
}

export async function registerAffiliateAccount(input: AffiliateRegistrationInput): Promise<AffiliateAccountResponse> {
  try {
    const response = await apiClient.post<AffiliateAccountResponse>('/account/affiliate', input);
    return response.data;
  } catch (error) {
    toAffiliateError(error);
  }
}

export async function getAffiliateSummary(): Promise<AffiliateSummaryResponse> {
  try {
    const response = await apiClient.get<AffiliateSummaryResponse>('/account/affiliate/summary');
    return response.data;
  } catch (error) {
    toAffiliateError(error);
  }
}

export async function getAffiliateReferrals(): Promise<AffiliateReferralsResponse> {
  try {
    const response = await apiClient.get<AffiliateReferralsResponse>('/account/affiliate/referrals');
    return response.data;
  } catch (error) {
    toAffiliateError(error);
  }
}

export async function getAffiliateRewards(): Promise<AffiliateRewardsResponse> {
  try {
    const response = await apiClient.get<AffiliateRewardsResponse>('/account/affiliate/rewards');
    return response.data;
  } catch (error) {
    toAffiliateError(error);
  }
}

export async function getAffiliatePayouts(): Promise<AffiliatePayoutsResponse> {
  try {
    const response = await apiClient.get<AffiliatePayoutsResponse>('/account/affiliate/payouts');
    return response.data;
  } catch (error) {
    toAffiliateError(error);
  }
}

export async function requestAffiliatePayout(input: AffiliatePayoutRequestInput): Promise<AffiliatePayoutRequestResponse> {
  try {
    const response = await apiClient.post<AffiliatePayoutRequestResponse>('/account/affiliate/payouts', input);
    return response.data;
  } catch (error) {
    toAffiliateError(error);
  }
}

export async function redeemAffiliateReward(rewardId: number): Promise<AffiliateRewardRedemptionResponse> {
  try {
    const response = await apiClient.post<AffiliateRewardRedemptionResponse>(`/account/affiliate/rewards/${rewardId}/redeem`);
    return response.data;
  } catch (error) {
    toAffiliateError(error);
  }
}
