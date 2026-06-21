import type { AccountSubscriptionDetails } from '@/lib/billing';

export function formatSubscriptionLabel(value: string | null | undefined) {
  if (!value) return 'Not Set';

  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function formatSubscriptionDate(value: string | null | undefined) {
  if (!value) return 'Not Set';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return 'Not Set';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(date);
}

export function subscriptionProviderDisplay(subscription: AccountSubscriptionDetails | null) {
  if (subscription?.billing_provider === 'internal') return 'Complimentary';
  if (subscription?.billing_provider === 'paypal') return 'PayPal';

  return formatSubscriptionLabel(subscription?.billing_provider);
}

export function subscriptionIdDisplay(subscription: AccountSubscriptionDetails | null) {
  if (subscription?.billing_provider === 'internal') return 'Not required';

  return subscription?.subscription_id || 'Not Set';
}
