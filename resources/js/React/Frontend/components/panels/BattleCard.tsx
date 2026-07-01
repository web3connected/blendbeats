import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { Clock, Trophy, Users, Volume2 } from 'lucide-react';

import { fadeUp } from '@/config/animations';
import type { BattleRecord } from '@/lib/battles';

type BattleCardProps = {
  battle: BattleRecord;
  delay: number;
};

function formatBattleType(value: string): string {
  return value
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function statusLabel(value: string): string {
  if (value === 'voting') return 'Voting Open';
  if (value === 'recording') return 'Recording';
  if (value === 'accepted') return 'Ready Check';

  return formatBattleType(value);
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat('en-US').format(value);
}

function formatScore(value: number): string {
  return value > 0 ? `${value.toFixed(1)}/100 avg` : 'No scores yet';
}

function deadlineFor(battle: BattleRecord): string | null {
  if (battle.status === 'voting') return battle.voting_ends_at;
  if (battle.status === 'recording') return battle.recording_ends_at;
  if (battle.status === 'accepted') return battle.ready_due_at;

  return null;
}

function formatCountdown(value: string | null): string {
  if (!value) return 'Live';

  const remainingMs = new Date(value).getTime() - Date.now();
  if (!Number.isFinite(remainingMs) || remainingMs <= 0) return 'Closing';

  const totalMinutes = Math.ceil(remainingMs / 60000);
  const days = Math.floor(totalMinutes / 1440);
  const hours = Math.floor((totalMinutes % 1440) / 60);
  const minutes = totalMinutes % 60;

  if (days > 0) return `${days}d ${hours}h`;
  if (hours > 0) return `${hours}h ${minutes}m`;

  return `${minutes}m`;
}

function profileInitial(name: string): string {
  return name.trim().charAt(0).toUpperCase() || 'D';
}

function BattleAvatar({
  name,
  avatarUrl,
  accent,
}: {
  name: string;
  avatarUrl: string | null;
  accent: string;
}) {
  return (
    <div
      className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full border-2 bg-[#1f1f1f] text-sm font-black uppercase text-white"
      style={{ borderColor: accent }}
    >
      {avatarUrl ? (
        <img src={avatarUrl} alt={name} className="h-full w-full rounded-full object-cover" />
      ) : (
        <Volume2 size={18} style={{ color: accent }} />
      )}
    </div>
  );
}

export default function BattleCard({ battle, delay }: BattleCardProps) {
  const challengerScore = Number(battle.result?.challenger_score ?? 0);
  const opponentScore = Number(battle.result?.opponent_score ?? 0);
  const totalScore = challengerScore + opponentScore;
  const pct1 = totalScore > 0 ? Math.round((challengerScore / totalScore) * 100) : 50;
  const pct2 = 100 - pct1;
  const voteCount = battle.result?.total_votes ?? battle.vote_count;
  const ctaHref = battle.status === 'voting' ? `/battles/${battle.uuid}/vote` : `/battles/${battle.uuid}`;
  const ctaLabel = battle.status === 'voting' ? 'Vote Now' : 'View Battle';
  const countdown = formatCountdown(deadlineFor(battle));

  return (
    <motion.div
      custom={delay}
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true }}
      variants={fadeUp}
      whileHover={{ y: -4, boxShadow: '0 0 24px rgba(255,26,26,0.35)' }}
      className="bg-[#141414] border border-[#2a2a2a] hover:border-primary transition-all duration-200 p-5 flex flex-col gap-4"
    >
      <div className="flex items-center justify-between">
        <span
          className="text-[10px] font-bold tracking-widest text-[#888888] uppercase"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          {formatBattleType(battle.battle_type)}
        </span>

        <span
          className="flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-primary uppercase"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          <span className="w-1.5 h-1.5 rounded-full bg-primary animate-pulse" />
          {statusLabel(battle.status)}
        </span>
      </div>

      <div className="min-w-0">
        <p
          className="truncate text-xs font-bold uppercase tracking-widest text-[#dddddd]"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          {battle.title}
        </p>
      </div>

      <div className="flex items-center gap-3">
        <div className="flex-1 text-center">
          <BattleAvatar name={battle.challenger.dj_name} avatarUrl={battle.challenger.avatar_url} accent="#ff1a1a" />
          <p
            className="text-white text-sm font-bold truncate"
            style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.05em' }}
          >
            {battle.challenger.dj_name}
          </p>
          <p className="text-[#888888] text-xs mt-0.5">
            {voteCount > 0 ? formatScore(challengerScore) : `@${battle.challenger.handle || profileInitial(battle.challenger.dj_name)}`}
          </p>
        </div>

        <div className="flex flex-col items-center">
          <span
            className="text-2xl font-black text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            VS
          </span>
        </div>

        <div className="flex-1 text-center">
          <BattleAvatar name={battle.opponent.dj_name} avatarUrl={battle.opponent.avatar_url} accent="#FFB800" />
          <p
            className="text-white text-sm font-bold truncate"
            style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.05em' }}
          >
            {battle.opponent.dj_name}
          </p>
          <p className="text-[#888888] text-xs mt-0.5">
            {voteCount > 0 ? formatScore(opponentScore) : `@${battle.opponent.handle || profileInitial(battle.opponent.dj_name)}`}
          </p>
        </div>
      </div>

      <div className="h-2 bg-[#1a1a1a] rounded-full overflow-hidden flex">
        <div className="bg-primary h-full transition-all duration-500" style={{ width: `${pct1}%` }} />
        <div className="bg-[#FFB800] h-full transition-all duration-500" style={{ width: `${pct2}%` }} />
      </div>

      <div className="flex justify-between text-[10px] text-[#888888] -mt-2">
        <span>{pct1}%</span>
        <span>{pct2}%</span>
      </div>

      <div className="grid grid-cols-3 gap-px overflow-hidden border border-[#242424] bg-[#242424]">
        <div className="bg-[#080808] p-2">
          <p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
            <Users size={12} className="text-primary" />
            Votes
          </p>
          <p className="mt-1 text-sm font-semibold text-white">{formatNumber(voteCount)}</p>
        </div>
        <div className="bg-[#080808] p-2">
          <p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
            <Trophy size={12} className="text-primary" />
            Reward
          </p>
          <p className="mt-1 text-sm font-semibold text-white">{formatNumber(battle.fan_reward_pool_amount)}</p>
        </div>
        <div className="bg-[#080808] p-2">
          <p className="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
            <Clock size={12} className="text-primary" />
            Time
          </p>
          <p className="mt-1 text-sm font-semibold text-white">{countdown}</p>
        </div>
      </div>

      <Link
        to={ctaHref}
        className="block text-center py-2.5 bg-primary text-white text-xs font-bold tracking-widest uppercase hover:bg-primary/90 transition-colors"
        style={{ fontFamily: 'var(--font-heading)' }}
      >
        {ctaLabel}
      </Link>
    </motion.div>
  );
}
