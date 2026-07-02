import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowLeft,
  ArrowRight,
  CalendarDays,
  Loader2,
  Medal,
  Search,
  Trophy,
  Users,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';

import { getBattles, type BattleProfile, type BattleRecord } from '@/lib/battles';

type WinnersSort = 'latest' | 'highest_score' | 'closest' | 'most_votes';

const sortOptions: Array<{ label: string; value: WinnersSort }> = [
  { label: 'Latest Results', value: 'latest' },
  { label: 'Highest Winner Score', value: 'highest_score' },
  { label: 'Closest Battles', value: 'closest' },
  { label: 'Most Votes', value: 'most_votes' },
];

function formatNumber(value: number): string {
  return new Intl.NumberFormat('en-US').format(value);
}

function formatScore(value: number): string {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  }).format(value);
}

function formatDate(value: string | null): string {
  if (!value) return 'Not set';

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(value));
}

function formatBattleType(value: string): string {
  return value
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function profileInitial(name: string): string {
  return name.trim().charAt(0).toUpperCase() || 'D';
}

function scoreFor(battle: BattleRecord, profileId: number): number {
  if (!battle.result) return 0;
  if (battle.challenger.id === profileId) return battle.result.challenger_score;
  if (battle.opponent.id === profileId) return battle.result.opponent_score;

  return 0;
}

function winnerFor(battle: BattleRecord): BattleProfile | null {
  if (battle.result?.is_draw) return null;
  if (battle.winner) return battle.winner;
  if (!battle.result) return null;

  return battle.result.challenger_score > battle.result.opponent_score
    ? battle.challenger
    : battle.opponent;
}

function opponentFor(battle: BattleRecord, winner: BattleProfile | null): BattleProfile | null {
  if (!winner) return null;

  return winner.id === battle.challenger.id ? battle.opponent : battle.challenger;
}

function highScore(battle: BattleRecord): number {
  if (!battle.result) return 0;

  return Math.max(battle.result.challenger_score, battle.result.opponent_score);
}

function scoreMargin(battle: BattleRecord): number {
  if (!battle.result) return 0;

  return Math.abs(battle.result.challenger_score - battle.result.opponent_score);
}

function battleSearchText(battle: BattleRecord): string {
  return [
    battle.title,
    battle.battle_type,
    battle.challenger.dj_name,
    battle.challenger.handle,
    battle.opponent.dj_name,
    battle.opponent.handle,
    battle.winner?.dj_name,
    battle.winner?.handle,
  ].filter(Boolean).join(' ').toLowerCase();
}

function Avatar({ profile }: { profile: BattleProfile }) {
  return (
    <Link
      to={`/djs/${profile.handle}`}
      className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border border-[#333333] bg-[#050505] text-lg font-black uppercase text-white"
    >
      {profile.avatar_url ? (
        <img src={profile.avatar_url} alt={profile.dj_name} className="h-full w-full rounded-full object-cover" />
      ) : (
        profileInitial(profile.dj_name)
      )}
    </Link>
  );
}

function ScoreLine({ label, profile, score }: { label: string; profile: BattleProfile; score: number }) {
  return (
    <div className="flex min-w-0 items-center justify-between gap-3 border border-[#242424] bg-[#080808] p-3">
      <div className="min-w-0">
        <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">{label}</p>
        <Link
          to={`/djs/${profile.handle}`}
          className="mt-1 block truncate text-xl uppercase leading-none text-white hover:text-primary"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          {profile.dj_name}
        </Link>
        <p className="mt-1 truncate text-xs text-[#888888]">@{profile.handle}</p>
      </div>
      <div className="shrink-0 text-right">
        <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Score</p>
        <p className="mt-1 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          {formatScore(score)}
        </p>
      </div>
    </div>
  );
}

function BattleWinnerCard({ battle }: { battle: BattleRecord }) {
  const winner = winnerFor(battle);
  const opponent = opponentFor(battle, winner);
  const isDraw = Boolean(battle.result?.is_draw);
  const winnerScore = winner ? scoreFor(battle, winner.id) : highScore(battle);
  const opponentScore = opponent ? scoreFor(battle, opponent.id) : highScore(battle);

  return (
    <article className="grid border border-[#2a2a2a] bg-[#111111] transition-colors hover:border-primary/70">
      <div className="grid gap-4 border-b border-[#242424] p-4">
        <div className="flex flex-wrap items-center gap-2">
          <span className="inline-flex h-7 items-center border border-primary/50 px-2 text-[10px] font-bold uppercase tracking-widest text-primary">
            Completed
          </span>
          <span className="inline-flex h-7 items-center border border-[#333333] px-2 text-[10px] font-bold uppercase tracking-widest text-[#aaaaaa]">
            {formatBattleType(battle.battle_type)}
          </span>
          {isDraw && (
            <span className="inline-flex h-7 items-center border border-[#FFB800]/50 px-2 text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
              Draw
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
        {winner ? (
          <div className="flex min-w-0 items-center gap-4 border border-primary/40 bg-primary/10 p-4">
            <Avatar profile={winner} />
            <div className="min-w-0 flex-1">
              <p className="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-primary">
                <Trophy size={14} />
                Winner
              </p>
              <Link
                to={`/djs/${winner.handle}`}
                className="mt-1 block truncate text-3xl uppercase leading-none text-white hover:text-primary"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                {winner.dj_name}
              </Link>
              <p className="mt-1 truncate text-xs text-[#888888]">@{winner.handle}</p>
            </div>
          </div>
        ) : (
          <div className="border border-[#FFB800]/40 bg-[#FFB800]/10 p-4">
            <p className="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
              <Medal size={14} />
              Final Result
            </p>
            <h3 className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              Draw Battle
            </h3>
          </div>
        )}

        <div className="grid gap-2">
          {winner && opponent ? (
            <>
              <ScoreLine label="Winner" profile={winner} score={winnerScore} />
              <ScoreLine label="Opponent" profile={opponent} score={opponentScore} />
            </>
          ) : (
            <>
              <ScoreLine label="DJ A" profile={battle.challenger} score={scoreFor(battle, battle.challenger.id)} />
              <ScoreLine label="DJ B" profile={battle.opponent} score={scoreFor(battle, battle.opponent.id)} />
            </>
          )}
        </div>

        <div className="grid grid-cols-3 gap-px overflow-hidden border border-[#242424] bg-[#242424]">
          <div className="bg-[#080808] p-3">
            <p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
              <Users size={13} className="text-primary" />
              Votes
            </p>
            <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatNumber(battle.result?.total_votes ?? battle.vote_count)}
            </p>
          </div>
          <div className="bg-[#080808] p-3">
            <p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
              <Medal size={13} className="text-primary" />
              Margin
            </p>
            <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatScore(scoreMargin(battle))}
            </p>
          </div>
          <div className="bg-[#080808] p-3">
            <p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
              <CalendarDays size={13} className="text-primary" />
              Completed
            </p>
            <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatDate(battle.completed_at)}
            </p>
          </div>
        </div>

        <Link
          to={`/battles/${battle.uuid}`}
          className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          View Results
          <ArrowRight size={15} />
        </Link>
      </div>
    </article>
  );
}

export default function BattleWinnersPage() {
  const [battles, setBattles] = useState<BattleRecord[]>([]);
  const [search, setSearch] = useState('');
  const [battleType, setBattleType] = useState('');
  const [sort, setSort] = useState<WinnersSort>('latest');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let mounted = true;

    setIsLoading(true);
    setError('');

    getBattles({ status: 'completed', limit: 100 })
      .then((records) => {
        if (mounted) setBattles(records);
      })
      .catch(() => {
        if (mounted) setError('Unable to load battle winners right now.');
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
        if (sort === 'highest_score') return highScore(b) - highScore(a);
        if (sort === 'closest') return scoreMargin(a) - scoreMargin(b);
        if (sort === 'most_votes') return (b.result?.total_votes ?? b.vote_count) - (a.result?.total_votes ?? a.vote_count);

        return new Date(b.completed_at ?? 0).getTime() - new Date(a.completed_at ?? 0).getTime();
      });
  }, [battleType, battles, search, sort]);

  const totalVotes = battles.reduce((total, battle) => total + (battle.result?.total_votes ?? battle.vote_count), 0);
  const drawCount = battles.filter((battle) => battle.result?.is_draw).length;
  const winnerCount = battles.length - drawCount;

  const clearFilters = () => {
    setSearch('');
    setBattleType('');
    setSort('latest');
  };

  return (
    <>
      <Helmet>
        <title>Battle Winners | The Blend Battlegrounds</title>
        <meta name="description" content="Review completed BlendBeats DJ battle winners and final fan scorecard results." />
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
              <p className="mb-2 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                <Trophy size={15} />
                Final Results
              </p>
              <h1 className="text-4xl uppercase leading-none text-white md:text-5xl" style={{ fontFamily: 'var(--font-heading)' }}>
                Battle Winners
              </h1>
            </div>

            <div className="grid grid-cols-3 border border-[#2a2a2a] bg-[#111111]">
              <div className="border-r border-[#242424] p-3">
                <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Completed</p>
                <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {formatNumber(battles.length)}
                </p>
              </div>
              <div className="border-r border-[#242424] p-3">
                <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Winners</p>
                <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {formatNumber(winnerCount)}
                </p>
              </div>
              <div className="p-3">
                <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Votes</p>
                <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {formatNumber(totalVotes)}
                </p>
              </div>
            </div>
          </section>

          <section className="grid gap-3 border border-[#2a2a2a] bg-[#111111] p-3 lg:grid-cols-[minmax(220px,1fr)_minmax(150px,200px)_minmax(170px,220px)_auto]">
            <label className="flex h-11 items-center gap-2 border border-[#333333] bg-[#080808] px-3">
              <Search size={16} className="shrink-0 text-primary" />
              <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search battle winners"
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
              onChange={(event) => setSort(event.target.value as WinnersSort)}
              className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
              aria-label="Sort battle winners"
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
            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {visibleBattles.map((battle) => (
                <BattleWinnerCard key={battle.uuid} battle={battle} />
              ))}
            </section>
          )}

          {!isLoading && !error && visibleBattles.length === 0 && (
            <section className="grid min-h-72 place-items-center border border-[#2a2a2a] bg-[#111111] p-8 text-center">
              <div>
                <Trophy size={36} className="mx-auto text-primary" />
                <h2 className="mt-4 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  No Battle Winners Found
                </h2>
                <p className="mt-2 text-sm text-[#aaaaaa]">
                  Completed battle results will appear here after fan voting closes.
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
