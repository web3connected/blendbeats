import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowRight,
  CreditCard,
  ListMusic,
  Music2,
  Radio,
  Settings,
  ShieldCheck,
  Swords,
  User,
  Users,
} from 'lucide-react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';

const dashboardActions = [
  {
    title: 'Enter DJLounge',
    description: 'Post, react, and keep up with the DJ community wall.',
    href: '/dj-lounge',
    icon: Users,
    accent: 'text-primary',
  },
  {
    title: 'Start DJ Career',
    description: 'Create your DJ profile, claim a handle, and unlock creator tools.',
    href: '/dj/start',
    icon: Radio,
    accent: 'text-primary',
  },
  {
    title: 'Explore Battles',
    description: 'Vote on live battles and follow the DJs making noise right now.',
    href: '/battles',
    icon: Swords,
    accent: 'text-[#FFB800]',
  },
  {
    title: 'My DJ Portfolio',
    description: 'Upload and manage your mixes, tracks, videos, and creator media.',
    href: '/dj/portfolio',
    icon: Music2,
    accent: 'text-primary',
  },
  {
    title: 'Account Settings',
    description: 'Manage profile details, email, password, and account preferences.',
    href: '/account',
    icon: Settings,
    accent: 'text-[#FFB800]',
  },
];

const membershipTiers: Record<string, { label: string; storage: string; groups: string; tone: string }> = {
  free: {
    label: 'Free Tier',
    storage: '500 MB storage',
    groups: 'Group F promotion access',
    tone: 'Core BlendBeats access is active.',
  },
  starter: {
    label: 'Free Tier',
    storage: '500 MB storage',
    groups: 'Group F promotion access',
    tone: 'Core BlendBeats access is active.',
  },
  dj_plus: {
    label: 'DJ Plus',
    storage: '3 GB storage',
    groups: 'Groups E-F promotion access',
    tone: 'Extra growth tools are active.',
  },
  growth: {
    label: 'DJ Plus',
    storage: '3 GB storage',
    groups: 'Groups E-F promotion access',
    tone: 'Extra growth tools are active.',
  },
  dj_pro: {
    label: 'DJ Pro',
    storage: '10 GB storage',
    groups: 'Groups C-F promotion access',
    tone: 'Pro growth tools are active.',
  },
  premium: {
    label: 'DJ Pro',
    storage: '10 GB storage',
    groups: 'Groups C-F promotion access',
    tone: 'Pro growth tools are active.',
  },
  dj_elite: {
    label: 'DJ Elite',
    storage: '25 GB storage',
    groups: 'Groups A-F promotion access',
    tone: 'Elite growth and promotion access is active.',
  },
};

export default function UserDashboardPage() {
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

  const hasDjProfile = Boolean(user.dj_profile);
  const avatarUrl = user.avatar_url || user.custom_avatar_url || user.gravatar_url || user.generated_avatar_url;
  const djProfileUrl = user.dj_profile?.handle ? `/djs/${user.dj_profile.handle}` : null;
  const tierKey = user.media_storage_tier ?? 'free';
  const membership = membershipTiers[tierKey] ?? membershipTiers.free;
  const isFreeTier = ['free', 'starter'].includes(tierKey);
  const djProfileActionLabel = hasDjProfile ? 'Edit DJ Profile' : 'Start DJ Career';
  const djProfileStatus = hasDjProfile ? 'DJ profile active' : 'DJ profile not started';
  const actions = dashboardActions.map((action) =>
    action.href === '/dj/start'
      ? {
          ...action,
          title: djProfileActionLabel,
          description: hasDjProfile
            ? 'Update your DJ profile, genres, links, booking status, and public presence.'
            : action.description,
          href: hasDjProfile ? '/dj/edit' : '/dj/start',
        }
      : action.href === '/dj/portfolio' && !hasDjProfile
        ? {
            ...action,
            title: 'Start DJ Career',
            description: 'Create your DJ profile before uploading and managing portfolio media.',
            href: '/dj/start',
            icon: Radio,
          }
      : action,
  );

  return (
    <>
      <Helmet>
        <title>Dashboard | The Blend Battlegrounds</title>
        <meta name="description" content="Your Blend Battlegrounds dashboard." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-6xl">
            <p
              className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              User Dashboard
            </p>
            <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <h1
                  className="text-white uppercase leading-none"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(4rem, 10vw, 8rem)' }}
                >
                  Welcome, {user.name}
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  This is your home base as a fan, listener, and DJ. Build your profile when you are ready to publish mixes, battle, and grow your channel.
                </p>
              </div>
              <Link
                to={hasDjProfile ? '/dj/edit' : '/dj/start'}
                className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                {djProfileActionLabel}
                <ArrowRight size={17} />
              </Link>
            </div>

            <div className="mt-8 border border-[#303030] bg-[#111111] p-5 sm:p-6">
              <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex gap-4">
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center bg-primary text-white">
                    <CreditCard size={20} />
                  </div>
                  <div>
                    <p className="text-xs font-bold uppercase tracking-widest text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                      Membership
                    </p>
                    <h2 className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      {membership.label}
                    </h2>
                    <p className="mt-2 text-sm leading-6 text-[#aaaaaa]">
                      {membership.tone} You have {membership.storage} and {membership.groups}.
                    </p>
                  </div>
                </div>
                <Link
                  to={isFreeTier ? '/pricing' : `/subscription?plan=${tierKey}`}
                  className="inline-flex h-12 items-center justify-center gap-2 border border-primary px-5 text-sm font-bold uppercase tracking-widest text-primary transition-colors hover:bg-primary hover:text-white"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  {isFreeTier ? 'Upgrade Membership' : 'Manage Membership'}
                  <ArrowRight size={16} />
                </Link>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
            <aside className="border border-[#2a2a2a] bg-[#111111] p-5 text-center">
              {avatarUrl ? (
                <img
                  src={avatarUrl}
                  alt={user.name}
                  className="mx-auto mb-5 h-16 w-16 border border-[#333333] bg-[#080808] object-cover"
                />
              ) : (
                <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center bg-primary text-2xl font-black uppercase text-white">
                  {user.name.charAt(0)}
                </div>
              )}
              <p className="text-lg font-semibold text-white">{user.name}</p>
              <p className="mt-1 break-all text-sm text-[#888888]">{user.email}</p>
              {djProfileUrl && (
                <Link
                  to={djProfileUrl}
                  className="mt-4 inline-flex h-10 items-center justify-center border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  View DJ Profile
                </Link>
              )}
              <div className="mt-6 grid gap-3 border-t border-[#252525] pt-5">
                <div className="flex items-center gap-3 text-sm text-[#cccccc]">
                  <ShieldCheck size={16} className="text-primary" />
                  Listener account active
                </div>
                <div className="flex items-center gap-3 text-sm text-[#cccccc]">
                  <User size={16} className="text-[#FFB800]" />
                  {djProfileStatus}
                </div>
              </div>
            </aside>

            <div className="grid gap-5">
              <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                <div className="mb-5 flex items-center gap-3">
                  <ListMusic size={18} className="text-primary" />
                  <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Next Steps
                  </h2>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                  {actions.map((action) => {
                    const Icon = action.icon;
                    return (
                      <Link
                        key={action.title}
                        to={action.href}
                        className="group min-h-40 border border-[#303030] bg-[#0b0b0b] p-4 transition-colors hover:border-primary"
                      >
                        <div className="mb-4 flex items-center justify-between gap-3">
                          <Icon size={22} className={action.accent} />
                          <ArrowRight size={16} className="text-[#555555] transition-colors group-hover:text-primary" />
                        </div>
                        <h3
                          className="text-2xl uppercase text-white"
                          style={{ fontFamily: 'var(--font-heading)' }}
                        >
                          {action.title}
                        </h3>
                        <p className="mt-2 text-sm leading-6 text-[#888888]">{action.description}</p>
                      </Link>
                    );
                  })}
                </div>
              </section>
            </div>
          </div>
        </section>
      </main>
    </>
  );
}
