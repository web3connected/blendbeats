import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { ChevronRight, Trophy } from 'lucide-react';
import StarRating from '../StarRatingContainer';
import { fadeUp } from '@/config/animations';

const TopDJsLeaderboardTeaser = () => {
  return (
    <section className="py-16 bg-[#0d0d0d] border-t border-[#1a1a1a]">
        <div className="container mx-auto px-4">
          <div className="flex items-end justify-between mb-8">
            <motion.h2
              initial={{ opacity: 0, x: -30 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.4 }}
              className="text-white uppercase"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(2rem, 5vw, 4rem)', letterSpacing: '-0.01em', lineHeight: 1 }}
            >
              TOP DJS
            </motion.h2>
            <Link
              to="/djs"
              className="flex items-center gap-2 text-primary text-xs font-bold tracking-widest uppercase hover:gap-3 transition-all"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              VIEW ALL <ChevronRight size={14} />
            </Link>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {[
              { rank: 1, name: 'BASS PROPHET', wins: 24, genre: 'Drum & Bass', rating: 4.9 },
              { rank: 2, name: 'DJ KROME', wins: 21, genre: 'Hip-Hop', rating: 4.8 },
              { rank: 3, name: 'LADY FREQ', wins: 19, genre: 'House', rating: 4.7 },
            ].map((dj, i) => (
              <motion.div
                key={dj.rank}
                custom={i}
                initial="hidden"
                whileInView="visible"
                viewport={{ once: true }}
                variants={fadeUp}
                whileHover={{ y: -3 }}
                className="bg-[#141414] border border-[#2a2a2a] hover:border-[#FFB800]/50 transition-all p-5 flex items-center gap-4"
              >
                <span
                  className="text-4xl font-black shrink-0"
                  style={{
                    fontFamily: 'var(--font-heading)',
                    color: dj.rank === 1 ? '#FFB800' : dj.rank === 2 ? '#aaaaaa' : '#cd7f32',
                  }}
                >
                  #{dj.rank}
                </span>
                <div className="flex-1 min-w-0">
                  <p className="text-white font-bold truncate" style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.05em' }}>{dj.name}</p>
                  <p className="text-[#888888] text-xs mt-0.5">{dj.genre}</p>
                  <div className="flex items-center gap-3 mt-1">
                    <span className="text-[#888888] text-xs">{dj.wins} wins</span>
                    <StarRating rating={Math.round(dj.rating)} />
                  </div>
                </div>
                <Trophy size={18} className={dj.rank === 1 ? 'text-[#FFB800]' : 'text-[#333333]'} />
              </motion.div>
            ))}
          </div>
        </div>
      </section>
  )
}

export default TopDJsLeaderboardTeaser
