import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { Play, Star, Trophy, Users, Zap } from 'lucide-react';
import { useEffect, useState } from 'react';

import { slamIn } from '@/config/animations';
import { getDjScratches, type DjScratch } from '@/lib/dj-scratches';
import { siteMedia } from '@/lib/site-media';

function getYoutubeVideoId(value: string | null | undefined) {
  if (!value) return null;

  try {
    const url = new URL(value);
    const host = url.hostname.replace(/^www\./, '').toLowerCase();

    if (host === 'youtu.be') {
      return url.pathname.split('/').filter(Boolean)[0] ?? null;
    }

    if (host.endsWith('youtube.com')) {
      if (url.pathname.startsWith('/embed/')) return url.pathname.split('/').filter(Boolean)[1] ?? null;
      if (url.pathname.startsWith('/shorts/')) return url.pathname.split('/').filter(Boolean)[1] ?? null;

      return url.searchParams.get('v');
    }
  } catch {
    return null;
  }

  return null;
}

function youtubeBackgroundUrl(scratch: DjScratch) {
  const baseUrl = scratch.embed_url || scratch.external_url || scratch.url;
  const videoId = getYoutubeVideoId(baseUrl);

  if (!videoId) return null;

  const embedUrl = new URL(`https://www.youtube.com/embed/${videoId}`);

  embedUrl.searchParams.set('autoplay', '1');
  embedUrl.searchParams.set('mute', '1');
  embedUrl.searchParams.set('controls', '0');
  embedUrl.searchParams.set('loop', '1');
  embedUrl.searchParams.set('playlist', videoId);
  embedUrl.searchParams.set('playsinline', '1');
  embedUrl.searchParams.set('rel', '0');
  embedUrl.searchParams.set('modestbranding', '1');
  embedUrl.searchParams.set('iv_load_policy', '3');
  embedUrl.searchParams.set('disablekb', '1');

  return embedUrl.toString();
}

function HeroScratchBackground({ scratch }: { scratch: DjScratch }) {
  const youtubeUrl = scratch.external_provider === 'youtube' ? youtubeBackgroundUrl(scratch) : null;

  if (youtubeUrl) {
    return (
      <iframe
        src={youtubeUrl}
        title=""
        aria-hidden="true"
        tabIndex={-1}
        allow="autoplay; encrypted-media; picture-in-picture"
        className="pointer-events-none absolute left-1/2 top-1/2 border-0 opacity-95"
        style={{
          height: 'max(100%, 56.25vw)',
          transform: 'translate(-50%, -50%) scale(1.08)',
          width: 'max(100%, 177.78vh)',
        }}
      />
    );
  }

  return (
    <video
      src={scratch.url}
      poster={scratch.cover_image_url ?? scratch.thumbnail_url ?? undefined}
      autoPlay
      muted
      loop
      playsInline
      preload="metadata"
      aria-hidden="true"
      className="pointer-events-none absolute inset-0 h-full w-full scale-105 object-cover opacity-95"
    >
      <track kind="captions" />
    </video>
  );
}

const DJHero = () => {
  const heroImage = siteMedia('images/pages/home/hero.jpg');
  const [heroScratch, setHeroScratch] = useState<DjScratch | null>(null);

  useEffect(() => {
    let isMounted = true;

    getDjScratches()
      .then((response) => {
        if (!isMounted) return;
        setHeroScratch(response.scratches[0] ?? null);
      })
      .catch(() => {
        if (!isMounted) return;
        setHeroScratch(null);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  return (
    <section className="relative min-h-screen flex flex-col overflow-hidden bg-[#0a0a0a]">
        {/* Background image — stronger presence */}
        <div className="absolute inset-0">
          <div className="h-full w-full bg-[radial-gradient(circle_at_70%_35%,rgba(255,26,26,0.28),transparent_34%),linear-gradient(135deg,#171717,#050505_55%,#210606)]" />
          <div className="absolute inset-y-0 right-0 w-full overflow-hidden md:w-[68%]" aria-hidden="true">
            <div
              className="absolute inset-0 scale-105 bg-cover bg-center opacity-70"
              style={{ backgroundImage: `url(${heroImage})` }}
            />
            {heroScratch && <HeroScratchBackground scratch={heroScratch} />}
          </div>
          <div className="absolute inset-0 bg-gradient-to-l from-[#0a0a0a]/10 via-[#0a0a0a]/35 to-[#0a0a0a]" />
          {/* Left-to-right fade so text stays readable */}
          <div className="absolute inset-0 bg-gradient-to-r from-[#0a0a0a] via-[#0a0a0a]/80 to-[#0a0a0a]/20" />
          {/* Bottom fade into next section */}
          <div className="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-transparent to-[#0a0a0a]/30" />
          {/* Red glow on right half */}
          <div className="absolute inset-0 bg-gradient-to-l from-primary/20 via-transparent to-transparent" />
        </div>

        {/* Vertical red slash accent */}
        <div
          className="absolute top-0 bottom-0 w-[3px] bg-primary hidden lg:block"
          style={{ left: 'calc(50% - 225px)', transform: 'skewX(-6deg)', boxShadow: '0 0 40px rgba(255,26,26,0.8), 0 0 80px rgba(255,26,26,0.3)' }}
        />

        {/* Main content — vertically centered, takes up full height */}
        <div className="relative z-10 flex-1 flex items-center">
          <div className="container mx-auto px-4 lg:px-8 py-24 lg:py-32">
            <div className="max-w-5xl">

              {/* Eyebrow */}
              <motion.div
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ duration: 0.3 }}
                className="flex items-center gap-3 mb-6"
              >
                <div className="w-8 h-[2px] bg-primary" />
                <span className="text-primary text-xs font-bold tracking-[0.25em] uppercase" style={{ fontFamily: 'var(--font-heading)' }}>
                  The Premier Underground DJ Battle Platform
                </span>
              </motion.div>

              {/* Headline — bleeds hard left, massive scale */}
              <div className="-ml-1 md:-ml-2">
                {['WHERE', 'DJS GO', 'TO WAR'].map((word, i) => (
                  <motion.div
                    key={word}
                    custom={i}
                    initial="hidden"
                    animate="visible"
                    variants={slamIn}
                  >
                    <span
                      className="block text-white leading-[0.88] uppercase"
                      style={{
                        fontFamily: 'var(--font-heading)',
                        fontSize: 'clamp(4.5rem, 15vw, 12rem)',
                        letterSpacing: '-0.03em',
                        textShadow: i === 2 ? '0 0 80px rgba(255,26,26,0.5)' : 'none',
                        color: i === 2 ? '#FF1A1A' : '#ffffff',
                      }}
                    >
                      {word}
                    </span>
                  </motion.div>
                ))}
              </div>

              {/* Divider + subtext row */}
              <motion.div
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.38, duration: 0.35 }}
                className="mt-8 flex items-start gap-5"
              >
                <div className="w-0.75 self-stretch bg-primary shrink-0 mt-1" />
                <p className="text-[#aaaaaa] text-base md:text-lg max-w-md leading-relaxed">
                  Compete head-to-head, post your mixes, get rated by the culture, and shop the gear that moves the crowd.
                </p>
              </motion.div>

              {/* CTAs */}
              <motion.div
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.5, duration: 0.3 }}
                className="flex flex-wrap gap-3 mt-8"
              >
                <Link
                  to="/battles"
                  className="inline-flex items-center gap-2 px-8 py-4 bg-primary text-white font-bold tracking-widest uppercase text-sm hover:bg-primary/90 transition-colors"
                  style={{ fontFamily: 'var(--font-heading)', paddingLeft: '2.0rem' }}
                >
                  <Zap size={16} />
                  ENTER A BATTLE
                </Link>
                <Link
                  to="/mixes"
                  className="inline-flex items-center gap-2 px-8 py-4 bg-transparent border border-[#444444] text-[#cccccc] font-bold tracking-widest uppercase text-sm hover:border-white hover:text-white transition-colors"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <Play size={16} />
                  POST YOUR MIX
                </Link>
              </motion.div>
            </div>
          </div>
        </div>

        {/* Stats bar — anchored to bottom, part of the hero */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.65, duration: 0.5 }}
          className="relative z-10 border-t border-[#ffffff10] bg-[#0a0a0a]/70 "
        >
          <div className="container mx-auto px-4 lg:px-8">
            <div className="flex flex-wrap items-stretch divide-x divide-[#ffffff10]">
              {[
                { icon: <Users size={15} />, stat: '247', label: 'DJs Active' },
                { icon: <Zap size={15} />, stat: '18', label: 'Battles Live' },
                { icon: <Star size={15} />, stat: '4,312', label: 'Mixes Rated' },
                { icon: <Trophy size={15} />, stat: '89', label: 'Champions Crowned' },
              ].map(({ icon, stat, label }) => (
                <div key={label} className="flex items-center gap-3 px-6 py-5 first:pl-0">
                  <span className="text-primary shrink-0">{icon}</span>
                  <div>
                    <p className="text-white font-bold text-lg leading-none" style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.03em' }}>{stat}</p>
                    <p className="text-[#555555] text-[10px] uppercase tracking-widest mt-0.5">{label}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </motion.div>
      </section>
  )
}

export default DJHero
