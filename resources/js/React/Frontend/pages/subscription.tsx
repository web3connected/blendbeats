import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { ArrowLeft, ArrowRight, Check, CreditCard, ExternalLink, Loader2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { useAuth } from '@/components/auth/AuthProvider';
import HeaderTitle from '@/layouts/HeaderTitle';
import {
  BillingApiError,
  getBillingPlans,
  getSubscriptionStatus,
  openBillingPortal,
  startCheckout,
  type BillingPlan,
  type PaymentProfile,
  type SubscriptionStatus,
} from '@/lib/billing';

export default function SubscriptionPage() {
  const { user, isLoading } = useAuth();
  const [searchParams] = useSearchParams();
  const [plans, setPlans] = useState<BillingPlan[]>([]);
  const [status, setStatus] = useState<SubscriptionStatus | null>(null);
  const [paymentProfile, setPaymentProfile] = useState<PaymentProfile | null>(null);
  const [paypalConfig, setPaypalConfig] = useState<{
    client_id: string;
    plan_id: string;
    mode: string;
  } | null>(null);
  const [error, setError] = useState('');
  const [isPageLoading, setIsPageLoading] = useState(true);
  const [isCheckoutLoading, setIsCheckoutLoading] = useState(false);
  const [isPortalLoading, setIsPortalLoading] = useState(false);

  useEffect(() => {
    fetch('/api/billing/paypal/subscription-config')
      .then((response) => response.json())
      .then((data) => {
        setPaypalConfig(data);
      })
      .catch((error) => {
        console.error('Unable to load PayPal subscription config:', error);
      });
  }, []);

  useEffect(() => {
    if (!paypalConfig?.client_id || !paypalConfig?.plan_id) {
      return;
    }

    if (document.getElementById('paypal-sdk')) {
      return;
    }

    const script = document.createElement('script');
    script.id = 'paypal-sdk';
    script.src = `https://www.paypal.com/sdk/js?client-id=${paypalConfig.client_id}&vault=true&intent=subscription`;
    script.async = true;

    document.body.appendChild(script);
  }, [paypalConfig]);

  const selectedPlanKey = searchParams.get('plan') ?? 'dj_pro';
  const selectedPlan = useMemo(
    () => plans.find((plan) => plan.key === selectedPlanKey) ?? plans.find((plan) => plan.key === status?.current_tier) ?? plans[0],
    [plans, selectedPlanKey, status?.current_tier],
  );

  useEffect(() => {
    if (
      !paypalConfig ||
      selectedPlan?.key !== 'dj_plus'
    ) {
      return;
    }

    const renderButtons = () => {
      const paypal = (window as any).paypal;

      if (!paypal) {
        setTimeout(renderButtons, 500);
        return;
      }

      const container = document.getElementById(
        'paypal-dj-plus-button-container'
      );

      if (!container || container.childElementCount > 0) {
        return;
      }

      paypal.Buttons({
        style: {
          shape: 'pill',
          color: 'silver',
          layout: 'horizontal',
          label: 'subscribe',
        },

        createSubscription: (_data: any, actions: any) => {
          return actions.subscription.create({
            plan_id: paypalConfig.plan_id,
          });
        },

        onApprove: async (data: any) => {
          await fetch('/api/billing/paypal/subscription-approved', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              'X-CSRF-TOKEN':
                document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
              subscriptionID: data.subscriptionID,
            }),
          });

          window.location.href =
            '/payment/success?subscription_id=' +
            data.subscriptionID;
        },

        onError: (err: any) => {
          console.error('PayPal Error', err);
        },
      }).render('#paypal-dj-plus-button-container');
    };

    renderButtons();
  }, [paypalConfig, selectedPlan?.key]);

  useEffect(() => {
    if (!user) return;

    setIsPageLoading(true);
    setError('');

    Promise.all([getBillingPlans(), getSubscriptionStatus()])
      .then(([plansResponse, subscriptionResponse]) => {
        setPlans(plansResponse.plans);
        setStatus(subscriptionResponse);
        setPaymentProfile(subscriptionResponse.payment_profile ?? plansResponse.payment_profile);
      })
      .catch((loadError) => {
        setError(loadError instanceof BillingApiError ? loadError.message : 'Unable to load subscription details.');
      })
      .finally(() => setIsPageLoading(false));
  }, [user]);

  const handleCheckout = () => {
    if (!selectedPlan) return;

    setIsCheckoutLoading(true);
    setError('');

    startCheckout(selectedPlan.key)
      .then((url) => {
        window.location.href = url;
      })
      .catch((checkoutError) => {
        setError(checkoutError instanceof BillingApiError ? checkoutError.message : 'Unable to start checkout.');
      })
      .finally(() => setIsCheckoutLoading(false));
  };

  const handlePortal = () => {
    setIsPortalLoading(true);
    setError('');

    openBillingPortal()
      .then((url) => {
        window.location.href = url;
      })
      .catch((portalError) => {
        setError(portalError instanceof BillingApiError ? portalError.message : 'Unable to open billing portal.');
      })
      .finally(() => setIsPortalLoading(false));
  };

  if (isLoading) {
    return (
      <main className="flex min-h-[60vh] items-center justify-center bg-[#080808] text-white">
        <Loader2 className="animate-spin text-primary" size={32} />
      </main>
    );
  }

  if (!user) {
    return <Navigate to="/register" replace />;
  }

  const primaryProvider = paymentProfile?.primary_provider;
  const providerName = primaryProvider?.display_name ?? 'Payment Provider';
  const providerStatusLabel = primaryProvider
    ? `${primaryProvider.display_name} ${primaryProvider.mode}`
    : 'No active provider';
  const providerCheckoutReady = Boolean(primaryProvider?.checkout_ready);
  const checkoutLabel = selectedPlan?.is_current
    ? 'Current Plan'
    : providerCheckoutReady
      ? `Continue To ${providerName} Checkout`
      : `${providerName} Setup Needed`;

  return (
    <>
      <HeaderTitle
        title="Subscription | BlendBeats"
        description="Manage your BlendBeats membership tier and promotion access."
      />

      <main className="bg-[#080808] text-white">
        <section className="border-b border-[#242424]">
          <div className="container mx-auto px-4 py-14 md:py-20">
            <Link
              to="/pricing"
              className="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-[#aaa] transition-colors hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={14} />
              Back To Pricing
            </Link>

            <div className="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">
              <div>
                <p className="text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                  Subscription
                </p>
                <h1
                  className="mt-3 max-w-4xl uppercase leading-none"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3rem, 8vw, 7rem)' }}
                >
                  Choose Your Membership
                </h1>
                <p className="mt-6 max-w-2xl text-base leading-7 text-[#c8c8c8]">
                  You are signed in as {user.name}. Pick a membership plan and continue through the active payment provider when checkout is ready.
                </p>
              </div>

              <div className="border border-primary bg-[#111] p-5 md:p-7">
                {isPageLoading ? (
                  <div className="flex h-64 items-center justify-center">
                    <Loader2 className="animate-spin text-primary" size={30} />
                  </div>
                ) : selectedPlan ? (
                  <>
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <p className="text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                          Selected Plan
                        </p>
                        <h2 className="mt-2 text-4xl uppercase" style={{ fontFamily: 'var(--font-heading)' }}>
                          {selectedPlan.name}
                        </h2>
                      </div>
                      <div className="text-right">
                        <p className="text-4xl text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                          {selectedPlan.price_label}
                        </p>
                        <p className="text-xs uppercase tracking-widest text-[#777]" style={{ fontFamily: 'var(--font-heading)' }}>
                          {selectedPlan.interval_label}
                        </p>
                      </div>
                    </div>
                    <p className="mt-5 text-sm leading-6 text-[#aaa]">{selectedPlan.purpose}</p>

                    <div className="mt-5 grid grid-cols-2 gap-2">
                      <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                        <p className="text-[10px] uppercase tracking-widest text-[#777]">Current Tier</p>
                        <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {status?.current_tier ?? user.media_storage_tier ?? 'free'}
                        </p>
                      </div>
                      <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                        <p className="text-[10px] uppercase tracking-widest text-[#777]">Payment Provider</p>
                        <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {providerStatusLabel}
                        </p>
                      </div>
                    </div>

                    {error && (
                      <div className="mt-5 border border-primary/30 bg-primary/10 p-3 text-sm text-primary">{error}</div>
                    )}

                    <div className="mt-7 grid gap-3">
                      {selectedPlan.key !== 'dj_plus' && (
                        <button
                          type="button"
                          disabled={!selectedPlan.checkout_enabled || !providerCheckoutReady || selectedPlan.is_current || isCheckoutLoading}
                          onClick={handleCheckout}
                          className="inline-flex w-full items-center justify-center gap-3 bg-primary px-6 py-4 text-xs font-bold uppercase tracking-widest text-white transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                          style={{ fontFamily: 'var(--font-heading)' }}
                        >
                          {isCheckoutLoading ? <Loader2 className="animate-spin" size={16} /> : <CreditCard size={16} />}
                          {selectedPlan.checkout_enabled ? checkoutLabel : 'Plan Price Needed'}
                        </button>
                      )}

                      {selectedPlan.key === 'dj_plus' && (
                        <div id="paypal-dj-plus-button-container" className="mt-4" />
                      )}

                      {status?.has_stripe_customer && (
                        <button
                          type="button"
                          disabled={isPortalLoading}
                          onClick={handlePortal}
                          className="inline-flex w-full items-center justify-center gap-3 border border-[#333] px-6 py-4 text-xs font-bold uppercase tracking-widest text-[#ddd] transition-colors hover:border-primary hover:text-primary disabled:opacity-50"
                          style={{ fontFamily: 'var(--font-heading)' }}
                        >
                          {isPortalLoading ? <Loader2 className="animate-spin" size={16} /> : <ExternalLink size={16} />}
                          Manage Billing
                        </button>
                      )}
                    </div>
                  </>
                ) : (
                  <p className="text-sm text-[#aaa]">No membership plans are configured yet.</p>
                )}
              </div>
            </div>
          </div>
        </section>

        <section className="py-14 md:py-20">
          <div className="container mx-auto px-4">
            {error && !selectedPlan && <div className="mb-5 border border-primary/30 bg-primary/10 p-4 text-sm text-primary">{error}</div>}

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
              {plans.map((plan) => {
                const isSelected = selectedPlan?.key === plan.key;

                return (
                  <article key={plan.key} className={`flex min-h-[390px] flex-col border bg-[#111] p-5 ${isSelected ? 'border-primary' : 'border-[#2a2a2a]'}`}>
                    <h2 className="text-3xl uppercase" style={{ fontFamily: 'var(--font-heading)' }}>{plan.name}</h2>
                    <p className="mt-4 text-4xl text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>{plan.price_label}</p>
                    <p className="mt-4 text-sm leading-6 text-[#aaa]">{plan.purpose}</p>
                    <ul className="mt-5 flex-1 space-y-3">
                      {plan.features.map((feature) => (
                        <li key={feature} className="flex gap-3 text-sm text-[#d0d0d0]">
                          <Check size={15} className="mt-0.5 shrink-0 text-primary" />
                          <span>{feature}</span>
                        </li>
                      ))}
                    </ul>
                    <Link
                      to={`/subscription?plan=${plan.key}`}
                      className={`mt-6 inline-flex items-center justify-center gap-2 px-5 py-3 text-xs font-bold uppercase tracking-widest ${isSelected ? 'bg-primary text-white' : 'border border-[#333] text-white hover:border-primary'}`}
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      {isSelected ? 'Selected' : 'Select Plan'}
                      <ArrowRight size={14} />
                    </Link>
                  </article>
                );
              })}
            </div>
          </div>
        </section>
      </main>
    </>
  );
}
