import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { ChevronRight, Eye, Loader2, Trophy, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

import { fadeUp } from '@/config/animations';
import { getDjHubDjs, type DjHubDj } from '@/lib/dj-hub';

function rankColor(rank: number) {
  if (rank === 1) return '#FFB800';
  if (rank === 2) return '#aaaaaa';
  if (rank === 3) return '#cd7f32';
  return '#777777';
}

const TopDJsLeaderboardTeaser = () => {
  const [djs, setDjs] = useState<DjHubDj[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    getDjHubDjs({ sort: 'top' })
      .then((response) => setDjs(response.djs.slice(0, 3)))
      .catch(() => setError('Top DJs could not be loaded.'))
      .finally(() => setIsLoading(false));
  }, []);

  return (
    <section className="border-t border-[#1a1a1a] bg-[#0d0d0d] py-16">
      <div className="container mx-auto px-4">
        <div className="mb-8 flex items-end justify-between gap-4">
          <div>
            <motion.p
              initial={{ opacity: 0, x: -30 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.35 }}
              className="mb-2 text-xs font-bold uppercase tracking-widest text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Views + Follows
            </motion.p>
            <motion.h2
              initial={{ opacity: 0, x: -30 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.4 }}
              className="uppercase text-white"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(2rem, 5vw, 4rem)', letterSpacing: '-0.01em', lineHeight: 1 }}
            >
              Top DJs
            </motion.h2>
          </div>
          <Link
            to="/djs?sort=top"
            className="flex shrink-0 items-center gap-2 text-xs font-bold uppercase tracking-widest text-primary transition-all hover:gap-3"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            View All <ChevronRight size={14} />
          </Link>
        </div>

        {error && <div className="mb-5 border border-primary bg-primary/10 p-4 text-sm text-white">{error}</div>}

        {isLoading ? (
          <div className="flex min-h-40 items-center justify-center border border-[#292929] bg-[#101010]">
            <Loader2 className="animate-spin text-primary" size={26} />
          </div>
        ) : djs.length === 0 ? (
          <div className="border border-[#292929] bg-[#101010] p-8 text-center">
            <Trophy className="mx-auto text-[#FFB800]" size={34} />
            <p className="mt-4 text-sm text-[#999999]">No public DJs are available yet.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            {djs.map((dj, index) => {
              const rank = index + 1;

              return (
                <motion.article
                  key={dj.id}
                  custom={index}
                  initial="hidden"
                  whileInView="visible"
                  viewport={{ once: true }}
                  variants={fadeUp}
                  whileHover={{ y: -3 }}
                  className="border border-[#2a2a2a] bg-[#141414] p-5 transition-all hover:border-[#FFB800]/50"
                >
                  <div className="flex items-center gap-4">
                    <span
                      className="shrink-0 text-4xl font-black"
                      style={{ fontFamily: 'var(--font-heading)', color: rankColor(rank) }}
                    >
                      #{rank}
                    </span>
                    <div className="min-w-0 flex-1">
                      <Link
                        to={`/djs/${dj.handle}`}
                        className="truncate text-xl font-bold uppercase text-white transition-colors hover:text-primary"
                        style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.05em' }}
                      >
                        {dj.dj_name}
                      </Link>
                      <p className="mt-1 truncate text-xs text-[#888888]">{dj.primary_genre || 'Open Format'}</p>
                    </div>
                    <Trophy size={18} className={rank === 1 ? 'text-[#FFB800]' : 'text-[#333333]'} />
                  </div>

                  <div className="mt-4 grid grid-cols-2 gap-2">
                    <div className="border border-[#2a2a2a] bg-[#0b0b0b] p-3">
                      <div className="flex items-center gap-2 text-[#dddddd]">
                        <Eye size={14} className="text-[#FFB800]" />
                        <span className="text-sm font-semibold">{dj.view_count.toLocaleString()}</span>
                      </div>
                      <p className="mt-2 text-xs text-[#888888]">Profile views</p>
                    </div>
                    <div className="border border-[#2a2a2a] bg-[#0b0b0b] p-3">
                      <div className="flex items-center gap-2 text-[#dddddd]">
                        <Users size={14} className="text-primary" />
                        <span className="text-sm font-semibold">{dj.followers_count.toLocaleString()}</span>
                      </div>
                      <p className="mt-2 text-xs text-[#888888]">Followers</p>
                    </div>
                  </div>

                  <p className="mt-4 text-xs leading-5 text-[#777777]">
                    Engagement score: {dj.engagement_score.toLocaleString()} points from profile views and followers.
                  </p>
                </motion.article>
              );
            })}
          </div>
        )}
      </div>
    </section>
  );
};

export default TopDJsLeaderboardTeaser;
