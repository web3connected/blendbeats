import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowLeft,
  ArrowRight,
  BadgeCheck,
  Bell,
  CreditCard,
  ReceiptText,
  ShieldCheck,
  WalletCards,
} from 'lucide-react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';

const billingCards = [
  {
    title: 'Payment Methods',
    description: 'View available payment methods, link provider accounts, and choose how future payments are handled.',
    icon: CreditCard,
    actionLabel: 'Manage Methods',
    href: '/account/payment-methods',
  },
  {
    title: 'Invoices & Receipts',
    description: 'PayPal receipts and BlendBeats billing records will appear here after checkout webhooks are connected.',
    icon: ReceiptText,
    actionLabel: 'Coming Soon',
  },
  {
    title: 'Billing Preferences',
    description: 'Control receipt emails, renewal notices, and billing contact preferences for your account.',
    icon: Bell,
    actionLabel: 'Coming Soon',
  },
  {
    title: 'Subscription Plans',
    description: 'Review membership tiers separately from payment methods and billing history.',
    icon: BadgeCheck,
    actionLabel: 'View Plans',
    href: '/pricing',
  },
];

export default function BillingPaymentsPage() {
  const { user, isLoading } = useAuth();

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
        <title>Billing & Payments | The Blend Battlegrounds</title>
        <meta
          name="description"
          content="Manage BlendBeats billing, PayPal payment methods, invoices, receipts, and billing preferences."
        />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-6xl">
            <Link
              to="/account/settings"
              className="mb-10 inline-flex h-11 items-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              Settings
            </Link>

            <p
              className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Account / Billing
            </p>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
              <div>
                <h1
                  className="uppercase leading-none text-white"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 8vw, 6.5rem)' }}
                >
                  Billing & Payments
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  Manage payment methods, invoices, receipts, and billing preferences. PayPal is the active payment provider while
                  Stripe stays ready for future checkout flows.
                </p>
              </div>

              <div className="border border-[#303030] bg-[#111111] p-5">
                <div className="mb-4 flex h-12 w-12 items-center justify-center bg-primary text-white">
                  <WalletCards size={20} />
                </div>
                <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Active Provider</p>
                <p className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  PayPal
                </p>
                <p className="mt-2 text-sm leading-6 text-[#888888]">Primary checkout and payment method provider.</p>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
            <aside className="border border-[#2a2a2a] bg-[#111111] p-5">
              <div className="mb-5 flex h-12 w-12 items-center justify-center bg-[#080808] text-primary">
                <ShieldCheck size={20} />
              </div>
              <p className="text-[11px] font-bold uppercase tracking-widest text-[#777777]">Billing Account</p>
              <p className="mt-3 truncate text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {user.name}
              </p>
              <p className="mt-1 break-all text-sm text-[#888888]">{user.email}</p>
              <div className="mt-6 border-t border-[#262626] pt-5">
                <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Provider Status</p>
                <p className="mt-2 text-sm leading-6 text-[#aaaaaa]">
                  PayPal management is separated from subscriptions so users can handle payment methods without changing plans.
                </p>
              </div>
            </aside>

            <div className="grid gap-4 md:grid-cols-2">
              {billingCards.map((item) => {
                const Icon = item.icon;
                const content = (
                  <>
                    {item.actionLabel}
                    <ArrowRight size={15} />
                  </>
                );

                return (
                  <article key={item.title} className="grid min-h-64 border border-[#2a2a2a] bg-[#111111] p-5">
                    <div>
                      <div className="mb-5 flex items-center justify-between gap-4">
                        <div className="flex h-11 w-11 items-center justify-center bg-[#080808] text-primary">
                          <Icon size={20} />
                        </div>
                        <span className="text-[10px] font-bold uppercase tracking-widest text-[#555555]">PayPal</span>
                      </div>
                      <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        {item.title}
                      </h2>
                      <p className="mt-3 text-sm leading-6 text-[#888888]">{item.description}</p>
                    </div>

                    {item.href ? (
                      <Link
                        to={item.href}
                        className="mt-6 inline-flex h-11 items-center justify-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        {content}
                      </Link>
                    ) : (
                      <button
                        type="button"
                        disabled
                        className="mt-6 inline-flex h-11 cursor-not-allowed items-center justify-center gap-2 border border-[#222222] px-4 text-xs font-bold uppercase tracking-widest text-[#555555]"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        {content}
                      </button>
                    )}
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
