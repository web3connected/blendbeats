import { Helmet } from '@dr.pogodin/react-helmet';
import { ArrowLeft, CheckCircle2, Clock, Loader2, PauseCircle, ShieldCheck, Trophy, Video, WalletCards, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  acceptBattle,
  bypassBattleSamplePack,
  cancelBattle,
  declineBattle,
  extendBattle,
  getAccountBattles,
  getBattle,
  readyBattle,
  readyBattleOpponentForTesting,
  type BattleRecord,
} from '@/lib/battles';
import type { AuthUser } from '@/lib/auth';
import { getWallet, type WalletResponse } from '@/lib/wallet';

function formatBattleType(value: string): string {
  return value
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function formatDate(value: string | null): string {
  if (!value) return 'Not set';

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(value));
}

function formatCountdown(value: string | null): string {
  if (!value) return 'Not set';

  const remainingMs = new Date(value).getTime() - Date.now();

  if (remainingMs <= 0) return 'Expired';

  const totalMinutes = Math.ceil(remainingMs / 60000);
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;

  if (hours <= 0) return `${minutes}m`;

  return `${hours}h ${minutes}m`;
}

function useClockTick(): number {
  const [tick, setTick] = useState(0);

  useEffect(() => {
    const timer = window.setInterval(() => setTick((value) => value + 1), 30000);
    return () => window.clearInterval(timer);
  }, []);

  return tick;
}

function shouldRefreshBattle(status: string | null | undefined): boolean {
  return ['pending', 'paused', 'accepted', 'recording', 'voting'].includes(status ?? '');
}

function formatTokens(value: number): string {
  return new Intl.NumberFormat('en-US').format(value);
}

function actionErrorMessage(error: unknown): string {
  if (error instanceof Error && 'response' in error) {
    const response = error.response as { data?: { message?: string; errors?: Record<string, string[]> } };
    const [first] = Object.values(response.data?.errors || {}).flat();
    return first || response.data?.message || 'Unable to update this battle.';
  }

  return 'Unable to update this battle.';
}

function formatEntryStatus(status: string): string {
  return status
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function defaultEntryTitle(entryStatus: string, battleStatus: string): string {
  if (entryStatus === 'submitted') return 'Entry Submitted';
  if (battleStatus === 'recording') return 'Recording Pending';

  return 'Entry Pending';
}

function participantStatus(battle: BattleRecord, profileId: number, role: 'challenger' | 'opponent') {
  const entry = battle.entries.find((item) => item.dj_profile_id === profileId);

  if (battle.status === 'pending') {
    return role === 'challenger'
      ? { label: 'Challenge Sent', tone: 'primary' }
      : { label: 'Needs Acceptance', tone: 'neutral' };
  }

  if (battle.status === 'accepted') {
    const ready = profileId === battle.challenger.id
      ? battle.readiness.challenger_ready
      : battle.readiness.opponent_ready;

    return ready
      ? { label: 'Ready', tone: 'ready' }
      : { label: 'Awaiting Ready', tone: 'neutral' };
  }

  if (battle.status === 'recording') {
    return entry?.status === 'submitted'
      ? { label: 'Submitted', tone: 'ready' }
      : { label: 'Recording', tone: 'primary' };
  }

  if (battle.status === 'voting') {
    return { label: 'Voting Open', tone: 'primary' };
  }

  if (battle.status === 'completed') {
    return battle.winner?.id === profileId
      ? { label: 'Winner', tone: 'ready' }
      : { label: 'Completed', tone: 'neutral' };
  }

  return { label: battle.status, tone: 'neutral' };
}

function statusClasses(tone: string): string {
  if (tone === 'ready') return 'border-emerald-500/50 text-emerald-300';
  if (tone === 'primary') return 'border-primary/50 text-primary';

  return 'border-[#444444] text-[#999999]';
}

function ProfileSide({
  profile,
  label,
  status,
}: {
  profile: BattleRecord['challenger'];
  label: string;
  status: { label: string; tone: string };
}) {
  return (
    <Link to={`/djs/${profile.handle}`} className="grid gap-4 border border-[#2a2a2a] bg-[#111111] p-5 transition-colors hover:border-primary/70">
      <div className="flex items-center justify-between gap-3">
        <span className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">{label}</span>
        <span className={`inline-flex h-7 items-center border px-2 text-[10px] font-bold uppercase tracking-widest ${statusClasses(status.tone)}`}>
          {status.label}
        </span>
      </div>
      <div className="flex items-center gap-4">
        <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border border-[#333333] bg-[#050505] text-2xl font-black uppercase text-white">
          {profile.avatar_url ? (
            <img src={profile.avatar_url} alt={profile.dj_name} className="h-full w-full rounded-full object-cover" />
          ) : (
            profile.dj_name.charAt(0)
          )}
        </div>
        <div className="min-w-0">
          <h2 className="truncate text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {profile.dj_name}
          </h2>
          <p className="truncate text-sm text-[#888888]">@{profile.handle}</p>
        </div>
      </div>
    </Link>
  );
}

function BattleActionPanel({
  battle,
  wallet,
  accountBattles,
  viewerProfileId,
  viewerProfile,
  actionLoading,
  actionError,
  onAction,
}: {
  battle: BattleRecord;
  wallet: WalletResponse | null;
  accountBattles: BattleRecord[];
  viewerProfileId: number | null;
  viewerProfile: AuthUser['dj_profile'];
  actionLoading: string;
  actionError: string;
  onAction: (key: string, action: () => Promise<BattleRecord>) => void;
}) {
  const isChallenger = viewerProfileId === battle.challenger.id;
  const isOpponent = viewerProfileId === battle.opponent.id;
  const isParticipant = isChallenger || isOpponent;
  const walletLoaded = !isParticipant || wallet !== null;
  const demoModeEnabled = Boolean(wallet?.demo_mode?.enabled);
  const tokenLabel = wallet?.demo_mode.token_label ?? 'Tokens';
  const availableBalance = wallet?.wallet.available_balance ?? 0;
  const walletActive = walletLoaded && wallet?.wallet.status === 'active';
  const alreadyActive = accountBattles.some((record) => (
    record.uuid !== battle.uuid && ['recording', 'voting', 'disputed'].includes(record.status)
  ));
  const hasFunds = availableBalance >= battle.stake_amount;
  const alreadyReady = isChallenger ? battle.readiness.challenger_ready : battle.readiness.opponent_ready;
  const samplePackStatus = battle.sample_pack_status || 'pending';
  const samplePackStatusLabel = demoModeEnabled && samplePackStatus === 'pending'
    ? 'Demo Bypass'
    : samplePackStatus
      .split('_')
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  const hasProfile = Boolean(viewerProfile);
  const profileActive = viewerProfile?.profile_status === 'active';
  const profilePublic = viewerProfile?.visibility === 'public';
  const battleReadyEnabled = Boolean(viewerProfile?.battle_enabled);
  const samplePackReady = ['ready', 'bypassed'].includes(samplePackStatus) || demoModeEnabled;
  const readinessRequirements = [
    {
      label: 'DJ profile',
      detail: hasProfile ? viewerProfile?.dj_name || 'Profile found' : 'Create a DJ profile',
      passed: hasProfile,
      actionHref: '/dj/start',
      actionLabel: 'Create Profile',
    },
    {
      label: 'Profile status',
      detail: profileActive ? 'Active' : `Current status: ${viewerProfile?.profile_status ?? 'missing'}`,
      passed: hasProfile && profileActive,
      actionHref: '/dj/edit',
      actionLabel: 'Edit Profile',
    },
    {
      label: 'Profile visibility',
      detail: profilePublic ? 'Public' : `Current visibility: ${viewerProfile?.visibility ?? 'missing'}`,
      passed: hasProfile && profilePublic,
      actionHref: '/dj/edit',
      actionLabel: 'Edit Profile',
    },
    {
      label: 'Battle Ready',
      detail: battleReadyEnabled ? 'Enabled' : 'Enable Battle Ready on your DJ profile',
      passed: hasProfile && battleReadyEnabled,
      actionHref: '/dj/edit',
      actionLabel: 'Enable',
    },
    {
      label: 'Wallet',
      detail: !walletLoaded ? 'Checking wallet' : walletActive ? 'Wallet active' : 'Wallet inactive',
      passed: walletLoaded && walletActive,
    },
    {
      label: 'Token balance',
      detail: walletLoaded
        ? `${formatTokens(availableBalance)} ${tokenLabel.toLowerCase()} available / ${formatTokens(battle.stake_amount)} required`
        : 'Checking balance',
      passed: walletLoaded && hasFunds,
    },
    {
      label: 'Active battles',
      detail: alreadyActive ? 'Finish your current active battle first' : 'No other active battle',
      passed: !alreadyActive,
    },
    {
      label: 'Sample pack',
      detail: samplePackStatus === 'bypassed'
        ? 'Bypassed for testing'
        : samplePackStatus === 'ready'
          ? 'Ready'
          : demoModeEnabled
            ? 'Demo bypass enabled'
            : 'Pending sample pack or bypass',
      passed: samplePackReady,
    },
  ];
  const missingRequirements = readinessRequirements.filter((requirement) => !requirement.passed);
  const readyBlock = missingRequirements.length > 0
    ? `${missingRequirements.length} requirement${missingRequirements.length === 1 ? '' : 's'} missing`
    : '';
  const isLocalTestHost = typeof window !== 'undefined'
    && ['localhost', '127.0.0.1', 'blendbeats.test'].includes(window.location.hostname);
  const otherDjReady = isChallenger ? battle.readiness.opponent_ready : battle.readiness.challenger_ready;
  const testReadyTarget = isChallenger ? battle.opponent.dj_name : battle.challenger.dj_name;
  const canSimulateOtherDjReady = isLocalTestHost && alreadyReady && !otherDjReady;
  const rules = battle.rules?.split('\n').filter(Boolean) ?? [];

  useClockTick();

  const timing = (() => {
    if (battle.status === 'pending') {
      return {
        badge: `Accept: ${formatCountdown(battle.response_due_at)}`,
        windowLabel: 'Acceptance Window',
        windowValue: formatCountdown(battle.response_due_at),
        dueLabel: 'Response Due',
        dueValue: formatDate(battle.response_due_at),
      };
    }

    if (battle.status === 'accepted') {
      return {
        badge: `Ready: ${formatCountdown(battle.ready_due_at)}`,
        windowLabel: 'Ready Window',
        windowValue: formatCountdown(battle.ready_due_at),
        dueLabel: 'Ready Due',
        dueValue: formatDate(battle.ready_due_at),
      };
    }

    if (battle.status === 'recording') {
      return {
        badge: `Record: ${formatCountdown(battle.recording_ends_at)}`,
        windowLabel: 'Recording Window',
        windowValue: formatCountdown(battle.recording_ends_at),
        dueLabel: 'Recording Ends',
        dueValue: formatDate(battle.recording_ends_at),
      };
    }

    if (battle.status === 'voting') {
      return {
        badge: `Vote: ${formatCountdown(battle.voting_ends_at)}`,
        windowLabel: 'Voting Window',
        windowValue: formatCountdown(battle.voting_ends_at),
        dueLabel: 'Voting Ends',
        dueValue: formatDate(battle.voting_ends_at),
      };
    }

    return {
      badge: battle.status,
      windowLabel: 'Battle Status',
      windowValue: battle.status,
      dueLabel: 'Created',
      dueValue: formatDate(battle.created_at),
    };
  })();

  const actionButton = (
    key: string,
    label: string,
    action: () => Promise<BattleRecord>,
    icon: typeof CheckCircle2,
    disabled = false,
    tone: 'primary' | 'neutral' = 'primary',
  ) => {
    const Icon = icon;

    return (
      <button
        type="button"
        onClick={() => onAction(key, action)}
        disabled={disabled || Boolean(actionLoading)}
        className={`inline-flex h-11 items-center justify-center gap-2 px-5 text-xs font-bold uppercase tracking-widest transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${
          tone === 'primary'
            ? 'bg-primary text-white hover:bg-primary/90'
            : 'border border-[#444444] text-[#dddddd] hover:border-primary hover:text-primary'
        }`}
        style={{ fontFamily: 'var(--font-heading)' }}
      >
        {actionLoading === key ? <Loader2 size={16} className="animate-spin" /> : <Icon size={16} />}
        {label}
      </button>
    );
  };

  let heading = 'Battle Status';
  let subcopy = 'This battle is moving through the BlendBeats challenge flow.';

  if (battle.status === 'pending') {
    heading = 'Pending Acceptance';
    subcopy = 'The challenged DJ has 24 hours to respond.';
  } else if (battle.status === 'paused') {
    heading = 'Challenge Paused';
    subcopy = 'The response window ended before the challenged DJ answered.';
  } else if (battle.status === 'accepted') {
    heading = 'Ready Phase';
    subcopy = 'The battle begins after both DJs confirm readiness.';
  } else if (battle.status === 'recording') {
    heading = 'Recording Window';
    subcopy = 'Both DJs record independently before fan voting opens.';
  } else if (battle.status === 'voting') {
    heading = 'Fan Voting';
    subcopy = 'Voting stays open for the selected battle length.';
  }

  return (
    <section className="grid gap-5 border border-[#2a2a2a] bg-[#111111] p-5">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-widest text-primary">{heading}</p>
          <p className="mt-2 text-sm leading-6 text-[#cccccc]">{subcopy}</p>
        </div>
        <span className="inline-flex h-8 items-center gap-2 border border-[#333333] px-3 text-[10px] font-bold uppercase tracking-widest text-[#dddddd]">
          <Clock size={13} className="text-primary" />
          {timing.badge}
        </span>
      </div>

      <div className="grid gap-3 sm:grid-cols-3">
        <div className="border border-[#242424] bg-[#080808] p-3">
          <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Stake</p>
          <p className="mt-1 text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {formatTokens(battle.stake_amount)}
          </p>
        </div>
        <div className="border border-[#242424] bg-[#080808] p-3">
          <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">{timing.windowLabel}</p>
          <p className="mt-1 text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {timing.windowValue}
          </p>
        </div>
        <div className="border border-[#242424] bg-[#080808] p-3">
          <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">{timing.dueLabel}</p>
          <p className="mt-2 text-sm text-[#dddddd]">{timing.dueValue}</p>
        </div>
      </div>

      {(rules.length > 0 || battle.challenge_message) && (
        <div className="grid gap-3 md:grid-cols-2">
          {rules.length > 0 && (
            <div className="border border-[#242424] bg-[#080808] p-4">
              <p className="mb-3 text-[10px] font-bold uppercase tracking-widest text-[#777777]">Rules</p>
              <ul className="grid gap-2 text-sm leading-6 text-[#cccccc]">
                {rules.map((rule) => <li key={rule}>{rule}</li>)}
              </ul>
            </div>
          )}
          {battle.challenge_message && (
            <div className="border border-[#242424] bg-[#080808] p-4">
              <p className="mb-3 text-[10px] font-bold uppercase tracking-widest text-[#777777]">Message</p>
              <p className="text-sm leading-6 text-[#dddddd]">{battle.challenge_message}</p>
            </div>
          )}
        </div>
      )}

      {isParticipant && ['accepted', 'recording'].includes(battle.status) && (
        <div className="grid gap-3 border border-[#242424] bg-[#080808] p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Sample Pack</p>
              <p className="mt-1 text-sm leading-6 text-[#cccccc]">
                {samplePackStatus === 'bypassed'
                  ? 'AI sample generation is bypassed for this testing battle.'
                  : samplePackStatus === 'ready'
                    ? 'The official battle sample pack is ready.'
                    : demoModeEnabled
                      ? 'Demo mode will bypass the sample pack when both DJs are ready.'
                      : 'AI samples are pending. Use the testing bypass to continue this battle flow.'}
              </p>
            </div>
            <span className={`inline-flex h-8 items-center border px-3 text-[10px] font-bold uppercase tracking-widest ${
              samplePackStatus === 'bypassed' || (demoModeEnabled && samplePackStatus === 'pending')
                ? 'border-primary/50 text-primary'
                : samplePackStatus === 'ready'
                  ? 'border-emerald-500/50 text-emerald-300'
                  : 'border-[#444444] text-[#999999]'
            }`}>
              {samplePackStatusLabel}
            </span>
          </div>

          {samplePackStatus === 'pending' && !demoModeEnabled && actionButton(
            'sample-bypass',
            'Bypass Samples',
            () => bypassBattleSamplePack(battle.uuid),
            ShieldCheck,
            false,
            'neutral',
          )}
        </div>
      )}

      {battle.status === 'accepted' && isParticipant && (
        <div className="grid gap-3 border border-[#242424] bg-[#080808] p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <WalletCards size={18} className="text-primary" />
              <div>
                <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Available {tokenLabel}</p>
                <p className="text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {walletLoaded ? formatTokens(availableBalance) : 'Loading'}
                </p>
              </div>
            </div>
            <span className="text-xs font-bold uppercase tracking-widest text-[#999999]">
              {isChallenger ? 'Challenger' : 'Opponent'} {alreadyReady ? 'Ready' : 'Not Ready'}
            </span>
          </div>

          {!alreadyReady && (
            <div className="grid gap-2 border border-[#242424] bg-[#050505] p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Ready Checklist</p>
                <span className={`text-[10px] font-bold uppercase tracking-widest ${
                  missingRequirements.length === 0 ? 'text-emerald-300' : 'text-primary'
                }`}>
                  {missingRequirements.length === 0 ? 'Ready to confirm' : readyBlock}
                </span>
              </div>

              <div className="grid gap-px overflow-hidden border border-[#242424] bg-[#242424] sm:grid-cols-2">
                {readinessRequirements.map((requirement) => {
                  const Icon = requirement.passed ? CheckCircle2 : XCircle;

                  return (
                    <div key={requirement.label} className="flex min-h-14 items-center justify-between gap-3 bg-[#080808] px-3 py-2">
                      <div className="flex min-w-0 items-center gap-2">
                        <Icon size={15} className={requirement.passed ? 'shrink-0 text-emerald-300' : 'shrink-0 text-primary'} />
                        <div className="min-w-0">
                          <p className="text-[10px] font-bold uppercase tracking-widest text-[#aaaaaa]">{requirement.label}</p>
                          <p className="truncate text-xs text-[#777777]">{requirement.detail}</p>
                        </div>
                      </div>

                      {!requirement.passed && requirement.actionHref && (
                        <Link
                          to={requirement.actionHref}
                          className="inline-flex h-7 shrink-0 items-center border border-[#444444] px-2 text-[10px] font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                          style={{ fontFamily: 'var(--font-heading)' }}
                        >
                          {requirement.actionLabel}
                        </Link>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {actionButton(
            'ready',
            alreadyReady ? 'Ready Submitted' : "I'm Ready",
            () => readyBattle(battle.uuid),
            CheckCircle2,
            alreadyReady || Boolean(readyBlock),
          )}

          {canSimulateOtherDjReady && (
            <div className="grid gap-3 border border-primary/40 bg-primary/10 p-3">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Test Mode</p>
                  <p className="mt-1 text-sm leading-6 text-[#dddddd]">
                    {testReadyTarget} still needs to press ready before recording can start.
                  </p>
                </div>
                {actionButton(
                  'test-ready-opponent',
                  `Ready ${testReadyTarget}`,
                  () => readyBattleOpponentForTesting(battle.uuid),
                  ShieldCheck,
                  false,
                  'neutral',
                )}
              </div>
            </div>
          )}
        </div>
      )}

      {battle.status === 'recording' && (
        <div className="grid gap-3">
          <div className="grid gap-3 sm:grid-cols-3">
            <div className="border border-[#242424] bg-[#080808] p-3">
              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Recording Ends</p>
              <p className="mt-2 text-sm text-[#dddddd]">{formatDate(battle.recording_ends_at)}</p>
            </div>
            <div className="border border-[#242424] bg-[#080808] p-3">
              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Fan Reward Pool</p>
              <p className="mt-1 text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {formatTokens(battle.fan_reward_pool_amount)}
              </p>
            </div>
            <div className="border border-[#242424] bg-[#080808] p-3">
              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Prize Pool</p>
              <p className="mt-1 text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {formatTokens(battle.prize_pool_amount)}
              </p>
            </div>
          </div>
          {isParticipant && (
            <Link
              to={`/battles/${battle.uuid}/record`}
              className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <Video size={16} />
              Open Recorder
            </Link>
          )}
        </div>
      )}

      {battle.status === 'voting' && (
        <div className="grid gap-3 border border-[#242424] bg-[#080808] p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Fan Voting</p>
              <p className="mt-1 text-sm leading-6 text-[#cccccc]">
                Fans watch and score each DJ one performance at a time.
              </p>
            </div>
            <span className="inline-flex h-8 items-center border border-[#333333] px-3 text-[10px] font-bold uppercase tracking-widest text-[#dddddd]">
              {battle.vote_count} Votes
            </span>
          </div>
          {!isParticipant && (
            <Link
              to={`/battles/${battle.uuid}/vote`}
              className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <Trophy size={16} />
              Vote Now
            </Link>
          )}
        </div>
      )}

      {actionError && <p className="text-sm text-primary">{actionError}</p>}

      {isParticipant && battle.status === 'pending' && (
        <div className="flex flex-wrap gap-3">
          {isOpponent && actionButton('accept', 'Accept Challenge', () => acceptBattle(battle.uuid), CheckCircle2)}
          {isOpponent && actionButton('decline', 'Decline Challenge', () => declineBattle(battle.uuid), XCircle, false, 'neutral')}
          {isChallenger && actionButton('cancel', 'Cancel Challenge', () => cancelBattle(battle.uuid), XCircle, false, 'neutral')}
        </div>
      )}

      {isChallenger && battle.status === 'paused' && (
        <div className="flex flex-wrap gap-3">
          {actionButton('extend', 'Extend Invitation', () => extendBattle(battle.uuid), PauseCircle)}
          {actionButton('cancel', 'Cancel Challenge', () => cancelBattle(battle.uuid), XCircle, false, 'neutral')}
        </div>
      )}
    </section>
  );
}

export default function BattleShowPage() {
  const { uuid } = useParams();
  const { user } = useAuth();
  const [battle, setBattle] = useState<BattleRecord | null>(null);
  const [wallet, setWallet] = useState<WalletResponse | null>(null);
  const [accountBattles, setAccountBattles] = useState<BattleRecord[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState('');
  const [actionError, setActionError] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    if (!uuid) return;

    let cancelled = false;

    setIsLoading(true);
    setError('');

    getBattle(uuid)
      .then((record) => {
        if (cancelled) return;
        setBattle(record);

        if (!user) return;

        Promise.all([getWallet(), getAccountBattles()])
          .then(([walletResponse, battles]) => {
            if (cancelled) return;
            setWallet(walletResponse);
            setAccountBattles(battles);
          })
          .catch(() => {
            if (!cancelled) setActionError('Unable to load wallet readiness.');
          });
      })
      .catch(() => {
        if (!cancelled) setError('Unable to load this battle.');
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [uuid, user?.id]);

  useEffect(() => {
    if (!uuid || !shouldRefreshBattle(battle?.status)) return;

    let cancelled = false;

    const refreshBattle = () => {
      getBattle(uuid)
        .then((record) => {
          if (!cancelled) setBattle(record);
        })
        .catch(() => {
          // Keep the current battle visible if a background refresh misses.
        });
    };

    const timer = window.setInterval(refreshBattle, 15000);
    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') refreshBattle();
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      cancelled = true;
      window.clearInterval(timer);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [uuid, battle?.status]);

  const refreshAccountState = async () => {
    if (!user) return;

    const [walletResponse, battles] = await Promise.all([getWallet(), getAccountBattles()]);
    setWallet(walletResponse);
    setAccountBattles(battles);
  };

  const runBattleAction = async (key: string, action: () => Promise<BattleRecord>) => {
    setActionLoading(key);
    setActionError('');

    try {
      const updatedBattle = await action();
      setBattle(updatedBattle);
      await refreshAccountState();
    } catch (requestError) {
      setActionError(actionErrorMessage(requestError));
    } finally {
      setActionLoading('');
    }
  };

  return (
    <>
      <Helmet>
        <title>{battle ? `${battle.title} | DJ Battles` : 'DJ Battle'} | The Blend Battlegrounds</title>
        <meta name="description" content="View a BlendBeats DJ battle." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-10 lg:px-8">
        <div className="container mx-auto max-w-5xl">
          <Link
            to="/battles"
            className="mb-6 inline-flex h-10 items-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <ArrowLeft size={15} />
            Battles
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
            <div className="grid gap-6">
              <section className="border border-[#2a2a2a] bg-[#111111] p-6">
                <div className="mb-4 flex flex-wrap gap-2">
                  <span className="inline-flex h-7 items-center border border-primary/50 px-2 text-[10px] font-bold uppercase tracking-widest text-primary">
                    {battle.status}
                  </span>
                  <span className="inline-flex h-7 items-center border border-[#333333] px-2 text-[10px] font-bold uppercase tracking-widest text-[#999999]">
                    {formatBattleType(battle.battle_type)}
                  </span>
                </div>
                <h1 className="text-5xl uppercase leading-none text-white md:text-7xl" style={{ fontFamily: 'var(--font-heading)' }}>
                  {battle.title}
                </h1>
                {battle.theme && <p className="mt-4 text-lg text-[#cccccc]">{battle.theme}</p>}
              </section>

              <section className="grid gap-4 md:grid-cols-2">
                <ProfileSide
                  profile={battle.challenger}
                  label="Challenger"
                  status={participantStatus(battle, battle.challenger.id, 'challenger')}
                />
                <ProfileSide
                  profile={battle.opponent}
                  label="Opponent"
                  status={participantStatus(battle, battle.opponent.id, 'opponent')}
                />
              </section>

              <section className="grid gap-4 border border-[#2a2a2a] bg-[#111111] p-5 sm:grid-cols-4">
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Stake</p>
                  <p className="mt-1 text-3xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {formatTokens(battle.stake_amount)}
                  </p>
                </div>
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Battle Length</p>
                  <p className="mt-2 inline-flex items-center gap-2 text-sm text-[#dddddd]">
                    <Clock size={15} className="text-primary" />
                    {battle.voting_duration_hours} hours
                  </p>
                </div>
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Votes</p>
                  <p className="mt-2 inline-flex items-center gap-2 text-sm text-[#dddddd]">
                    <ShieldCheck size={15} className="text-primary" />
                    {battle.result?.total_votes ?? 0}
                  </p>
                </div>
                <div>
                  <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Winner</p>
                  <p className="mt-2 inline-flex items-center gap-2 text-sm text-[#dddddd]">
                    <Trophy size={15} className="text-primary" />
                    {battle.winner?.dj_name ?? 'Pending'}
                  </p>
                </div>
              </section>

              <BattleActionPanel
                battle={battle}
                wallet={wallet}
                accountBattles={accountBattles}
                viewerProfileId={user?.dj_profile?.id ?? null}
                viewerProfile={user?.dj_profile ?? null}
                actionLoading={actionLoading}
                actionError={actionError}
                onAction={runBattleAction}
              />

              {battle.status === 'accepted' && (
                <section className="grid gap-4 md:grid-cols-2">
                  {[
                    { profile: battle.challenger, ready: battle.readiness.challenger_ready, role: 'Challenger' },
                    { profile: battle.opponent, ready: battle.readiness.opponent_ready, role: 'Opponent' },
                  ].map((participant) => (
                    <article key={participant.profile.id} className="border border-[#2a2a2a] bg-[#111111] p-5">
                      <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                          <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">{participant.role}</p>
                          <h2 className="mt-1 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                            {participant.profile.dj_name}
                          </h2>
                        </div>
                        <span className={`inline-flex h-8 items-center gap-2 border px-3 text-[10px] font-bold uppercase tracking-widest ${
                          participant.ready ? 'border-emerald-500/50 text-emerald-300' : 'border-[#444444] text-[#999999]'
                        }`}>
                          {participant.ready ? <CheckCircle2 size={14} /> : <Clock size={14} />}
                          {participant.ready ? 'Ready' : 'Awaiting Ready'}
                        </span>
                      </div>
                    </article>
                  ))}
                </section>
              )}

              {['recording', 'voting', 'completed'].includes(battle.status) && (
                <section className="grid gap-4">
                  {battle.entries.map((entry) => {
                    const profile = entry.dj_profile_id === battle.challenger.id ? battle.challenger : battle.opponent;
                    const canViewEntryMedia = battle.status !== 'recording' || entry.dj_profile_id === user?.dj_profile?.id;

                    return (
                      <article key={entry.id} className="border border-[#2a2a2a] bg-[#111111] p-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                          <div>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">{profile.dj_name}</p>
                            <h2 className="mt-1 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                              {entry.title || defaultEntryTitle(entry.status, battle.status)}
                            </h2>
                          </div>
                          <span className="inline-flex h-7 items-center border border-[#333333] px-2 text-[10px] font-bold uppercase tracking-widest text-[#999999]">
                            {formatEntryStatus(entry.status)}
                          </span>
                        </div>
                        {entry.media_url && canViewEntryMedia && (
                          <video src={entry.media_url} controls className="mt-4 aspect-video w-full bg-black" />
                        )}
                        {entry.media_url && !canViewEntryMedia && (
                          <div className="mt-4 flex min-h-32 items-center justify-center border border-[#242424] bg-[#050505] text-xs font-bold uppercase tracking-widest text-[#888888]">
                            Hidden Until Voting
                          </div>
                        )}
                      </article>
                    );
                  })}
                </section>
              )}
            </div>
          )}
        </div>
      </main>
    </>
  );
}
