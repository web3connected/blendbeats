import { Helmet } from '@dr.pogodin/react-helmet';
import { CalendarCheck, Headphones, MapPin, Pause, Play, Search, SlidersHorizontal, Star, Users } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';

import FeaturedDjAdSection from '@/components/featured/FeaturedDjAdSection';
import { usePlayer } from '@/components/player/PlayerProvider';
import { getDjHubDjs, type DjHubDj, type DjHubFilters, type DjHubQuery } from '@/lib/dj-hub';

const sortOptions: Array<{ label: string; value: NonNullable<DjHubQuery['sort']> }> = [
  { label: 'Featured', value: 'featured' },
  { label: 'Most Followed', value: 'followers' },
  { label: 'New DJs', value: 'new' },
  { label: 'Name', value: 'name' },
];

function formatDjType(value: string) {
  return value
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function DjCard({ dj }: { dj: DjHubDj }) {
  const featuredLabel = dj.featured_statuses[0];
  const { currentTrack, isPlaying, playTrack, togglePlay } = usePlayer();
  const featuredMixTrackId = dj.featured_mix ? `dj-featured-${dj.featured_mix.id}` : null;
  const isFeaturedMixPlaying = Boolean(featuredMixTrackId && currentTrack?.id === featuredMixTrackId && isPlaying);

  return (
    <article className="group grid overflow-hidden border border-[#2a2a2a] bg-[#111111] transition-colors hover:border-primary/70">
      <div className="relative border-b border-[#242424] bg-[#080808] px-5 pb-5 pt-6">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_20%,rgba(255,29,29,0.18),transparent_42%)] opacity-80" />
        <div className="relative mx-auto flex h-40 w-40 items-center justify-center rounded-full border border-[#333333] bg-[#050505] p-2 shadow-2xl shadow-black/40">
          {dj.avatar_url ? (
            <img
              src={dj.avatar_url}
              alt={dj.dj_name}
              className="h-full w-full rounded-full border-2 border-[#f2f2f2] bg-[#090909] object-cover"
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center rounded-full bg-primary text-6xl font-black uppercase text-white">
              {dj.dj_name.charAt(0)}
            </div>
          )}
        </div>
        {featuredLabel && (
          <span
            className="absolute left-3 top-3 inline-flex h-8 items-center gap-2 bg-primary px-3 text-[10px] font-bold uppercase tracking-widest text-white"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <Star size={13} />
            {featuredLabel}
          </span>
        )}
        <div className="relative mt-5 flex items-center justify-center gap-2 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
          <span className="h-px w-8 bg-[#333333]" />
          <span>{dj.primary_genre ?? 'Open Format'}</span>
          <span className="h-px w-8 bg-[#333333]" />
        </div>
      </div>

      <div className="grid gap-4 p-4">
        <div>
          <h2 className="text-3xl uppercase leading-none text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {dj.dj_name}
          </h2>
          <p className="mt-3 min-h-12 text-sm leading-6 text-[#aaaaaa]">
            {dj.headline || 'BlendBeats DJ building a public portfolio.'}
          </p>
        </div>

        <div className="grid gap-2 text-sm text-[#888888]">
          <span className="inline-flex items-center gap-2">
            <MapPin size={15} className="text-primary" />
            {dj.location || 'Location not listed'}
          </span>
          <span className="inline-flex items-center gap-2">
            <Users size={15} className="text-primary" />
            {dj.followers_count.toLocaleString()} followers
          </span>
          {dj.open_for_bookings && (
            <span className="inline-flex items-center gap-2 text-[#dddddd]">
              <CalendarCheck size={15} className="text-primary" />
              Open for bookings
            </span>
          )}
        </div>

        <div className="border border-[#2a2a2a] bg-[#080808] p-3">
          <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-[#777777]">Featured Mix</p>
          {dj.featured_mix ? (
            <div className="grid gap-2">
              <p className="truncate text-sm font-semibold text-white">{dj.featured_mix.title}</p>
              <button
                type="button"
                onClick={() => {
                  if (currentTrack?.id === featuredMixTrackId) {
                    togglePlay();
                    return;
                  }

                  playTrack({
                    id: featuredMixTrackId!,
                    title: dj.featured_mix!.title,
                    artist: dj.dj_name,
                    src: dj.featured_mix!.url,
                    artwork: dj.avatar_url,
                    meta: `${dj.primary_genre ?? 'Open Format'} featured mix`,
                  });
                }}
                className="inline-flex h-9 items-center justify-center gap-2 bg-primary px-3 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                {isFeaturedMixPlaying ? <Pause size={14} /> : <Play size={14} fill="currentColor" />}
                {isFeaturedMixPlaying ? 'Pause Mix' : 'Play Mix'}
              </button>
            </div>
          ) : (
            <p className="text-sm text-[#777777]">No public mix featured yet.</p>
          )}
        </div>

        <Link
          to={`/djs/${dj.handle}`}
          className="inline-flex h-11 items-center justify-center border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          View Profile
        </Link>
      </div>
    </article>
  );
}

export default function DjsPage() {
  const [query, setQuery] = useState<DjHubQuery>({ sort: 'featured' });
  const [searchInput, setSearchInput] = useState('');
  const [djs, setDjs] = useState<DjHubDj[]>([]);
  const [filters, setFilters] = useState<DjHubFilters>({ genres: [], dj_types: [] });
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let isMounted = true;

    setIsLoading(true);
    setError('');

    getDjHubDjs(query)
      .then((data) => {
        if (!isMounted) return;
        setDjs(data.djs);
        setFilters(data.filters);
      })
      .catch(() => {
        if (!isMounted) return;
        setError('Unable to load DJ Hub right now.');
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [query]);

  const resultLabel = useMemo(() => {
    if (isLoading) return 'Loading DJs';
    if (djs.length === 1) return '1 DJ found';
    return `${djs.length} DJs found`;
  }, [djs.length, isLoading]);

  const updateQuery = (nextQuery: Partial<DjHubQuery>) => {
    setQuery((currentQuery) => ({ ...currentQuery, ...nextQuery }));
  };

  const submitSearch = () => {
    updateQuery({ search: searchInput.trim() });
  };

  return (
    <>
      <Helmet>
        <title>DJ Hub | The Blend Battlegrounds</title>
        <meta name="description" content="Discover public DJs, mixes, genres, and booking-ready talent on BlendBeats." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-14 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            <p
              className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              DJ Hub
            </p>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
              <div>
                <h1
                  className="text-white uppercase leading-none"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(4rem, 11vw, 8rem)' }}
                >
                  Discover DJs
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  Find public DJ profiles by sound, city, booking availability, and platform activity.
                </p>
              </div>
              <div className="border border-[#2a2a2a] bg-[#111111] p-4">
                <p className="text-[11px] font-bold uppercase tracking-widest text-[#777777]">Directory</p>
                <p className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {resultLabel}
                </p>
              </div>
            </div>
          </div>
        </section>

        <FeaturedDjAdSection placement="dj-hub" />

        <section className="px-4 py-8 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-5 lg:grid-cols-[300px_minmax(0,1fr)]">
            <aside className="h-fit border border-[#2a2a2a] bg-[#111111] p-5">
              <div className="mb-5 flex items-center gap-3 border-b border-[#242424] pb-4">
                <SlidersHorizontal size={18} className="text-primary" />
                <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>Filters</h2>
              </div>

              <div className="grid gap-4">
                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Search</span>
                  <div className="flex border border-[#333333] bg-[#080808]">
                    <input
                      value={searchInput}
                      onChange={(event) => setSearchInput(event.target.value)}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter') submitSearch();
                      }}
                      placeholder="Name, genre, city"
                      className="h-11 min-w-0 flex-1 bg-transparent px-3 text-sm text-white outline-none placeholder:text-[#555555]"
                    />
                    <button
                      type="button"
                      onClick={submitSearch}
                      className="inline-flex h-11 w-11 items-center justify-center text-[#dddddd] transition-colors hover:text-primary"
                      aria-label="Search DJs"
                    >
                      <Search size={17} />
                    </button>
                  </div>
                </label>

                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Genre</span>
                  <select
                    value={query.genre ?? ''}
                    onChange={(event) => updateQuery({ genre: event.target.value || undefined })}
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                  >
                    <option value="">All genres</option>
                    {filters.genres.map((genre) => (
                      <option key={genre} value={genre}>{genre}</option>
                    ))}
                  </select>
                </label>

                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">DJ Type</span>
                  <select
                    value={query.dj_type ?? ''}
                    onChange={(event) => updateQuery({ dj_type: event.target.value || undefined })}
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                  >
                    <option value="">All DJ types</option>
                    {filters.dj_types.map((djType) => (
                      <option key={djType} value={djType}>{formatDjType(djType)}</option>
                    ))}
                  </select>
                </label>

                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Location</span>
                  <input
                    value={query.location ?? ''}
                    onChange={(event) => updateQuery({ location: event.target.value || undefined })}
                    placeholder="City, state, country"
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none placeholder:text-[#555555] focus:border-primary"
                  />
                </label>

                <label className="flex items-center justify-between gap-4 border border-[#333333] bg-[#080808] p-4">
                  <span className="text-sm font-semibold text-white">Open For Bookings</span>
                  <input
                    type="checkbox"
                    checked={Boolean(query.bookings)}
                    onChange={(event) => updateQuery({ bookings: event.target.checked || undefined })}
                    className="h-4 w-4 accent-primary"
                  />
                </label>

                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Sort</span>
                  <select
                    value={query.sort ?? 'featured'}
                    onChange={(event) => updateQuery({ sort: event.target.value as DjHubQuery['sort'] })}
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                  >
                    {sortOptions.map((option) => (
                      <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                  </select>
                </label>
              </div>
            </aside>

            <section className="min-w-0">
              {error && <div className="border border-primary/40 bg-primary/10 p-4 text-sm text-primary">{error}</div>}

              {isLoading && (
                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                  {Array.from({ length: 6 }).map((_, index) => (
                    <div key={index} className="h-[480px] animate-pulse border border-[#2a2a2a] bg-[#111111]" />
                  ))}
                </div>
              )}

              {!isLoading && !error && djs.length === 0 && (
                <div className="border border-[#2a2a2a] bg-[#111111] p-8 text-center">
                  <Headphones size={28} className="mx-auto text-primary" />
                  <h2 className="mt-4 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    No DJs Found
                  </h2>
                  <p className="mx-auto mt-3 max-w-xl text-sm leading-6 text-[#888888]">
                    Try a wider search, remove a filter, or check back as more public DJ profiles go live.
                  </p>
                </div>
              )}

              {!isLoading && !error && djs.length > 0 && (
                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                  {djs.map((dj) => (
                    <DjCard key={dj.id} dj={dj} />
                  ))}
                </div>
              )}
            </section>
          </div>
        </section>
      </main>
    </>
  );
}
