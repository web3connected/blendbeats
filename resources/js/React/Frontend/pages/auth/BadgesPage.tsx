import { Helmet } from '@dr.pogodin/react-helmet';
import { CheckCircle2, Lock, ShieldCheck, Trophy } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  getAccountGamification,
  getGamificationBadges,
  type AccountGamificationSummary,
  type GamificationBadgeCatalogItem,
} from '@/lib/gamification';

function formatRarity(value: string | null | undefined): string {
  if (!value) return 'Common';

  return value
    .split('_')
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

function BadgeImage({
  badge,
  isUnlocked,
}: {
  badge: Pick<GamificationBadgeCatalogItem, 'icon' | 'name'>;
  isUnlocked: boolean;
}) {
  return badge.icon ? (
    <img
      src={`/assets/${badge.icon}`}
      alt={badge.name}
      className={`h-16 w-16 shrink-0 rounded-full border border-[#303030] bg-[#080808] object-contain p-1 ${
        isUnlocked ? '' : 'grayscale opacity-45'
      }`}
    />
  ) : (
    <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border border-[#303030] bg-[#080808] text-[#FFB800]">
      <Trophy size={24} />
    </div>
  );
}

function BadgeCard({
  badge,
  isUnlocked,
}: {
  badge: GamificationBadgeCatalogItem;
  isUnlocked: boolean;
}) {
  return (
    <article className={`border border-[#303030] bg-[#0b0b0b] p-4 ${isUnlocked ? '' : 'opacity-80'}`}>
      <div className="flex gap-4">
        <BadgeImage badge={badge} isUnlocked={isUnlocked} />
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            {isUnlocked ? (
              <CheckCircle2 size={15} className="text-[#FFB800]" />
            ) : (
              <Lock size={15} className="text-[#777777]" />
            )}
            <h2 className="truncate text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {badge.name}
            </h2>
          </div>
          <p className="mt-2 text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
            {formatRarity(badge.rarity)}
          </p>
        </div>
      </div>
      <p className="mt-4 text-sm leading-6 text-[#999999]">{badge.unlock_condition}</p>
    </article>
  );
}

export default function BadgesPage() {
  const { user, isLoading: isAuthLoading } = useAuth();
  const [gamification, setGamification] = useState<AccountGamificationSummary | null>(null);
  const [catalog, setCatalog] = useState<GamificationBadgeCatalogItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [message, setMessage] = useState('');

  useEffect(() => {
    let isMounted = true;

    if (!user) {
      setIsLoading(false);
      return () => {
        isMounted = false;
      };
    }

    setIsLoading(true);
    setMessage('');

    Promise.all([getAccountGamification(), getGamificationBadges()])
      .then(([gamificationResponse, catalogResponse]) => {
        if (!isMounted) return;
        setGamification(gamificationResponse);
        setCatalog(catalogResponse);
      })
      .catch(() => {
        if (!isMounted) return;
        setMessage('Unable to load badges right now.');
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [user]);

  const unlockedKeys = useMemo(
    () => new Set((gamification?.badges ?? []).map((badge) => badge.badge_key).filter(Boolean)),
    [gamification?.badges],
  );
  const unlockedBadges = useMemo(
    () => catalog.filter((badge) => unlockedKeys.has(badge.badge_key)),
    [catalog, unlockedKeys],
  );
  const lockedBadges = useMemo(
    () => catalog.filter((badge) => !unlockedKeys.has(badge.badge_key)),
    [catalog, unlockedKeys],
  );
  const progressPercent = catalog.length > 0 ? Math.round((unlockedBadges.length / catalog.length) * 100) : 0;

  if (isAuthLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20 text-white">
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
        <title>My Badges | The Blend Battlegrounds</title>
        <meta name="description" content="View your BlendBeats badge collection." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-10 text-white lg:px-8">
        <div className="container mx-auto max-w-6xl">
          <section className="border-b border-[#242424] pb-8">
            <p className="text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
              Account Achievements
            </p>
            <div className="mt-3 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <h1 className="text-5xl uppercase leading-none text-white sm:text-7xl" style={{ fontFamily: 'var(--font-heading)' }}>
                  My Badges
                </h1>
                <p className="mt-4 max-w-2xl text-sm leading-6 text-[#aaaaaa] sm:text-base">
                  Track unlocked achievements, preview locked badges, and see the requirements for each reward.
                </p>
              </div>
              <Link
                to="/account"
                className="inline-flex h-12 items-center justify-center gap-2 border border-[#444444] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Account Dashboard
              </Link>
            </div>
            {message && <p className="mt-4 text-sm text-[#FFB800]">{message}</p>}
          </section>

          <section className="grid gap-4 py-8 md:grid-cols-3">
            <div className="border border-[#303030] bg-[#111111] p-5">
              <Trophy size={20} className="text-[#FFB800]" />
              <p className="mt-4 text-[10px] font-bold uppercase tracking-widest text-[#777777]">Progress</p>
              <p className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {isLoading ? 'Loading' : `${progressPercent}%`}
              </p>
              <div className="mt-4 h-2 bg-[#222222]">
                <div className="h-full bg-primary" style={{ width: `${progressPercent}%` }} />
              </div>
            </div>
            <div className="border border-[#303030] bg-[#111111] p-5">
              <CheckCircle2 size={20} className="text-[#FFB800]" />
              <p className="mt-4 text-[10px] font-bold uppercase tracking-widest text-[#777777]">Unlocked</p>
              <p className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {isLoading ? 'Loading' : unlockedBadges.length.toLocaleString()}
              </p>
            </div>
            <div className="border border-[#303030] bg-[#111111] p-5">
              <Lock size={20} className="text-[#777777]" />
              <p className="mt-4 text-[10px] font-bold uppercase tracking-widest text-[#777777]">Locked</p>
              <p className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {isLoading ? 'Loading' : lockedBadges.length.toLocaleString()}
              </p>
            </div>
          </section>

          <section className="grid gap-8 pb-10">
            <div>
              <div className="mb-4 flex items-center gap-3">
                <ShieldCheck size={18} className="text-[#FFB800]" />
                <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Unlocked Badges
                </h2>
              </div>

              {isLoading ? (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  {[0, 1, 2].map((item) => (
                    <div key={item} className="h-36 animate-pulse border border-[#222222] bg-[#111111]" />
                  ))}
                </div>
              ) : unlockedBadges.length > 0 ? (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  {unlockedBadges.map((badge) => (
                    <BadgeCard key={badge.badge_key} badge={badge} isUnlocked />
                  ))}
                </div>
              ) : (
                <div className="border border-[#303030] bg-[#111111] p-6 text-sm leading-6 text-[#888888]">
                  No badges unlocked yet.
                </div>
              )}
            </div>

            <div>
              <div className="mb-4 flex items-center gap-3">
                <Lock size={18} className="text-primary" />
                <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Locked Badges
                </h2>
              </div>

              {isLoading ? (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  {[0, 1, 2].map((item) => (
                    <div key={item} className="h-36 animate-pulse border border-[#222222] bg-[#111111]" />
                  ))}
                </div>
              ) : lockedBadges.length > 0 ? (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  {lockedBadges.map((badge) => (
                    <BadgeCard key={badge.badge_key} badge={badge} isUnlocked={false} />
                  ))}
                </div>
              ) : (
                <div className="border border-[#303030] bg-[#111111] p-6 text-sm leading-6 text-[#888888]">
                  All available badges are unlocked.
                </div>
              )}
            </div>
          </section>
        </div>
      </main>
    </>
  );
}
