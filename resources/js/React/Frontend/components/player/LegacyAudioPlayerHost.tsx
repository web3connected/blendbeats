import { CircleStop, Pause, Play, Volume2, X } from 'lucide-react';
import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, useState } from 'react';

import type { PlayerMode, PlayerTrack } from './player-types';

export type LegacyAudioPlayerHandle = {
  loadTrack: (track: PlayerTrack, startAtSeconds: number, autoplay: boolean) => void;
  pause: () => void;
  play: () => void;
  seekToSeconds: (seconds: number) => void;
  stop: () => void;
};

type LegacyAudioPlayerHostProps = {
  currentTrack: PlayerTrack | null;
  mode: PlayerMode;
  volume: number;
  isPlaying: boolean;
  error: string | null;
  playbackBlocked: boolean;
  onDurationChange: (duration: number) => void;
  onEnded: () => void;
  onError: (message: string) => void;
  onPause: () => void;
  onPlaybackBlocked: () => void;
  onPlay: () => void;
  onStop: () => void;
  onTimeUpdate: (time: number) => void;
  onTogglePlay: () => void;
  onVolumeChange: (volume: number) => void;
};

function formatTime(seconds: number) {
  if (!Number.isFinite(seconds) || seconds <= 0) return '0:00';

  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = Math.floor(seconds % 60);
  return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

export function resolvePlayableSource(src: string) {
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
    <div className={`flex items-end gap-1 ${className}`} aria-hidden="true">
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

export const LegacyAudioPlayerHost = forwardRef<LegacyAudioPlayerHandle, LegacyAudioPlayerHostProps>(
  function LegacyAudioPlayerHost({
    currentTrack,
    mode,
    volume,
    isPlaying,
    error,
    playbackBlocked,
    onDurationChange,
    onEnded,
    onError,
    onPause,
    onPlaybackBlocked,
    onPlay,
    onStop,
    onTimeUpdate,
    onTogglePlay,
    onVolumeChange,
  }, ref) {
    const audioRef = useRef<HTMLAudioElement | null>(null);
    const [duration, setDuration] = useState(0);
    const [currentTime, setCurrentTime] = useState(0);

    useEffect(() => {
      const audio = audioRef.current;
      if (!audio) return;

      audio.volume = volume;
    }, [volume]);

    const startAudio = useCallback((autoplay = true) => {
      const audio = audioRef.current;
      if (!audio || !autoplay) return;

      audio.play()
        .then(onPlay)
        .catch((playError: unknown) => {
          if (playError instanceof DOMException && playError.name === 'NotAllowedError') {
            onPlaybackBlocked();
            return;
          }

          onError('Audio file could not be loaded.');
        });
    }, [onError, onPlaybackBlocked, onPlay]);

    const stopPlayback = useCallback(() => {
      const audio = audioRef.current;
      if (audio) {
        audio.pause();
        audio.removeAttribute('src');
        audio.load();
      }

      setCurrentTime(0);
      setDuration(0);
      onStop();
    }, [onStop]);

    useImperativeHandle(ref, () => ({
      loadTrack(track, startAtSeconds, autoplay) {
        const audio = audioRef.current;
        if (!audio) return;

        const safeStartAtSeconds = Math.max(0, startAtSeconds);
        const playableTrack = {
          ...track,
          src: resolvePlayableSource(track.src),
        };
        const isSameTrack = audio.currentSrc === playableTrack.src || audio.src === playableTrack.src;

        if (!isSameTrack) {
          audio.src = playableTrack.src;
          audio.load();
          setDuration(0);
          onDurationChange(0);
        }

        if (!isSameTrack || Math.abs(audio.currentTime - safeStartAtSeconds) > 8) {
          try {
            audio.currentTime = safeStartAtSeconds;
          } catch {
            audio.addEventListener('loadedmetadata', () => {
              audio.currentTime = safeStartAtSeconds;
            }, { once: true });
          }

          setCurrentTime(safeStartAtSeconds);
          onTimeUpdate(safeStartAtSeconds);
        }

        startAudio(autoplay);
      },
      pause() {
        audioRef.current?.pause();
        onPause();
      },
      play() {
        startAudio(true);
      },
      seekToSeconds(seconds) {
        const audio = audioRef.current;
        if (!audio) return;

        const nextTime = Math.max(0, seconds);
        audio.currentTime = nextTime;
        setCurrentTime(nextTime);
        onTimeUpdate(nextTime);
      },
      stop() {
        stopPlayback();
      },
    }), [onDurationChange, onPause, onPlay, onTimeUpdate, startAudio, stopPlayback]);

    const displayDuration = duration || currentTrack?.duration || 0;
    const progress = displayDuration > 0 ? (currentTime / displayDuration) * 100 : 0;

    return (
      <>
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
          onLoadedMetadata={(event) => {
            const loadedDuration = event.currentTarget.duration;
            const nextDuration = Number.isFinite(loadedDuration) ? loadedDuration : currentTrack?.duration ?? 0;
            setDuration(nextDuration);
            onDurationChange(nextDuration);
          }}
          onTimeUpdate={(event) => {
            const nextTime = event.currentTarget.currentTime;
            setCurrentTime(nextTime);
            onTimeUpdate(nextTime);
          }}
          onPlay={onPlay}
          onPause={onPause}
          onEnded={onEnded}
          onError={() => onError('Audio file could not be loaded.')}
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
                  {mode === 'lounge_live' && (
                    <p
                      className="mb-0.5 text-[10px] font-bold uppercase tracking-widest text-[#FFB800]"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      DJ Lounge Live
                    </p>
                  )}
                  <p className="truncate text-sm font-semibold text-white">{currentTrack.title}</p>
                  <p className="truncate text-xs text-[#888888]">
                    {error || (playbackBlocked ? 'Tap play to start lounge music.' : currentTrack.artist || currentTrack.meta || 'BlendBeats')}
                  </p>
                  {typeof currentTrack.countValue === 'number' && (
                    <p className="mt-0.5 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
                      {new Intl.NumberFormat('en', { notation: currentTrack.countValue >= 10000 ? 'compact' : 'standard' }).format(currentTrack.countValue)}{' '}
                      {currentTrack.countLabel || 'plays'}
                    </p>
                  )}
                </div>
              </div>

              <PlayerVisualizer isPlaying={isPlaying} hasError={Boolean(error)} />

              <div className="grid gap-2">
                <div className="flex items-center gap-3">
                  <button
                    type="button"
                    onClick={onTogglePlay}
                    className="inline-flex h-10 w-10 shrink-0 items-center justify-center bg-primary text-white transition-colors hover:bg-primary/90"
                    aria-label={isPlaying ? 'Pause current track' : 'Play current track'}
                  >
                    {isPlaying ? <Pause size={17} /> : <Play size={17} fill="currentColor" />}
                  </button>
                  <div className="h-2 flex-1 bg-[#1f1f1f]">
                    <div className="h-full bg-primary" style={{ width: `${progress}%` }} />
                  </div>
                  <span className="w-20 text-right text-xs text-[#888888]">
                    {formatTime(currentTime)} / {formatTime(displayDuration)}
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
                  onChange={(event) => onVolumeChange(Number(event.target.value))}
                  aria-label="Player volume"
                  className="w-20 accent-primary"
                />
                <button
                  type="button"
                  onClick={stopPlayback}
                  className="inline-flex h-10 w-10 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  aria-label="Stop playback"
                >
                  <CircleStop size={16} />
                </button>
                <button
                  type="button"
                  onClick={stopPlayback}
                  className="inline-flex h-10 w-10 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  aria-label="Close player"
                >
                  <X size={16} />
                </button>
              </div>
            </div>
          </div>
        )}
      </>
    );
  },
);
