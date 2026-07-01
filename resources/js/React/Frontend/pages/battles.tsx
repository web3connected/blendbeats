import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowRight,
  Filter,
  Flame,
  Headphones,
  Loader2,
  Medal,
  Search,
  Send,
  ShieldCheck,
  SlidersHorizontal,
  Star,
  Trophy,
  Users,
  X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { createBattle, getAccountBattles, type BattleRecord } from '@/lib/battles';
import { getDjHubDjs, type DjHubDj, type DjHubFilters } from '@/lib/dj-hub';

type BattleHubSort =
  | 'recommended'
  | 'highest_ranked'
  | 'most_active'
  | 'most_wins'
  | 'recently_joined'
  | 'followers'
  | 'available';

const sortOptions: Array<{ label: string; value: BattleHubSort }> = [
  { label: 'Recommended', value: 'recommended' },
  { label: 'Highest Ranked', value: 'highest_ranked' },
  { label: 'Most Active', value: 'most_active' },
  { label: 'Most Wins', value: 'most_wins' },
  { label: 'Recently Joined', value: 'recently_joined' },
  { label: 'Most Followers', value: 'followers' },
  { label: 'Available to Battle', value: 'available' },
];

const battleRules = [
  'Both DJs receive the same AI-generated sample pack.',
  'All required samples must be used.',
  'Maximum recording length: 3 minutes.',
  'Recording takes place inside the BlendBeat recorder.',
  'Fan voting determines the winner.',
];

const returnableBattleStatuses = ['recording', 'voting', 'accepted', 'pending', 'paused'] as const;
const battleStatusPriority: Record<string, number> = {
  recording: 0,
  voting: 1,
  accepted: 2,
  pending: 3,
  paused: 4,
};

function firstFieldError(errors: Record<string, string[]>): string {
  const [first] = Object.values(errors).flat();
  return first || 'Unable to send challenge.';
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat('en-US').format(value);
}

function formatPercent(value: number): string {
  return `${value}%`;
}

function formatBattleStatus(value: string): string {
  return value
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function uniqueValues(values: Array<string | null | undefined>): string[] {
  return Array.from(new Set(values.filter(Boolean) as string[])).sort((a, b) => a.localeCompare(b));
}

function publishedTime(dj: DjHubDj): number {
  return dj.published_at ? new Date(dj.published_at).getTime() : 0;
}

function activityScore(dj: DjHubDj): number {
  return dj.battle_stats.battles + dj.battle_stats.active_battles;
}

function sortDjs(djs: DjHubDj[], sort: BattleHubSort): DjHubDj[] {
  return [...djs].sort((a, b) => {
    if (sort === 'highest_ranked') {
      const aRank = a.ranking.global_rank ?? Number.MAX_SAFE_INTEGER;
      const bRank = b.ranking.global_rank ?? Number.MAX_SAFE_INTEGER;
      return aRank - bRank || b.battle_stats.wins - a.battle_stats.wins || b.followers_count - a.followers_count;
    }

    if (sort === 'most_active') {
      return activityScore(b) - activityScore(a) || b.followers_count - a.followers_count;
    }

    if (sort === 'most_wins') {
      return b.battle_stats.wins - a.battle_stats.wins || b.battle_stats.win_rate - a.battle_stats.win_rate;
    }

    if (sort === 'recently_joined') {
      return publishedTime(b) - publishedTime(a);
    }

    if (sort === 'followers') {
      return b.followers_count - a.followers_count;
    }

    if (sort === 'available') {
      return Number(b.battle_enabled) - Number(a.battle_enabled) || activityScore(b) - activityScore(a);
    }

    return Number(b.battle_enabled) - Number(a.battle_enabled)
      || activityScore(b) - activityScore(a)
      || b.followers_count - a.followers_count;
  });
}

function badgeRows(dj: DjHubDj): Array<{ label: string; icon: typeof Trophy; tone: string }> {
  const rows: Array<{ label: string; icon: typeof Trophy; tone: string }> = [];

  if (dj.verification_status === 'verified') {
    rows.push({ label: 'Verified DJ', icon: ShieldCheck, tone: 'text-sky-300' });
  }

  if (dj.battle_stats.wins > 0) {
    rows.push({ label: 'Winner', icon: Trophy, tone: 'text-[#FFB800]' });
  }

  if (dj.battle_stats.active_battles > 0) {
    rows.push({ label: 'Active', icon: Flame, tone: 'text-primary' });
  }

  if (dj.gamification.dj_level >= 5) {
    rows.push({ label: 'Top Performer', icon: Star, tone: 'text-[#FFB800]' });
  }

  dj.gamification.badges.slice(0, 2).forEach((badge) => {
    if (badge.name) rows.push({ label: badge.name, icon: Medal, tone: 'text-[#dddddd]' });
  });

  return rows.slice(0, 4);
}

function rankLabel(dj: DjHubDj): string {
  if (!dj.ranking.global_rank && !dj.ranking.division && !dj.ranking.rating) {
    return 'Unranked';
  }

  return [
    dj.ranking.global_rank ? `#${dj.ranking.global_rank}` : null,
    dj.ranking.division,
    dj.ranking.rating ? `${dj.ranking.rating} rating` : null,
  ].filter(Boolean).join(' / ');
}

function ActiveBattleBanner({ battle, viewerProfileId }: { battle: BattleRecord; viewerProfileId: number | null }) {
  const opponent = viewerProfileId === battle.challenger.id ? battle.opponent : battle.challenger;
  const needsReady = battle.status === 'accepted'
    && ((viewerProfileId === battle.challenger.id && !battle.readiness.challenger_ready)
      || (viewerProfileId === battle.opponent.id && !battle.readiness.opponent_ready));
  const message = needsReady
    ? 'Your readiness is still needed.'
    : battle.status === 'recording'
      ? 'Recording window is open.'
      : battle.status === 'voting'
        ? 'Fan voting is open.'
        : 'This battle is waiting for the next step.';

  return (
    <div className="border border-primary/50 bg-primary/10 p-4">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="min-w-0">
          <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Active Battle</p>
          <h2 className="mt-1 truncate text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {battle.title}
          </h2>
          <p className="mt-1 text-sm text-[#dddddd]">
            {formatBattleStatus(battle.status)} vs {opponent.dj_name}. {message}
          </p>
        </div>

        <Link
          to={`/battles/${battle.uuid}`}
          className="inline-flex h-11 shrink-0 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          Return To Battle
          <ArrowRight size={16} />
        </Link>
      </div>
    </div>
  );
}

function DjBattleCard({ dj, onChallenge }: { dj: DjHubDj; onChallenge: (dj: DjHubDj) => void }) {
  const badges = badgeRows(dj);
  const stats = [
    ['Battles', dj.battle_stats.battles],
    ['Wins', dj.battle_stats.wins],
    ['Losses', dj.battle_stats.losses],
    ['Win Rate', formatPercent(dj.battle_stats.win_rate)],
    ['Followers', formatNumber(dj.followers_count)],
  ];

  return (
    <article className="grid border border-[#2a2a2a] bg-[#111111] transition-colors hover:border-primary/70">
      <div className="flex items-start gap-3 border-b border-[#242424] p-3">
        <div className="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-[#333333] bg-[#050505] text-lg font-black uppercase text-white">
          {dj.avatar_url ? (
            <img src={dj.avatar_url} alt={dj.dj_name} className="h-full w-full rounded-full object-cover" />
          ) : (
            dj.dj_name.charAt(0)
          )}
        </div>
        <div className="min-w-0 flex-1">
          <h2 className="truncate text-xl uppercase leading-none text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {dj.dj_name}
          </h2>
          <p className="mt-1 truncate text-xs text-[#888888]">@{dj.handle}</p>
          <p className="mt-2 truncate text-[11px] font-bold uppercase tracking-widest text-[#777777]">
            {dj.primary_genre ?? dj.gamification.dj_rank}
          </p>
        </div>
      </div>

      <div className="grid gap-3 p-3">
        <div className="flex items-center justify-between gap-2">
          <span className="inline-flex h-7 items-center border border-[#333333] px-2 text-[10px] font-bold uppercase tracking-widest text-[#dddddd]">
            {rankLabel(dj)}
          </span>
          <span className={`inline-flex h-7 items-center border px-2 text-[10px] font-bold uppercase tracking-widest ${
            dj.battle_enabled ? 'border-primary/50 text-primary' : 'border-[#444444] text-[#777777]'
          }`}>
            {dj.battle_enabled ? 'Available' : 'Unavailable'}
          </span>
        </div>

        <dl className="grid grid-cols-2 gap-px overflow-hidden border border-[#242424] bg-[#242424] text-xs">
          {stats.map(([label, value]) => (
            <div key={label} className="grid gap-1 bg-[#080808] px-2 py-2">
              <dt className="text-[10px] font-bold uppercase tracking-widest text-[#666666]">{label}</dt>
              <dd className="font-semibold text-[#eeeeee]">{value}</dd>
            </div>
          ))}
        </dl>

        <div className="flex min-h-8 flex-wrap items-center gap-2">
          {badges.length === 0 && (
            <span className="inline-flex h-7 items-center gap-1 border border-[#333333] px-2 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
              <Headphones size={12} />
              New Competitor
            </span>
          )}
          {badges.map((badge) => {
            const Icon = badge.icon;

            return (
              <span
                key={badge.label}
                className="inline-flex h-7 max-w-full items-center gap-1 border border-[#333333] px-2 text-[10px] font-bold uppercase tracking-widest text-[#bbbbbb]"
                title={badge.label}
              >
                <Icon size={12} className={badge.tone} />
                <span className="truncate">{badge.label}</span>
              </span>
            );
          })}
        </div>

        <div className="grid gap-2">
          <button
            type="button"
            onClick={() => onChallenge(dj)}
            disabled={!dj.battle_enabled}
            className="inline-flex h-10 items-center justify-center gap-2 bg-primary px-3 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <Trophy size={15} />
            Battle Ready
          </button>
          <Link
            to={`/djs/${dj.handle}`}
            className="inline-flex h-9 items-center justify-center border border-[#444444] px-3 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            View Profile
          </Link>
        </div>
      </div>
    </article>
  );
}

function CreateBattleChallengeModal({ dj, onClose }: { dj: DjHubDj; onClose: () => void }) {
  const navigate = useNavigate();
  const { user } = useAuth();
  const [durationHours, setDurationHours] = useState<24 | 48 | 72>(24);
  const [stakeAmount, setStakeAmount] = useState('100');
  const [message, setMessage] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formError, setFormError] = useState('');

  const viewerProfile = user?.dj_profile;
  const isBattleReady = Boolean(
    viewerProfile?.battle_enabled
    && viewerProfile.profile_status === 'active'
    && viewerProfile.visibility === 'public',
  );
  const isOwnProfile = Boolean(viewerProfile?.id === dj.id);
  const stake = Math.max(0, Math.floor(Number(stakeAmount || 0)));
  const canSubmit = Boolean(user && viewerProfile && dj.battle_enabled && !isOwnProfile && !isSubmitting);

  const submitChallenge = async () => {
    if (!canSubmit || !viewerProfile) return;

    setIsSubmitting(true);
    setFormError('');

    try {
      const battle = await createBattle({
        opponent_dj_profile_id: dj.id,
        battle_type: 'open_format',
        title: `${viewerProfile.dj_name ?? 'DJ'} vs ${dj.dj_name}`,
        rules: battleRules.join('\n'),
        duration_seconds: 180,
        voting_duration_hours: durationHours,
        minimum_votes: 1,
        stake_amount: stake,
        challenge_message: message.trim() || undefined,
      });

      navigate(`/battles/${battle.uuid}`);
    } catch (requestError) {
      if (requestError instanceof Error && 'errors' in requestError) {
        setFormError(firstFieldError((requestError as { errors: Record<string, string[]> }).errors));
      } else {
        setFormError('Unable to send challenge.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/80 px-4 py-6">
      <section className="grid max-h-[92vh] w-full max-w-3xl overflow-y-auto border border-[#333333] bg-[#0d0d0d] shadow-2xl">
        <div className="flex items-start justify-between gap-4 border-b border-[#242424] p-5">
          <div className="min-w-0">
            <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Create Battle Challenge</p>
            <h2 className="mt-2 truncate text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {dj.dj_name}
            </h2>
            <p className="mt-1 truncate text-sm text-[#888888]">@{dj.handle}</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="inline-flex h-10 w-10 shrink-0 items-center justify-center border border-[#444444] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            aria-label="Close challenge modal"
          >
            <X size={17} />
          </button>
        </div>

        <div className="grid gap-5 p-5">
          {!user && (
            <Link
              to="/login"
              className="inline-flex h-11 w-fit items-center justify-center bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Sign In To Challenge
            </Link>
          )}

          {user && !viewerProfile && (
            <Link
              to="/dj/start"
              className="inline-flex h-11 w-fit items-center justify-center border border-[#444444] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Create DJ Profile
            </Link>
          )}

          {user && viewerProfile && (
            <>
              {!isBattleReady && (
                <div className="border border-primary/40 bg-primary/10 p-4 text-sm leading-6 text-primary">
                  You can send this challenge now. Your DJ profile must be active, public, and battle-ready before you can press I'm Ready.
                </div>
              )}

              <div className="grid gap-4 sm:grid-cols-[1fr_220px]">
                <div className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Battle Length</span>
                  <div className="grid grid-cols-3 gap-2">
                    {([24, 48, 72] as const).map((hours) => (
                      <button
                        key={hours}
                        type="button"
                        onClick={() => setDurationHours(hours)}
                        className={`h-11 border px-3 text-xs font-bold uppercase tracking-widest transition-colors ${
                          durationHours === hours
                            ? 'border-primary bg-primary text-white'
                            : 'border-[#333333] bg-[#080808] text-[#dddddd] hover:border-primary'
                        }`}
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        {hours} Hours
                      </button>
                    ))}
                  </div>
                </div>

                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Battle Stake</span>
                  <input
                    type="number"
                    min="0"
                    value={stakeAmount}
                    onChange={(event) => setStakeAmount(event.target.value)}
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                  />
                </label>
              </div>

              <section className="border border-[#242424] bg-[#080808] p-4">
                <div className="mb-3 flex items-center gap-2">
                  <ShieldCheck size={16} className="text-primary" />
                  <h3 className="text-sm font-bold uppercase tracking-widest text-white">Battle Rules</h3>
                </div>
                <ul className="grid gap-2 text-sm leading-6 text-[#cccccc]">
                  {battleRules.map((rule) => <li key={rule}>{rule}</li>)}
                </ul>
              </section>

              <label className="grid gap-2">
                <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Optional Message</span>
                <textarea
                  value={message}
                  onChange={(event) => setMessage(event.target.value)}
                  rows={3}
                  maxLength={500}
                  className="resize-none border border-[#333333] bg-[#080808] px-3 py-2 text-sm text-white outline-none placeholder:text-[#555555] focus:border-primary"
                  placeholder="I've been waiting to battle you."
                />
              </label>

              {(formError || isOwnProfile || !dj.battle_enabled) && (
                <p className="text-sm leading-6 text-primary">
                  {formError || (isOwnProfile ? 'Choose another DJ to challenge.' : 'This DJ is not available to battle.')}
                </p>
              )}

              <button
                type="button"
                onClick={submitChallenge}
                disabled={!canSubmit}
                className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                {isSubmitting ? <Loader2 size={16} className="animate-spin" /> : <Send size={16} />}
                Send Challenge
              </button>
            </>
          )}
        </div>
      </section>
    </div>
  );
}

export default function BattlesPage() {
  const { user } = useAuth();
  const [djs, setDjs] = useState<DjHubDj[]>([]);
  const [accountBattles, setAccountBattles] = useState<BattleRecord[]>([]);
  const [filters, setFilters] = useState<DjHubFilters>({ genres: [], dj_types: [] });
  const [search, setSearch] = useState('');
  const [genre, setGenre] = useState('');
  const [country, setCountry] = useState('');
  const [skillLevel, setSkillLevel] = useState('');
  const [verifiedOnly, setVerifiedOnly] = useState(false);
  const [onlineOnly, setOnlineOnly] = useState(false);
  const [availableOnly, setAvailableOnly] = useState(false);
  const [sort, setSort] = useState<BattleHubSort>('most_active');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [challengeDj, setChallengeDj] = useState<DjHubDj | null>(null);

  useEffect(() => {
    let mounted = true;

    setIsLoading(true);
    setError('');

    getDjHubDjs({ sort: 'top' })
      .then((response) => {
        if (!mounted) return;
        setDjs(response.djs);
        setFilters(response.filters);
      })
      .catch(() => {
        if (!mounted) return;
        setError('Unable to load DJs right now.');
      })
      .finally(() => {
        if (mounted) setIsLoading(false);
      });

    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    if (!user) {
      setAccountBattles([]);
      return;
    }

    let mounted = true;

    getAccountBattles()
      .then((battles) => {
        if (mounted) setAccountBattles(battles);
      })
      .catch(() => {
        if (mounted) setAccountBattles([]);
      });

    return () => {
      mounted = false;
    };
  }, [user?.id]);

  const countries = useMemo(() => uniqueValues(djs.map((dj) => dj.country)), [djs]);
  const skillLevels = useMemo(() => uniqueValues(djs.map((dj) => dj.gamification.dj_rank)), [djs]);
  const totalActiveDjs = djs.filter((dj) => dj.battle_enabled).length;
  const returnBattle = useMemo(() => accountBattles
    .filter((battle) => returnableBattleStatuses.includes(battle.status as typeof returnableBattleStatuses[number]))
    .sort((a, b) => {
      const statusSort = (battleStatusPriority[a.status] ?? 99) - (battleStatusPriority[b.status] ?? 99);
      if (statusSort !== 0) return statusSort;

      return new Date(b.created_at ?? 0).getTime() - new Date(a.created_at ?? 0).getTime();
    })[0] ?? null, [accountBattles]);

  const visibleDjs = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();

    return sortDjs(djs.filter((dj) => {
      const matchesSearch = !normalizedSearch || [
        dj.dj_name,
        dj.handle,
        dj.headline,
        dj.primary_genre,
        dj.city,
        dj.state,
        dj.country,
      ].filter(Boolean).some((value) => String(value).toLowerCase().includes(normalizedSearch));

      return matchesSearch
        && dj.id !== user?.dj_profile?.id
        && (!genre || dj.primary_genre === genre || dj.secondary_genres.includes(genre))
        && (!country || dj.country === country)
        && (!skillLevel || dj.gamification.dj_rank === skillLevel)
        && (!verifiedOnly || dj.verification_status === 'verified')
        && (!onlineOnly)
        && (!availableOnly || dj.battle_enabled);
    }), sort);
  }, [availableOnly, country, djs, genre, onlineOnly, search, skillLevel, sort, user?.dj_profile?.id, verifiedOnly]);

  const clearFilters = () => {
    setSearch('');
    setGenre('');
    setCountry('');
    setSkillLevel('');
    setVerifiedOnly(false);
    setOnlineOnly(false);
    setAvailableOnly(false);
    setSort('most_active');
  };

  const browseAll = () => {
    setSearch('');
    setGenre('');
    setCountry('');
    setSkillLevel('');
    setVerifiedOnly(false);
    setOnlineOnly(false);
    setAvailableOnly(false);
  };

  return (
    <>
      <Helmet>
        <title>DJ Battlegrounds | The Blend Battlegrounds</title>
        <meta name="description" content="Find battle-ready DJs, compare stats, and start a BlendBeats DJ challenge." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-6 lg:px-8">
          <div className="mx-auto grid max-w-[1800px] gap-5">
            {returnBattle && (
              <ActiveBattleBanner battle={returnBattle} viewerProfileId={user?.dj_profile?.id ?? null} />
            )}

            <div className="flex flex-wrap items-end justify-between gap-4">
              <div>
                <p className="mb-2 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                  DJ Battlegrounds
                </p>
                <h1 className="text-4xl uppercase leading-none text-white md:text-5xl" style={{ fontFamily: 'var(--font-heading)' }}>
                  Search DJs
                </h1>
              </div>

              <div className="grid grid-cols-2 border border-[#2a2a2a] bg-[#111111] sm:grid-cols-3">
                <div className="border-r border-[#242424] p-3">
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Season</p>
                  <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>Preseason</p>
                </div>
                <div className="border-r border-[#242424] p-3">
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Active DJs</p>
                  <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>{formatNumber(totalActiveDjs)}</p>
                </div>
                <div className="col-span-2 p-3 sm:col-span-1">
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Showing</p>
                  <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>{formatNumber(visibleDjs.length)}</p>
                </div>
              </div>
            </div>

            <div className="grid gap-3 border border-[#2a2a2a] bg-[#111111] p-3 lg:grid-cols-[minmax(220px,1fr)_repeat(4,minmax(150px,180px))_minmax(180px,220px)]">
              <label className="flex h-11 items-center gap-2 border border-[#333333] bg-[#080808] px-3">
                <Search size={16} className="shrink-0 text-primary" />
                <input
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Search DJs"
                  className="min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-[#666666]"
                />
              </label>

              <select
                value={genre}
                onChange={(event) => setGenre(event.target.value)}
                className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                aria-label="Genre"
              >
                <option value="">Genre</option>
                {filters.genres.map((item) => <option key={item} value={item}>{item}</option>)}
              </select>

              <select
                value={country}
                onChange={(event) => setCountry(event.target.value)}
                className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                aria-label="Country"
              >
                <option value="">Country</option>
                {countries.map((item) => <option key={item} value={item}>{item}</option>)}
              </select>

              <select
                value={skillLevel}
                onChange={(event) => setSkillLevel(event.target.value)}
                className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                aria-label="Skill Level"
              >
                <option value="">Skill Level</option>
                {skillLevels.map((item) => <option key={item} value={item}>{item}</option>)}
              </select>

              <select
                value={sort}
                onChange={(event) => setSort(event.target.value as BattleHubSort)}
                className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                aria-label="Sort DJs"
              >
                {sortOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
              </select>

              <div className="flex h-11 items-center gap-3 overflow-x-auto border border-[#333333] bg-[#080808] px-3">
                <Filter size={15} className="shrink-0 text-primary" />
                <label className="inline-flex shrink-0 items-center gap-2 text-xs text-[#dddddd]">
                  <input type="checkbox" checked={verifiedOnly} onChange={(event) => setVerifiedOnly(event.target.checked)} className="h-4 w-4 accent-primary" />
                  Verified
                </label>
                <label className="inline-flex shrink-0 items-center gap-2 text-xs text-[#dddddd]">
                  <input type="checkbox" checked={false} disabled onChange={() => setOnlineOnly(false)} className="h-4 w-4 accent-primary disabled:opacity-40" />
                  Online
                </label>
                <label className="inline-flex shrink-0 items-center gap-2 text-xs text-[#dddddd]">
                  <input type="checkbox" checked={availableOnly} onChange={(event) => setAvailableOnly(event.target.checked)} className="h-4 w-4 accent-primary" />
                  Available
                </label>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-6 lg:px-8">
          <div className="mx-auto max-w-[1800px]">
            {error && <div className="border border-primary/40 bg-primary/10 p-4 text-sm text-primary">{error}</div>}

            {isLoading && (
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                {Array.from({ length: 10 }).map((_, index) => (
                  <div key={index} className="h-[330px] animate-pulse border border-[#2a2a2a] bg-[#111111]" />
                ))}
              </div>
            )}

            {!isLoading && !error && visibleDjs.length === 0 && (
              <div className="grid min-h-72 place-items-center border border-[#2a2a2a] bg-[#111111] p-8 text-center">
                <div>
                  <SlidersHorizontal size={30} className="mx-auto text-primary" />
                  <h2 className="mt-4 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    No DJs match your current search.
                  </h2>
                  <div className="mt-5 flex flex-wrap justify-center gap-3">
                    <button
                      type="button"
                      onClick={clearFilters}
                      className="inline-flex h-10 items-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      <X size={15} />
                      Clear Filters
                    </button>
                    <button
                      type="button"
                      onClick={browseAll}
                      className="inline-flex h-10 items-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      <Users size={15} />
                      Browse All DJs
                    </button>
                  </div>
                </div>
              </div>
            )}

            {!isLoading && !error && visibleDjs.length > 0 && (
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                {visibleDjs.map((dj) => (
                  <DjBattleCard key={dj.id} dj={dj} onChallenge={setChallengeDj} />
                ))}
              </div>
            )}
          </div>
        </section>

        {challengeDj && (
          <CreateBattleChallengeModal
            dj={challengeDj}
            onClose={() => setChallengeDj(null)}
          />
        )}
      </main>
    </>
  );
}
