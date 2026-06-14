import apiClient from '@/lib/api-client';

export type BillingPlan = {
  key: string;
  name: string;
  purpose: string | null;
  features: string[];
  future_features: string[];
  storage_bytes: number;
  storage_label: string;
  advertising_groups: string[];
  advertising_groups_label: string;
  is_free: boolean;
  is_current: boolean;
  price_cents: number;
  price_label: string;
  interval_label: string;
  checkout_enabled: boolean;
};

export type BillingPlansResponse = {
  plans: BillingPlan[];
  current_tier: string;
};

export type SubscriptionStatus = {
  current_tier: string;
  subscription: {
    stripe_status: string;
    stripe_price: string | null;
    ends_at: string | null;
    on_grace_period: boolean;
    valid: boolean;
  } | null;
  has_stripe_customer: boolean;
};

export type PaymentMethodProvider = {
  id: number;
  provider: string;
  display_name: string;
  mode: string;
  is_primary: boolean;
  supported_features: string[];
  linking_enabled: boolean;
  is_linked: boolean;
  status_label: string;
};

export type PaymentMethodsResponse = {
  payment_methods: PaymentMethodProvider[];
};

export class BillingApiError extends Error {
  status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = 'BillingApiError';
    this.status = status;
  }
}

function toBillingError(error: unknown): never {
  if (error instanceof Error && 'response' in error) {
    const response = error.response as { status?: number; data?: { message?: string } };

    throw new BillingApiError(
      response.data?.message || 'Billing is not available right now.',
      response.status || 500,
    );
  }

  throw error;
}

export async function getBillingPlans(): Promise<BillingPlansResponse> {
  try {
    const response = await apiClient.get<BillingPlansResponse>('/billing/plans');
    return response.data;
  } catch (error) {
    toBillingError(error);
  }
}

export async function getSubscriptionStatus(): Promise<SubscriptionStatus> {
  try {
    const response = await apiClient.get<SubscriptionStatus>('/billing/subscription');
    return response.data;
  } catch (error) {
    toBillingError(error);
  }
}

export async function getPaymentMethods(): Promise<PaymentMethodsResponse> {
  try {
    const response = await apiClient.get<PaymentMethodsResponse>('/billing/payment-methods');
    return response.data;
  } catch (error) {
    toBillingError(error);
  }
}

export async function startCheckout(plan: string): Promise<string> {
  try {
    const response = await apiClient.post<{ url: string }>('/billing/checkout', { plan });
    return response.data.url;
  } catch (error) {
    toBillingError(error);
  }
}

export async function openBillingPortal(): Promise<string> {
  try {
    const response = await apiClient.post<{ url: string }>('/billing/portal');
    return response.data.url;
  } catch (error) {
    toBillingError(error);
  }
}
