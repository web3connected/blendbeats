import { Link } from 'react-router-dom';
import { ArrowRight, FileText, Mail, ShieldCheck, UsersRound } from 'lucide-react';

import HeaderTitle from '@/layouts/HeaderTitle';

type InfoPageProps = {
  eyebrow: string;
  title: string;
  description: string;
  icon: 'users' | 'mail' | 'shield' | 'file';
  sections: Array<{
    title: string;
    body: string;
  }>;
  cta?: {
    label: string;
    href: string;
  };
};

const icons = {
  users: UsersRound,
  mail: Mail,
  shield: ShieldCheck,
  file: FileText,
};

function InfoPage({ eyebrow, title, description, icon, sections, cta }: InfoPageProps) {
  const Icon = icons[icon];

  return (
    <>
      <HeaderTitle title={`${title} | BlendBeats`} description={description} />

      <main className="bg-[#080808] text-white">
        <section className="border-b border-[#242424]">
          <div className="container mx-auto grid min-h-[48vh] grid-cols-1 gap-8 px-4 py-16 lg:grid-cols-[1fr_320px] lg:items-end lg:py-24">
            <div>
              <p className="mb-3 text-xs font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                {eyebrow}
              </p>
              <h1 className="max-w-4xl uppercase leading-none" style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3rem, 9vw, 7rem)' }}>
                {title}
              </h1>
              <p className="mt-6 max-w-2xl text-base leading-7 text-[#c9c9c9] md:text-lg">{description}</p>
              {cta && (
                <Link
                  to={cta.href}
                  className="mt-8 inline-flex items-center justify-center gap-3 bg-primary px-7 py-4 text-xs font-bold uppercase tracking-widest text-white transition-opacity hover:opacity-90"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  {cta.label}
                  <ArrowRight size={16} />
                </Link>
              )}
            </div>

            <div className="border border-[#303030] bg-[#111111] p-6">
              <Icon className="text-[#FFB800]" size={28} />
              <p className="mt-8 text-sm leading-6 text-[#aaaaaa]">
                BlendBeats keeps the public experience centered on discovery, DJ growth, transparent account tools, and community-first platform rules.
              </p>
            </div>
          </div>
        </section>

        <section className="py-14 md:py-20">
          <div className="container mx-auto grid gap-4 px-4 md:grid-cols-2">
            {sections.map((section) => (
              <article key={section.title} className="border border-[#2a2a2a] bg-[#111111] p-5">
                <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {section.title}
                </h2>
                <p className="mt-4 text-sm leading-7 text-[#aaaaaa]">{section.body}</p>
              </article>
            ))}
          </div>
        </section>
      </main>
    </>
  );
}

export function AboutPage() {
  return (
    <InfoPage
      eyebrow="About the platform"
      title="About BlendBeats"
      description="BlendBeats is a DJ culture platform for battles, mixes, profiles, merch, news, and community tools."
      icon="users"
      cta={{ label: 'Explore DJ Hub', href: '/djs' }}
      sections={[
        {
          title: 'What We Build',
          body: 'The platform gives DJs a place to publish mixes, shape a public profile, enter competitive moments, and connect with listeners through music-first community features.',
        },
        {
          title: 'Who It Serves',
          body: 'BlendBeats is built for working DJs, emerging selectors, fans, crews, promoters, and music lovers who want discovery, performance, and culture in one place.',
        },
        {
          title: 'Current Focus',
          body: 'The active public experience includes battles, mixes, DJ profiles, pricing, merch, BlendNews, affiliate membership credits, and DJ community tools.',
        },
        {
          title: 'What Comes Next',
          body: 'The foundation is ready for richer battle workflows, advanced analytics, promotion inventory, creator tools, and deeper reporting as the platform grows.',
        },
      ]}
    />
  );
}

export function ContactPage() {
  return (
    <InfoPage
      eyebrow="Contact"
      title="Contact BlendBeats"
      description="Use the current account support center for platform help, billing questions, uploads, DJ profiles, and promotion support."
      icon="mail"
      cta={{ label: 'Open Support Center', href: '/account/support' }}
      sections={[
        {
          title: 'Account Support',
          body: 'Signed-in users can use the support center for account, billing, storage, uploads, DJ profile, notification, and featured advertising help.',
        },
        {
          title: 'DJ Profile Help',
          body: 'For profile setup, portfolio uploads, scratch routines, and public DJ profile questions, start with your account dashboard and support resources.',
        },
        {
          title: 'Billing And Membership',
          body: 'Membership and payment information is available from account billing. Free membership credits from affiliate rewards appear in the affiliate dashboard.',
        },
        {
          title: 'Business And Promotions',
          body: 'Promotion and marketplace features are managed through the account area while public campaign and commerce tools continue to expand.',
        },
      ]}
    />
  );
}

export function PrivacyPage() {
  return (
    <InfoPage
      eyebrow="Privacy"
      title="Privacy Policy"
      description="A clear summary of how BlendBeats handles account, profile, billing, content, and platform activity data."
      icon="shield"
      sections={[
        {
          title: 'Account Data',
          body: 'BlendBeats stores account information such as name, email, avatar, contact preferences, membership tier, and profile details needed to operate the service.',
        },
        {
          title: 'Content And Activity',
          body: 'Uploaded media, DJ profile data, mixes, ratings, follows, playlist actions, affiliate activity, notifications, and community posts may be stored as part of normal platform use.',
        },
        {
          title: 'Billing And Providers',
          body: 'Billing providers process payment details. BlendBeats stores provider identifiers, subscription status, membership tier, and related billing metadata needed for access control.',
        },
        {
          title: 'Operational Use',
          body: 'Data is used to provide accounts, profiles, playback, memberships, referrals, notifications, moderation, analytics, support, fraud protection, and platform administration.',
        },
      ]}
    />
  );
}

export function TermsPage() {
  return (
    <InfoPage
      eyebrow="Terms"
      title="Terms Of Service"
      description="The core expectations for using BlendBeats accounts, DJ tools, public profiles, commerce, memberships, and community features."
      icon="file"
      sections={[
        {
          title: 'Platform Use',
          body: 'Users are responsible for keeping account information accurate, respecting other members, and using BlendBeats features in ways that support the DJ community.',
        },
        {
          title: 'Content Responsibility',
          body: 'Users should only upload or link content they have the right to share. BlendBeats may review, restrict, or remove content that violates platform rules.',
        },
        {
          title: 'Memberships And Rewards',
          body: 'Membership access, free credits, affiliate rewards, promotions, and billing features depend on current program settings and provider availability.',
        },
        {
          title: 'Administration',
          body: 'BlendBeats staff may manage accounts, content, promotions, referrals, rewards, notifications, and platform settings to keep the service reliable and fair.',
        },
      ]}
    />
  );
}
