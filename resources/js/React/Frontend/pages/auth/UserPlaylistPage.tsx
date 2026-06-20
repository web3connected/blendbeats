import { Helmet } from '@dr.pogodin/react-helmet';
import { CalendarDays, Disc3, ListMusic, Play, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { type PlayerTrack, usePlayer } from '@/components/player/PlayerProvider';
import type { PublicMix } from '@/lib/mixes';
import { getUserPlaylist, removePlaylistMix } from '@/lib/user-playlist';

function mixToTrack(mix: PublicMix): PlayerTrack {
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

function formatDate(value?: string | null) {
  if (!value) return 'Saved recently';

  return new Intl.DateTimeFormat('en', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(value));
}

export default function UserPlaylistPage() {
  const { user, isLoading: isAuthLoading } = useAuth();
  const { loadQueue } = usePlayer();
  const [mixes, setMixes] = useState<PublicMix[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [removingMixId, setRemovingMixId] = useState<number | null>(null);
  const [message, setMessage] = useState('');

  useEffect(() => {
    let isMounted = true;

    if (!user) {
      setIsLoading(false);
      return () => {
        isMounted = false;
      };
    }

    setIsLoading(true);
    setMessage('');

    getUserPlaylist()
      .then((response) => {
        if (!isMounted) return;
        setMixes(response.playlist.map((item) => item.mix));
      })
      .catch(() => {
        if (!isMounted) return;
        setMessage('Your playlist could not be loaded right now.');
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [user]);

  const playableTracks = useMemo(() => mixes.filter((mix) => mix.audio_url).map(mixToTrack), [mixes]);

  if (isAuthLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20 text-white">
        <div className="container mx-auto max-w-6xl">
          <div className="h-48 animate-pulse bg-[#141414]" />
        </div>
      </main>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  const playPlaylist = (startMixId?: number) => {
    if (playableTracks.length === 0) {
      setMessage('Save a playable mix first.');
      return;
    }

    loadQueue({
      tracks: playableTracks,
      currentTrackId: startMixId ? `mix-${startMixId}` : playableTracks[0].id,
      autoplay: true,
    });
    setMessage(`Playing ${playableTracks.length} saved mix${playableTracks.length === 1 ? '' : 'es'}.`);
  };

  const removeMix = async (mix: PublicMix) => {
    setRemovingMixId(mix.id);
    setMessage('');

    try {
      await removePlaylistMix(mix.id);
      setMixes((current) => current.filter((item) => item.id !== mix.id));
      setMessage('Removed from your playlist.');
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Mix could not be removed.');
    } finally {
      setRemovingMixId(null);
    }
  };

  return (
    <>
      <Helmet>
        <title>My Playlist | The Blend Battlegrounds</title>
        <meta name="description" content="Play your saved BlendBeats mixes." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-10 text-white lg:px-8">
        <div className="container mx-auto max-w-6xl">
          <section className="border-b border-[#242424] pb-8">
            <p className="text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
              Account Player
            </p>
            <div className="mt-3 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <h1 className="text-5xl uppercase leading-none text-white sm:text-7xl" style={{ fontFamily: 'var(--font-heading)' }}>
                  My Playlist
                </h1>
                <p className="mt-4 max-w-2xl text-sm leading-6 text-[#aaaaaa] sm:text-base">
                  Your saved favorite mixes live here. Press play to load them into the BlendBeats player queue.
                </p>
              </div>
              <div className="flex flex-wrap gap-3">
                <button
                  type="button"
                  onClick={() => playPlaylist()}
                  disabled={isLoading || playableTracks.length === 0}
                  className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-55"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <Play size={16} fill="currentColor" />
                  Play Playlist
                </button>
                <Link
                  to="/mixes"
                  className="inline-flex h-12 items-center justify-center gap-2 border border-[#444444] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <ListMusic size={16} />
                  Browse Mixes
                </Link>
              </div>
            </div>
            {message && <p className="mt-4 text-sm text-[#FFB800]">{message}</p>}
          </section>

          <section className="py-8">
            {isLoading ? (
              <div className="grid gap-4">
                {[0, 1, 2].map((item) => (
                  <div key={item} className="h-28 animate-pulse border border-[#222222] bg-[#111111]" />
                ))}
              </div>
            ) : mixes.length === 0 ? (
              <div className="grid place-items-center border border-[#2a2a2a] bg-[#111111] p-10 text-center">
                <Disc3 size={42} className="text-primary" />
                <h2 className="mt-5 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  No Saved Mixes Yet
                </h2>
                <p className="mt-3 max-w-md text-sm leading-6 text-[#888888]">
                  Save mixes from the Mixes page and they will appear here as your personal playlist.
                </p>
                <Link
                  to="/mixes"
                  className="mt-6 inline-flex h-11 items-center justify-center bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Find Mixes
                </Link>
              </div>
            ) : (
              <div className="grid gap-3">
                {mixes.map((mix, index) => (
                  <article
                    key={mix.id}
                    className="grid gap-4 border border-[#2a2a2a] bg-[#111111] p-3 sm:grid-cols-[84px_minmax(0,1fr)_auto] sm:items-center"
                  >
                    <div className="relative aspect-square overflow-hidden bg-[#050505]">
                      {mix.cover_image_url ? (
                        <img src={mix.cover_image_url} alt={`${mix.title} cover`} className="h-full w-full object-cover" />
                      ) : (
                        <div className="grid h-full w-full place-items-center bg-[#171717] text-[#FFB800]">
                          <Disc3 size={28} />
                        </div>
                      )}
                    </div>
                    <div className="min-w-0">
                      <p className="text-xs font-bold uppercase tracking-widest text-[#777777]">
                        Track {index + 1}
                      </p>
                      <h2 className="mt-1 truncate text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        {mix.title}
                      </h2>
                      <p className="mt-1 text-sm text-[#999999]">by {mix.dj.name}</p>
                      <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-[#777777]">
                        <span>{mix.genre || 'Open Format'}</span>
                        <span className="inline-flex items-center gap-1">
                          <CalendarDays size={13} className="text-primary" />
                          {formatDate(mix.published_at || mix.created_at)}
                        </span>
                      </div>
                    </div>
                    <div className="flex flex-wrap gap-2 sm:justify-end">
                      <button
                        type="button"
                        onClick={() => playPlaylist(mix.id)}
                        disabled={!mix.audio_url}
                        className="inline-flex h-10 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        <Play size={14} fill="currentColor" />
                        Play
                      </button>
                      <button
                        type="button"
                        onClick={() => void removeMix(mix)}
                        disabled={removingMixId === mix.id}
                        className="inline-flex h-10 items-center justify-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary disabled:cursor-wait disabled:opacity-50"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        <Trash2 size={14} />
                        Remove
                      </button>
                    </div>
                  </article>
                ))}
              </div>
            )}
          </section>
        </div>
      </main>
    </>
  );
}
