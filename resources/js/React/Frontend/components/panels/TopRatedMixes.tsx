import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { ChevronRight, Disc3, Play, Star } from 'lucide-react';
import { useEffect, useState } from 'react';

import StarRatingContainer from '../StarRatingContainer';
import WaveFormSVG from '../WaveFormSVG';
import { fadeUp } from '@/config/animations';
import { type PlayerTrack, usePlayer } from '@/components/player/PlayerProvider';
import { getTopMixes, trackMixPlay, type PublicMix } from '@/lib/mixes';

function formatNumber(value: number): string {
  return new Intl.NumberFormat('en-US', { notation: value >= 10000 ? 'compact' : 'standard' }).format(value);
}

function mixToTrack(mix: PublicMix): PlayerTrack {
  return {
    id: `home-mix-${mix.id}`,
    title: mix.title,
    artist: mix.dj.name,
    src: mix.audio_url || '',
    artwork: mix.cover_image_url,
    meta: mix.genre || 'Mix',
    duration: mix.duration,
    countLabel: 'plays',
    countValue: mix.play_count,
  };
}

function Waveform() {
  const bars = [3, 8, 5, 12, 7, 15, 9, 6, 14, 10, 4, 11, 8, 13, 6, 9, 12, 5, 10, 7];
  return <WaveFormSVG bars={bars} />;
}

function MixArtwork({ mix }: { mix: PublicMix }) {
  if (mix.cover_image_url) {
    return (
      <img
        src={mix.cover_image_url}
        alt={`${mix.title} cover`}
        loading="lazy"
        className="absolute inset-0 h-full w-full object-cover"
      />
    );
  }

  return (
    <div className="absolute inset-0 bg-[#0a0a0a]">
      <div className="absolute inset-0 opacity-80">
        <Waveform />
      </div>
      <Disc3 size={30} className="absolute right-4 top-1/2 -translate-y-1/2 text-[#FFB800]/45" />
    </div>
  );
}

function TopMixCard({
  mix,
  delay,
  onPlay,
}: {
  mix: PublicMix;
  delay: number;
  onPlay: (mix: PublicMix) => void;
}) {
  const rating = Math.round(mix.rating_average);
  const hasAudio = Boolean(mix.audio_url);

  return (
    <motion.article
      custom={delay}
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true }}
      variants={fadeUp}
      whileHover={{ y: -4, boxShadow: '0 0 20px rgba(255,26,26,0.2)' }}
      className="flex min-w-[220px] flex-col gap-3 border border-[#2a2a2a] bg-[#141414] p-4 transition-all duration-200 hover:border-primary/50"
    >
      <div className="relative aspect-[3.25/1] overflow-hidden border border-[#1f1f1f] bg-[#0a0a0a]">
        <MixArtwork mix={mix} />
        <div className="absolute inset-0 bg-gradient-to-r from-black/40 via-transparent to-black/20" />
        <button
          type="button"
          onClick={() => onPlay(mix)}
          disabled={!hasAudio}
          className="absolute right-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-primary text-white transition-colors hover:bg-primary/80 disabled:cursor-not-allowed disabled:opacity-50"
          aria-label={hasAudio ? `Play ${mix.title}` : `${mix.title} audio is unavailable`}
        >
          <Play size={13} className="ml-0.5 fill-white" />
        </button>
      </div>

      <div className="min-w-0">
        <Link
          to={`/mixes#mix-${mix.id}`}
          className="block truncate text-sm font-bold uppercase leading-tight text-white transition-colors hover:text-[#FFB800]"
          style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.03em' }}
        >
          {mix.title}
        </Link>
        <p className="mt-0.5 truncate text-xs text-[#888888]">{mix.dj.name}</p>
      </div>

      <div className="flex items-center justify-between gap-3">
        <div className="grid gap-1">
          <StarRatingContainer rating={rating} animated />
          <span className="text-[10px] text-[#555555]">
            {mix.rating_count > 0 ? `${mix.rating_average.toFixed(1)} from ${formatNumber(mix.rating_count)}` : 'No ratings yet'}
          </span>
        </div>
        <span className="shrink-0 text-[10px] text-[#555555]">{formatNumber(mix.play_count)} plays</span>
      </div>

      <span
        className="inline-block w-fit border border-[#FFB800]/30 px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-[#FFB800]"
        style={{ fontFamily: 'var(--font-heading)' }}
      >
        {mix.genre || 'Open Format'}
      </span>
    </motion.article>
  );
}

export default function TopRatedMixes() {
  const { playTrack, updateCurrentTrack } = usePlayer();
  const [mixes, setMixes] = useState<PublicMix[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;

    const loadMixes = async (showLoading: boolean) => {
      if (showLoading) setIsLoading(true);
      setError('');

      try {
        const records = await getTopMixes(5);

        if (!cancelled) {
          setMixes(records);
        }
      } catch {
        if (!cancelled) {
          setError('Top mixes are temporarily unavailable.');
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    };

    void loadMixes(true);

    const refreshTimer = window.setInterval(() => {
      void loadMixes(false);
    }, 60000);

    return () => {
      cancelled = true;
      window.clearInterval(refreshTimer);
    };
  }, []);

  const handlePlay = async (mix: PublicMix) => {
    if (!mix.audio_url) return;

    playTrack(mixToTrack(mix));

    try {
      const playCount = await trackMixPlay(mix.slug);

      updateCurrentTrack({
        countLabel: 'plays',
        countValue: playCount,
      });

      setMixes((current) => current.map((item) => (
        item.id === mix.id ? { ...item, play_count: playCount } : item
      )));
    } catch {
      setError('The mix is playing, but play tracking failed.');
    }
  };

  return (
    <section className="border-t border-[#1a1a1a] bg-[#0d0d0d] py-20">
      <div className="container mx-auto px-4">
        <div className="mb-10 flex items-end justify-between">
          <div>
            <motion.p
              initial={{ opacity: 0 }}
              whileInView={{ opacity: 1 }}
              viewport={{ once: true }}
              className="mb-2 flex items-center gap-1 text-xs font-bold uppercase tracking-widest text-[#FFB800]"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <Star size={12} className="fill-[#FFB800]" />
              Community Rated
            </motion.p>
            <motion.h2
              initial={{ opacity: 0, x: -30 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.4 }}
              className="text-5xl uppercase leading-none text-white md:text-7xl"
              style={{ fontFamily: 'var(--font-heading)', letterSpacing: 0 }}
            >
              Top Mixes
            </motion.h2>
          </div>
          <Link
            to="/mixes"
            className="hidden items-center gap-2 text-xs font-bold uppercase tracking-widest text-[#FFB800] transition-all hover:gap-3 md:flex"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            All Mixes <ChevronRight size={14} />
          </Link>
        </div>

        {isLoading && (
          <div className="flex gap-4 overflow-x-auto pb-4" style={{ scrollbarWidth: 'none' }}>
            {Array.from({ length: 5 }).map((_, index) => (
              <div key={index} className="h-[218px] min-w-[220px] animate-pulse border border-[#2a2a2a] bg-[#141414]" />
            ))}
          </div>
        )}

        {!isLoading && error && (
          <div className="border border-[#FFB800]/40 bg-[#FFB800]/10 p-5 text-sm text-[#FFB800]">
            {error}
          </div>
        )}

        {!isLoading && !error && mixes.length > 0 && (
          <div className="flex gap-4 overflow-x-auto pb-4" style={{ scrollbarWidth: 'none' }}>
            {mixes.map((mix, index) => (
              <TopMixCard key={mix.id} mix={mix} delay={index} onPlay={handlePlay} />
            ))}
          </div>
        )}

        {!isLoading && !error && mixes.length === 0 && (
          <div className="grid min-h-52 place-items-center border border-[#2a2a2a] bg-[#111111] p-8 text-center">
            <div>
              <Disc3 size={38} className="mx-auto text-[#FFB800]" />
              <h3 className="mt-4 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                No Top Mixes Yet
              </h3>
              <p className="mt-2 text-sm text-[#aaaaaa]">
                Rated public mixes will appear here once the community starts listening.
              </p>
              <Link
                to="/mixes"
                className="mt-5 inline-flex h-10 items-center justify-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-[#FFB800] hover:text-[#FFB800]"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Browse Mixes
                <ChevronRight size={14} />
              </Link>
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
