import { Helmet } from '@dr.pogodin/react-helmet';
import { ArrowLeft, ArrowRight, BookOpen, CheckCircle2, HelpCircle } from 'lucide-react';
import { Link, Navigate, useParams } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';

type SupportDoc = {
  title: string;
  eyebrow: string;
  summary: string;
  manageHref: string;
  sections: Array<{
    title: string;
    body: string;
  }>;
};

const docs: Record<string, SupportDoc> = {
  'account-profile': {
    title: 'Account & Profile',
    eyebrow: 'Documentation',
    summary: 'Learn how your account profile, public DJ profile, avatar, identity fields, and settings work together.',
    manageHref: '/account/profile',
    sections: [
      {
        title: 'Account Profile',
        body: 'Your account profile stores personal account data like name, contact details, location, timezone, and avatar. This information supports account activity across the platform.',
      },
      {
        title: 'DJ Profile',
        body: 'Your DJ profile is the public creator profile shown in DJ Hub. It includes your DJ name, handle, genres, booking status, bio, location, and portfolio connections.',
      },
      {
        title: 'Avatar',
        body: 'BlendBeats uses one shared avatar for your account, posts, DJ profile, and platform activity. Uploading or changing the avatar updates the same user avatar source.',
      },
      {
        title: 'Visibility',
        body: 'A DJ profile must be active and public before it appears in DJ Hub or becomes eligible for public discovery and featured promotion.',
      },
    ],
  },
  'uploads-storage': {
    title: 'Uploads & Storage',
    eyebrow: 'Documentation',
    summary: 'Understand upload storage, portfolio media, cover images, public/private visibility, and storage limits.',
    manageHref: '/account/storage',
    sections: [
      {
        title: 'Storage Limits',
        body: 'Your membership tier controls the total upload storage available to your account. Free accounts include a starter storage limit, and paid tiers unlock more capacity.',
      },
      {
        title: 'Portfolio Media',
        body: 'Audio, video, images, cover art, and future media assets uploaded through the portfolio count toward your storage usage.',
      },
      {
        title: 'Public Visibility',
        body: 'Only media marked public can appear on public pages such as Mixes. Private, draft, and unpublished media stay hidden from public discovery.',
      },
      {
        title: 'Playback',
        body: 'Uploaded audio plays through the global bottom player when the file is public and the media URL is available through the media manager.',
      },
    ],
  },
  'billing-payments': {
    title: 'Billing & Payments',
    eyebrow: 'Documentation',
    summary: 'Review how payment providers, PayPal linking, billing pages, and subscription payments fit together.',
    manageHref: '/account/billing',
    sections: [
      {
        title: 'Payment Providers',
        body: 'BlendBeats can support multiple providers. PayPal is active first, while Stripe remains prepared for future subscription and checkout flows.',
      },
      {
        title: 'Payment Methods',
        body: 'The payment methods page shows available payment providers and lets users start linking or managing provider-specific payment access.',
      },
      {
        title: 'Subscriptions',
        body: 'Membership tiers control storage, advertising group access, and future growth tools. Checkout uses the active payment provider configured by admins.',
      },
      {
        title: 'Receipts And Billing',
        body: 'Billing history and receipts will be expanded as payment flows become more complete. The billing page is the long-term home for these records.',
      },
    ],
  },
  'featured-ads': {
    title: 'Featured Ads',
    eyebrow: 'Documentation',
    summary: 'Learn how featured placements, campaign groups, slots, impressions, clicks, and analytics work.',
    manageHref: '/account/featured-ads',
    sections: [
      {
        title: 'Placement Groups',
        body: 'Campaign groups A through F represent different visibility levels. Higher groups receive stronger placement opportunities and higher display probability.',
      },
      {
        title: 'Slots',
        body: 'Each group has reusable slots. A campaign claim creates a real ad instance inside a slot while keeping the group layout reusable.',
      },
      {
        title: 'Campaign Length',
        body: 'Campaign setup supports durations such as 1-day and 7-day placements. The setup flow lets you select duration, start date, and payment.',
      },
      {
        title: 'Analytics',
        body: 'Ad analytics track impressions when an ad enters view and clicks when users interact with the ad card. CTR is calculated from those totals.',
      },
    ],
  },
  'upload-mixes': {
    title: 'Uploading Mixes',
    eyebrow: 'Quick Answer',
    summary: 'Use the DJ Portfolio to upload audio, add media details, and publish mixes publicly.',
    manageHref: '/dj/portfolio',
    sections: [
      {
        title: 'Upload Flow',
        body: 'Go to DJ Portfolio, choose a media file, add title, genre, description, media type, visibility, and optional cover image.',
      },
      {
        title: 'Public Mixes',
        body: 'A mix appears on the public Mixes page only when its visibility is public and the file is available through the media manager.',
      },
    ],
  },
  'payment-methods': {
    title: 'Payment Methods',
    eyebrow: 'Quick Answer',
    summary: 'Payment methods are managed through the account payment methods area.',
    manageHref: '/account/payment-methods',
    sections: [
      {
        title: 'Provider List',
        body: 'The payment methods page should show available providers first, then let the user connect or manage the provider they choose.',
      },
      {
        title: 'PayPal Sandbox',
        body: 'Sandbox PayPal uses sandbox buyer accounts. A real PayPal account will not stay logged in inside sandbox checkout.',
      },
    ],
  },
  'ad-performance': {
    title: 'Ad Performance',
    eyebrow: 'Quick Answer',
    summary: 'Featured Ad Analytics shows campaign impressions, clicks, CTR, and placement performance.',
    manageHref: '/account/featured-ads/analytics',
    sections: [
      {
        title: 'Impressions',
        body: 'An impression is counted when the ad card comes into view while the page is focused.',
      },
      {
        title: 'Clicks',
        body: 'A click is counted when a user selects the displayed ad card.',
      },
      {
        title: 'CTR',
        body: 'CTR is the percentage of impressions that turned into clicks.',
      },
    ],
  },
};

export default function SupportDocPage() {
  const { user, isLoading } = useAuth();
  const { topic = '' } = useParams();
  const doc = docs[topic];

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

  if (!doc) return <Navigate to="/account/support" replace />;

  return (
    <>
      <Helmet>
        <title>{doc.title} Support | The Blend Battlegrounds</title>
        <meta name="description" content={doc.summary} />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-5xl">
            <Link
              to="/account/support"
              className="mb-10 inline-flex h-11 items-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              Support
            </Link>

            <p className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
              {doc.eyebrow}
            </p>
            <h1
              className="uppercase leading-none text-white"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.2rem, 7vw, 6rem)' }}
            >
              {doc.title}
            </h1>
            <p className="mt-5 max-w-3xl text-base leading-7 text-[#aaaaaa]">{doc.summary}</p>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
            <div className="grid gap-4">
              {doc.sections.map((section) => (
                <article key={section.title} className="border border-[#2a2a2a] bg-[#111111] p-5">
                  <div className="flex items-start gap-3">
                    <CheckCircle2 className="mt-1 shrink-0 text-[#FFB800]" size={20} />
                    <div>
                      <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        {section.title}
                      </h2>
                      <p className="mt-3 text-sm leading-7 text-[#aaaaaa]">{section.body}</p>
                    </div>
                  </div>
                </article>
              ))}
            </div>

            <aside className="grid h-fit gap-5">
              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <BookOpen className="text-primary" size={24} />
                <h2 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Documentation
                </h2>
                <p className="mt-3 text-sm leading-6 text-[#888888]">
                  This page explains how this support area works. Use the action below when you are ready to manage the real setting.
                </p>
                <Link
                  to={doc.manageHref}
                  className="mt-6 inline-flex h-11 w-full items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-[#d91515]"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Go Manage
                  <ArrowRight size={15} />
                </Link>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <HelpCircle className="text-[#FFB800]" size={22} />
                <h2 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Still Need Help?
                </h2>
                <p className="mt-3 text-sm leading-6 text-[#888888]">
                  Return to the Support Center and use the contact panel when ticket submission is enabled.
                </p>
              </section>
            </aside>
          </div>
        </section>
      </main>
    </>
  );
}
