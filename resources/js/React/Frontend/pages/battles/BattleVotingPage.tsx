import { Helmet } from '@dr.pogodin/react-helmet';
import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  Clock,
  Loader2,
  Play,
  Send,
  ShieldCheck,
  Trophy,
  Video,
  X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  getBattle,
  submitBattleVote,
  type BattleEntry,
  type BattleProfile,
  type BattleRecord,
  type BattleVoteScores,
} from '@/lib/battles';

type VotingStep = 'select' | 'watch' | 'score' | 'review' | 'submitted';

type BattleParticipant = {
  profile: BattleProfile;
  entry: BattleEntry | null;
  label: 'DJ A' | 'DJ B';
};

const SCORE_CATEGORIES: Array<{ key: keyof BattleVoteScores; label: string }> = [
  { key: 'sample_integration', label: 'Sample Integration' },
  { key: 'scratching_ability', label: 'Scratching Ability' },
  { key: 'mixing_ability', label: 'Mixing Ability' },
  { key: 'blending', label: 'Blending' },
  { key: 'creativity', label: 'Creativity' },
  { key: 'technical_execution', label: 'Technical Execution' },
  { key: 'music_selection', label: 'Music Selection' },
  { key: 'battle_composition', label: 'Battle Composition' },
  { key: 'entertainment_value', label: 'Entertainment Value' },
  { key: 'overall_performance', label: 'Overall Performance' },
];

function defaultScores(): BattleVoteScores {
  return SCORE_CATEGORIES.reduce((scores, category) => ({
    ...scores,
    [category.key]: 5,
  }), {} as BattleVoteScores);
}

function scoreTotal(scores: BattleVoteScores): number {
  return SCORE_CATEGORIES.reduce((total, category) => total + scores[category.key], 0);
}

function formatTokens(value: number): string {
  return new Intl.NumberFormat('en-US').format(value);
}

function formatTime(seconds: number): string {
  const safeSeconds = Number.isFinite(seconds) ? Math.max(0, Math.floor(seconds)) : 0;
  const minutes = Math.floor(safeSeconds / 60);
  const remainder = safeSeconds % 60;
  return `${minutes}:${remainder.toString().padStart(2, '0')}`;
}

function formatCountdown(target: string | null): string {
  if (!target) return 'Not set';

  const remainingMs = new Date(target).getTime() - Date.now();
  if (remainingMs <= 0) return 'Closed';

  const totalMinutes = Math.ceil(remainingMs / 60000);
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;

  return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
}

function actionErrorMessage(error: unknown): string {
  if (error instanceof Error && 'response' in error) {
    const response = error.response as { data?: { message?: string; errors?: Record<string, string[]> } };
    const [first] = Object.values(response.data?.errors || {}).flat();
    return first || response.data?.message || 'Unable to submit your vote.';
  }

  return error instanceof Error ? error.message : 'Unable to submit your vote.';
}

function BattleVotingHeader({ battle }: { battle: BattleRecord }) {
  const [tick, setTick] = useState(0);

  useEffect(() => {
    const timer = window.setInterval(() => setTick((value) => value + 1), 30000);
    return () => window.clearInterval(timer);
  }, []);

  return (
    <section className="border border-[#2a2a2a] bg-[#111111] p-5">
      <div className="flex flex-wrap items-start justify-between gap-5">
        <div className="min-w-0">
          <div className="mb-3 flex flex-wrap gap-2">
            <span className="inline-flex h-7 items-center border border-primary/50 px-2 text-[10px] font-bold uppercase tracking-widest text-primary">
              Fan Voting
            </span>
            <span className="inline-flex h-7 items-center border border-[#333333] px-2 text-[10px] font-bold uppercase tracking-widest text-[#999999]">
              {tick >= 0 ? formatCountdown(battle.voting_ends_at) : ''}
            </span>
          </div>
          <h1 className="text-4xl uppercase leading-none text-white md:text-6xl" style={{ fontFamily: 'var(--font-heading)' }}>
            {battle.title}
          </h1>
          <p className="mt-3 text-sm text-[#aaaaaa]">
            {battle.challenger.dj_name} vs {battle.opponent.dj_name}
          </p>
        </div>

        <div className="grid min-w-72 grid-cols-3 border border-[#242424] bg-[#050505]">
          <div className="border-r border-[#242424] p-3">
            <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Voting Time</p>
            <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatCountdown(battle.voting_ends_at)}
            </p>
          </div>
          <div className="border-r border-[#242424] p-3">
            <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Fan Rewards</p>
            <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatTokens(battle.fan_reward_pool_amount)}
            </p>
          </div>
          <div className="p-3">
            <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Votes</p>
            <p className="mt-1 text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {battle.vote_count}
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}

function BattleVideoSelector({
  participants,
  onChoose,
}: {
  participants: BattleParticipant[];
  onChoose: (participant: BattleParticipant) => void;
}) {
  return (
    <section className="grid gap-4 md:grid-cols-2">
      {participants.map((participant) => (
        <article key={participant.profile.id} className="grid gap-4 border border-[#2a2a2a] bg-[#111111] p-5">
          <div className="flex items-center gap-4">
            <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border border-[#333333] bg-[#050505] text-2xl font-black uppercase text-white">
              {participant.profile.avatar_url ? (
                <img src={participant.profile.avatar_url} alt={participant.profile.dj_name} className="h-full w-full rounded-full object-cover" />
              ) : (
                participant.profile.dj_name.charAt(0)
              )}
            </div>
            <div className="min-w-0">
              <p className="text-[10px] font-bold uppercase tracking-widest text-primary">{participant.label}</p>
              <h2 className="truncate text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {participant.profile.dj_name}
              </h2>
              <p className="truncate text-sm text-[#888888]">@{participant.profile.handle}</p>
            </div>
          </div>

          <div className="flex aspect-video items-center justify-center border border-[#242424] bg-black text-[#777777]">
            <Video size={42} />
          </div>

          <button
            type="button"
            onClick={() => onChoose(participant)}
            disabled={!participant.entry?.media_url}
            className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <Play size={16} />
            Watch Performance
          </button>
        </article>
      ))}
    </section>
  );
}

function BattleVideoPlayer({
  participant,
  watched,
  nextLabel,
  onWatched,
  onContinue,
}: {
  participant: BattleParticipant;
  watched: boolean;
  nextLabel: string;
  onWatched: () => void;
  onContinue: () => void;
}) {
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);

  return (
    <section className="grid gap-4 border border-[#2a2a2a] bg-[#111111] p-5">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Watch Performance</p>
          <h2 className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {participant.profile.dj_name}
          </h2>
        </div>
        <span className="inline-flex h-8 items-center gap-2 border border-[#333333] px-3 text-[10px] font-bold uppercase tracking-widest text-[#dddddd]">
          <Clock size={14} className="text-primary" />
          {formatTime(currentTime)} / {formatTime(duration)}
        </span>
      </div>

      {participant.entry?.media_url ? (
        <video
          src={participant.entry.media_url}
          controls
          className="aspect-video w-full bg-black"
          onLoadedMetadata={(event) => setDuration(event.currentTarget.duration || 0)}
          onTimeUpdate={(event) => setCurrentTime(event.currentTarget.currentTime || 0)}
          onEnded={onWatched}
        />
      ) : (
        <div className="flex aspect-video items-center justify-center border border-[#242424] bg-black text-sm text-primary">
          This DJ does not have a submitted battle video.
        </div>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3 border border-[#242424] bg-[#080808] p-4">
        <p className="inline-flex items-center gap-2 text-sm text-[#dddddd]">
          {watched ? <CheckCircle2 size={16} className="text-emerald-300" /> : <ShieldCheck size={16} className="text-primary" />}
          {watched ? 'Viewing requirement complete.' : 'Watch the full performance to continue.'}
        </p>
        <button
          type="button"
          onClick={onContinue}
          disabled={!watched}
          className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          {nextLabel}
        </button>
      </div>
    </section>
  );
}

function ScoreCategorySlider({
  label,
  value,
  onChange,
}: {
  label: string;
  value: number;
  onChange: (value: number) => void;
}) {
  return (
    <label className="grid gap-2 border border-[#242424] bg-[#080808] p-3">
      <div className="flex items-center justify-between gap-3">
        <span className="text-xs font-bold uppercase tracking-widest text-[#aaaaaa]">{label}</span>
        <span className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>{value}</span>
      </div>
      <input
        type="range"
        min="1"
        max="10"
        step="1"
        value={value}
        onChange={(event) => onChange(Number(event.target.value))}
        className="accent-primary"
      />
    </label>
  );
}

function BattleScorecard({
  participant,
  scores,
  actionLabel,
  onScoreChange,
  onContinue,
}: {
  participant: BattleParticipant;
  scores: BattleVoteScores;
  actionLabel: string;
  onScoreChange: (category: keyof BattleVoteScores, value: number) => void;
  onContinue: () => void;
}) {
  return (
    <section className="grid gap-4 border border-[#2a2a2a] bg-[#111111] p-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Score This DJ</p>
          <h2 className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {participant.profile.dj_name}
          </h2>
        </div>
        <div className="border border-primary/50 px-4 py-2 text-right">
          <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Total</p>
          <p className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>{scoreTotal(scores)} / 100</p>
        </div>
      </div>

      <div className="grid gap-3 md:grid-cols-2">
        {SCORE_CATEGORIES.map((category) => (
          <ScoreCategorySlider
            key={category.key}
            label={category.label}
            value={scores[category.key]}
            onChange={(value) => onScoreChange(category.key, value)}
          />
        ))}
      </div>

      <button
        type="button"
        onClick={onContinue}
        className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
        style={{ fontFamily: 'var(--font-heading)' }}
      >
        {actionLabel}
      </button>
    </section>
  );
}

function BattleVoteReview({
  participants,
  scoresByProfile,
  onBack,
  onSubmit,
}: {
  participants: BattleParticipant[];
  scoresByProfile: Record<number, BattleVoteScores>;
  onBack: () => void;
  onSubmit: () => void;
}) {
  const totals = participants.map((participant) => ({
    participant,
    total: scoreTotal(scoresByProfile[participant.profile.id] ?? defaultScores()),
  }));
  const sortedTotals = [...totals].sort((a, b) => b.total - a.total);
  const winner = sortedTotals[0];
  const runnerUp = sortedTotals[1];
  const difference = Math.abs((winner?.total ?? 0) - (runnerUp?.total ?? 0));

  return (
    <section className="grid gap-5 border border-[#2a2a2a] bg-[#111111] p-5">
      <div>
        <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Review My Vote</p>
        <h2 className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          Final Scorecards
        </h2>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        {participants.map((participant) => {
          const scores = scoresByProfile[participant.profile.id] ?? defaultScores();

          return (
            <article key={participant.profile.id} className="border border-[#242424] bg-[#080808] p-4">
              <p className="text-[10px] font-bold uppercase tracking-widest text-primary">{participant.label}</p>
              <h3 className="mt-1 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {participant.profile.dj_name}
              </h3>
              <div className="mt-4 grid gap-2">
                {SCORE_CATEGORIES.map((category) => (
                  <div key={category.key} className="flex items-center justify-between gap-3 border-b border-[#1f1f1f] pb-2 text-sm text-[#dddddd]">
                    <span>{category.label}</span>
                    <span className="font-bold text-white">{scores[category.key]}</span>
                  </div>
                ))}
              </div>
              <p className="mt-4 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {scoreTotal(scores)} / 100
              </p>
            </article>
          );
        })}
      </div>

      <div className="border border-primary/50 bg-primary/10 p-4">
        <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Winner Based On Your Scores</p>
        <p className="mt-2 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          {winner?.participant.profile.dj_name} - {winner?.total}
        </p>
        <p className="mt-1 text-sm text-[#dddddd]">
          {runnerUp?.participant.profile.dj_name} - {runnerUp?.total}. Difference: {difference} point{difference === 1 ? '' : 's'}.
        </p>
      </div>

      <p className="text-sm text-primary">Votes become permanent once submitted and cannot be changed.</p>

      <div className="flex flex-wrap gap-3">
        <button
          type="button"
          onClick={onBack}
          className="inline-flex h-11 items-center justify-center gap-2 border border-[#444444] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          Back
        </button>
        <button
          type="button"
          onClick={onSubmit}
          className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          <Send size={16} />
          Submit Vote
        </button>
      </div>
    </section>
  );
}

function ConfirmVoteModal({
  isOpen,
  isSubmitting,
  onClose,
  onConfirm,
}: {
  isOpen: boolean;
  isSubmitting: boolean;
  onClose: () => void;
  onConfirm: () => void;
}) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-4">
      <div className="w-full max-w-lg border border-[#333333] bg-[#101010] p-5 shadow-2xl">
        <div className="mb-5 flex items-start justify-between gap-4">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Confirm Vote</p>
            <h2 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              Lock Scorecards
            </h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            disabled={isSubmitting}
            className="inline-flex h-10 w-10 items-center justify-center border border-[#444444] text-[#dddddd] transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-50"
          >
            <X size={18} />
          </button>
        </div>
        <p className="text-sm leading-6 text-[#dddddd]">
          Your vote becomes permanent once submitted and cannot be changed.
        </p>
        <button
          type="button"
          onClick={onConfirm}
          disabled={isSubmitting}
          className="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          {isSubmitting ? <Loader2 size={16} className="animate-spin" /> : <Send size={16} />}
          Submit Vote
        </button>
      </div>
    </div>
  );
}

function VoteSubmittedSuccess({ battle }: { battle: BattleRecord }) {
  return (
    <section className="border border-emerald-500/40 bg-emerald-500/10 p-6">
      <div className="flex items-start gap-4">
        <CheckCircle2 size={32} className="mt-1 shrink-0 text-emerald-300" />
        <div>
          <p className="text-[10px] font-bold uppercase tracking-widest text-emerald-300">Thank you for voting</p>
          <h2 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            Your vote has been recorded.
          </h2>
          <p className="mt-3 text-sm leading-6 text-[#dddddd]">
            You are now eligible to receive your share of the Fan Reward Pool when this battle concludes.
          </p>
          <Link
            to={`/battles/${battle.uuid}`}
            className="mt-5 inline-flex h-11 items-center justify-center gap-2 border border-[#444444] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <ArrowLeft size={16} />
            Battle
          </Link>
        </div>
      </div>
    </section>
  );
}

export default function BattleVotingPage() {
  const { uuid } = useParams();
  const { user } = useAuth();
  const [battle, setBattle] = useState<BattleRecord | null>(null);
  const [step, setStep] = useState<VotingStep>('select');
  const [selectedProfileId, setSelectedProfileId] = useState<number | null>(null);
  const [watchOrder, setWatchOrder] = useState<number[]>([]);
  const [watchedProfiles, setWatchedProfiles] = useState<Record<number, boolean>>({});
  const [scoresByProfile, setScoresByProfile] = useState<Record<number, BattleVoteScores>>({});
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');

  useEffect(() => {
    if (!uuid) return;

    let cancelled = false;
    setIsLoading(true);
    setError('');

    getBattle(uuid)
      .then((record) => {
        if (cancelled) return;
        setBattle(record);

        if (record.viewer_vote) {
          setStep('submitted');
        }
      })
      .catch(() => {
        if (!cancelled) setError('Unable to load this voting page.');
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [uuid]);

  const participants = useMemo<BattleParticipant[]>(() => {
    if (!battle) return [];

    return [
      {
        profile: battle.challenger,
        entry: battle.entries.find((entry) => entry.dj_profile_id === battle.challenger.id) ?? null,
        label: 'DJ A',
      },
      {
        profile: battle.opponent,
        entry: battle.entries.find((entry) => entry.dj_profile_id === battle.opponent.id) ?? null,
        label: 'DJ B',
      },
    ];
  }, [battle]);

  const selectedParticipant = participants.find((participant) => participant.profile.id === selectedProfileId) ?? null;
  const viewerProfileId = user?.dj_profile?.id ?? null;
  const isCompetingDj = Boolean(battle && viewerProfileId && [battle.challenger.id, battle.opponent.id].includes(viewerProfileId));

  const chooseParticipant = (participant: BattleParticipant) => {
    setSelectedProfileId(participant.profile.id);
    setWatchOrder((current) => current.includes(participant.profile.id) ? current : [...current, participant.profile.id]);
    setStep('watch');
  };

  const markWatched = () => {
    if (!selectedProfileId) return;
    setWatchedProfiles((current) => ({ ...current, [selectedProfileId]: true }));
  };

  const continueToScore = () => {
    if (!selectedProfileId) return;
    setScoresByProfile((current) => ({
      ...current,
      [selectedProfileId]: current[selectedProfileId] ?? defaultScores(),
    }));
    setStep('score');
  };

  const updateScore = (category: keyof BattleVoteScores, value: number) => {
    if (!selectedProfileId) return;

    setScoresByProfile((current) => ({
      ...current,
      [selectedProfileId]: {
        ...(current[selectedProfileId] ?? defaultScores()),
        [category]: value,
      },
    }));
  };

  const continueAfterScore = () => {
    if (!selectedProfileId) return;

    const nextParticipant = participants.find((participant) => !watchOrder.includes(participant.profile.id));

    if (nextParticipant) {
      chooseParticipant(nextParticipant);
      return;
    }

    setStep('review');
  };

  const submitVote = async () => {
    if (!battle) return;

    setIsSubmitting(true);
    setActionError('');

    try {
      const updatedBattle = await submitBattleVote(battle.uuid, {
        watch_order: watchOrder,
        scores: participants.map((participant) => ({
          dj_profile_id: participant.profile.id,
          scores: scoresByProfile[participant.profile.id],
        })),
      });

      setBattle(updatedBattle);
      setConfirmOpen(false);
      setStep('submitted');
    } catch (requestError) {
      setActionError(actionErrorMessage(requestError));
      setConfirmOpen(false);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>{battle ? `${battle.title} Voting` : 'Battle Voting'} | The Blend Battlegrounds</title>
        <meta name="description" content="Vote on a BlendBeats DJ battle." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-10 lg:px-8">
        <div className="container mx-auto max-w-6xl">
          <Link
            to={battle ? `/battles/${battle.uuid}` : '/battles'}
            className="mb-6 inline-flex h-10 items-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <ArrowLeft size={15} />
            Battle
          </Link>

          {isLoading && (
            <div className="flex min-h-96 items-center justify-center border border-[#2a2a2a] bg-[#111111] text-[#dddddd]">
              <Loader2 size={26} className="animate-spin text-primary" />
            </div>
          )}

          {!isLoading && error && (
            <div className="border border-primary/40 bg-primary/10 p-5 text-sm text-primary">{error}</div>
          )}

          {!isLoading && battle && (
            <div className="grid gap-5">
              <BattleVotingHeader battle={battle} />

              {!user && (
                <section className="flex items-start gap-3 border border-primary/40 bg-primary/10 p-5 text-sm leading-6 text-[#eeeeee]">
                  <AlertTriangle size={18} className="mt-1 shrink-0 text-primary" />
                  <span>Log in before voting in this battle.</span>
                </section>
              )}

              {battle.status !== 'voting' && (
                <section className="flex items-start gap-3 border border-primary/40 bg-primary/10 p-5 text-sm leading-6 text-[#eeeeee]">
                  <AlertTriangle size={18} className="mt-1 shrink-0 text-primary" />
                  <span>This battle is not open for fan voting.</span>
                </section>
              )}

              {isCompetingDj && (
                <section className="flex items-start gap-3 border border-primary/40 bg-primary/10 p-5 text-sm leading-6 text-[#eeeeee]">
                  <AlertTriangle size={18} className="mt-1 shrink-0 text-primary" />
                  <span>Competing DJs cannot vote in their own battle.</span>
                </section>
              )}

              {actionError && <p className="text-sm text-primary">{actionError}</p>}

              {step === 'submitted' && <VoteSubmittedSuccess battle={battle} />}

              {user && battle.status === 'voting' && !isCompetingDj && !battle.viewer_vote && step === 'select' && (
                <BattleVideoSelector participants={participants} onChoose={chooseParticipant} />
              )}

              {user && battle.status === 'voting' && !isCompetingDj && !battle.viewer_vote && step === 'watch' && selectedParticipant && (
                <BattleVideoPlayer
                  participant={selectedParticipant}
                  watched={Boolean(watchedProfiles[selectedParticipant.profile.id])}
                  nextLabel="Continue to Score"
                  onWatched={markWatched}
                  onContinue={continueToScore}
                />
              )}

              {user && battle.status === 'voting' && !isCompetingDj && !battle.viewer_vote && step === 'score' && selectedParticipant && (
                <BattleScorecard
                  participant={selectedParticipant}
                  scores={scoresByProfile[selectedParticipant.profile.id] ?? defaultScores()}
                  actionLabel={watchOrder.length < 2 ? 'Watch Next DJ' : 'Review My Vote'}
                  onScoreChange={updateScore}
                  onContinue={continueAfterScore}
                />
              )}

              {user && battle.status === 'voting' && !isCompetingDj && !battle.viewer_vote && step === 'review' && (
                <BattleVoteReview
                  participants={participants}
                  scoresByProfile={scoresByProfile}
                  onBack={() => setStep('score')}
                  onSubmit={() => setConfirmOpen(true)}
                />
              )}
            </div>
          )}
        </div>
      </main>

      <ConfirmVoteModal
        isOpen={confirmOpen}
        isSubmitting={isSubmitting}
        onClose={() => setConfirmOpen(false)}
        onConfirm={submitVote}
      />
    </>
  );
}
