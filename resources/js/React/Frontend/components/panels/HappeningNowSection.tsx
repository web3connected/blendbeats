import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';

import type { HomeBattle } from '@/config/home';
import BattleCard from './BattleCard';

const HappeningNowSection = ({
    battles,
    }: {
        battles: HomeBattle[]
}) => {
  return (
     <section className="py-20 bg-[#0a0a0a] relative overflow-hidden">
        {/* Gritty texture */}
        <div className="absolute inset-0 opacity-[0.03]" style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'n\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.9\' numOctaves=\'4\' stitchTiles=\'stitch\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23n)\'/%3E%3C/svg%3E")', backgroundSize: '200px' }} />

        <div className="container mx-auto px-4 relative z-10">
          <div className="flex items-end justify-between mb-10">
            <div>
              <motion.p
                initial={{ opacity: 0 }}
                whileInView={{ opacity: 1 }}
                viewport={{ once: true }}
                className="text-primary text-xs font-bold tracking-widest uppercase mb-2"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                ● HAPPENING NOW
              </motion.p>
              <motion.h2
                initial={{ opacity: 0, x: -30 }}
                whileInView={{ opacity: 1, x: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.4 }}
                className="text-white uppercase"
                style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(2.5rem, 6vw, 5rem)', letterSpacing: '-0.01em', lineHeight: 1 }}
              >
                LIVE BATTLES
              </motion.h2>
            </div>
            <Link
              to="/battles"
              className="hidden md:flex items-center gap-2 text-primary text-xs font-bold tracking-widest uppercase hover:gap-3 transition-all"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              ALL BATTLES <ChevronRight size={14} />
            </Link>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
            {battles.map((b, i) => (
              <BattleCard key={i} {...b} delay={i} />
            ))}
          </div>
        </div>
      </section>
  )
}

export default HappeningNowSection
