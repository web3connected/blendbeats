import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  Clock,
  Loader2,
  Search,
  ShieldAlert,
  Trophy,
  Users,
  Video,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { getBattles, type BattleRecord } from '@/lib/battles';

type VotingSort = 'ending_soon' | 'most_votes' | 'fan_rewards' | 'newest';

const sortOptions: Array<{ label: string; value: VotingSort }> = [
  { label: 'Ending Soon', value: 'ending_soon' },
  { label: 'Most Votes', value: 'most_votes' },
  { label: 'Fan Reward Pool', value: 'fan_rewards' },
  { label: 'Newest', value: 'newest' },
];

function formatNumber(value: number): string {
  return new Intl.NumberFormat('en-US').format(value);
}

function formatTokens(value: number): string {
  return new Intl.NumberFormat('en-US').format(value);
}

function formatBattleType(value: string): string {
  return value
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function timeLeftMs(value: string | null): number {
  if (!value) return Number.MAX_SAFE_INTEGER;

  const timestamp = new Date(value).getTime();
  return Number.isFinite(timestamp) ? timestamp - Date.now() : Number.MAX_SAFE_INTEGER;
}

function formatCountdown(value: string | null): string {
  if (!value) return 'Not set';

  const remainingMs = timeLeftMs(value);
  if (remainingMs <= 0) return 'Closed';

  const totalMinutes = Math.ceil(remainingMs / 60000);
  const days = Math.floor(totalMinutes / 1440);
  const hours = Math.floor((totalMinutes % 1440) / 60);
  const minutes = totalMinutes % 60;

  if (days > 0) return `${days}d ${hours}h`;
  if (hours > 0) return `${hours}h ${minutes}m`;

  return `${minutes}m`;
}

function useClockTick(): number {
  const [tick, setTick] = useState(0);

  useEffect(() => {
    const timer = window.setInterval(() => setTick((value) => value + 1), 30000);
    return () => window.clearInterval(timer);
  }, []);

  return tick;
}

function profileInitial(name: string): string {
  return name.trim().charAt(0).toUpperCase() || 'D';
}

function hasSubmittedVideo(battle: BattleRecord, profileId: number): boolean {
  const entry = battle.entries.find((item) => item.dj_profile_id === profileId);
  return Boolean(entry?.media_url && entry.status === 'submitted');
}

function battleSearchText(battle: BattleRecord): string {
  return [
    battle.title,
    battle.battle_type,
    battle.challenger.dj_name,
    battle.challenger.handle,
    battle.opponent.dj_name,
    battle.opponent.handle,
  ].join(' ').toLowerCase();
}

function ParticipantLine({ profile }: { profile: BattleRecord['challenger'] }) {
  return (
    <div className="flex min-w-0 items-center gap-3">
      <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-[#333333] bg-[#050505] text-sm font-black uppercase text-white">
        {profile.avatar_url ? (
          <img src={profile.avatar_url} alt={profile.dj_name} className="h-full w-full rounded-full object-cover" />
        ) : (
          profileInitial(profile.dj_name)
        )}
      </div>
      <div className="min-w-0">
        <p className="truncate text-lg uppercase leading-none text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          {profile.dj_name}
        </p>
        <p className="mt-1 truncate text-xs text-[#888888]">@{profile.handle}</p>
      </div>
    </div>
  );
}

function BattleVoteCard({ battle, viewerProfileId }: { battle: BattleRecord; viewerProfileId: number | null }) {
  const hasBothVideos = hasSubmittedVideo(battle, battle.challenger.id) && hasSubmittedVideo(battle, battle.opponent.id);
  const isClosed = timeLeftMs(battle.voting_ends_at) <= 0;
  const isCompetingDj = Boolean(viewerProfileId && [battle.challenger.id, battle.opponent.id].includes(viewerProfileId));
  const hasVoted = Boolean(battle.viewer_vote);
  const showVoteLink = hasVoted || (hasBothVideos && !isClosed && !isCompetingDj);

  return (
    <article className="grid border border-[#2a2a2a] bg-[#111111] transition-colors hover:border-primary/70">
      <div className="grid gap-4 border-b border-[#242424] p-4">
        <div className="flex flex-wrap items-center gap-2">
          <span className="inline-flex h-7 items-center border border-primary/50 px-2 text-[10px] font-bold uppercase tracking-widest text-primary">
            Voting Open
          </span>
          <span className="inline-flex h-7 items-center border border-[#333333] px-2 text-[10px] font-bold uppercase tracking-widest text-[#aaaaaa]">
            {formatBattleType(battle.battle_type)}
          </span>
          {hasVoted && (
            <span className="inline-flex h-7 items-center gap-1 border border-emerald-500/50 px-2 text-[10px] font-bold uppercase tracking-widest text-emerald-300">
              <CheckCircle2 size={12} />
              Voted
            </span>
          )}
        </div>

        <div className="min-w-0">
          <h2 className="truncate text-3xl uppercase leading-none text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {battle.title}
          </h2>
          <p className="mt-2 text-sm text-[#aaaaaa]">
            {battle.challenger.dj_name} vs {battle.opponent.dj_name}
          </p>
        </div>
      </div>

      <div className="grid gap-4 p-4">
        <div className="grid gap-3">
          <ParticipantLine profile={battle.challenger} />
          <div className="h-px bg-[#242424]" />
          <ParticipantLine profile={battle.opponent} />
        </div>

        <div className="grid grid-cols-2 gap-px overflow-hidden border border-[#242424] bg-[#242424]">
          <div className="bg-[#080808] p-3">
            <p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
              <Clock size={13} className="text-primary" />
              Voting Ends
            </p>
            <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatCountdown(battle.voting_ends_at)}
            </p>
          </div>
          <div className="bg-[#080808] p-3">
            <p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
              <Users size={13} className="text-primary" />
              Votes
            </p>
            <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatNumber(battle.vote_count)}
            </p>
          </div>
          <div className="bg-[#080808] p-3">
            <p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
              <Trophy size={13} className="text-primary" />
              Fan Rewards
            </p>
            <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatTokens(battle.fan_reward_pool_amount)}
            </p>
          </div>
          <div className="bg-[#080808] p-3">
            <p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
              <Video size={13} className="text-primary" />
              Videos
            </p>
            <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {hasBothVideos ? 'Ready' : 'Pending'}
            </p>
          </div>
        </div>

        {isCompetingDj && (
          <div className="flex items-start gap-2 border border-primary/40 bg-primary/10 p-3 text-sm leading-6 text-[#eeeeee]">
            <ShieldAlert size={16} className="mt-1 shrink-0 text-primary" />
            Competing DJs cannot vote in their own battle.
          </div>
        )}

        <div className="grid gap-2">
          {showVoteLink ? (
            <Link
              to={`/battles/${battle.uuid}/vote`}
              className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              {hasVoted ? 'Review Vote' : 'Vote Now'}
              <ArrowRight size={15} />
            </Link>
          ) : (
            <span
              className="inline-flex h-11 items-center justify-center border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#777777]"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              {isClosed ? 'Voting Closed' : isCompetingDj ? 'View Only' : 'Waiting On Videos'}
            </span>
          )}
          <Link
            to={`/battles/${battle.uuid}`}
            className="inline-flex h-10 items-center justify-center border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            View Battle
          </Link>
        </div>
      </div>
    </article>
  );
}

export default function BattleVotingListPage() {
  const { user } = useAuth();
  const [battles, setBattles] = useState<BattleRecord[]>([]);
  const [search, setSearch] = useState('');
  const [battleType, setBattleType] = useState('');
  const [sort, setSort] = useState<VotingSort>('ending_soon');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const tick = useClockTick();

  useEffect(() => {
    let mounted = true;

    setIsLoading(true);
    setError('');

    getBattles({ status: 'voting', limit: 100 })
      .then((records) => {
        if (mounted) setBattles(records);
      })
      .catch(() => {
        if (mounted) setError('Unable to load voting battles right now.');
      })
      .finally(() => {
        if (mounted) setIsLoading(false);
      });

    return () => {
      mounted = false;
    };
  }, []);

  const battleTypes = useMemo(() => (
    Array.from(new Set(battles.map((battle) => battle.battle_type))).sort((a, b) => a.localeCompare(b))
  ), [battles]);

  const visibleBattles = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();

    return battles
      .filter((battle) => (
        (!battleType || battle.battle_type === battleType)
        && (!normalizedSearch || battleSearchText(battle).includes(normalizedSearch))
      ))
      .sort((a, b) => {
        if (sort === 'most_votes') return b.vote_count - a.vote_count;
        if (sort === 'fan_rewards') return b.fan_reward_pool_amount - a.fan_reward_pool_amount;
        if (sort === 'newest') {
          return new Date(b.created_at ?? 0).getTime() - new Date(a.created_at ?? 0).getTime();
        }

        return timeLeftMs(a.voting_ends_at) - timeLeftMs(b.voting_ends_at);
      });
  }, [battleType, battles, search, sort, tick]);

  const totalRewards = battles.reduce((total, battle) => total + battle.fan_reward_pool_amount, 0);
  const totalVotes = battles.reduce((total, battle) => total + battle.vote_count, 0);
  const viewerProfileId = user?.dj_profile?.id ?? null;

  const clearFilters = () => {
    setSearch('');
    setBattleType('');
    setSort('ending_soon');
  };

  return (
    <>
      <Helmet>
        <title>Voting Battles | The Blend Battlegrounds</title>
        <meta name="description" content="Find open BlendBeats DJ battles and cast a fan vote." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-8 lg:px-8">
        <div className="mx-auto grid max-w-[1800px] gap-6">
          <Link
            to="/battles"
            className="inline-flex h-10 w-fit items-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <ArrowLeft size={15} />
            DJ Search
          </Link>

          <section className="grid gap-5 border-b border-[#1a1a1a] pb-6 lg:grid-cols-[1fr_auto] lg:items-end">
            <div>
              <p className="mb-2 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                Fan Voting
              </p>
              <h1 className="text-4xl uppercase leading-none text-white md:text-5xl" style={{ fontFamily: 'var(--font-heading)' }}>
                Voting Battles
              </h1>
            </div>

            <div className="grid grid-cols-3 border border-[#2a2a2a] bg-[#111111]">
              <div className="border-r border-[#242424] p-3">
                <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Open</p>
                <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {formatNumber(battles.length)}
                </p>
              </div>
              <div className="border-r border-[#242424] p-3">
                <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Votes</p>
                <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {formatNumber(totalVotes)}
                </p>
              </div>
              <div className="p-3">
                <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Rewards</p>
                <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {formatTokens(totalRewards)}
                </p>
              </div>
            </div>
          </section>

          <section className="grid gap-3 border border-[#2a2a2a] bg-[#111111] p-3 lg:grid-cols-[minmax(220px,1fr)_minmax(150px,200px)_minmax(150px,200px)_auto]">
            <label className="flex h-11 items-center gap-2 border border-[#333333] bg-[#080808] px-3">
              <Search size={16} className="shrink-0 text-primary" />
              <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search voting battles"
                className="min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-[#666666]"
              />
            </label>

            <select
              value={battleType}
              onChange={(event) => setBattleType(event.target.value)}
              className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
              aria-label="Battle type"
            >
              <option value="">Battle Type</option>
              {battleTypes.map((type) => <option key={type} value={type}>{formatBattleType(type)}</option>)}
            </select>

            <select
              value={sort}
              onChange={(event) => setSort(event.target.value as VotingSort)}
              className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
              aria-label="Sort voting battles"
            >
              {sortOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
            </select>

            <button
              type="button"
              onClick={clearFilters}
              className="inline-flex h-11 items-center justify-center border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Clear
            </button>
          </section>

          {isLoading && (
            <div className="flex min-h-80 items-center justify-center border border-[#2a2a2a] bg-[#111111] text-[#dddddd]">
              <Loader2 size={28} className="animate-spin text-primary" />
            </div>
          )}

          {!isLoading && error && (
            <div className="border border-primary/40 bg-primary/10 p-5 text-sm text-primary">{error}</div>
          )}

          {!isLoading && !error && visibleBattles.length > 0 && (
            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
              {visibleBattles.map((battle) => (
                <BattleVoteCard key={battle.uuid} battle={battle} viewerProfileId={viewerProfileId} />
              ))}
            </section>
          )}

          {!isLoading && !error && visibleBattles.length === 0 && (
            <section className="grid min-h-72 place-items-center border border-[#2a2a2a] bg-[#111111] p-8 text-center">
              <div>
                <Trophy size={36} className="mx-auto text-primary" />
                <h2 className="mt-4 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  No Voting Battles Found
                </h2>
                <p className="mt-2 text-sm text-[#aaaaaa]">
                  No battles match the current voting filters.
                </p>
                <button
                  type="button"
                  onClick={clearFilters}
                  className="mt-5 inline-flex h-11 items-center justify-center border border-[#444444] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Clear Filters
                </button>
              </div>
            </section>
          )}
        </div>
      </main>
    </>
  );
}
