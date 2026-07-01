import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { ChevronRight, Trophy } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { getBattles, type BattleRecord } from '@/lib/battles';
import BattleCard from './BattleCard';

const liveStatusPriority: Record<string, number> = {
  voting: 0,
  recording: 1,
  accepted: 2,
};

function activeDeadline(battle: BattleRecord): string | null {
  if (battle.status === 'voting') return battle.voting_ends_at;
  if (battle.status === 'recording') return battle.recording_ends_at;
  if (battle.status === 'accepted') return battle.ready_due_at;

  return null;
}

function deadlineTimestamp(value: string | null): number {
  if (!value) return Number.MAX_SAFE_INTEGER;

  const timestamp = new Date(value).getTime();

  return Number.isFinite(timestamp) ? timestamp : Number.MAX_SAFE_INTEGER;
}

function isLiveBattle(battle: BattleRecord): boolean {
  return ['voting', 'recording', 'accepted'].includes(battle.status);
}

export default function HappeningNowSection() {
  const [battles, setBattles] = useState<BattleRecord[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;

    const loadBattles = async (showLoading: boolean) => {
      if (showLoading) setIsLoading(true);
      setError('');

      try {
        const records = await getBattles({ limit: 12 });

        if (!cancelled) {
          setBattles(records);
        }
      } catch {
        if (!cancelled) {
          setError('Live battles are temporarily unavailable.');
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    };

    void loadBattles(true);

    const refreshTimer = window.setInterval(() => {
      void loadBattles(false);
    }, 60000);

    return () => {
      cancelled = true;
      window.clearInterval(refreshTimer);
    };
  }, []);

  const liveBattles = useMemo(() => (
    battles
      .filter(isLiveBattle)
      .sort((a, b) => {
        const statusSort = (liveStatusPriority[a.status] ?? 99) - (liveStatusPriority[b.status] ?? 99);
        if (statusSort !== 0) return statusSort;

        const deadlineSort = deadlineTimestamp(activeDeadline(a)) - deadlineTimestamp(activeDeadline(b));
        if (deadlineSort !== 0) return deadlineSort;

        return b.vote_count - a.vote_count;
      })
      .slice(0, 3)
  ), [battles]);

  return (
    <section className="relative overflow-hidden bg-[#0a0a0a] py-20">
      <div
        className="absolute inset-0 opacity-[0.03]"
        style={{
          backgroundImage: 'url("data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'n\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.9\' numOctaves=\'4\' stitchTiles=\'stitch\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23n)\'/%3E%3C/svg%3E")',
          backgroundSize: '200px',
        }}
      />

      <div className="container relative z-10 mx-auto px-4">
        <div className="mb-10 flex items-end justify-between">
          <div>
            <motion.p
              initial={{ opacity: 0 }}
              whileInView={{ opacity: 1 }}
              viewport={{ once: true }}
              className="mb-2 text-xs font-bold uppercase tracking-widest text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Live Feed
            </motion.p>
            <motion.h2
              initial={{ opacity: 0, x: -30 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.4 }}
              className="text-white uppercase"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(2.5rem, 6vw, 5rem)', letterSpacing: 0, lineHeight: 1 }}
            >
              Live Battles
            </motion.h2>
          </div>
          <Link
            to="/battles/voting"
            className="hidden items-center gap-2 text-xs font-bold uppercase tracking-widest text-primary transition-all hover:gap-3 md:flex"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            All Battles <ChevronRight size={14} />
          </Link>
        </div>

        {isLoading && (
          <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
            {Array.from({ length: 3 }).map((_, index) => (
              <div key={index} className="h-[340px] animate-pulse border border-[#2a2a2a] bg-[#141414]" />
            ))}
          </div>
        )}

        {!isLoading && error && (
          <div className="border border-primary/40 bg-primary/10 p-5 text-sm text-primary">
            {error}
          </div>
        )}

        {!isLoading && !error && liveBattles.length > 0 && (
          <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
            {liveBattles.map((battle, index) => (
              <BattleCard key={battle.uuid} battle={battle} delay={index} />
            ))}
          </div>
        )}

        {!isLoading && !error && liveBattles.length === 0 && (
          <div className="grid min-h-56 place-items-center border border-[#2a2a2a] bg-[#111111] p-8 text-center">
            <div>
              <Trophy size={34} className="mx-auto text-primary" />
              <h3 className="mt-4 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                No Live Battles Yet
              </h3>
              <p className="mt-2 text-sm text-[#aaaaaa]">
                Active battle cards will appear here once DJs enter ready check, recording, or fan voting.
              </p>
              <Link
                to="/battles/voting"
                className="mt-5 inline-flex h-10 items-center justify-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Browse Voting
                <ChevronRight size={14} />
              </Link>
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
