import { Link } from 'react-router-dom';
import type { LucideIcon } from 'lucide-react';
import { ArrowRight, Bot, BriefcaseBusiness, Check, Sparkles, Star, TrendingUp } from 'lucide-react';

import { useAuth } from '@/components/auth/AuthProvider';
import HeaderTitle from '@/layouts/HeaderTitle';

type Tier = {
  key: string;
  name: string;
  price: string;
  note: string;
  storage: string;
  groups: string;
  featured?: boolean;
  cta: string;
  href: string;
  features: string[];
};

const tiers: Tier[] = [
  {
    key: 'free',
    name: 'Free',
    price: '$0',
    note: 'Join, build, upload, and participate.',
    storage: '500 MB',
    groups: 'Group F',
    cta: 'Start Free',
    href: '/register',
    features: [
      'DJ profile and DJ Hub listing',
      'DJ Lounge community access',
      'Public mixes and portfolio tools',
      'Basic analytics',
      'Basic promotion access',
    ],
  },
  {
    key: 'dj_plus',
    name: 'DJ Plus',
    price: 'Soon',
    note: 'More room and better promotion access.',
    storage: '3 GB',
    groups: 'Groups E-F',
    cta: 'Join Plus',
    href: '/register',
    features: [
      'Everything in Free',
      'Expanded storage',
      'Promotion groups E-F',
      'Growth-focused profile tools',
      'Priority feature access as tools launch',
    ],
  },
  {
    key: 'dj_pro',
    name: 'DJ Pro',
    price: 'Soon',
    note: 'For active DJs building an audience.',
    storage: '10 GB',
    groups: 'Groups C-F',
    featured: true,
    cta: 'Go Pro',
    href: '/register',
    features: [
      'Everything in DJ Plus',
      'Advanced analytics path',
      'Promotion groups C-F',
      'Booking growth tools',
      'Mix and profile optimization support',
    ],
  },
  {
    key: 'dj_elite',
    name: 'DJ Elite',
    price: 'Soon',
    note: 'Full growth, booking, and promotion access.',
    storage: '25 GB',
    groups: 'Groups A-F',
    cta: 'Join Elite',
    href: '/register',
    features: [
      'Everything in DJ Pro',
      'Premium promotion inventory',
      'AI DJ Assistant path',
      'AI Booking Assistant path',
      'Business automation tools',
    ],
  },
];

const promotionGroups = [
  ['A', 'Premium site visibility with limited inventory.'],
  ['B', 'High visibility across multiple site locations.'],
  ['C', 'Major community placement access.'],
  ['D', 'Community visibility for growing DJs.'],
  ['E', 'Entry-level promotion inventory.'],
  ['F', 'Basic promotional access for Free Tier users.'],
];

const futureFeatures: Array<[LucideIcon, string, string]> = [
  [TrendingUp, 'Analytics Suite', 'Profile views, mix plays, follower growth, and promotion performance.'],
  [Bot, 'AI DJ Assistant', 'Career recommendations, mix promotion ideas, and profile optimization.'],
  [BriefcaseBusiness, 'Booking Tools', 'Booking requests, client records, reminders, and scheduling workflows.'],
  [Sparkles, 'Automation', 'Follow-ups, lead nurturing, opportunity tracking, and future AI agent workflows.'],
];

export default function PricingPage() {
  const { user } = useAuth();

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
                Every DJ can join, upload mixes, build a profile, use the Lounge, and grow a following for free. Paid tiers add storage, analytics, AI assistance, booking tools, and stronger promotion access.
              </p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <Link
                  to={user ? '/subscription' : '/register'}
                  className="inline-flex items-center justify-center gap-3 bg-primary px-7 py-4 text-xs font-bold uppercase tracking-widest text-white transition-opacity hover:opacity-90"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Create Account
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

            <div className="border border-[#2a2a2a] bg-[#101010] p-5 md:p-7">
              <div className="grid grid-cols-2 gap-3">
                {[
                  ['Free', 'Core participation'],
                  ['A-F', 'Promotion groups'],
                  ['1/7', 'Campaign options'],
                  ['AI', 'Future growth tools'],
                ].map(([value, label]) => (
                  <div key={label} className="border border-[#2a2a2a] bg-[#080808] p-4">
                    <p className="text-3xl text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
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
                  Featured promotion
                </p>
                <p className="mt-3 text-sm leading-6 text-[#aaa]">
                  Optional campaigns let DJs promote a profile or mix for 1 day or 7 days. Higher memberships unlock stronger placement groups.
                </p>
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
                Pricing is ready for Stripe products. Until checkout is connected, paid plans show as coming soon.
              </p>
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
              {tiers.map((tier) => (
                <article
                  key={tier.name}
                  className={`relative flex min-h-[560px] flex-col border bg-[#111] p-5 ${
                    tier.featured ? 'border-primary shadow-[0_0_0_1px_rgba(255,32,32,0.35)]' : 'border-[#2a2a2a]'
                  }`}
                >
                  {tier.featured ? (
                    <div className="absolute right-4 top-4 inline-flex items-center gap-1 bg-primary px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      <Star size={12} />
                      Popular
                    </div>
                  ) : null}
                  <h3 className="text-3xl uppercase" style={{ fontFamily: 'var(--font-heading)' }}>
                    {tier.name}
                  </h3>
                  <div className="mt-5">
                    <span className="text-5xl text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                      {tier.price}
                    </span>
                    {tier.price !== '$0' ? <span className="ml-2 text-sm text-[#777]">test pricing</span> : null}
                  </div>
                  <p className="mt-4 min-h-12 text-sm leading-6 text-[#aaa]">{tier.note}</p>

                  <div className="mt-5 grid grid-cols-2 gap-2">
                    <div className="border border-[#2a2a2a] p-3">
                      <p className="text-[10px] uppercase tracking-widest text-[#777]" style={{ fontFamily: 'var(--font-heading)' }}>Storage</p>
                      <p className="mt-1 text-lg text-white" style={{ fontFamily: 'var(--font-heading)' }}>{tier.storage}</p>
                    </div>
                    <div className="border border-[#2a2a2a] p-3">
                      <p className="text-[10px] uppercase tracking-widest text-[#777]" style={{ fontFamily: 'var(--font-heading)' }}>Ads</p>
                      <p className="mt-1 text-lg text-white" style={{ fontFamily: 'var(--font-heading)' }}>{tier.groups}</p>
                    </div>
                  </div>

                  <ul className="mt-6 flex-1 space-y-3">
                    {tier.features.map((feature) => (
                      <li key={feature} className="flex gap-3 text-sm leading-5 text-[#d0d0d0]">
                        <Check size={16} className="mt-0.5 shrink-0 text-primary" />
                        <span>{feature}</span>
                      </li>
                    ))}
                  </ul>

                  <Link
                    to={user ? `/subscription?plan=${tier.key}` : tier.href}
                    className={`mt-7 inline-flex items-center justify-center gap-2 px-5 py-3 text-xs font-bold uppercase tracking-widest transition-opacity hover:opacity-90 ${
                      tier.featured ? 'bg-primary text-white' : 'border border-[#333] text-white hover:border-primary'
                    }`}
                    style={{ fontFamily: 'var(--font-heading)' }}
                  >
                    {tier.cta}
                    <ArrowRight size={14} />
                  </Link>
                </article>
              ))}
            </div>
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
                Featured promotion is optional. DJs can claim 1-day or 7-day campaigns, with stronger groups unlocked by higher memberships.
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
