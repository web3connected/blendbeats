import { motion } from 'motion/react';
import { Link } from 'react-router-dom';
import { Star, Play, Users, Zap, ShoppingBag, ChevronRight, Volume2, Trophy } from 'lucide-react';
import { Helmet } from '@dr.pogodin/react-helmet';

// ─── Animation Variants ────────────────────────────────────────────────────
const fadeUp = {
  hidden: { opacity: 0, y: 40 },
  visible: (i: number) => ({
    opacity: 1,
    y: 0,
    transition: { duration: 0.4, delay: i * 0.08, ease: 'easeOut' as const },
  }),
};

const slamIn = {
  hidden: { opacity: 0, scale: 1.15 },
  visible: (i: number) => ({
    opacity: 1,
    scale: 1,
    transition: { duration: 0.25, delay: i * 0.07, ease: 'easeOut' as const },
  }),
};

// ─── Star Rating ────────────────────────────────────────────────────────────
function StarRating({ rating, animated = false }: { rating: number; animated?: boolean }) {
  return (
    <div className="flex gap-0.5">
      {[1, 2, 3, 4, 5].map((star) => (
        <motion.div
          key={star}
          initial={animated ? { opacity: 0, scale: 0 } : false}
          whileInView={animated ? { opacity: 1, scale: 1 } : undefined}
          viewport={{ once: true }}
          transition={{ delay: star * 0.06, duration: 0.2, ease: 'backOut' as const }}
        >
          <Star
            size={14}
            className={star <= rating ? 'text-[#FFB800] fill-[#FFB800]' : 'text-[#333333]'}
          />
        </motion.div>
      ))}
    </div>
  );
}

// ─── Waveform SVG ───────────────────────────────────────────────────────────
function Waveform() {
  const bars = [3, 8, 5, 12, 7, 15, 9, 6, 14, 10, 4, 11, 8, 13, 6, 9, 12, 5, 10, 7];
  return (
    <div className="flex items-end gap-px h-8 opacity-30">
      {bars.map((h, i) => (
        <div
          key={i}
          className="w-1 bg-primary rounded-sm"
          style={{ height: `${h * 2}px` }}
        />
      ))}
    </div>
  );
}

// ─── Battle Card ─────────────────────────────────────────────────────────────
function BattleCard({
  dj1,
  dj2,
  genre,
  votes1,
  votes2,
  live,
  delay,
}: {
  dj1: string;
  dj2: string;
  genre: string;
  votes1: number;
  votes2: number;
  live: boolean;
  delay: number;
}) {
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
      {/* Header */}
      <div className="flex items-center justify-between">
        <span className="text-[10px] font-bold tracking-widest text-[#888888] uppercase" style={{ fontFamily: 'var(--font-heading)' }}>
          {genre}
        </span>
        {live && (
          <span className="flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-primary uppercase" style={{ fontFamily: 'var(--font-heading)' }}>
            <span className="w-1.5 h-1.5 rounded-full bg-primary animate-pulse" />
            LIVE
          </span>
        )}
      </div>

      {/* VS Layout */}
      <div className="flex items-center gap-3">
        {/* DJ 1 */}
        <div className="flex-1 text-center">
          <div className="w-12 h-12 rounded-full bg-[#1f1f1f] border-2 border-primary mx-auto mb-2 flex items-center justify-center">
            <Volume2 size={18} className="text-primary" />
          </div>
          <p className="text-white text-sm font-bold truncate" style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.05em' }}>{dj1}</p>
          <p className="text-[#888888] text-xs mt-0.5">{votes1.toLocaleString()} votes</p>
        </div>

        {/* VS */}
        <div className="flex flex-col items-center">
          <span className="text-2xl font-black text-primary" style={{ fontFamily: 'var(--font-heading)' }}>VS</span>
        </div>

        {/* DJ 2 */}
        <div className="flex-1 text-center">
          <div className="w-12 h-12 rounded-full bg-[#1f1f1f] border-2 border-[#FFB800] mx-auto mb-2 flex items-center justify-center">
            <Volume2 size={18} className="text-[#FFB800]" />
          </div>
          <p className="text-white text-sm font-bold truncate" style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.05em' }}>{dj2}</p>
          <p className="text-[#888888] text-xs mt-0.5">{votes2.toLocaleString()} votes</p>
        </div>
      </div>

      {/* Vote Bar */}
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

// ─── Mix Card ────────────────────────────────────────────────────────────────
function MixCard({
  djName,
  title,
  genre,
  rating,
  plays,
  delay,
}: {
  djName: string;
  title: string;
  genre: string;
  rating: number;
  plays: string;
  delay: number;
}) {
  return (
    <motion.div
      custom={delay}
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true }}
      variants={fadeUp}
      whileHover={{ y: -4, boxShadow: '0 0 20px rgba(255,26,26,0.2)' }}
      className="bg-[#141414] border border-[#2a2a2a] hover:border-primary/50 transition-all duration-200 p-4 min-w-[220px] flex flex-col gap-3"
    >
      <div className="bg-[#0a0a0a] border border-[#1f1f1f] p-3 flex items-end justify-between">
        <Waveform />
        <button className="w-8 h-8 rounded-full bg-primary flex items-center justify-center hover:bg-primary/80 transition-colors shrink-0 ml-2">
          <Play size={12} className="text-white fill-white ml-0.5" />
        </button>
      </div>
      <div>
        <p className="text-white text-sm font-bold leading-tight" style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.03em' }}>{title}</p>
        <p className="text-[#888888] text-xs mt-0.5">{djName}</p>
      </div>
      <div className="flex items-center justify-between">
        <StarRating rating={rating} animated />
        <span className="text-[10px] text-[#555555]">{plays} plays</span>
      </div>
      <span className="inline-block text-[10px] font-bold tracking-widest text-[#FFB800] uppercase border border-[#FFB800]/30 px-2 py-0.5 w-fit" style={{ fontFamily: 'var(--font-heading)' }}>
        {genre}
      </span>
    </motion.div>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function HomePage() {
  const battles = [
    { dj1: 'DJ KROME', dj2: 'SPINMASTER X', genre: 'Hip-Hop', votes1: 1842, votes2: 1203, live: true },
    { dj1: 'LADY FREQ', dj2: 'BASS PROPHET', genre: 'House', votes1: 987, votes2: 1456, live: true },
    { dj1: 'VINYL KING', dj2: 'DJ STATIC', genre: 'Drum & Bass', votes1: 2310, votes2: 2188, live: false },
  ];

  const mixes = [
    { djName: 'DJ KROME', title: 'UNDERGROUND SESSIONS VOL.7', genre: 'Hip-Hop', rating: 5, plays: '12.4K' },
    { djName: 'LADY FREQ', title: 'DEEP HOUSE CHRONICLES', genre: 'House', rating: 4, plays: '8.7K' },
    { djName: 'BASS PROPHET', title: 'BASS HEAVY RITUAL', genre: 'Drum & Bass', rating: 5, plays: '21.1K' },
    { djName: 'VINYL KING', title: 'WAX ON WAX OFF', genre: 'Scratch', rating: 4, plays: '6.3K' },
    { djName: 'DJ STATIC', title: 'FREQUENCY WARS EP.3', genre: 'Techno', rating: 5, plays: '9.8K' },
  ];

  return (
    <>
      <Helmet>
        <title>The Blend Battlegrounds — Where DJs Go To War</title>
        <meta name="description" content="The premier underground DJ battle platform. Head-to-head battles, mix ratings, DJ gear and merch. Join the culture." />
      </Helmet>

      {/* ── HERO ─────────────────────────────────────────────────────────── */}
      <section className="relative min-h-screen flex flex-col overflow-hidden bg-[#0a0a0a]">
        {/* Background image — stronger presence */}
        <div className="absolute inset-0">
          <img
            src="/airo-assets/images/pages/home/hero"
            alt=""
            className="w-full h-full object-cover object-center opacity-55"
          />
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
          style={{ left: '55%', transform: 'skewX(-6deg)', boxShadow: '0 0 40px rgba(255,26,26,0.8), 0 0 80px rgba(255,26,26,0.3)' }}
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
                <div className="w-[3px] self-stretch bg-primary shrink-0 mt-1" />
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
                  style={{ fontFamily: 'var(--font-heading)' }}
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
          className="relative z-10 border-t border-[#ffffff10] bg-[#0a0a0a]/70 backdrop-blur-md"
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

      {/* ── LIVE BATTLES ─────────────────────────────────────────────────── */}
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

      {/* ── TOP RATED MIXES ───────────────────────────────────────────────── */}
      <section className="py-20 bg-[#0d0d0d] border-t border-[#1a1a1a]">
        <div className="container mx-auto px-4">
          <div className="flex items-end justify-between mb-10">
            <div>
              <motion.p
                initial={{ opacity: 0 }}
                whileInView={{ opacity: 1 }}
                viewport={{ once: true }}
                className="text-[#FFB800] text-xs font-bold tracking-widest uppercase mb-2"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                ★ COMMUNITY RATED
              </motion.p>
              <motion.h2
                initial={{ opacity: 0, x: -30 }}
                whileInView={{ opacity: 1, x: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.4 }}
                className="text-white uppercase"
                style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(2.5rem, 6vw, 5rem)', letterSpacing: '-0.01em', lineHeight: 1 }}
              >
                TOP MIXES
              </motion.h2>
            </div>
            <Link
              to="/mixes"
              className="hidden md:flex items-center gap-2 text-[#FFB800] text-xs font-bold tracking-widest uppercase hover:gap-3 transition-all"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              ALL MIXES <ChevronRight size={14} />
            </Link>
          </div>

          {/* Horizontal scroll */}
          <div className="flex gap-4 overflow-x-auto pb-4 scrollbar-hide" style={{ scrollbarWidth: 'none' }}>
            {mixes.map((m, i) => (
              <MixCard key={i} {...m} delay={i} />
            ))}
          </div>
        </div>
      </section>

      {/* ── SHOP THE CULTURE ─────────────────────────────────────────────── */}
      <section className="py-20 bg-[#0a0a0a] border-t border-[#1a1a1a]">
        <div className="container mx-auto px-4">
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.4 }}
            className="text-center mb-12"
          >
            <p className="text-primary text-xs font-bold tracking-widest uppercase mb-2" style={{ fontFamily: 'var(--font-heading)' }}>
              GEAR UP
            </p>
            <h2
              className="text-white uppercase"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(2.5rem, 7vw, 6rem)', letterSpacing: '-0.01em', lineHeight: 1 }}
            >
              SHOP THE CULTURE
            </h2>
          </motion.div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Merch */}
            <motion.div
              custom={0}
              initial="hidden"
              whileInView="visible"
              viewport={{ once: true }}
              variants={fadeUp}
              className="relative overflow-hidden group"
            >
              <div className="aspect-[4/3] bg-[#141414] border border-[#2a2a2a] overflow-hidden">
                <img
                  src="/airo-assets/images/pages/home/merch-hoodie"
                  alt="Blend Battlegrounds Merch"
                  className="w-full h-full object-cover opacity-60 group-hover:opacity-80 group-hover:scale-105 transition-all duration-500"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/30 to-transparent" />
              </div>
              <div className="absolute bottom-0 left-0 right-0 p-6">
                <p className="text-[#888888] text-xs font-bold tracking-widest uppercase mb-1" style={{ fontFamily: 'var(--font-heading)' }}>
                  REPRESENT THE CULTURE
                </p>
                <h3 className="text-white text-3xl uppercase mb-4" style={{ fontFamily: 'var(--font-heading)', letterSpacing: '-0.01em' }}>
                  MERCH DROP
                </h3>
                <Link
                  to="/merch"
                  className="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white text-xs font-bold tracking-widest uppercase hover:bg-primary/90 transition-colors"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <ShoppingBag size={14} />
                  SHOP MERCH
                </Link>
              </div>
            </motion.div>

            {/* Gear */}
            <motion.div
              custom={1}
              initial="hidden"
              whileInView="visible"
              viewport={{ once: true }}
              variants={fadeUp}
              className="relative overflow-hidden group"
            >
              <div className="aspect-[4/3] bg-[#141414] border border-[#2a2a2a] overflow-hidden">
                <img
                  src="/airo-assets/images/pages/home/dj-mixer"
                  alt="DJ Gear"
                  className="w-full h-full object-cover opacity-60 group-hover:opacity-80 group-hover:scale-105 transition-all duration-500"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/30 to-transparent" />
              </div>
              <div className="absolute bottom-0 left-0 right-0 p-6">
                <p className="text-[#888888] text-xs font-bold tracking-widest uppercase mb-1" style={{ fontFamily: 'var(--font-heading)' }}>
                  TOOLS OF THE TRADE
                </p>
                <h3 className="text-white text-3xl uppercase mb-4" style={{ fontFamily: 'var(--font-heading)', letterSpacing: '-0.01em' }}>
                  DJ GEAR
                </h3>
                <Link
                  to="/gear"
                  className="inline-flex items-center gap-2 px-6 py-3 bg-[#FFB800] text-[#0a0a0a] text-xs font-bold tracking-widest uppercase hover:bg-[#FFB800]/90 transition-colors"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <ShoppingBag size={14} />
                  SHOP GEAR
                </Link>
              </div>
            </motion.div>
          </div>
        </div>
      </section>

      {/* ── TOP DJS LEADERBOARD TEASER ────────────────────────────────────── */}
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

      {/* ── CTA SECTION ──────────────────────────────────────────────────── */}
      <section className="relative py-28 overflow-hidden bg-[#0a0a0a]">
        {/* Diagonal red wash */}
        <div
          className="absolute inset-0"
          style={{
            background: 'linear-gradient(135deg, #FF1A1A 0%, #cc0000 40%, #0a0a0a 70%)',
            clipPath: 'polygon(0 0, 70% 0, 45% 100%, 0 100%)',
          }}
        />
        <div className="absolute inset-0 bg-[#0a0a0a]/60" />

        {/* Background crowd image */}
        <div className="absolute inset-0">
          <img
            src="/airo-assets/images/pages/home/crowd-energy"
            alt=""
            className="w-full h-full object-cover opacity-15"
          />
        </div>

        <div className="relative z-10 container mx-auto px-4 text-center">
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            whileInView={{ opacity: 1, scale: 1 }}
            viewport={{ once: true }}
            transition={{ duration: 0.4 }}
          >
            <h2
              className="text-white uppercase leading-none mb-6"
              style={{
                fontFamily: 'var(--font-heading)',
                fontSize: 'clamp(2.5rem, 9vw, 8rem)',
                letterSpacing: '-0.02em',
                textShadow: '0 0 80px rgba(255,26,26,0.4)',
              }}
            >
              THINK YOU GOT<br />WHAT IT TAKES?
            </h2>
            <p className="text-[#cccccc] text-lg mb-10 max-w-xl mx-auto">
              Join thousands of DJs already battling, posting mixes, and building their reputation on the Battlegrounds.
            </p>
            <Link
              to="/battles"
              className="inline-flex items-center gap-3 px-10 py-5 bg-white text-[#0a0a0a] font-black tracking-widest uppercase text-base hover:bg-[#FFB800] transition-colors"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <Zap size={18} />
              JOIN THE BATTLEGROUNDS
            </Link>
          </motion.div>
        </div>
      </section>
    </>
  );
}
