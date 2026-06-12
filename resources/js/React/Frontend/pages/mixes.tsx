import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { CalendarDays, Disc3, Eye, Headphones, Play, Radio, Star } from 'lucide-react';

import StarRatingContainer from '@/components/StarRatingContainer';
import HeaderTitle from '@/layouts/HeaderTitle';
import { useAuth } from '@/components/auth/AuthProvider';
import { usePlayer } from '@/components/player/PlayerProvider';
import { getMixesIndex, trackMixPlay, type MixesIndexResponse, type PublicMix } from '@/lib/mixes';

const emptyStats = {
  featured_mixes: 0,
  total_plays: 0,
  average_rating: 0,
  genre_count: 0,
};

function formatNumber(value: number): string {
  return new Intl.NumberFormat('en', { notation: value >= 10000 ? 'compact' : 'standard' }).format(value);
}

function formatDuration(seconds?: number | null): string {
  if (!seconds) return 'Runtime TBD';

  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;
  return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

function formatDate(value?: string | null): string {
  if (!value) return 'Unpublished';

  return new Intl.DateTimeFormat('en', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(value));
}

function CoverArt({ mix, compact = false }: { mix: PublicMix; compact?: boolean }) {
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
    <div className="absolute inset-0 bg-[radial-gradient(circle_at_55%_30%,rgba(255,184,0,0.22),transparent_35%),linear-gradient(135deg,#1b1b1b,#070707)]">
      <Disc3
        size={compact ? 42 : 64}
        className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-[#FFB800]/60"
      />
    </div>
  );
}

function PlayButton({ mix, onPlay }: { mix: PublicMix; onPlay: (mix: PublicMix) => void }) {
  return (
    <button
      type="button"
      onClick={() => onPlay(mix)}
      className="inline-flex h-11 w-11 items-center justify-center rounded-full bg-[#FFB800] text-[#0a0a0a] transition-transform hover:scale-105"
      aria-label={`Play ${mix.title}`}
    >
      <Play size={17} fill="currentColor" />
    </button>
  );
}

function FeaturedMixCard({ mix, onPlay }: { mix: PublicMix; onPlay: (mix: PublicMix) => void }) {
  return (
    <article className="group grid overflow-hidden border border-[#2a2a2a] bg-[#111] md:grid-cols-[0.9fr_1.1fr]">
      <div className="relative min-h-[260px] overflow-hidden">
        <CoverArt mix={mix} />
        <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />
        <div className="absolute left-5 top-5 border border-[#FFB800]/50 bg-black/50 px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
          Featured
        </div>
      </div>

      <div className="flex flex-col justify-between p-6">
        <div>
          <div className="mb-4 flex items-center justify-between gap-4">
            <span className="text-xs font-bold uppercase tracking-[0.25em] text-[#FFB800]">{mix.genre || 'Open Format'}</span>
            <StarRatingContainer rating={Math.round(mix.rating_average)} animated />
          </div>
          <h3 className="text-4xl uppercase leading-none text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {mix.title}
          </h3>
          <p className="mt-2 text-sm text-[#888]">by {mix.dj.name}</p>
          <p className="mt-5 line-clamp-3 text-sm leading-6 text-[#c8c8c8]">
            {mix.description || 'A public BlendBeats mix ready for the community to hear and rate.'}
          </p>
        </div>

        <div className="mt-8 flex flex-wrap items-center gap-3">
          <PlayButton mix={mix} onPlay={onPlay} />
          <Link
            to={`/mixes/${mix.slug}`}
            className="inline-flex items-center gap-2 border border-[#444] px-4 py-3 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:border-[#FFB800] hover:text-[#FFB800]"
          >
            <Eye size={14} />
            View Details
          </Link>
          <span className="text-xs text-[#777]">{formatNumber(mix.play_count)} plays</span>
        </div>
      </div>
    </article>
  );
}

function MixCard({ mix, onPlay }: { mix: PublicMix; onPlay: (mix: PublicMix) => void }) {
  return (
    <article id={`mix-${mix.id}`} className="group overflow-hidden border border-[#242424] bg-[#121212] transition-colors hover:border-[#FFB800]/60">
      <div className="relative aspect-square overflow-hidden">
        <CoverArt mix={mix} />
        <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/10 to-transparent opacity-90" />
        <div className="absolute bottom-4 right-4">
          <PlayButton mix={mix} onPlay={onPlay} />
        </div>
      </div>

      <div className="p-5">
        <div className="mb-3 flex items-center justify-between gap-3">
          <span className="text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">{mix.genre || 'Open Format'}</span>
          <span className="text-xs text-[#777]">{formatDuration(mix.duration)}</span>
        </div>
        <h3 className="text-2xl uppercase leading-tight text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          {mix.title}
        </h3>
        <p className="mt-1 text-sm text-[#888]">by {mix.dj.name}</p>

        <div className="mt-5 flex items-center justify-between gap-3">
          <StarRatingContainer rating={Math.round(mix.rating_average)} />
          <span className="text-xs text-[#777]">{formatNumber(mix.play_count)} plays</span>
        </div>

        <div className="mt-4 flex items-center gap-2 border-t border-[#242424] pt-4 text-xs text-[#777]">
          <CalendarDays size={14} className="text-[#FFB800]" />
          {formatDate(mix.published_at || mix.created_at)}
        </div>
      </div>
    </article>
  );
}

function GenreRow({ genre, mixes, onPlay }: { genre: string; mixes: PublicMix[]; onPlay: (mix: PublicMix) => void }) {
  return (
    <section>
      <div className="mb-4 flex items-center justify-between">
        <h3 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          {genre}
        </h3>
        <span className="text-xs uppercase tracking-widest text-[#777]">{mixes.length} mixes</span>
      </div>
      <div className="flex gap-4 overflow-x-auto pb-3" style={{ scrollbarWidth: 'none' }}>
        {mixes.map((mix) => (
          <article key={mix.id} className="min-w-[240px] border border-[#242424] bg-[#111]">
            <div className="relative aspect-[4/3] overflow-hidden">
              <CoverArt mix={mix} compact />
              <div className="absolute inset-0 bg-gradient-to-t from-black/85 to-transparent" />
              <div className="absolute bottom-3 right-3">
                <PlayButton mix={mix} onPlay={onPlay} />
              </div>
            </div>
            <div className="p-4">
              <h4 className="text-xl uppercase leading-tight text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {mix.title}
              </h4>
              <p className="mt-1 text-xs text-[#888]">by {mix.dj.name}</p>
              <div className="mt-3 flex items-center justify-between text-xs text-[#777]">
                <span>{formatNumber(mix.play_count)} plays</span>
                <span>{formatDuration(mix.duration)}</span>
              </div>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}

export default function MixesPage() {
  const { user } = useAuth();
  const { playTrack } = usePlayer();
  const [data, setData] = useState<MixesIndexResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    getMixesIndex()
      .then((response) => {
        if (!isMounted) return;
        setData(response);
        setError(null);
      })
      .catch(() => {
        if (!isMounted) return;
        setError('Unable to load public mixes right now.');
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const stats = data?.stats ?? emptyStats;
  const hasMixes = Boolean(data?.mixes.length);
  const isDj = Boolean(user?.dj_profile);

  const statCards = useMemo(
    () => [
      { label: 'Featured Mixes', value: formatNumber(stats.featured_mixes), icon: Star },
      { label: 'Total Plays', value: formatNumber(stats.total_plays), icon: Headphones },
      { label: 'Avg Rating', value: stats.average_rating ? stats.average_rating.toFixed(1) : '0.0', icon: Radio },
      { label: 'Genres', value: formatNumber(stats.genre_count), icon: Disc3 },
    ],
    [stats],
  );

  const handlePlay = async (mix: PublicMix) => {
    if (mix.audio_url) {
      playTrack({
        id: `mix-${mix.id}`,
        title: mix.title,
        artist: mix.dj.name,
        src: mix.audio_url,
        artwork: mix.cover_image_url,
        meta: mix.genre || 'Mix',
      });
    }

    try {
      const playCount = await trackMixPlay(mix.slug);
      setData((current) => {
        if (!current) return current;

        const updateMix = (item: PublicMix) => (item.id === mix.id ? { ...item, play_count: playCount } : item);

        return {
          ...current,
          stats: {
            ...current.stats,
            total_plays: current.stats.total_plays + Math.max(0, playCount - mix.play_count),
          },
          featured: current.featured.map(updateMix),
          mixes: current.mixes.map(updateMix),
          genres: current.genres.map((row) => ({ ...row, mixes: row.mixes.map(updateMix) })),
        };
      });
    } catch {
      setError('The mix is available, but play tracking failed.');
    }
  };

  return (
    <>
      <HeaderTitle
        title="Mixes | BlendBeats"
        description="Browse public DJ mixes, featured sets, genre rows, ratings, play counts, and fresh published uploads on BlendBeats."
      />

      <main className="bg-[#0a0a0a] text-white">
        <section className="border-b border-[#1f1f1f]">
          <div className="container mx-auto grid min-h-[54vh] grid-cols-1 gap-10 px-4 py-16 md:grid-cols-[1.05fr_0.95fr] md:items-center md:py-24">
            <div>
              <p className="mb-3 text-xs font-bold uppercase tracking-[0.3em] text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                Listen and rate
              </p>
              <h1 className="max-w-4xl uppercase leading-none" style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 10vw, 8rem)' }}>
                Mixes
              </h1>
              <p className="mt-6 max-w-2xl text-base leading-7 text-[#c8c8c8] md:text-lg">
                Browse public DJ mixes, discover genre lanes, and follow the sets earning plays and ratings from the BlendBeats community.
              </p>
            </div>

            <div className="grid grid-cols-2 border border-[#2a2a2a] bg-[#111]">
              {statCards.map(({ label, value, icon: Icon }) => (
                <div key={label} className="border-b border-r border-[#2a2a2a] p-5 md:p-6">
                  <Icon size={20} className="mb-4 text-[#FFB800]" />
                  <p className="text-3xl font-black text-[#FFB800] md:text-5xl" style={{ fontFamily: 'var(--font-heading)' }}>
                    {isLoading ? '...' : value}
                  </p>
                  <p className="mt-2 text-xs uppercase tracking-widest text-[#888]" style={{ fontFamily: 'var(--font-heading)' }}>
                    {label}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {error && (
          <div className="container mx-auto px-4 pt-8">
            <div className="border border-primary/40 bg-primary/10 px-4 py-3 text-sm text-white">{error}</div>
          </div>
        )}

        {isLoading ? (
          <section className="container mx-auto px-4 py-20">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
              {[0, 1, 2].map((item) => (
                <div key={item} className="h-[360px] animate-pulse border border-[#222] bg-[#111]" />
              ))}
            </div>
          </section>
        ) : !hasMixes ? (
          <section className="container mx-auto px-4 py-24">
            <div className="mx-auto max-w-2xl border border-[#2a2a2a] bg-[#111] p-8 text-center">
              <Disc3 size={44} className="mx-auto text-[#FFB800]" />
              <h2 className="mt-6 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                No public mixes are available yet.
              </h2>
              {isDj && (
                <Link
                  to="/dj/portfolio"
                  className="mt-8 inline-flex items-center gap-2 bg-[#FFB800] px-6 py-4 text-xs font-bold uppercase tracking-widest text-[#0a0a0a] transition-opacity hover:opacity-90"
                >
                  <Play size={15} />
                  Upload Your First Mix
                </Link>
              )}
            </div>
          </section>
        ) : (
          <>
            {Boolean(data?.featured.length) && (
              <section className="container mx-auto px-4 py-16 md:py-20">
                <div className="mb-8">
                  <p className="text-xs font-bold uppercase tracking-[0.3em] text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                    Featured Mixes
                  </p>
                  <h2 className="mt-2 text-5xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Editor Picks
                  </h2>
                </div>
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                  {data?.featured.map((mix) => (
                    <FeaturedMixCard key={mix.id} mix={mix} onPlay={handlePlay} />
                  ))}
                </div>
              </section>
            )}

            <section className="border-t border-[#1f1f1f] py-16 md:py-20">
              <div className="container mx-auto px-4">
                <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                  <div>
                    <p className="text-xs font-bold uppercase tracking-[0.3em] text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                      Public Mix Grid
                    </p>
                    <h2 className="mt-2 text-5xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Latest Public Uploads
                    </h2>
                  </div>
                  <span className="text-sm text-[#888]">{data?.mixes.length} public mixes</span>
                </div>
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                  {data?.mixes.map((mix) => (
                    <MixCard key={mix.id} mix={mix} onPlay={handlePlay} />
                  ))}
                </div>
              </div>
            </section>

            {Boolean(data?.genres.length) && (
              <section className="border-t border-[#1f1f1f] py-16 md:py-20">
                <div className="container mx-auto space-y-12 px-4">
                  <div>
                    <p className="text-xs font-bold uppercase tracking-[0.3em] text-[#FFB800]" style={{ fontFamily: 'var(--font-heading)' }}>
                      Genre Rows
                    </p>
                    <h2 className="mt-2 text-5xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Browse By Sound
                    </h2>
                  </div>
                  {data?.genres.map((row) => (
                    <GenreRow key={row.genre} genre={row.genre} mixes={row.mixes} onPlay={handlePlay} />
                  ))}
                </div>
              </section>
            )}
          </>
        )}
      </main>
    </>
  );
}
