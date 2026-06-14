import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowRight,
  Bell,
  CreditCard,
  HelpCircle,
  Lock,
  Megaphone,
  ShieldCheck,
  SlidersHorizontal,
  UploadCloud,
  User,
  WalletCards,
} from 'lucide-react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';

const settingsCards = [
  {
    title: 'Profile',
    description: 'Manage your personal data, avatar, contact info, location, and social links.',
    href: '/account',
    icon: User,
  },
  {
    title: 'Billing & Payments',
    description: 'Review payment methods, invoices, receipts, and billing preferences.',
    href: '/subscription',
    icon: WalletCards,
  },
  {
    title: 'Membership Plan',
    description: 'View your current tier, compare plans, and manage membership upgrades.',
    href: '/pricing',
    icon: CreditCard,
  },
  {
    title: 'Featured Ads',
    description: 'Manage featured placement campaigns and promotional visibility.',
    href: '/settings',
    icon: Megaphone,
  },
  {
    title: 'Storage',
    description: 'Track storage usage, upload limits, and future storage upgrade options.',
    href: '/dj/portfolio',
    icon: UploadCloud,
  },
  {
    title: 'Security',
    description: 'Control passwords, sessions, account access, and future security tools.',
    href: '/settings',
    icon: Lock,
  },
  {
    title: 'Notifications',
    description: 'Choose how BlendBeats contacts you about posts, mixes, battles, and billing.',
    href: '/settings',
    icon: Bell,
  },
  {
    title: 'Support',
    description: 'Get help with your account, billing, uploads, promotions, and platform tools.',
    href: '/settings',
    icon: HelpCircle,
  },
];

export default function SettingsPage() {
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
        <title>Settings | The Blend Battlegrounds</title>
        <meta name="description" content="Manage your BlendBeats account settings, billing, subscriptions, ads, storage, and support options." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-6xl">
            <p
              className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Account Center
            </p>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
              <div>
                <h1
                  className="uppercase leading-none text-white"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(4rem, 10vw, 8rem)' }}
                >
                  Settings
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  Manage account-level preferences, billing, subscriptions, promotions, storage, security, notifications, and support.
                </p>
              </div>
              <div className="border border-[#303030] bg-[#111111] p-5">
                <div className="mb-4 flex h-12 w-12 items-center justify-center bg-primary text-white">
                  <SlidersHorizontal size={20} />
                </div>
                <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Signed in as</p>
                <p className="mt-2 truncate text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {user.name}
                </p>
                <p className="mt-1 break-all text-sm text-[#888888]">{user.email}</p>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            <div className="mb-6 flex items-center gap-3">
              <ShieldCheck size={18} className="text-primary" />
              <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                Account Settings
              </h2>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {settingsCards.map((item) => {
                const Icon = item.icon;

                return (
                  <article key={item.title} className="grid min-h-56 border border-[#2a2a2a] bg-[#111111] p-5">
                    <div>
                      <div className="mb-5 flex items-center justify-between gap-4">
                        <div className="flex h-11 w-11 items-center justify-center bg-[#080808] text-primary">
                          <Icon size={20} />
                        </div>
                        <span className="text-[10px] font-bold uppercase tracking-widest text-[#555555]">Settings</span>
                      </div>
                      <h3 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        {item.title}
                      </h3>
                      <p className="mt-3 text-sm leading-6 text-[#888888]">{item.description}</p>
                    </div>

                    <Link
                      to={item.href}
                      className="mt-6 inline-flex h-11 items-center justify-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      Manage
                      <ArrowRight size={15} />
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
