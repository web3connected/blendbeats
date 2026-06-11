import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { Volume2 } from 'lucide-react';

import { fadeUp } from '@/config/animations';
import type { HomeBattle } from '@/config/home';

type BattleCardProps = HomeBattle & {
  delay: number;
};

export default function BattleCard({
  dj1,
  dj2,
  genre,
  votes1,
  votes2,
  live,
  delay,
}: BattleCardProps) {
  const total = votes1 + votes2;
  const pct1 = total > 0 ? Math.round((votes1 / total) * 100) : 50;
  const pct2 = 100 - pct1;

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
          {genre}
        </span>

        {live && (
          <span
            className="flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-primary uppercase"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <span className="w-1.5 h-1.5 rounded-full bg-primary animate-pulse" />
            LIVE
          </span>
        )}
      </div>

      <div className="flex items-center gap-3">
        <div className="flex-1 text-center">
          <div className="w-12 h-12 rounded-full bg-[#1f1f1f] border-2 border-primary mx-auto mb-2 flex items-center justify-center">
            <Volume2 size={18} className="text-primary" />
          </div>
          <p
            className="text-white text-sm font-bold truncate"
            style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.05em' }}
          >
            {dj1}
          </p>
          <p className="text-[#888888] text-xs mt-0.5">{votes1.toLocaleString()} votes</p>
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
          <div className="w-12 h-12 rounded-full bg-[#1f1f1f] border-2 border-[#FFB800] mx-auto mb-2 flex items-center justify-center">
            <Volume2 size={18} className="text-[#FFB800]" />
          </div>
          <p
            className="text-white text-sm font-bold truncate"
            style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.05em' }}
          >
            {dj2}
          </p>
          <p className="text-[#888888] text-xs mt-0.5">{votes2.toLocaleString()} votes</p>
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

      <Link
        to="/battles"
        className="block text-center py-2.5 bg-primary text-white text-xs font-bold tracking-widest uppercase hover:bg-primary/90 transition-colors"
        style={{ fontFamily: 'var(--font-heading)' }}
      >
        VOTE NOW
      </Link>
    </motion.div>
  );
}
