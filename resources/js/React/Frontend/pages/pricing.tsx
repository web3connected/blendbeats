import { Link } from 'react-router-dom';
import type { LucideIcon } from 'lucide-react';
import { ArrowRight, Bot, BriefcaseBusiness, Check, Loader2, Sparkles, Star, TrendingUp } from 'lucide-react';
import { useEffect, useState } from 'react';

import { useAuth } from '@/components/auth/AuthProvider';
import HeaderTitle from '@/layouts/HeaderTitle';
import { BillingApiError, getBillingPlans, type BillingPlan, type PaymentProfile } from '@/lib/billing';

const promotionGroups = [
  ['E', 'Entry-level promotion inventory.'],
  ['F', 'Basic promotional access for Free Tier users.'],
];

const activePricingPlanKeys = ['free', 'dj_plus'];

const futureFeatures: Array<[LucideIcon, string, string]> = [
  [TrendingUp, 'Analytics Suite', 'Profile views, mix plays, follower growth, and promotion performance.'],
  [Bot, 'AI DJ Assistant', 'Career recommendations, mix promotion ideas, and profile optimization.'],
  [BriefcaseBusiness, 'Booking Tools', 'Booking requests, client records, reminders, and scheduling workflows.'],
  [Sparkles, 'Automation', 'Follow-ups, lead nurturing, opportunity tracking, and future AI agent workflows.'],
];

const tierStyles: Record<string, {
  card: string;
  eyebrow: string;
  price: string;
  stat: string;
  cta: string;
  check: string;
}> = {
  free: {
    card: 'border-[#2f3a46] bg-[linear-gradient(180deg,rgba(45,58,70,0.34),rgba(17,17,17,0.98)_38%)]',
    eyebrow: 'bg-[#273746] text-[#9ec5ff]',
    price: 'text-[#8ec5ff]',
    stat: 'border-[#304253] bg-[#0a1118]',
    cta: 'border border-[#304253] text-[#d9ecff] hover:border-[#8ec5ff]',
    check: 'text-[#8ec5ff]',
  },
  dj_plus: {
    card: 'border-[#5a4820] bg-[linear-gradient(180deg,rgba(255,184,0,0.16),rgba(17,17,17,0.98)_38%)]',
    eyebrow: 'bg-[#33270b] text-[#FFB800]',
    price: 'text-[#FFB800]',
    stat: 'border-[#4a3a15] bg-[#151106]',
    cta: 'border border-[#4a3a15] text-[#ffe39a] hover:border-[#FFB800]',
    check: 'text-[#FFB800]',
  },
  dj_pro: {
    card: 'border-primary bg-[linear-gradient(180deg,rgba(255,32,32,0.18),rgba(17,17,17,0.98)_38%)] shadow-[0_0_0_1px_rgba(255,32,32,0.35)]',
    eyebrow: 'bg-primary text-white',
    price: 'text-primary',
    stat: 'border-[#5b2222] bg-[#190909]',
    cta: 'bg-primary text-white',
    check: 'text-primary',
  },
  dj_elite: {
    card: 'border-[#5a315f] bg-[linear-gradient(180deg,rgba(184,92,255,0.16),rgba(17,17,17,0.98)_38%)]',
    eyebrow: 'bg-[#321936] text-[#e0a6ff]',
    price: 'text-[#e0a6ff]',
    stat: 'border-[#4a2a52] bg-[#140917]',
    cta: 'border border-[#4a2a52] text-[#f1d2ff] hover:border-[#e0a6ff]',
    check: 'text-[#e0a6ff]',
  },
};

function ctaLabel(plan: BillingPlan, signedIn: boolean) {
  if (plan.is_current) return 'Current Plan';
  if (plan.is_free) return signedIn ? 'Use Free Tier' : 'Start Free';
  if (!plan.checkout_enabled) return 'Setup Needed';
  return `Choose ${plan.name}`;
}

export default function PricingPage() {
  const { user } = useAuth();
  const [plans, setPlans] = useState<BillingPlan[]>([]);
  const [paymentProfile, setPaymentProfile] = useState<PaymentProfile | null>(null);
  const [currentTier, setCurrentTier] = useState('free');
  const [error, setError] = useState('');
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    setIsLoading(true);
    setError('');

    getBillingPlans()
      .then((response) => {
        setPlans(response.plans);
        setPaymentProfile(response.payment_profile);
        setCurrentTier(response.current_tier);
      })
      .catch((loadError) => {
        setError(loadError instanceof BillingApiError ? loadError.message : 'Unable to load pricing right now.');
      })
      .finally(() => setIsLoading(false));
  }, []);

  const primaryProvider = paymentProfile?.primary_provider;
  const providerName = primaryProvider?.display_name ?? 'Payment Provider';
  const providerMode = primaryProvider?.mode ?? 'setup';
  const activeProviderLabel = paymentProfile?.active_providers.length
    ? paymentProfile.active_providers.map((provider) => provider.display_name).join(', ')
    : 'None active';
  const visiblePlans = plans.filter((plan) => activePricingPlanKeys.includes(plan.key));
  const currentPlan = visiblePlans.find((plan) => plan.is_current) ?? visiblePlans.find((plan) => plan.key === currentTier);
  const currentPlanStyle = currentPlan ? (tierStyles[currentPlan.key] ?? tierStyles.free) : tierStyles.free;
  const heroStats = currentPlan
    ? [
        [currentPlan.name, 'Current tier'],
        [currentPlan.price_label, currentPlan.interval_label === 'forever' ? 'Plan cost' : currentPlan.interval_label],
        [currentPlan.storage_label, 'Storage limit'],
        [currentPlan.advertising_groups_label, 'Promotion access'],
      ]
    : [
        ['Free + Plus', 'Active tiers'],
        ['$0-$9.99', 'Monthly range'],
        ['E-F', 'Promotion groups'],
        [providerName, `${providerMode} checkout`],
      ];

  return (
    <>
      <HeaderTitle
        title="Pricing | BlendBeats"
        description="BlendBeats membership tiers, promotion access, storage, analytics, AI tools, and DJ growth features."
      />

      <main className="bg-[#080808] text-white">
        <section className="border-b border-[#242424]">
          <div className="container mx-auto grid min-h-[58vh] grid-cols-1 gap-10 px-4 py-16 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:py-24">
            <div>
              <p className="mb-3 text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                Membership and promotion
              </p>
              <h1
                className="max-w-5xl uppercase leading-none"
                style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.4rem, 9vw, 8rem)' }}
              >
                Grow Free. Upgrade When Ready.
              </h1>
              <p className="mt-6 max-w-2xl text-base leading-7 text-[#c9c9c9] md:text-lg">
                Every DJ can join, upload mixes, build a profile, use the Lounge, and grow a following for free. Plus adds more storage, enhanced analytics, promotion planning tools, and stronger entry-level promotion access.
              </p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <Link
                  to={user ? '/subscription' : '/register'}
                  className="inline-flex items-center justify-center gap-3 bg-primary px-7 py-4 text-xs font-bold uppercase tracking-widest text-white transition-opacity hover:opacity-90"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  {user ? 'Manage Membership' : 'Create Account'}
                  <ArrowRight size={16} />
                </Link>
                <Link
                  to="/djs"
                  className="inline-flex items-center justify-center gap-3 border border-[#333] px-7 py-4 text-xs font-bold uppercase tracking-widest text-[#d8d8d8] transition-colors hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Explore DJ Hub
                </Link>
              </div>
            </div>

            <div className={`border p-5 md:p-7 ${user && currentPlan ? currentPlanStyle.card : 'border-[#2a2a2a] bg-[#101010]'}`}>
              {user && currentPlan ? (
                <div className="mb-5 flex items-start justify-between gap-4">
                  <div>
                    <p className="text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                      Your subscription
                    </p>
                    <h2 className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      {currentPlan.name}
                    </h2>
                  </div>
                  <span className={`px-3 py-1 text-[10px] font-bold uppercase tracking-widest ${currentPlanStyle.eyebrow}`} style={{ fontFamily: 'var(--font-heading)' }}>
                    Active
                  </span>
                </div>
              ) : null}

              <div className="grid grid-cols-2 gap-3">
                {heroStats.map(([value, label]) => (
                  <div key={label} className={`border p-4 ${user && currentPlan ? currentPlanStyle.stat : 'border-[#2a2a2a] bg-[#080808]'}`}>
                    <p className={`text-3xl ${user && currentPlan ? currentPlanStyle.price : 'text-[#FFB800]'}`} style={{ fontFamily: 'var(--font-heading)' }}>
                      {value}
                    </p>
                    <p className="mt-2 text-[11px] uppercase tracking-widest text-[#888]" style={{ fontFamily: 'var(--font-heading)' }}>
                      {label}
                    </p>
                  </div>
                ))}
              </div>
              <div className="mt-4 border border-[#2a2a2a] bg-[#080808] p-5">
                <p className="text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                  {user && currentPlan ? 'Included right now' : 'Featured promotion'}
                </p>
                <p className="mt-3 text-sm leading-6 text-[#aaa]">
                  {user && currentPlan
                    ? `${currentPlan.purpose} Your active payment provider is ${providerName} in ${providerMode} mode.`
                    : 'Optional campaigns let DJs promote a profile or mix for 1 day or 7 days. Higher memberships unlock stronger placement groups.'}
                </p>
                {user && currentPlan ? (
                  <ul className="mt-4 grid gap-2 sm:grid-cols-2">
                    {currentPlan.features.slice(0, 4).map((feature) => (
                      <li key={feature} className="flex gap-2 text-sm text-[#d0d0d0]">
                        <Check size={14} className={`mt-0.5 shrink-0 ${currentPlanStyle.check}`} />
                        <span>{feature}</span>
                      </li>
                    ))}
                  </ul>
                ) : null}
              </div>
            </div>
          </div>
        </section>

        <section className="py-14 md:py-20">
          <div className="container mx-auto px-4">
            <div className="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
              <div>
                <p className="text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                  Membership tiers
                </p>
                <h2 className="mt-2 text-3xl uppercase md:text-5xl" style={{ fontFamily: 'var(--font-heading)' }}>
                  Choose Your Growth Lane
                </h2>
              </div>
              <p className="max-w-xl text-sm leading-6 text-[#aaa]">
                Free and Plus are the active membership options right now. Current active provider: {activeProviderLabel}.
              </p>
            </div>

            {isLoading && (
              <div className="flex min-h-40 items-center justify-center border border-[#2a2a2a] bg-[#111]">
                <Loader2 className="animate-spin text-primary" size={28} />
              </div>
            )}

            {error && <div className="border border-primary/30 bg-primary/10 p-4 text-sm text-primary">{error}</div>}

            {!isLoading && !error && (
              <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {visiblePlans.map((plan) => {
                  const style = tierStyles[plan.key] ?? tierStyles.free;

                  return (
                  <article
                    key={plan.key}
                    className={`relative flex min-h-[560px] flex-col overflow-hidden border p-5 ${style.card}`}
                  >
                    <div className={`mb-5 inline-flex w-fit items-center gap-2 px-3 py-1 text-[10px] font-bold uppercase tracking-widest ${style.eyebrow}`} style={{ fontFamily: 'var(--font-heading)' }}>
                      {plan.key === 'dj_pro' ? <Star size={12} /> : null}
                      {plan.key === 'dj_pro' ? 'Popular' : plan.advertising_groups_label}
                    </div>
                    <h3 className="text-3xl uppercase" style={{ fontFamily: 'var(--font-heading)' }}>
                      {plan.name}
                    </h3>
                    <div className="mt-5">
                      <span className={`text-5xl ${style.price}`} style={{ fontFamily: 'var(--font-heading)' }}>
                        {plan.price_label}
                      </span>
                      {!plan.is_free ? <span className="ml-2 text-sm text-[#777]">{plan.interval_label}</span> : null}
                    </div>
                    <p className="mt-4 min-h-12 text-sm leading-6 text-[#aaa]">{plan.purpose}</p>

                    <div className="mt-5 grid grid-cols-2 gap-2">
                      <div className={`border p-3 ${style.stat}`}>
                        <p className="text-[10px] uppercase tracking-widest text-[#777]" style={{ fontFamily: 'var(--font-heading)' }}>Storage</p>
                        <p className="mt-1 text-lg text-white" style={{ fontFamily: 'var(--font-heading)' }}>{plan.storage_label}</p>
                      </div>
                      <div className={`border p-3 ${style.stat}`}>
                        <p className="text-[10px] uppercase tracking-widest text-[#777]" style={{ fontFamily: 'var(--font-heading)' }}>Ads</p>
                        <p className="mt-1 text-lg text-white" style={{ fontFamily: 'var(--font-heading)' }}>{plan.advertising_groups_label}</p>
                      </div>
                    </div>

                    <ul className="mt-6 flex-1 space-y-3">
                      {plan.features.map((feature) => (
                        <li key={feature} className="flex gap-3 text-sm leading-5 text-[#d0d0d0]">
                          <Check size={16} className={`mt-0.5 shrink-0 ${style.check}`} />
                          <span>{feature}</span>
                        </li>
                      ))}
                    </ul>

                    <Link
                      to={plan.key === 'dj_plus' ? '/subscription?plan=dj_plus' : user ? `/subscription?plan=${plan.key}` : '/register'}
                      className={`mt-7 inline-flex items-center justify-center gap-2 px-5 py-3 text-xs font-bold uppercase tracking-widest transition-opacity hover:opacity-90 ${style.cta} ${plan.is_current ? 'pointer-events-none opacity-70' : ''}`}
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      {ctaLabel(plan, Boolean(user))}
                      <ArrowRight size={14} />
                    </Link>
                  </article>
                  );
                })}
              </div>
            )}
          </div>
        </section>

        <section className="border-y border-[#242424] bg-[#0c0c0c] py-14 md:py-18">
          <div className="container mx-auto grid grid-cols-1 gap-8 px-4 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">
            <div>
              <p className="text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                Promotion inventory
              </p>
              <h2 className="mt-2 text-3xl uppercase md:text-5xl" style={{ fontFamily: 'var(--font-heading)' }}>
                Advertising Groups
              </h2>
              <p className="mt-4 text-sm leading-6 text-[#aaa]">
                Featured promotion is optional. Free and Plus currently focus on the basic and entry-level groups.
              </p>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              {promotionGroups.map(([group, description]) => (
                <div key={group} className="flex gap-4 border border-[#2a2a2a] bg-[#080808] p-4">
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center bg-primary text-xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {group}
                  </div>
                  <div>
                    <h3 className="text-lg uppercase" style={{ fontFamily: 'var(--font-heading)' }}>Group {group}</h3>
                    <p className="mt-1 text-sm leading-5 text-[#aaa]">{description}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="py-14 md:py-20">
          <div className="container mx-auto px-4">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
              {futureFeatures.map(([FeatureIcon, title, body]) => {
                return (
                  <article key={title} className="border border-[#2a2a2a] bg-[#111] p-5">
                    <FeatureIcon size={24} className="text-[#FFB800]" />
                    <h3 className="mt-5 text-xl uppercase" style={{ fontFamily: 'var(--font-heading)' }}>{title}</h3>
                    <p className="mt-3 text-sm leading-6 text-[#aaa]">{body}</p>
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
