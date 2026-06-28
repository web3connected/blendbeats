import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowRight,
  BookOpen,
  CreditCard,
  History,
  ListMusic,
  Music2,
  Radio,
  ShieldCheck,
  Trophy,
  Swords,
  User,
  Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { BillingApiError, getAccountSubscription, type AccountSubscriptionDetails } from '@/lib/billing';
import {
  getAccountGamification,
  getAccountGamificationEvents,
  type AccountGamificationEvent,
  type AccountGamificationSummary,
} from '@/lib/gamification';
import {
  formatSubscriptionDate,
  formatSubscriptionLabel,
  subscriptionIdDisplay,
  subscriptionProviderDisplay,
} from '@/lib/subscription-display';

const dashboardActions = [
  {
    title: 'Enter DJLounge',
    description: 'Post, react, and keep up with the DJ community wall.',
    href: '/dj-lounge',
    icon: Users,
    accent: 'text-primary',
  },
  {
    title: 'My Playlist',
    description: 'Save favorite mixes and play them as your personal BlendBeats queue.',
    href: '/account/playlist',
    icon: ListMusic,
    accent: 'text-[#FFB800]',
  },
  {
    title: 'My Badges',
    description: 'View unlocked achievements, locked badges, rarity, and progress.',
    href: '/account/badges',
    icon: Trophy,
    accent: 'text-[#FFB800]',
  },
  {
    title: 'Documentation Center',
    description: 'Find account, membership, affiliate, DJ, marketplace, community, and FAQ articles.',
    href: '/account/docs',
    icon: BookOpen,
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
    title: 'Profile',
    description: 'Manage your personal data, avatar, email, contact info, and location.',
    href: '/account/profile',
    icon: User,
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

function formatActionKey(actionKey: string): string {
  return actionKey
    .split('_')
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

function formatBadgeDate(value: string | null): string {
  if (!value) return 'Not Set';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(value));
}

export default function UserDashboardPage() {
  const { user, isLoading } = useAuth();
  const [subscription, setSubscription] = useState<AccountSubscriptionDetails | null>(null);
  const [gamification, setGamification] = useState<AccountGamificationSummary | null>(null);
  const [gamificationEvents, setGamificationEvents] = useState<AccountGamificationEvent[]>([]);
  const [isSubscriptionLoading, setIsSubscriptionLoading] = useState(false);
  const [isGamificationLoading, setIsGamificationLoading] = useState(false);
  const [isGamificationEventsLoading, setIsGamificationEventsLoading] = useState(false);
  const [subscriptionError, setSubscriptionError] = useState('');
  const [gamificationError, setGamificationError] = useState('');
  const [gamificationEventsError, setGamificationEventsError] = useState('');

  useEffect(() => {
    if (!user) {
      setSubscription(null);
      setIsSubscriptionLoading(false);
      setSubscriptionError('');
      return;
    }

    setIsSubscriptionLoading(true);
    setSubscriptionError('');

    getAccountSubscription()
      .then((response) => setSubscription(response))
      .catch((loadError) => {
        setSubscription(null);
        setSubscriptionError(loadError instanceof BillingApiError ? loadError.message : 'Unable to load subscription details.');
      })
      .finally(() => setIsSubscriptionLoading(false));
  }, [user?.id]);

  useEffect(() => {
    if (!user) {
      setGamification(null);
      setGamificationEvents([]);
      setIsGamificationLoading(false);
      setIsGamificationEventsLoading(false);
      setGamificationError('');
      setGamificationEventsError('');
      return;
    }

    setIsGamificationLoading(true);
    setIsGamificationEventsLoading(true);
    setGamificationError('');
    setGamificationEventsError('');

    getAccountGamification()
      .then((response) => setGamification(response))
      .catch(() => {
        setGamification(null);
        setGamificationError('Unable to load gamification details.');
      })
      .finally(() => setIsGamificationLoading(false));

    getAccountGamificationEvents()
      .then((response) => setGamificationEvents(response))
      .catch(() => {
        setGamificationEvents([]);
        setGamificationEventsError('Unable to load recent achievements.');
      })
      .finally(() => setIsGamificationEventsLoading(false));
  }, [user?.id]);

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
  const subscriptionRows = [
    ['Current Plan', formatSubscriptionLabel(subscription?.plan ?? tierKey)],
    ['Status', formatSubscriptionLabel(subscription?.status)],
    ['Billing Provider', subscriptionProviderDisplay(subscription)],
    ['Subscription ID', subscriptionIdDisplay(subscription)],
    ['Approved Date', formatSubscriptionDate(subscription?.approved_at)],
    ['Expiration Date', formatSubscriptionDate(subscription?.expires_at)],
    ['Reason', subscription?.reason || 'Not Set'],
  ];
  const djProfileActionLabel = hasDjProfile ? 'Edit DJ Profile' : 'Start DJ Career';
  const djProfileStatus = hasDjProfile ? 'DJ profile active' : 'DJ profile not started';
  const gamificationRows = [
    ['DJ Level', gamification?.dj_level ?? 1],
    ['Fan Level', gamification?.fan_level ?? 1],
    ['Total XP', gamification?.total_xp ?? 0],
    ['DJ XP', gamification?.dj_xp ?? 0],
    ['Fan XP', gamification?.fan_xp ?? 0],
  ];
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

              {subscriptionError ? (
                <p className="mt-5 border-t border-[#252525] pt-5 text-sm leading-6 text-primary">
                  {subscriptionError}
                </p>
              ) : (
                <dl className="mt-5 grid gap-4 border-t border-[#252525] pt-5 sm:grid-cols-2 lg:grid-cols-4">
                  {subscriptionRows.map(([label, value]) => (
                    <div key={label}>
                      <dt className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">{label}</dt>
                      <dd className="mt-1 break-words text-sm font-semibold text-[#eeeeee]">
                        {isSubscriptionLoading ? 'Loading' : value}
                      </dd>
                    </div>
                  ))}
                </dl>
              )}
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
                  <Trophy size={18} className="text-[#FFB800]" />
                  <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Gamification
                  </h2>
                </div>

                {gamificationError ? (
                  <p className="text-sm leading-6 text-primary">{gamificationError}</p>
                ) : (
                  <>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                      {gamificationRows.map(([label, value]) => (
                        <div key={label} className="border border-[#303030] bg-[#0b0b0b] p-4">
                          <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">
                            {label}
                          </p>
                          <p className="mt-2 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                            {isGamificationLoading ? 'Loading' : value.toLocaleString()}
                          </p>
                        </div>
                      ))}
                    </div>

                    <div className="mt-6 border-t border-[#252525] pt-5">
                      <div className="mb-4 flex items-center gap-3">
                        <ShieldCheck size={17} className="text-[#FFB800]" />
                        <h3 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          Badges
                        </h3>
                      </div>

                      {isGamificationLoading ? (
                        <p className="text-sm leading-6 text-[#888888]">Loading badges.</p>
                      ) : gamification?.badges?.length ? (
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                          {gamification.badges.map((badge) => (
                            <div
                              key={badge.badge_key ?? badge.name ?? badge.unlocked_at ?? 'badge'}
                              className="flex min-h-28 gap-4 border border-[#303030] bg-[#0b0b0b] p-4"
                            >
                              <img
                                src={`/assets/${badge.icon}`}
                                alt={badge.name ?? 'Badge'}
                                className="h-14 w-14 shrink-0 rounded-full border border-[#303030] bg-[#080808] object-contain p-1"
                              />
                              <div className="min-w-0">
                                <p className="text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                                  {badge.name}
                                </p>
                                <p className="mt-1 text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
                                  {badge.rarity ?? 'common'}
                                </p>
                                <p className="mt-2 text-xs leading-5 text-[#888888]">
                                  Unlocked {formatBadgeDate(badge.unlocked_at)}
                                </p>
                              </div>
                            </div>
                          ))}
                        </div>
                      ) : (
                        <p className="text-sm leading-6 text-[#888888]">No badges unlocked yet.</p>
                      )}
                    </div>
                  </>
                )}

                <div className="mt-6 border-t border-[#252525] pt-5">
                  <div className="mb-4 flex items-center gap-3">
                    <History size={17} className="text-primary" />
                    <h3 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Recent Achievements
                    </h3>
                  </div>

                  {gamificationEventsError ? (
                    <p className="text-sm leading-6 text-primary">{gamificationEventsError}</p>
                  ) : isGamificationEventsLoading ? (
                    <p className="text-sm leading-6 text-[#888888]">Loading recent achievements.</p>
                  ) : gamificationEvents.length > 0 ? (
                    <div className="grid gap-2">
                      {gamificationEvents.map((event, index) => (
                        <div
                          key={`${event.action_key}-${event.created_at ?? index}`}
                          className="flex items-center justify-between gap-4 border border-[#303030] bg-[#0b0b0b] px-4 py-3"
                        >
                          <p className="text-sm font-semibold text-white">
                            <span className="text-[#FFB800]">+{event.xp_awarded.toLocaleString()} XP</span>
                            <span className="text-[#666666]"> — </span>
                            {formatActionKey(event.action_key)}
                          </p>
                          <span className="shrink-0 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
                            {event.role_context}
                          </span>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-sm leading-6 text-[#888888]">
                      Earn XP by uploading, saving mixes, following DJs, rating mixes, and logging in daily.
                    </p>
                  )}
                </div>
              </section>

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
