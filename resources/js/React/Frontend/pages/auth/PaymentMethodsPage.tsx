import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowLeft,
  BadgeCheck,
  CreditCard,
  Link as LinkIcon,
  Loader2,
  ShieldCheck,
  WalletCards,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  BillingApiError,
  getPaymentMethods,
  type PaymentMethodProvider,
} from '@/lib/billing';

function providerCopy(provider: PaymentMethodProvider): string {
  if (provider.provider === 'paypal') {
    return 'PayPal is available for checkout. User wallet linking is not enabled yet, so PayPal will ask you to log in during payment.';
  }

  if (provider.provider === 'stripe') {
    return 'Stripe support is available for future checkout, subscriptions, and saved billing flows.';
  }

  return 'Connect this provider when account linking is available.';
}

export default function PaymentMethodsPage() {
  const { user, isLoading } = useAuth();
  const [methods, setMethods] = useState<PaymentMethodProvider[]>([]);
  const [isLoadingMethods, setIsLoadingMethods] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!user) return;

    let cancelled = false;

    setIsLoadingMethods(true);
    getPaymentMethods()
      .then((response) => {
        if (!cancelled) {
          setMethods(response.payment_methods);
          setError(null);
        }
      })
      .catch((loadError) => {
        if (!cancelled) {
          setError(loadError instanceof BillingApiError ? loadError.message : 'Unable to load payment methods.');
        }
      })
      .finally(() => {
        if (!cancelled) setIsLoadingMethods(false);
      });

    return () => {
      cancelled = true;
    };
  }, [user]);

  if (isLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20">
        <div className="container mx-auto max-w-6xl">
          <div className="h-48 animate-pulse bg-[#141414]" />
        </div>
      </main>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  return (
    <>
      <Helmet>
        <title>Payment Methods | The Blend Battlegrounds</title>
        <meta
          name="description"
          content="View and link available BlendBeats payment methods."
        />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-6xl">
            <Link
              to="/account/billing"
              className="mb-10 inline-flex h-11 items-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              Billing
            </Link>

            <p
              className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Account / Billing
            </p>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-end">
              <div>
                <h1
                  className="uppercase leading-none text-white"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 8vw, 6.5rem)' }}
                >
                  Payment Methods
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  Choose from the payment providers currently available on BlendBeats. Saved wallet linking will be added later.
                </p>
              </div>

              <div className="border border-[#303030] bg-[#111111] p-5">
                <div className="mb-4 flex h-12 w-12 items-center justify-center bg-primary text-white">
                  <WalletCards size={20} />
                </div>
                <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Available Methods</p>
                <p className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {isLoadingMethods ? '...' : methods.length}
                </p>
                <p className="mt-2 text-sm leading-6 text-[#888888]">Controlled by active admin payment providers.</p>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            {isLoadingMethods && (
              <div className="flex min-h-48 items-center justify-center border border-[#2a2a2a] bg-[#111111] text-[#888888]">
                <Loader2 className="mr-3 animate-spin text-primary" size={20} />
                Loading payment methods
              </div>
            )}

            {!isLoadingMethods && error && (
              <div className="border border-primary bg-[#140909] p-5 text-sm text-[#dddddd]">{error}</div>
            )}

            {!isLoadingMethods && !error && methods.length === 0 && (
              <div className="border border-[#2a2a2a] bg-[#111111] p-8">
                <div className="mb-5 flex h-12 w-12 items-center justify-center bg-[#080808] text-primary">
                  <ShieldCheck size={20} />
                </div>
                <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  No Payment Methods Available
                </h2>
                <p className="mt-3 max-w-2xl text-sm leading-6 text-[#888888]">
                  Payment providers are not active yet. Once an admin enables PayPal or Stripe, available methods will appear here.
                </p>
              </div>
            )}

            {!isLoadingMethods && !error && methods.length > 0 && (
              <div className="grid gap-4 md:grid-cols-2">
                {methods.map((method) => (
                  <article key={method.id} className="grid min-h-72 border border-[#2a2a2a] bg-[#111111] p-5">
                    <div>
                      <div className="mb-5 flex items-center justify-between gap-4">
                        <div className="flex h-11 w-11 items-center justify-center bg-[#080808] text-primary">
                          <CreditCard size={20} />
                        </div>
                        <div className="flex items-center gap-2">
                          {method.is_primary && (
                            <span className="text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">Primary</span>
                          )}
                            <span className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">
                              {method.is_linked ? 'Linked Wallet' : method.status_label}
                          </span>
                        </div>
                      </div>

                      <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        {method.display_name}
                      </h2>
                      <p className="mt-3 text-sm leading-6 text-[#888888]">{providerCopy(method)}</p>

                      {method.supported_features.length > 0 && (
                        <div className="mt-5 flex flex-wrap gap-2">
                          {method.supported_features.map((feature) => (
                            <span key={feature} className="border border-[#333333] px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-[#999999]">
                              {feature.replaceAll('_', ' ')}
                            </span>
                          ))}
                        </div>
                      )}
                    </div>

                    <button
                      type="button"
                      disabled={!method.linking_enabled}
                      className="mt-6 inline-flex h-11 cursor-not-allowed items-center justify-center gap-2 border border-[#222222] px-4 text-xs font-bold uppercase tracking-widest text-[#555555]"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      {method.is_linked ? <BadgeCheck size={15} /> : <LinkIcon size={15} />}
                      {method.is_linked ? 'Linked Wallet' : `${method.display_name} Wallet Linking Coming Soon`}
                    </button>
                  </article>
                ))}
              </div>
            )}
          </div>
        </section>
      </main>
    </>
  );
}
