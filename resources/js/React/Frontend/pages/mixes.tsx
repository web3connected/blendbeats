import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { CalendarDays, ChevronLeft, ChevronRight, Disc3, Eye, Headphones, Heart, ListMusic, Play, Radio, Star } from 'lucide-react';

import HeaderTitle from '@/layouts/HeaderTitle';
import MixesFeaturedDjAdSpaces from '@/components/advertising/MixesFeaturedDjAdSpaces';
import { useAuth } from '@/components/auth/AuthProvider';
import { type PlayerTrack, usePlayer } from '@/components/player/PlayerProvider';
import { useCounter } from '@/hooks/useCounter';
import { getMixesIndex, type MixesIndexResponse, type PublicMix } from '@/lib/mixes';
import { getRatingSummary, rateTarget, type RatingSummary } from '@/lib/ratings';
import { getUserPlaylist, removePlaylistMix, savePlaylistMix } from '@/lib/user-playlist';

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

function mixToPlayerTrack(mix: PublicMix): PlayerTrack {
  return {
    id: `mix-${mix.id}`,
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

function PlayButton({
  mix,
  onPlay,
  compact = false,
}: {
  mix: PublicMix;
  onPlay: (mix: PublicMix) => void;
  compact?: boolean;
}) {
  return (
    <button
      type="button"
      onClick={() => onPlay(mix)}
      className={`${compact ? 'h-9 w-9' : 'h-11 w-11'} inline-flex items-center justify-center rounded-full bg-[#FFB800] text-[#0a0a0a] transition-transform hover:scale-105`}
      aria-label={`Play ${mix.title}`}
    >
      <Play size={compact ? 15 : 17} fill="currentColor" />
    </button>
  );
}

function SaveMixButton({
  mix,
  isSaved,
  isSaving,
  onToggleSaved,
  compact = false,
}: {
  mix: PublicMix;
  isSaved: boolean;
  isSaving: boolean;
  onToggleSaved: (mix: PublicMix) => void;
  compact?: boolean;
}) {
  return (
    <button
      type="button"
      onClick={() => onToggleSaved(mix)}
      disabled={isSaving}
      className={`inline-flex items-center justify-center gap-2 border transition-colors disabled:cursor-wait disabled:opacity-60 ${
        compact ? 'h-9 w-9' : 'h-11 px-4'
      } ${
        isSaved
          ? 'border-primary bg-primary text-white hover:bg-primary/90'
          : 'border-[#444444] text-[#dddddd] hover:border-primary hover:text-primary'
      }`}
      aria-label={`${isSaved ? 'Remove' : 'Save'} ${mix.title} ${isSaved ? 'from' : 'to'} your playlist`}
      title={isSaved ? 'Saved to playlist' : 'Save to playlist'}
    >
      <Heart size={compact ? 15 : 16} fill={isSaved ? 'currentColor' : 'none'} />
      {!compact && (
        <span className="text-xs font-bold uppercase tracking-widest" style={{ fontFamily: 'var(--font-heading)' }}>
          {isSaved ? 'Saved' : 'Save'}
        </span>
      )}
    </button>
  );
}

function MixRatingStars({
  mix,
  canRate,
  onRated,
}: {
  mix: PublicMix;
  canRate: boolean;
  onRated: (mixId: number, rating: RatingSummary) => void;
}) {
  const [summary, setSummary] = useState<RatingSummary>({
    average: mix.rating_average,
    count: mix.rating_count,
    user_rating: null,
    context: 'default',
  });
  const [hoverRating, setHoverRating] = useState<number | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState('');
  const activeRating = hoverRating ?? summary.user_rating ?? Math.round(summary.average);

  useEffect(() => {
    let isMounted = true;

    getRatingSummary('mixes', mix.id)
      .then((rating) => {
        if (!isMounted) return;
        setSummary(rating);
        onRated(mix.id, rating);
      })
      .catch(() => {
        if (!isMounted) return;
        setSummary({
          average: mix.rating_average,
          count: mix.rating_count,
          user_rating: null,
          context: 'default',
        });
      });

    return () => {
      isMounted = false;
    };
  }, [mix.id, mix.rating_average, mix.rating_count, onRated]);

  const submitRating = (rating: number) => {
    if (!canRate) {
      setError('Log in to rate mixes.');
      return;
    }

    setIsSaving(true);
    setError('');

    rateTarget('mixes', mix.id, rating)
      .then((nextSummary) => {
        setSummary(nextSummary);
        onRated(mix.id, nextSummary);
      })
      .catch((ratingError) => {
        setError(ratingError instanceof Error ? ratingError.message : 'Rating could not be saved.');
      })
      .finally(() => setIsSaving(false));
  };

  return (
    <div className="grid gap-1">
      <div className="flex items-center gap-1" onMouseLeave={() => setHoverRating(null)}>
        {[1, 2, 3, 4, 5].map((rating) => (
          <button
            key={rating}
            type="button"
            onMouseEnter={() => setHoverRating(rating)}
            onFocus={() => setHoverRating(rating)}
            onClick={() => submitRating(rating)}
            disabled={isSaving}
            className="text-[#333333] transition-colors hover:text-[#FFB800] disabled:cursor-wait"
            aria-label={`Rate ${mix.title} ${rating} star${rating === 1 ? '' : 's'}`}
          >
            <Star
              size={16}
              className={rating <= activeRating ? 'fill-[#FFB800] text-[#FFB800]' : 'text-[#333333]'}
            />
          </button>
        ))}
      </div>
      <p className="text-[11px] text-[#777777]">
        {summary.count > 0 ? `${summary.average.toFixed(1)} / 5 from ${summary.count} rating${summary.count === 1 ? '' : 's'}` : 'No ratings yet'}
      </p>
      {error && <p className="text-[11px] text-primary">{error}</p>}
    </div>
  );
}

function FeaturedMixCard({
  mix,
  canRate,
  isSaved,
  isSaving,
  onPlay,
  onRated,
  onToggleSaved,
}: {
  mix: PublicMix;
  canRate: boolean;
  isSaved: boolean;
  isSaving: boolean;
  onPlay: (mix: PublicMix) => void;
  onRated: (mixId: number, rating: RatingSummary) => void;
  onToggleSaved: (mix: PublicMix) => void;
}) {
  return (
    <article className="group grid overflow-hidden border border-[#2a2a2a] bg-[#111] md:grid-cols-[0.85fr_1.15fr]">
      <div className="relative min-h-[220px] overflow-hidden">
        <CoverArt mix={mix} />
        <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />
        <div className="absolute left-5 top-5 border border-[#FFB800]/50 bg-black/50 px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
          Featured
        </div>
      </div>

      <div className="flex flex-col justify-between p-5">
        <div>
          <div className="mb-4 flex items-center justify-between gap-4">
            <span className="text-xs font-bold uppercase tracking-[0.25em] text-[#FFB800]">{mix.genre || 'Open Format'}</span>
            <MixRatingStars mix={mix} canRate={canRate} onRated={onRated} />
          </div>
          <h3 className="text-3xl uppercase leading-none text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {mix.title}
          </h3>
          <p className="mt-2 text-sm text-[#888]">by {mix.dj.name}</p>
          <p className="mt-5 line-clamp-3 text-sm leading-6 text-[#c8c8c8]">
            {mix.description || 'A public BlendBeats mix ready for the community to hear and rate.'}
          </p>
        </div>

        <div className="mt-6 flex flex-wrap items-center gap-3">
          <PlayButton mix={mix} onPlay={onPlay} />
          <SaveMixButton mix={mix} isSaved={isSaved} isSaving={isSaving} onToggleSaved={onToggleSaved} />
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

function MixCard({
  mix,
  canRate,
  isSaved,
  isSaving,
  onPlay,
  onRated,
  onToggleSaved,
}: {
  mix: PublicMix;
  canRate: boolean;
  isSaved: boolean;
  isSaving: boolean;
  onPlay: (mix: PublicMix) => void;
  onRated: (mixId: number, rating: RatingSummary) => void;
  onToggleSaved: (mix: PublicMix) => void;
}) {
  return (
    <article id={`mix-${mix.id}`} className="group overflow-hidden border border-[#242424] bg-[#121212] transition-colors hover:border-[#FFB800]/60">
      <div className="relative aspect-[4/3] overflow-hidden">
        <CoverArt mix={mix} compact />
        <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/10 to-transparent opacity-90" />
        <div className="absolute bottom-3 right-3">
          <PlayButton mix={mix} onPlay={onPlay} compact />
        </div>
        <div className="absolute left-3 top-3">
          <SaveMixButton mix={mix} isSaved={isSaved} isSaving={isSaving} onToggleSaved={onToggleSaved} compact />
        </div>
      </div>

      <div className="p-4">
        <div className="mb-2 flex items-center justify-between gap-3">
          <span className="text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">{mix.genre || 'Open Format'}</span>
          <span className="text-xs text-[#777]">{formatDuration(mix.duration)}</span>
        </div>
        <h3 className="text-xl uppercase leading-tight text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          {mix.title}
        </h3>
        <p className="mt-1 text-xs text-[#888]">by {mix.dj.name}</p>

        <div className="mt-4 flex items-center justify-between gap-3">
          <MixRatingStars mix={mix} canRate={canRate} onRated={onRated} />
          <span className="text-xs text-[#777]">{formatNumber(mix.play_count)} plays</span>
        </div>

        <div className="mt-3 flex items-center gap-2 border-t border-[#242424] pt-3 text-xs text-[#777]">
          <CalendarDays size={13} className="text-[#FFB800]" />
          {formatDate(mix.published_at || mix.created_at)}
        </div>
      </div>
    </article>
  );
}

function GenreRow({
  genre,
  mixes,
  savedMixIds,
  savingMixIds,
  onPlay,
  onToggleSaved,
}: {
  genre: string;
  mixes: PublicMix[];
  savedMixIds: Set<number>;
  savingMixIds: Set<number>;
  onPlay: (mix: PublicMix) => void;
  onToggleSaved: (mix: PublicMix) => void;
}) {
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
          <article key={mix.id} className="min-w-[210px] border border-[#242424] bg-[#111]">
            <div className="relative aspect-[4/3] overflow-hidden">
              <CoverArt mix={mix} compact />
              <div className="absolute inset-0 bg-gradient-to-t from-black/85 to-transparent" />
              <div className="absolute bottom-3 right-3">
                <PlayButton mix={mix} onPlay={onPlay} compact />
              </div>
              <div className="absolute left-3 top-3">
                <SaveMixButton
                  mix={mix}
                  isSaved={savedMixIds.has(mix.id)}
                  isSaving={savingMixIds.has(mix.id)}
                  onToggleSaved={onToggleSaved}
                  compact
                />
              </div>
            </div>
            <div className="p-3">
              <h4 className="text-lg uppercase leading-tight text-white" style={{ fontFamily: 'var(--font-heading)' }}>
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
  const { playTrack, updateCurrentTrack, loadQueue } = usePlayer();
  const { count: countTarget } = useCounter();
  const [data, setData] = useState<MixesIndexResponse | null>(null);
  const [savedPlaylist, setSavedPlaylist] = useState<PublicMix[]>([]);
  const [savingMixIds, setSavingMixIds] = useState<Set<number>>(() => new Set());
  const [isPlaylistLoading, setIsPlaylistLoading] = useState(false);
  const [playlistMessage, setPlaylistMessage] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    setIsLoading(true);

    getMixesIndex(currentPage)
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
  }, [currentPage]);

  useEffect(() => {
    let isMounted = true;

    if (!user) {
      setSavedPlaylist([]);
      setPlaylistMessage('');
      return () => {
        isMounted = false;
      };
    }

    setIsPlaylistLoading(true);

    getUserPlaylist()
      .then((response) => {
        if (!isMounted) return;
        setSavedPlaylist(response.playlist.map((item) => item.mix));
      })
      .catch(() => {
        if (!isMounted) return;
        setPlaylistMessage('Your saved playlist could not be loaded right now.');
      })
      .finally(() => {
        if (isMounted) setIsPlaylistLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [user]);

  const stats = data?.stats ?? emptyStats;
  const hasMixes = Boolean(data?.mixes.length);
  const pagination = data?.pagination;
  const hasPagination = Boolean(pagination && pagination.last_page > 1);
  const isDj = Boolean(user?.dj_profile);
  const canRate = Boolean(user);
  const savedMixIds = useMemo(() => new Set(savedPlaylist.map((mix) => mix.id)), [savedPlaylist]);
  const playableSavedPlaylist = useMemo(
    () => savedPlaylist.filter((mix) => Boolean(mix.audio_url)),
    [savedPlaylist],
  );

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
      playTrack(mixToPlayerTrack(mix));
    }

    try {
      const counter = await countTarget({ type: 'mixes', id: mix.slug, action: 'play' });
      const playCount = counter.count;

      updateCurrentTrack({
        countLabel: counter.label,
        countValue: playCount,
      });

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

  const handleToggleSaved = async (mix: PublicMix) => {
    if (!user) {
      setPlaylistMessage('Log in to save mixes to your playlist.');
      return;
    }

    setPlaylistMessage('');
    setSavingMixIds((current) => new Set(current).add(mix.id));

    try {
      if (savedMixIds.has(mix.id)) {
        await removePlaylistMix(mix.id);
        setSavedPlaylist((current) => current.filter((item) => item.id !== mix.id));
        setPlaylistMessage('Removed from your playlist.');
      } else {
        const response = await savePlaylistMix(mix.id);
        setSavedPlaylist((current) => {
          if (current.some((item) => item.id === response.item.mix.id)) return current;
          return [...current, response.item.mix];
        });
        setPlaylistMessage('Saved to your playlist.');
      }
    } catch (saveError) {
      setPlaylistMessage(saveError instanceof Error ? saveError.message : 'Playlist could not be updated.');
    } finally {
      setSavingMixIds((current) => {
        const next = new Set(current);
        next.delete(mix.id);
        return next;
      });
    }
  };

  const handlePlaySavedPlaylist = () => {
    if (!user) {
      setPlaylistMessage('Log in to play your saved playlist.');
      return;
    }

    if (playableSavedPlaylist.length === 0) {
      setPlaylistMessage('Save a playable mix first.');
      return;
    }

    loadQueue({
      tracks: playableSavedPlaylist.map(mixToPlayerTrack),
      autoplay: true,
    });
    setPlaylistMessage(`Playing ${playableSavedPlaylist.length} saved mix${playableSavedPlaylist.length === 1 ? '' : 'es'}.`);
  };

  const goToMixPage = (page: number) => {
    const nextPage = Math.max(1, Math.min(page, pagination?.last_page ?? page));
    setCurrentPage(nextPage);
  };

  const handleRatingUpdate = useCallback((mixId: number, rating: RatingSummary) => {
    setData((current) => {
      if (!current) return current;

      const updateMix = (item: PublicMix) =>
        item.id === mixId ? { ...item, rating_average: rating.average, rating_count: rating.count } : item;
      const nextMixes = current.mixes.map(updateMix);
      const ratedMixes = nextMixes.filter((mix) => mix.rating_count > 0);

      return {
        ...current,
        stats: {
          ...current.stats,
          average_rating: ratedMixes.length
            ? Math.round((ratedMixes.reduce((total, mix) => total + mix.rating_average, 0) / ratedMixes.length) * 10) / 10
            : 0,
        },
        featured: current.featured.map(updateMix),
        mixes: nextMixes,
        genres: current.genres.map((row) => ({ ...row, mixes: row.mixes.map(updateMix) })),
      };
    });
  }, []);

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
              <div className="mt-8 flex flex-wrap items-center gap-3">
                <button
                  type="button"
                  onClick={handlePlaySavedPlaylist}
                  disabled={Boolean(user) && (isPlaylistLoading || playableSavedPlaylist.length === 0)}
                  className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-55"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <ListMusic size={16} />
                  Play My Playlist
                </button>
                <span className="text-sm text-[#999999]">
                  {user
                    ? isPlaylistLoading
                      ? 'Loading saved playlist'
                      : `${savedPlaylist.length} saved mix${savedPlaylist.length === 1 ? '' : 'es'}`
                    : 'Log in to save and play your own playlist'}
                </span>
              </div>
              {playlistMessage && (
                <p className="mt-3 max-w-md text-sm text-[#FFB800]">{playlistMessage}</p>
              )}
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

        <MixesFeaturedDjAdSpaces />

        {isLoading ? (
          <section className="container mx-auto px-4 py-20">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
              {[0, 1, 2].map((item) => (
                <div key={item} className="h-[300px] animate-pulse border border-[#222] bg-[#111]" />
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
                    <FeaturedMixCard
                      key={mix.id}
                      mix={mix}
                      canRate={canRate}
                      isSaved={savedMixIds.has(mix.id)}
                      isSaving={savingMixIds.has(mix.id)}
                      onPlay={handlePlay}
                      onRated={handleRatingUpdate}
                      onToggleSaved={handleToggleSaved}
                    />
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
                  <span className="text-sm text-[#888]">
                    {pagination?.total
                      ? `${pagination.from ?? 0}-${pagination.to ?? 0} of ${pagination.total} public mixes`
                      : `${data?.mixes.length ?? 0} public mixes`}
                  </span>
                </div>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                  {data?.mixes.map((mix) => (
                    <MixCard
                      key={mix.id}
                      mix={mix}
                      canRate={canRate}
                      isSaved={savedMixIds.has(mix.id)}
                      isSaving={savingMixIds.has(mix.id)}
                      onPlay={handlePlay}
                      onRated={handleRatingUpdate}
                      onToggleSaved={handleToggleSaved}
                    />
                  ))}
                </div>

                {hasPagination && pagination && (
                  <div className="mt-8 flex flex-col gap-4 border-t border-[#1f1f1f] pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-[#888888]">
                      Page {pagination.current_page.toLocaleString()} of {pagination.last_page.toLocaleString()}
                    </p>
                    <div className="flex items-center gap-3">
                      <button
                        type="button"
                        onClick={() => goToMixPage(pagination.current_page - 1)}
                        disabled={pagination.current_page <= 1 || isLoading}
                        className="inline-flex h-11 items-center justify-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-[#FFB800] hover:text-[#FFB800] disabled:cursor-not-allowed disabled:opacity-50"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        <ChevronLeft size={15} />
                        Previous
                      </button>
                      <button
                        type="button"
                        onClick={() => goToMixPage(pagination.current_page + 1)}
                        disabled={!pagination.has_more_pages || isLoading}
                        className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        Next
                        <ChevronRight size={15} />
                      </button>
                    </div>
                  </div>
                )}
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
                    <GenreRow
                      key={row.genre}
                      genre={row.genre}
                      mixes={row.mixes}
                      savedMixIds={savedMixIds}
                      savingMixIds={savingMixIds}
                      onPlay={handlePlay}
                      onToggleSaved={handleToggleSaved}
                    />
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
