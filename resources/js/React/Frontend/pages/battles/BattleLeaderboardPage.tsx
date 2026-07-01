import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowLeft,
  BarChart3,
  CalendarDays,
  CheckCircle2,
  Filter,
  Loader2,
  Medal,
  SearchX,
  ShieldCheck,
  Trophy,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';

import {
  getBattleLeaderboard,
  type BattleLeaderboardCategory,
  type BattleLeaderboardCategoryOption,
  type BattleLeaderboardPeriod,
  type BattleLeaderboardResponse,
  type BattleLeaderboardRow as BattleLeaderboardRowRecord,
} from '@/lib/battles';

const periodOptions: Array<{ label: string; value: BattleLeaderboardPeriod }> = [
  { label: 'All Time', value: 'all_time' },
  { label: 'This Week', value: 'week' },
  { label: 'This Month', value: 'month' },
  { label: 'This Season', value: 'season' },
];

function formatNumber(value: number): string {
  return new Intl.NumberFormat('en-US').format(value);
}

function formatScore(value: number, maxScore: number): string {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: maxScore === 100 ? 1 : 2,
    maximumFractionDigits: maxScore === 100 ? 1 : 2,
  }).format(value);
}

function formatDate(value: string | null): string {
  if (!value) return 'No completed battle';

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(value));
}

function initial(name: string): string {
  return name.trim().charAt(0).toUpperCase() || 'D';
}

function LeaderboardCategoryDropdown({
  categories,
  value,
  onChange,
}: {
  categories: BattleLeaderboardCategoryOption[];
  value: BattleLeaderboardCategory;
  onChange: (value: BattleLeaderboardCategory) => void;
}) {
  return (
    <label className="grid gap-2">
      <span className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Viewing</span>
      <select
        value={value}
        onChange={(event) => onChange(event.target.value as BattleLeaderboardCategory)}
        className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
      >
        {categories.map((category) => (
          <option key={category.value} value={category.value}>{category.label}</option>
        ))}
      </select>
    </label>
  );
}

function LeaderboardFilters({
  period,
  verifiedOnly,
  activeOnly,
  onPeriodChange,
  onVerifiedChange,
  onActiveChange,
}: {
  period: BattleLeaderboardPeriod;
  verifiedOnly: boolean;
  activeOnly: boolean;
  onPeriodChange: (value: BattleLeaderboardPeriod) => void;
  onVerifiedChange: (value: boolean) => void;
  onActiveChange: (value: boolean) => void;
}) {
  return (
    <div className="grid gap-3 md:grid-cols-[minmax(150px,220px)_auto] md:items-end">
      <label className="grid gap-2">
        <span className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Period</span>
        <select
          value={period}
          onChange={(event) => onPeriodChange(event.target.value as BattleLeaderboardPeriod)}
          className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
        >
          {periodOptions.map((option) => (
            <option key={option.value} value={option.value}>{option.label}</option>
          ))}
        </select>
      </label>

      <div className="flex min-h-11 flex-wrap items-center gap-3 border border-[#333333] bg-[#080808] px-3">
        <Filter size={15} className="text-primary" />
        <label className="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-[#dddddd]">
          <input
            type="checkbox"
            checked={verifiedOnly}
            onChange={(event) => onVerifiedChange(event.target.checked)}
            className="h-4 w-4 accent-primary"
          />
          Verified DJs
        </label>
        <label className="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-[#dddddd]">
          <input
            type="checkbox"
            checked={activeOnly}
            onChange={(event) => onActiveChange(event.target.checked)}
            className="h-4 w-4 accent-primary"
          />
          Active DJs
        </label>
      </div>
    </div>
  );
}

function LeaderboardScoreBadge({ row }: { row: BattleLeaderboardRowRecord }) {
  return (
    <div className="min-w-28 border border-primary/50 bg-primary/10 px-3 py-2 text-right">
      <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Score</p>
      <p className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
        {formatScore(row.selected_category_score, row.selected_category_max_score)}
      </p>
      <p className="text-[10px] font-bold uppercase tracking-widest text-[#888888]">
        / {row.selected_category_max_score}
      </p>
    </div>
  );
}

function LeaderboardRow({
  row,
  qualified,
}: {
  row: BattleLeaderboardRowRecord;
  qualified: boolean;
}) {
  const winRate = row.completed_battles_count > 0
    ? Math.round((row.wins / row.completed_battles_count) * 100)
    : 0;

  return (
    <article className="grid gap-4 border border-[#2a2a2a] bg-[#111111] p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
      <div className="flex min-w-0 items-center gap-4">
        <div className={`flex h-11 w-14 shrink-0 items-center justify-center border text-lg uppercase ${
          qualified ? 'border-primary/50 text-primary' : 'border-[#444444] text-[#999999]'
        }`} style={{ fontFamily: 'var(--font-heading)' }}>
          {qualified ? `#${row.rank}` : 'New'}
        </div>

        <Link
          to={`/djs/${row.handle}`}
          className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border border-[#333333] bg-[#050505] text-lg font-black uppercase text-white"
        >
          {row.avatar_url ? (
            <img src={row.avatar_url} alt={row.dj_name} className="h-full w-full rounded-full object-cover" />
          ) : (
            initial(row.dj_name)
          )}
        </Link>

        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <Link to={`/djs/${row.handle}`} className="truncate text-2xl uppercase leading-none text-white hover:text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
              {row.dj_name}
            </Link>
            {qualified ? (
              <span className="inline-flex h-6 items-center gap-1 border border-emerald-500/40 px-2 text-[10px] font-bold uppercase tracking-widest text-emerald-300">
                <CheckCircle2 size={12} />
                Qualified
              </span>
            ) : (
              <span className="inline-flex h-6 items-center border border-[#444444] px-2 text-[10px] font-bold uppercase tracking-widest text-[#999999]">
                Needs more battles
              </span>
            )}
          </div>
          <p className="mt-1 truncate text-sm text-[#888888]">@{row.handle}</p>
          <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#aaaaaa]">
            <span>Battles: {formatNumber(row.completed_battles_count)}</span>
            <span>Scored: {formatNumber(row.scored_battles_count)}</span>
            <span>Wins: {formatNumber(row.wins)}</span>
            <span>Losses: {formatNumber(row.losses)}</span>
            <span>Win Rate: {winRate}%</span>
          </div>
          <div className="mt-2 flex flex-wrap items-center gap-3 text-[11px] font-bold uppercase tracking-widest text-[#777777]">
            <span className="inline-flex items-center gap-1">
              <Trophy size={13} className="text-[#FFB800]" />
              Avg Total {formatScore(row.average_total_score, 100)}
            </span>
            <span className="inline-flex items-center gap-1">
              <CalendarDays size={13} className="text-primary" />
              {formatDate(row.last_battle_date)}
            </span>
          </div>
        </div>
      </div>

      <LeaderboardScoreBadge row={row} />
    </article>
  );
}

function LeaderboardList({
  title,
  icon: Icon,
  rows,
  qualified,
}: {
  title: string;
  icon: typeof Trophy;
  rows: BattleLeaderboardRowRecord[];
  qualified: boolean;
}) {
  if (rows.length === 0) return null;

  return (
    <section className="grid gap-3">
      <div className="flex items-center justify-between gap-4">
        <h2 className="inline-flex items-center gap-2 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          <Icon size={18} className="text-primary" />
          {title}
        </h2>
        <span className="text-xs font-bold uppercase tracking-widest text-[#777777]">
          {formatNumber(rows.length)} DJs
        </span>
      </div>

      <div className="grid gap-3">
        {rows.map((row) => (
          <LeaderboardRow key={`${qualified ? 'official' : 'new'}-${row.dj_id}`} row={row} qualified={qualified} />
        ))}
      </div>
    </section>
  );
}

function LeaderboardEmptyState() {
  return (
    <section className="grid min-h-72 place-items-center border border-[#2a2a2a] bg-[#111111] p-8 text-center">
      <div>
        <SearchX size={36} className="mx-auto text-primary" />
        <h2 className="mt-4 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          No leaderboard data yet.
        </h2>
        <p className="mt-2 text-sm text-[#aaaaaa]">
          Leaderboards will update once battles receive completed fan scorecards.
        </p>
      </div>
    </section>
  );
}

export default function BattleLeaderboardPage() {
  const [category, setCategory] = useState<BattleLeaderboardCategory>('overall');
  const [period, setPeriod] = useState<BattleLeaderboardPeriod>('all_time');
  const [verifiedOnly, setVerifiedOnly] = useState(false);
  const [activeOnly, setActiveOnly] = useState(false);
  const [data, setData] = useState<BattleLeaderboardResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;

    setIsLoading(true);
    setError('');

    getBattleLeaderboard({
      category,
      period,
      verified: verifiedOnly,
      active: activeOnly,
      min_battles: 3,
      limit: 100,
    })
      .then((response) => {
        if (!cancelled) setData(response);
      })
      .catch(() => {
        if (!cancelled) setError('Unable to load battle leaderboards right now.');
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [activeOnly, category, period, verifiedOnly]);

  const categories = data?.categories ?? [
    { value: 'overall', label: 'Overall', max_score: 100 },
  ] as BattleLeaderboardCategoryOption[];
  const selectedCategory = useMemo(() => (
    categories.find((item) => item.value === category) ?? categories[0]
  ), [categories, category]);
  const officialRows = data?.leaderboard ?? [];
  const newRows = data?.new_competitors ?? [];
  const hasRows = officialRows.length > 0 || newRows.length > 0;

  return (
    <>
      <Helmet>
        <title>DJ Battle Leaderboards | The Blend Battlegrounds</title>
        <meta name="description" content="Rank battle DJs by overall score and individual fan scorecard categories." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-8 lg:px-8">
        <div className="mx-auto grid max-w-[1400px] gap-6">
          <Link
            to="/battles"
            className="inline-flex h-10 w-fit items-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <ArrowLeft size={15} />
            Battles
          </Link>

          <section className="grid gap-5 border-b border-[#1a1a1a] pb-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,520px)] lg:items-end">
            <div>
              <p className="mb-2 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                <BarChart3 size={15} />
                Battle Rankings
              </p>
              <h1 className="text-4xl uppercase leading-none text-white md:text-5xl" style={{ fontFamily: 'var(--font-heading)' }}>
                DJ Battle Leaderboards
              </h1>
              <p className="mt-3 max-w-2xl text-sm leading-6 text-[#aaaaaa]">
                Rank DJs by average fan scorecard results across completed battles, from overall performance to individual battle skills.
              </p>
            </div>

            <div className="grid gap-3 border border-[#2a2a2a] bg-[#111111] p-3">
              <LeaderboardCategoryDropdown categories={categories} value={category} onChange={setCategory} />
              <LeaderboardFilters
                period={period}
                verifiedOnly={verifiedOnly}
                activeOnly={activeOnly}
                onPeriodChange={setPeriod}
                onVerifiedChange={setVerifiedOnly}
                onActiveChange={setActiveOnly}
              />
            </div>
          </section>

          <section className="grid gap-3 border border-[#2a2a2a] bg-[#111111] p-4 sm:grid-cols-3">
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Viewing</p>
              <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {selectedCategory?.label ?? 'Overall'}
              </p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Minimum</p>
              <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {formatNumber(data?.minimum_battles ?? 3)} Scored Battles
              </p>
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Official DJs</p>
              <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {formatNumber(officialRows.length)}
              </p>
            </div>
          </section>

          {isLoading && (
            <div className="flex min-h-80 items-center justify-center border border-[#2a2a2a] bg-[#111111] text-[#dddddd]">
              <Loader2 size={28} className="animate-spin text-primary" />
            </div>
          )}

          {!isLoading && error && (
            <div className="border border-primary/40 bg-primary/10 p-5 text-sm text-primary">{error}</div>
          )}

          {!isLoading && !error && !hasRows && <LeaderboardEmptyState />}

          {!isLoading && !error && hasRows && (
            <div className="grid gap-8">
              <LeaderboardList title="Official Rankings" icon={Medal} rows={officialRows} qualified />
              <LeaderboardList title="New Competitors" icon={ShieldCheck} rows={newRows} qualified={false} />
            </div>
          )}
        </div>
      </main>
    </>
  );
}
