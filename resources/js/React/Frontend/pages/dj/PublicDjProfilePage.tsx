import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowLeft,
  CalendarCheck,
  Disc3,
  FileAudio,
  Headphones,
  MapPin,
  Pause,
  Play,
  Radio,
  ShieldCheck,
  Star,
  Users,
  Video,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';

import { usePlayer } from '@/components/player/PlayerProvider';
import { getDjHubDj, type DjHubDj } from '@/lib/dj-hub';

function formatDate(date: string | null) {
  if (!date) return 'Recently added';

  return new Intl.DateTimeFormat('en', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(date));
}

function mediaKindLabel(kind: string | null, fallback: string) {
  if (kind === 'battle_entry') return 'Battle Entry';
  if (kind) return kind.replace(/_/g, ' ');
  return fallback;
}

export default function PublicDjProfilePage() {
  const { handle } = useParams();
  const { currentTrack, isPlaying, playTrack, togglePlay } = usePlayer();
  const [dj, setDj] = useState<DjHubDj | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!handle) return;

    setIsLoading(true);
    setError('');

    getDjHubDj(handle)
      .then(setDj)
      .catch(() => setError('Unable to load this DJ profile.'))
      .finally(() => setIsLoading(false));
  }, [handle]);

  const portfolioMedia = useMemo(() => dj?.portfolio_media ?? [], [dj?.portfolio_media]);
  const audioMedia = useMemo(() => portfolioMedia.filter((media) => media.is_audio), [portfolioMedia]);
  const videoMedia = useMemo(() => portfolioMedia.filter((media) => media.is_video), [portfolioMedia]);
  const soundTags = useMemo(
    () => [dj?.primary_genre, ...(dj?.secondary_genres ?? [])].filter(Boolean) as string[],
    [dj?.primary_genre, dj?.secondary_genres],
  );

  if (isLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-16">
        <div className="container mx-auto h-96 max-w-6xl animate-pulse border border-[#2a2a2a] bg-[#111111]" />
      </main>
    );
  }

  if (error || !dj) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-16">
        <div className="container mx-auto max-w-3xl border border-[#2a2a2a] bg-[#111111] p-8 text-center">
          <Headphones size={28} className="mx-auto text-primary" />
          <h1 className="mt-4 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            DJ Not Found
          </h1>
          <p className="mt-3 text-sm text-[#888888]">{error || 'This profile is not public right now.'}</p>
          <Link
            to="/djs"
            className="mt-6 inline-flex h-11 items-center justify-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <ArrowLeft size={15} />
            Back To DJ Hub
          </Link>
        </div>
      </main>
    );
  }

  return (
    <>
      <Helmet>
        <title>{dj.dj_name} | DJ Hub</title>
        <meta name="description" content={dj.headline || `Discover ${dj.dj_name} on BlendBeats.`} />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-10 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            <Link
              to="/djs"
              className="mb-8 inline-flex h-10 items-center gap-2 border border-[#333333] px-3 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              DJ Hub
            </Link>

            <div className="grid gap-8 lg:grid-cols-[320px_minmax(0,1fr)] lg:items-end">
              <div className="border border-[#333333] bg-[#111111] p-4">
                <div className="aspect-square overflow-hidden rounded-full border-4 border-white bg-[#080808] shadow-2xl shadow-primary/10">
                  {dj.avatar_url ? (
                    <img src={dj.avatar_url} alt={dj.dj_name} className="h-full w-full object-cover" />
                  ) : (
                    <div className="flex h-full w-full items-center justify-center bg-primary text-7xl font-black uppercase text-white">
                      {dj.dj_name.charAt(0)}
                    </div>
                  )}
                </div>
                <div className="mt-4 grid grid-cols-2 gap-2">
                  <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Followers</p>
                    <p className="mt-1 text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      {dj.followers_count.toLocaleString()}
                    </p>
                  </div>
                  <div className="border border-[#2a2a2a] bg-[#080808] p-3">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Uploads</p>
                    <p className="mt-1 text-2xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      {(dj.portfolio_stats?.public_media_count ?? portfolioMedia.length).toLocaleString()}
                    </p>
                  </div>
                </div>
              </div>

              <div>
                <p
                  className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  {dj.primary_genre ?? 'Open Format'}
                </p>
                <h1
                  className="text-white uppercase leading-none"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 8vw, 6.5rem)' }}
                >
                  {dj.dj_name}
                </h1>
                <p className="mt-5 max-w-2xl text-lg leading-7 text-[#bbbbbb]">
                  {dj.headline || 'BlendBeats DJ building a public portfolio.'}
                </p>

                <div className="mt-6 flex flex-wrap gap-3 text-sm text-[#dddddd]">
                  {dj.location && (
                    <span className="inline-flex h-10 items-center gap-2 border border-[#333333] px-3">
                      <MapPin size={15} className="text-primary" />
                      {dj.location}
                    </span>
                  )}
                  <span className="inline-flex h-10 items-center gap-2 border border-[#333333] px-3">
                    <Users size={15} className="text-primary" />
                    {dj.followers_count.toLocaleString()} followers
                  </span>
                  {dj.open_for_bookings && (
                    <span className="inline-flex h-10 items-center gap-2 border border-primary/50 px-3 text-primary">
                      <CalendarCheck size={15} />
                      Open for bookings
                    </span>
                  )}
                </div>

                <div className="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                  {[
                    { label: 'Public Media', value: dj.portfolio_stats?.public_media_count ?? portfolioMedia.length, icon: Disc3 },
                    { label: 'Audio', value: dj.portfolio_stats?.audio_count ?? audioMedia.length, icon: FileAudio },
                    { label: 'Videos', value: dj.portfolio_stats?.video_count ?? videoMedia.length, icon: Video },
                    { label: 'Genres', value: dj.portfolio_stats?.genre_count ?? soundTags.length, icon: Radio },
                  ].map((stat) => {
                    const Icon = stat.icon;
                    return (
                      <div key={stat.label} className="border border-[#2a2a2a] bg-[#111111] p-4">
                        <Icon size={18} className="text-primary" />
                        <p className="mt-3 text-[10px] font-bold uppercase tracking-widest text-[#777777]">{stat.label}</p>
                        <p className="mt-1 text-3xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {stat.value.toLocaleString()}
                        </p>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-8 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div className="grid gap-5">
              <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                <div className="flex items-center gap-3">
                  <Headphones size={20} className="text-primary" />
                  <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    About The DJ
                  </h2>
                </div>
                <p className="mt-4 whitespace-pre-line text-sm leading-7 text-[#aaaaaa]">
                  {dj.bio || 'This DJ has not added a public biography yet.'}
                </p>
                {soundTags.length > 0 && (
                  <div className="mt-6 flex flex-wrap gap-2">
                    {soundTags.map((tag) => (
                      <span
                        key={tag}
                        className="inline-flex h-8 items-center border border-[#333333] px-3 text-[10px] font-bold uppercase tracking-widest text-[#dddddd]"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        {tag}
                      </span>
                    ))}
                  </div>
                )}
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                <div className="flex flex-col gap-2 border-b border-[#252525] pb-4 sm:flex-row sm:items-end sm:justify-between">
                  <div>
                    <p className="text-[11px] font-bold uppercase tracking-widest text-primary">Public Work</p>
                    <h2 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Latest Portfolio
                    </h2>
                  </div>
                  <Link
                    to="/mixes"
                    className="text-xs font-bold uppercase tracking-widest text-[#aaaaaa] transition-colors hover:text-primary"
                    style={{ fontFamily: 'var(--font-heading)' }}
                  >
                    Browse Mixes
                  </Link>
                </div>

                {portfolioMedia.length > 0 ? (
                  <div className="mt-5 grid gap-3">
                    {portfolioMedia.map((media) => {
                      const trackId = `dj-profile-media-${media.id}`;
                      const isActiveTrack = currentTrack?.id === trackId;
                      const fallbackType = media.is_audio ? 'Audio' : media.is_video ? 'Video' : 'Media';

                      return (
                        <article
                          key={media.id}
                          className="grid gap-4 border border-[#2a2a2a] bg-[#080808] p-4 md:grid-cols-[56px_minmax(0,1fr)_120px] md:items-center"
                        >
                          <div className="flex h-14 w-14 items-center justify-center border border-[#333333] text-primary">
                            {media.is_video ? <Video size={22} /> : <FileAudio size={22} />}
                          </div>
                          <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                              <p className="truncate text-base font-semibold text-white">{media.title}</p>
                              {media.genre && (
                                <span className="text-[10px] font-bold uppercase tracking-widest text-primary">
                                  {media.genre}
                                </span>
                              )}
                            </div>
                            <p className="mt-1 line-clamp-2 text-sm leading-6 text-[#888888]">
                              {media.description || `${mediaKindLabel(media.kind, fallbackType)} added ${formatDate(media.created_at)}.`}
                            </p>
                            <p className="mt-2 text-[11px] uppercase tracking-widest text-[#666666]">
                              {mediaKindLabel(media.kind, fallbackType)} / {formatDate(media.created_at)} / {media.formatted_size}
                            </p>
                          </div>
                          {media.is_audio ? (
                            <button
                              type="button"
                              onClick={() => {
                                if (isActiveTrack) {
                                  togglePlay();
                                  return;
                                }

                                playTrack({
                                  id: trackId,
                                  title: media.title,
                                  artist: dj.dj_name,
                                  src: media.url,
                                  meta: media.genre || mediaKindLabel(media.kind, fallbackType),
                                });
                              }}
                              className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                              style={{ fontFamily: 'var(--font-heading)' }}
                            >
                              {isActiveTrack && isPlaying ? <Pause size={14} /> : <Play size={14} fill="currentColor" />}
                              {isActiveTrack && isPlaying ? 'Pause' : 'Play'}
                            </button>
                          ) : (
                            <a
                              href={media.url}
                              target="_blank"
                              rel="noreferrer"
                              className="inline-flex h-11 items-center justify-center border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                              style={{ fontFamily: 'var(--font-heading)' }}
                            >
                              View
                            </a>
                          )}
                        </article>
                      );
                    })}
                  </div>
                ) : (
                  <div className="mt-5 border border-[#2a2a2a] bg-[#080808] p-6 text-sm leading-6 text-[#888888]">
                    No public portfolio media has been published yet.
                  </div>
                )}
              </section>
            </div>

            <aside className="grid gap-5 self-start">
              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <div className="flex items-center gap-3">
                  <Disc3 size={18} className="text-primary" />
                  <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Featured Mix
                  </h2>
                </div>
                <div className="mt-4 border border-[#333333] bg-[#080808] p-3">
                  {dj.featured_mix ? (
                    <div className="grid gap-3">
                      <p className="text-sm font-semibold text-white">{dj.featured_mix.title}</p>
                      <button
                        type="button"
                        onClick={() => {
                          const trackId = `dj-profile-featured-${dj.featured_mix!.id}`;
                          if (currentTrack?.id === trackId) {
                            togglePlay();
                            return;
                          }

                          playTrack({
                            id: trackId,
                            title: dj.featured_mix!.title,
                            artist: dj.dj_name,
                            src: dj.featured_mix!.url,
                            meta: `${dj.primary_genre ?? 'Open Format'} featured mix`,
                          });
                        }}
                        className="inline-flex h-10 items-center justify-center gap-2 bg-primary px-3 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        {currentTrack?.id === `dj-profile-featured-${dj.featured_mix.id}` && isPlaying ? (
                          <Pause size={14} />
                        ) : (
                          <Play size={14} fill="currentColor" />
                        )}
                        Play Mix
                      </button>
                    </div>
                  ) : (
                    <p className="text-sm text-[#777777]">No public mix featured yet.</p>
                  )}
                </div>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <div className="flex items-center gap-3">
                  <ShieldCheck size={18} className="text-primary" />
                  <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Profile Status
                  </h2>
                </div>
                <div className="mt-4 grid gap-2">
                  {dj.featured_statuses.length > 0 ? (
                    dj.featured_statuses.map((status) => (
                      <span
                        key={status}
                        className="inline-flex h-9 items-center gap-2 bg-primary px-3 text-[10px] font-bold uppercase tracking-widest text-white"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        <Star size={13} />
                        {status}
                      </span>
                    ))
                  ) : (
                    <span className="text-sm text-[#777777]">Standard public listing</span>
                  )}
                  <span className="text-sm text-[#aaaaaa]">
                    {dj.open_for_bookings ? 'Available for booking inquiries.' : 'Booking availability not listed.'}
                  </span>
                </div>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Sound Snapshot
                </h2>
                <div className="mt-4 grid gap-3 text-sm">
                  <div className="flex items-center justify-between border-b border-[#252525] pb-3">
                    <span className="text-[#777777]">Primary Genre</span>
                    <span className="font-semibold text-white">{dj.primary_genre ?? 'Open Format'}</span>
                  </div>
                  <div className="flex items-center justify-between border-b border-[#252525] pb-3">
                    <span className="text-[#777777]">DJ Type</span>
                    <span className="font-semibold capitalize text-white">{dj.dj_type ?? 'Not listed'}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-[#777777]">Location</span>
                    <span className="max-w-[170px] truncate font-semibold text-white">{dj.location || 'Not listed'}</span>
                  </div>
                </div>
              </section>
            </aside>
          </div>
        </section>
      </main>
    </>
  );
}
