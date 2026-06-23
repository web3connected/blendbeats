import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowLeft,
  ArrowRight,
  BadgeHelp,
  BookOpen,
  CreditCard,
  Headphones,
  HelpCircle,
  Megaphone,
  MessageSquareText,
  ShieldCheck,
  UploadCloud,
} from 'lucide-react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';

import AccountLoadingState from './AccountLoadingState';

const helpTopics = [
  {
    title: 'Account & Profile',
    description: 'Get help with login, profile details, avatar, DJ identity, and account settings.',
    icon: ShieldCheck,
    href: '/account/docs/account-management',
  },
  {
    title: 'Uploads & Storage',
    description: 'Review upload limits, portfolio media, audio playback, file storage, and cover images.',
    icon: UploadCloud,
    href: '/account/docs/dj-portfolio-and-mixes',
  },
  {
    title: 'Billing & Payments',
    description: 'Manage PayPal, payment methods, receipts, provider status, and billing questions.',
    icon: CreditCard,
    href: '/account/docs/memberships-subscriptions',
  },
  {
    title: 'Featured Ads',
    description: 'Get support for placement setup, campaign checkout, ad status, impressions, and clicks.',
    icon: Megaphone,
    href: '/account/docs/featured-ads-promotions',
  },
];

const quickAnswers = [
  {
    question: 'Where do I upload mixes?',
    answer: 'Use your DJ Portfolio to upload audio, add cover art, set visibility, and publish public mixes.',
    href: '/account/docs/dj-portfolio-and-mixes',
  },
  {
    question: 'Where do I manage payment methods?',
    answer: 'Payment methods live in the account billing area. PayPal is the active provider right now.',
    href: '/account/docs/purchases-downloads',
  },
  {
    question: 'Where do I review ad performance?',
    answer: 'Featured Ad Analytics shows impressions, clicks, CTR, and campaign performance.',
    href: '/account/docs/featured-ads-promotions',
  },
];

export default function SupportPage() {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return <AccountLoadingState />;
  }

  if (!user) return <Navigate to="/login" replace />;

  return (
    <>
      <Helmet>
        <title>Support | The Blend Battlegrounds</title>
        <meta name="description" content="Get help with BlendBeats account, billing, uploads, DJ profile, promotions, and platform tools." />
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

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
              <div>
                <p
                  className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Account / Support
                </p>
                <h1
                  className="uppercase leading-none text-white"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 8vw, 6.5rem)' }}
                >
                  Support Center
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  Start here for help with your account, DJ profile, uploads, billing, featured ads, and platform tools.
                </p>
              </div>

              <div className="border border-[#303030] bg-[#111111] p-5">
                <div className="mb-4 flex h-12 w-12 items-center justify-center bg-primary text-white">
                  <Headphones size={20} />
                </div>
                <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Signed in as</p>
                <p className="mt-2 truncate text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {user.name}
                </p>
                <p className="mt-1 break-all text-sm text-[#888888]">{user.email}</p>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div className="grid gap-6">
              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <div className="mb-6 flex items-center gap-3 border-b border-[#262626] pb-5">
                  <BadgeHelp className="text-primary" size={22} />
                  <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Help Topics
                  </h2>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                  {helpTopics.map((topic) => {
                    const Icon = topic.icon;

                    return (
                      <article key={topic.title} className="grid min-h-56 border border-[#2a2a2a] bg-[#080808] p-5">
                        <div>
                          <Icon className="text-primary" size={22} />
                          <h3 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                            {topic.title}
                          </h3>
                          <p className="mt-3 text-sm leading-6 text-[#888888]">{topic.description}</p>
                        </div>
                        <Link
                          to={topic.href}
                          className="mt-6 inline-flex h-11 items-center justify-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                          style={{ fontFamily: 'var(--font-heading)' }}
                        >
                          Review
                          <ArrowRight size={15} />
                        </Link>
                      </article>
                    );
                  })}
                </div>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <div className="mb-6 flex items-center gap-3 border-b border-[#262626] pb-5">
                  <BookOpen className="text-[#FFB800]" size={22} />
                  <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Quick Answers
                  </h2>
                </div>

                <div className="grid gap-3">
                  {quickAnswers.map((item) => (
                    <Link
                      key={item.question}
                      to={item.href}
                      className="grid gap-2 border border-[#2a2a2a] bg-[#080808] p-4 transition-colors hover:border-primary"
                    >
                      <span className="text-lg font-semibold text-white">{item.question}</span>
                      <span className="text-sm leading-6 text-[#888888]">{item.answer}</span>
                    </Link>
                  ))}
                </div>
              </section>
            </div>

            <aside className="grid h-fit gap-5">
              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <MessageSquareText className="text-primary" size={24} />
                <h2 className="mt-5 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Contact Support
                </h2>
                <p className="mt-3 text-sm leading-6 text-[#888888]">
                  Ticket submission is coming next. For now, collect the issue details here so the support workflow has a clean home.
                </p>
                <div className="mt-5 grid gap-3">
                  <input
                    readOnly
                    value={user.email}
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-[#aaaaaa] outline-none"
                    aria-label="Account email"
                  />
                  <select className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none">
                    <option>Account Help</option>
                    <option>Billing & Payments</option>
                    <option>Uploads & Storage</option>
                    <option>Featured Ads</option>
                    <option>Bug Report</option>
                  </select>
                  <textarea
                    rows={5}
                    placeholder="Tell us what happened..."
                    className="resize-none border border-[#333333] bg-[#080808] px-3 py-3 text-sm text-white outline-none placeholder:text-[#555555]"
                  />
                  <button
                    type="button"
                    disabled
                    className="inline-flex h-11 cursor-not-allowed items-center justify-center gap-2 bg-primary/50 px-4 text-xs font-bold uppercase tracking-widest text-white"
                    style={{ fontFamily: 'var(--font-heading)' }}
                  >
                    Submit Coming Soon
                  </button>
                </div>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <BookOpen className="text-primary" size={24} />
                <h2 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Documentation Center
                </h2>
                <p className="mt-3 text-sm leading-6 text-[#888888]">
                  Browse account, membership, affiliate, DJ, marketplace, community, and FAQ articles.
                </p>
                <Link
                  to="/account/docs"
                  className="mt-6 inline-flex h-11 w-full items-center justify-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Open Docs
                  <ArrowRight size={15} />
                </Link>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <HelpCircle className="text-[#FFB800]" size={22} />
                <h2 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Before You Send
                </h2>
                <div className="mt-4 space-y-3 text-sm leading-6 text-[#888888]">
                  <p>Include the page URL where the issue happened.</p>
                  <p>For upload issues, include file type and approximate file size.</p>
                  <p>For payment issues, include provider and campaign or plan name.</p>
                </div>
              </section>
            </aside>
          </div>
        </section>
      </main>
    </>
  );
}
