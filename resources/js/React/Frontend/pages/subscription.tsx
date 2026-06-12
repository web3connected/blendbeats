import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { ArrowLeft, ArrowRight, Check, CreditCard, Loader2 } from 'lucide-react';

import { useAuth } from '@/components/auth/AuthProvider';
import HeaderTitle from '@/layouts/HeaderTitle';

const plans = [
  {
    key: 'free',
    name: 'Free',
    price: '$0',
    summary: 'Keep building with the core BlendBeats tools.',
    features: ['DJ profile', 'Public mixes', 'DJ Lounge', 'Portfolio system', 'Group F promotion access'],
  },
  {
    key: 'dj_plus',
    name: 'DJ Plus',
    price: 'Soon',
    summary: 'Unlock more storage and stronger entry promotion.',
    features: ['3 GB storage', 'Groups E-F', 'Growth tools', 'Priority feature access'],
  },
  {
    key: 'dj_pro',
    name: 'DJ Pro',
    price: 'Soon',
    summary: 'Built for active DJs growing audience and bookings.',
    features: ['10 GB storage', 'Groups C-F', 'Advanced analytics path', 'Booking growth tools'],
  },
  {
    key: 'dj_elite',
    name: 'DJ Elite',
    price: 'Soon',
    summary: 'Full promotion inventory and future AI business tools.',
    features: ['25 GB storage', 'Groups A-F', 'AI assistant path', 'Business automation path'],
  },
];

export default function SubscriptionPage() {
  const { user, isLoading } = useAuth();
  const [searchParams] = useSearchParams();
  const selectedPlanKey = searchParams.get('plan') ?? 'dj_pro';
  const selectedPlan = plans.find((plan) => plan.key === selectedPlanKey) ?? plans[2];

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
                  You are signed in as {user.name}. Pick the plan you want to use. Stripe checkout will attach here next, so this screen is the member-only subscription step.
                </p>
              </div>

              <div className="border border-primary bg-[#111] p-5 md:p-7">
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
                      {selectedPlan.price}
                    </p>
                    <p className="text-xs uppercase tracking-widest text-[#777]" style={{ fontFamily: 'var(--font-heading)' }}>
                      Test setup
                    </p>
                  </div>
                </div>
                <p className="mt-5 text-sm leading-6 text-[#aaa]">{selectedPlan.summary}</p>
                <button
                  type="button"
                  disabled
                  className="mt-7 inline-flex w-full items-center justify-center gap-3 bg-primary/60 px-6 py-4 text-xs font-bold uppercase tracking-widest text-white"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <CreditCard size={16} />
                  Stripe Checkout Coming Next
                </button>
              </div>
            </div>
          </div>
        </section>

        <section className="py-14 md:py-20">
          <div className="container mx-auto px-4">
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
              {plans.map((plan) => {
                const isSelected = plan.key === selectedPlan.key;

                return (
                  <article key={plan.key} className={`flex min-h-[390px] flex-col border bg-[#111] p-5 ${isSelected ? 'border-primary' : 'border-[#2a2a2a]'}`}>
                    <h2 className="text-3xl uppercase" style={{ fontFamily: 'var(--font-heading)' }}>{plan.name}</h2>
                    <p className="mt-4 text-4xl text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>{plan.price}</p>
                    <p className="mt-4 text-sm leading-6 text-[#aaa]">{plan.summary}</p>
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
