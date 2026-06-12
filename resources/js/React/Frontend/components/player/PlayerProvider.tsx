import { createContext, type ReactNode, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { CircleStop, Pause, Play, Volume2, X } from 'lucide-react';

export type PlayerTrack = {
  id: string | number;
  title: string;
  artist?: string | null;
  src: string;
  artwork?: string | null;
  meta?: string | null;
};

type PlayerContextValue = {
  currentTrack: PlayerTrack | null;
  isPlaying: boolean;
  error: string | null;
  playTrack: (track: PlayerTrack) => void;
  togglePlay: () => void;
  stop: () => void;
};

const PlayerContext = createContext<PlayerContextValue | undefined>(undefined);

function formatTime(seconds: number) {
  if (!Number.isFinite(seconds) || seconds <= 0) return '0:00';

  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = Math.floor(seconds % 60);
  return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

function resolvePlayableSource(src: string) {
  const normalizedSrc = src.trim().replace(/\\/g, '/');

  if (!normalizedSrc) return normalizedSrc;

  if (normalizedSrc.startsWith('media/') || normalizedSrc.startsWith('storage/')) {
    return `/${normalizedSrc}`;
  }

  try {
    const url = new URL(normalizedSrc, window.location.origin);

    if (url.pathname.startsWith('/media/') || url.pathname.startsWith('/storage/')) {
      return `${url.pathname}${url.search}${url.hash}`;
    }

    return url.href;
  } catch {
    return normalizedSrc;
  }
}

export function PlayerVisualizer({
  isPlaying,
  hasError,
  barCount = 28,
  className = 'h-12 border-x border-[#1f1f1f] px-3',
}: {
  isPlaying: boolean;
  hasError: boolean;
  barCount?: number;
  className?: string;
}) {
  const bars = Array.from({ length: barCount }, (_, index) => index);

  return (
    <div
      className={`flex items-end gap-1 ${className}`}
      aria-hidden="true"
    >
      {bars.map((bar) => {
        const height = 18 + ((bar * 11) % 28);

        return (
          <span
            key={bar}
            className="block w-1 bg-primary/85 shadow-[0_0_12px_rgba(255,29,29,0.35)]"
            style={{
              height: hasError ? 8 : height,
              animation: `blendbeats-player-bar ${0.72 + (bar % 7) * 0.08}s ease-in-out infinite alternate`,
              animationDelay: `${bar * -0.045}s`,
              animationPlayState: isPlaying && !hasError ? 'running' : 'paused',
              opacity: isPlaying && !hasError ? 1 : 0.35,
              transformOrigin: 'bottom',
            }}
          />
        );
      })}
    </div>
  );
}

export function PlayerProvider({ children }: { children: ReactNode }) {
  const audioRef = useRef<HTMLAudioElement | null>(null);
  const [currentTrack, setCurrentTrack] = useState<PlayerTrack | null>(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [duration, setDuration] = useState(0);
  const [currentTime, setCurrentTime] = useState(0);
  const [volume, setVolume] = useState(0.85);

  useEffect(() => {
    const audio = audioRef.current;
    if (!audio) return;

    audio.volume = volume;
  }, [volume]);

  const playTrack = (track: PlayerTrack) => {
    const audio = audioRef.current;
    if (!audio) return;

    const playableTrack = {
      ...track,
      src: resolvePlayableSource(track.src),
    };
    const isSameTrack = currentTrack?.src === playableTrack.src;

    setError(null);
    setCurrentTrack(playableTrack);

    if (!isSameTrack) {
      audio.src = playableTrack.src;
      audio.currentTime = 0;
      audio.load();
      setCurrentTime(0);
      setDuration(0);
    }

    audio.play()
      .then(() => setIsPlaying(true))
      .catch(() => {
        setIsPlaying(false);
        setError('Audio file could not be loaded.');
      });
  };

  const togglePlay = () => {
    const audio = audioRef.current;
    if (!audio || !currentTrack) return;

    if (audio.paused) {
      audio.play()
        .then(() => setIsPlaying(true))
        .catch(() => {
          setIsPlaying(false);
          setError('Audio file could not be loaded.');
        });
      return;
    }

    audio.pause();
    setIsPlaying(false);
  };

  const stop = () => {
    const audio = audioRef.current;
    if (audio) {
      audio.pause();
      audio.removeAttribute('src');
      audio.load();
    }

    setCurrentTrack(null);
    setIsPlaying(false);
    setError(null);
    setCurrentTime(0);
    setDuration(0);
  };

  const value = useMemo(
    () => ({ currentTrack, isPlaying, error, playTrack, togglePlay, stop }),
    [currentTrack, isPlaying, error],
  );

  const progress = duration > 0 ? (currentTime / duration) * 100 : 0;

  return (
    <PlayerContext.Provider value={value}>
      {children}
      <style>
        {`
          @keyframes blendbeats-player-bar {
            0% { transform: scaleY(0.35); }
            45% { transform: scaleY(1); }
            100% { transform: scaleY(0.55); }
          }
        `}
      </style>
      <audio
        ref={audioRef}
        onLoadedMetadata={(event) => setDuration(event.currentTarget.duration)}
        onTimeUpdate={(event) => setCurrentTime(event.currentTarget.currentTime)}
        onPlay={() => setIsPlaying(true)}
        onPause={() => setIsPlaying(false)}
        onEnded={() => setIsPlaying(false)}
        onError={() => {
          setIsPlaying(false);
          setError('Audio file could not be loaded.');
        }}
      >
        <track kind="captions" />
      </audio>

      {currentTrack && (
        <div className="fixed inset-x-0 bottom-0 z-50 border-t border-[#2a2a2a] bg-[#080808]/95 px-4 py-3 text-white shadow-2xl shadow-black/60 backdrop-blur lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-3 lg:grid-cols-[minmax(0,1fr)_220px_360px_150px] lg:items-center">
            <div className="flex min-w-0 items-center gap-3">
              {currentTrack.artwork ? (
                <img src={currentTrack.artwork} alt={currentTrack.title} className="h-12 w-12 shrink-0 object-cover" />
              ) : (
                <div className="flex h-12 w-12 shrink-0 items-center justify-center bg-primary text-white">
                  <Volume2 size={20} />
                </div>
              )}
              <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-white">{currentTrack.title}</p>
                <p className="truncate text-xs text-[#888888]">
                  {error || currentTrack.artist || currentTrack.meta || 'BlendBeats'}
                </p>
              </div>
            </div>

            <PlayerVisualizer isPlaying={isPlaying} hasError={Boolean(error)} />

            <div className="grid gap-2">
              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={togglePlay}
                  className="inline-flex h-10 w-10 shrink-0 items-center justify-center bg-primary text-white transition-colors hover:bg-primary/90"
                  aria-label={isPlaying ? 'Pause current track' : 'Play current track'}
                >
                  {isPlaying ? <Pause size={17} /> : <Play size={17} fill="currentColor" />}
                </button>
                <div className="h-2 flex-1 bg-[#1f1f1f]">
                  <div className="h-full bg-primary" style={{ width: `${progress}%` }} />
                </div>
                <span className="w-20 text-right text-xs text-[#888888]">
                  {formatTime(currentTime)} / {formatTime(duration)}
                </span>
              </div>
            </div>

            <div className="flex items-center justify-end gap-2">
              <input
                type="range"
                min="0"
                max="1"
                step="0.01"
                value={volume}
                onChange={(event) => setVolume(Number(event.target.value))}
                aria-label="Player volume"
                className="w-20 accent-primary"
              />
              <button
                type="button"
                onClick={stop}
                className="inline-flex h-10 w-10 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                aria-label="Stop playback"
              >
                <CircleStop size={16} />
              </button>
              <button
                type="button"
                onClick={stop}
                className="inline-flex h-10 w-10 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                aria-label="Close player"
              >
                <X size={16} />
              </button>
            </div>
          </div>
        </div>
      )}
    </PlayerContext.Provider>
  );
}

export function usePlayer() {
  const context = useContext(PlayerContext);
  if (!context) throw new Error('usePlayer must be used within PlayerProvider');
  return context;
}
