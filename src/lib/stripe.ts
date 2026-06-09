import { loadStripe, type Stripe } from '@stripe/stripe-js';

const publishableKey = import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY as string | undefined;

export const stripePromise: Promise<Stripe | null> = loadStripe(publishableKey ?? '');

export const stripeConfig = {
  publishableKey: publishableKey ?? '',
  currency: (import.meta.env.VITE_STRIPE_CURRENCY as string | undefined) ?? 'usd',
};
