import { CircleStop, Pause, Play, Volume2, X } from 'lucide-react';
import { forwardRef, useCallback, useEffect, useImperativeHandle, useMemo, useRef, useState } from 'react';

import type { PlayerMode, PlayerTrack } from './player-types';
import { FWDUVP_CONTENT_PATH, loadFWDUVPlayer } from './fwduvp-loader';
import { toFWDUVPTrackSources } from './fwduvp-playlist';
import type { FWDUVPEvent, FWDUVPInstance } from './fwduvp-types';
import { PlayerVisualizer } from './LegacyAudioPlayerHost';

const FALLBACK_ARTWORK = '/media/site/images/pages/home/live-battles/dj-hub.jpg';

export type FWDUVPPlaybackRequest = {
  autoplay: boolean;
  revision: number;
  startAtSeconds: number;
};

export type FWDUVPPlayerHostHandle = {
  pause: () => void;
  play: () => void;
  seekToSeconds: (seconds: number) => void;
  setVolume: (volume: number) => void;
  stop: () => void;
};

type FWDUVPPlayerHostProps = {
  currentTrack: PlayerTrack | null;
  currentTime: number;
  duration: number;
  error: string | null;
  isPlaying: boolean;
  mode: PlayerMode;
  playbackBlocked: boolean;
  playbackRequest: FWDUVPPlaybackRequest;
  queue: PlayerTrack[];
  queueIndex: number;
  volume: number;
  onDurationChange: (duration: number) => void;
  onError: (message: string) => void;
  onPause: () => void;
  onPlay: () => void;
  onPlaybackBlocked: () => void;
  onStop: () => void;
  onTimeUpdate: (time: number) => void;
  onTrackChange: (track: PlayerTrack, index: number) => void;
  onTogglePlay: () => void;
  onVolumeChange: (volume: number) => void;
};

function secondsToFWDUVPTime(seconds: number) {
  const safeSeconds = Math.max(0, Math.floor(seconds));
  const hours = Math.floor(safeSeconds / 3600);
  const minutes = Math.floor((safeSeconds % 3600) / 60);
  const remainingSeconds = safeSeconds % 60;

  return [hours, minutes, remainingSeconds].map((part) => part.toString().padStart(2, '0')).join(':');
}

function parseFWDUVPTime(value: unknown) {
  if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
  if (typeof value !== 'string') return 0;

  const parts = value.split(':').map((part) => Number(part));
  if (parts.some((part) => !Number.isFinite(part))) return 0;

  if (parts.length === 3) return parts[0] * 3600 + parts[1] * 60 + parts[2];
  if (parts.length === 2) return parts[0] * 60 + parts[1];

  return Number(value) || 0;
}

function formatTime(seconds: number) {
  if (!Number.isFinite(seconds) || seconds <= 0) return '0:00';

  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = Math.floor(seconds % 60);

  return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

export const FWDUVPPlayerHost = forwardRef<FWDUVPPlayerHostHandle, FWDUVPPlayerHostProps>(
  function FWDUVPPlayerHost({
    currentTrack,
    currentTime,
    duration,
    error,
    isPlaying,
    mode,
    playbackBlocked,
    playbackRequest,
    queue,
    queueIndex,
    volume,
    onDurationChange,
    onError,
    onPause,
    onPlay,
    onPlaybackBlocked,
    onStop,
    onTimeUpdate,
    onTrackChange,
    onTogglePlay,
    onVolumeChange,
  }, ref) {
    const idPrefix = useRef(`bb-fwduvp-${Math.random().toString(36).slice(2)}`);
    const instancePrefix = useRef(`bbFwduvp${Math.random().toString(36).slice(2)}`);
    const activeInstanceNameRef = useRef<string | null>(null);
    const playerRef = useRef<FWDUVPInstance | null>(null);
    const listenerCleanupRef = useRef<(() => void) | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const parentId = `${idPrefix.current}-player`;
    const playlistsId = `${idPrefix.current}-playlists`;
    const playlistId = `${idPrefix.current}-playlist`;
    const trackSources = useMemo(() => toFWDUVPTrackSources(queue), [queue]);
    const safeQueueIndex = Math.max(0, Math.min(queueIndex, Math.max(0, queue.length - 1)));
    const playerHeight = mode === 'lounge_live' ? 118 : 156;
    const showQueueControls = queue.length > 1 && mode !== 'lounge_live';

    const cleanupPlayer = () => {
      listenerCleanupRef.current?.();
      listenerCleanupRef.current = null;

      const player = playerRef.current;
      if (player) {
        try {
          player.stop();
          player.destroy?.();
        } catch {
          // The vendor player may already be mid-destroy during navigation.
        }
      }

      playerRef.current = null;
      if (activeInstanceNameRef.current && typeof window !== 'undefined') {
        delete (window as unknown as Record<string, unknown>)[activeInstanceNameRef.current];
      }
      activeInstanceNameRef.current = null;

      const parent = document.getElementById(parentId);
      if (parent) parent.innerHTML = '';
    };

    const seekPlayerToSeconds = useCallback((seconds: number) => {
      const player = playerRef.current;
      if (!player) return;

      const safeSeconds = Math.max(0, seconds);

      if (player.scrubbAtTime) {
        player.scrubbAtTime(secondsToFWDUVPTime(safeSeconds));
        onTimeUpdate(safeSeconds);
        return;
      }

      const totalSeconds = parseFWDUVPTime(player.getTotalTime?.('text'));
      if (player.scrub && totalSeconds > 0) {
        player.scrub(Math.min(1, safeSeconds / totalSeconds));
        onTimeUpdate(safeSeconds);
      }
    }, [onTimeUpdate]);

    useImperativeHandle(ref, () => ({
      pause() {
        playerRef.current?.pause();
        onPause();
      },
      play() {
        playerRef.current?.play();
      },
      seekToSeconds(seconds) {
        seekPlayerToSeconds(seconds);
      },
      setVolume(nextVolume) {
        playerRef.current?.setVolume?.(Math.min(1, Math.max(0, nextVolume)));
      },
      stop() {
        playerRef.current?.stop();
        onStop();
      },
    }), [onPause, onStop, seekPlayerToSeconds]);

    useEffect(() => {
      playerRef.current?.setVolume?.(Math.min(1, Math.max(0, volume)));
    }, [volume]);

    useEffect(() => {
      if (queue.length === 0) {
        cleanupPlayer();
        return;
      }

      let cancelled = false;

      loadFWDUVPlayer()
        .then(() => {
          if (cancelled || !window.FWDUVPlayer) return;

          cleanupPlayer();
          setLoadError(null);

          const instanceName = `${instancePrefix.current}_${Date.now()}_${playbackRequest.revision}`;
          activeInstanceNameRef.current = instanceName;

          const player = new window.FWDUVPlayer({
            instanceName,
            parentId,
            playlistsId,
            mainFolderPath: FWDUVP_CONTENT_PATH,
            skinPath: 'minimal_skin_dark',
            displayType: 'responsive',
            initializeOnlyWhenVisible: 'no',
            autoPlay: playbackRequest.autoplay ? 'yes' : 'no',
            autoPlayText: '',
            autoScale: 'no',
            maxWidth: 980,
            maxHeight: playerHeight,
            startAtPlaylist: 0,
            startAtVideo: safeQueueIndex,
            backgroundColor: '#050505',
            videoBackgroundColor: '#050505',
            posterBackgroundColor: '#050505',
            playlistBackgroundColor: '#050505',
            showPoster: 'yes',
            fillEntireVideoScreen: 'no',
            fillEntireposterScreen: 'yes',
            showPlaylistsSearchInput: 'no',
            showSearchInput: 'no',
            usePlaylistsSelectBox: 'no',
            showPlaylistsButtonAndPlaylists: 'no',
            showPlaylistsByDefault: 'no',
            showPlaylistButtonAndPlaylist: 'no',
            showPlaylistByDefault: 'no',
            showPlaylistName: 'no',
            playlistPosition: 'bottom',
            playlistBottomHeight: 0,
            maxPlaylistItems: 24,
            randomizePlaylist: 'no',
            stopAfterLastVideoHasPlayed: 'no',
            showDownloadButton: 'no',
            showShareButton: 'no',
            showEmbedButton: 'no',
            showFullScreenButton: 'no',
            showInfoButton: 'no',
            showLogo: 'no',
            showContextmenu: 'no',
            showShuffleButton: 'no',
            showLoopButton: 'no',
            showRewindButton: 'no',
            showChromecastButton: 'no',
            showPlaybackRateButton: 'no',
            showNextAndPrevButtons: showQueueControls ? 'yes' : 'no',
            showNextAndPrevButtonsInController: showQueueControls ? 'yes' : 'no',
            showControllerWhenVideoIsStopped: 'yes',
            folderAudioSecondTitleColor: '#999999',
            audioVisualizerLinesColor: '#ff1d1d',
            audioVisualizerCircleColor: '#ffb800',
            mainBackgroundImagePath: `${FWDUVP_CONTENT_PATH}/minimal_skin_dark/main-background.png`,
          });

          playerRef.current = player;
          player.setVolume?.(Math.min(1, Math.max(0, volume)));

          const autoplayTimers: number[] = [];
          let autoplayStarted = false;
          let playlistReady = false;
          let initialVideoRequested = false;

          const clearAutoplayTimers = () => {
            autoplayTimers.forEach((timer) => window.clearTimeout(timer));
            autoplayTimers.length = 0;
          };

          const syncStartPosition = () => {
            if (playbackRequest.startAtSeconds > 0) {
              player.scrubbAtTime?.(secondsToFWDUVPTime(playbackRequest.startAtSeconds));
            }
          };

          const attemptAutoplay = () => {
            if (cancelled || autoplayStarted || !playbackRequest.autoplay) return;

            try {
              if (playlistReady && !initialVideoRequested) {
                initialVideoRequested = true;
                player.playVideo?.(safeQueueIndex);
              }

              player.play();
            } catch {
              onPlaybackBlocked();
            }
          };

          const queueAutoplayAttempt = (delay: number) => {
            if (!playbackRequest.autoplay) return;

            const timer = window.setTimeout(attemptAutoplay, delay);
            autoplayTimers.push(timer);
          };

          const listeners: Array<[string | undefined, (event: FWDUVPEvent) => void]> = [
            [window.FWDUVPlayer.READY, () => {
              syncStartPosition();
              queueAutoplayAttempt(0);
              queueAutoplayAttempt(300);
            }],
            [window.FWDUVPlayer.LOAD_PLAYLIST_COMPLETE, () => {
              playlistReady = true;
              syncStartPosition();
              queueAutoplayAttempt(0);
              queueAutoplayAttempt(300);
            }],
            [window.FWDUVPlayer.SAFE_TO_SCRUB, () => {
              syncStartPosition();
              queueAutoplayAttempt(0);
            }],
            [window.FWDUVPlayer.PLAY, () => {
              autoplayStarted = true;
              clearAutoplayTimers();
              onPlay();
            }],
            [window.FWDUVPlayer.PAUSE, onPause],
            [window.FWDUVPlayer.STOP, onStop],
            [window.FWDUVPlayer.ERROR, () => onError('The new player could not load this track.')],
            [window.FWDUVPlayer.PLAY_COMPLETE, onPause],
            [window.FWDUVPlayer.UPDATE_TIME, (event) => {
              const nextTime = parseFWDUVPTime(event.currentTime);
              const nextDuration = parseFWDUVPTime(event.totalTime);
              if (nextTime > 0) onTimeUpdate(nextTime);
              if (nextDuration > 0) onDurationChange(nextDuration);
            }],
            [window.FWDUVPlayer.UPDATE_VIDEO_SOURCE, (event) => {
              const nextSource = event.source || player.getVideoSource?.();
              if (!nextSource) return;

              const nextIndex = trackSources.findIndex((trackSource) => trackSource.source === nextSource);
              const nextTrack = nextIndex >= 0 ? queue[nextIndex] : null;

              if (nextTrack) onTrackChange(nextTrack, nextIndex);
            }],
          ];

          listeners.forEach(([eventName, handler]) => {
            if (eventName) player.addListener?.(eventName, handler);
          });

          listenerCleanupRef.current = () => {
            clearAutoplayTimers();
            listeners.forEach(([eventName, handler]) => {
              if (eventName) player.removeListener?.(eventName, handler);
            });
          };

          queueAutoplayAttempt(0);
          queueAutoplayAttempt(500);
          queueAutoplayAttempt(1200);
        })
        .catch((loadErrorValue) => {
          const message = loadErrorValue instanceof Error ? loadErrorValue.message : 'Unable to load the new player.';
          setLoadError(message);
          onError(message);
        });

      return () => {
        cancelled = true;
        cleanupPlayer();
      };
    }, [
      onDurationChange,
      onError,
      onPause,
      onPlay,
      onPlaybackBlocked,
      onStop,
      onTimeUpdate,
      onTrackChange,
      parentId,
      playbackRequest.autoplay,
      playbackRequest.revision,
      playbackRequest.startAtSeconds,
      playerHeight,
      playlistsId,
      queue,
      queue.length,
      safeQueueIndex,
      showQueueControls,
      trackSources,
      volume,
    ]);

    if (!currentTrack) return null;

    const displayDuration = duration || currentTrack.duration || 0;
    const safeDuration = Math.max(1, displayDuration);
    const safeCurrentTime = Math.max(0, Math.min(currentTime, safeDuration));
    const statusText = loadError || error || (playbackBlocked ? 'Tap play to start lounge music.' : currentTrack.artist || currentTrack.meta || 'BlendBeats');

    return (
      <div className="fixed inset-x-0 bottom-0 z-50 border-t border-[#2a2a2a] bg-[#080808]/95 px-4 py-3 text-white shadow-2xl shadow-black/60 backdrop-blur lg:px-8">
        <style>
          {`
            @keyframes blendbeats-player-bar {
              0% { transform: scaleY(0.35); }
              45% { transform: scaleY(1); }
              100% { transform: scaleY(0.55); }
            }
          `}
        </style>

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
              <p className="truncate text-xs text-[#888888]">{statusText}</p>
              {typeof currentTrack.countValue === 'number' && (
                <p className="mt-0.5 text-[10px] font-bold uppercase tracking-widest text-[#777777]">
                  {new Intl.NumberFormat('en', { notation: currentTrack.countValue >= 10000 ? 'compact' : 'standard' }).format(currentTrack.countValue)}{' '}
                  {currentTrack.countLabel || 'plays'}
                </p>
              )}
            </div>
          </div>

          <PlayerVisualizer isPlaying={isPlaying} hasError={Boolean(loadError || error)} />

          <div className="grid gap-2">
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={onTogglePlay}
                className="inline-flex h-10 w-10 shrink-0 items-center justify-center bg-primary text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                aria-label={isPlaying ? 'Pause current track' : 'Play current track'}
                disabled={Boolean(loadError)}
              >
                {isPlaying ? <Pause size={17} /> : <Play size={17} fill="currentColor" />}
              </button>
              <input
                type="range"
                min="0"
                max={safeDuration}
                step="1"
                value={safeCurrentTime}
                onChange={(event) => seekPlayerToSeconds(Number(event.currentTarget.value))}
                disabled={displayDuration <= 0 || Boolean(loadError)}
                aria-label="Player progress"
                className="h-2 flex-1 accent-primary disabled:opacity-50"
              />
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
              onChange={(event) => onVolumeChange(Number(event.currentTarget.value))}
              aria-label="Player volume"
              className="w-20 accent-primary"
            />
            <button
              type="button"
              onClick={() => {
                playerRef.current?.stop();
                onStop();
              }}
              className="inline-flex h-10 w-10 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              aria-label="Stop playback"
            >
              <CircleStop size={16} />
            </button>
            <button
              type="button"
              onClick={() => {
                playerRef.current?.stop();
                onStop();
              }}
              className="inline-flex h-10 w-10 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              aria-label="Close player"
            >
              <X size={16} />
            </button>
          </div>
        </div>

        {!loadError && (
          <div
            aria-hidden="true"
            className="pointer-events-none absolute top-0 overflow-hidden opacity-0"
            style={{ left: -10000, width: 980, height: playerHeight }}
          >
            <div id={parentId} className="w-full overflow-hidden" style={{ height: playerHeight }} />
          </div>
        )}

        {loadError && (
          <p className="container mx-auto mt-2 max-w-6xl text-xs text-primary">
            {loadError}
          </p>
        )}

        <ul id={playlistsId} style={{ display: 'none' }}>
          <li
            data-source={playlistId}
            data-playlist-name={mode === 'lounge_live' ? 'DJ Lounge Live' : 'BlendBeats Queue'}
            data-thumbnail-path={trackSources[safeQueueIndex]?.poster || FALLBACK_ARTWORK}
          />
        </ul>

        <ul id={playlistId} style={{ display: 'none' }}>
          {trackSources.map((source, index) => {
            const track = queue[index];
            const poster = source.poster || FALLBACK_ARTWORK;
            const subtitle = source.subtitle || track?.meta || 'BlendBeats';

            return (
              <li
                key={track?.id ?? `${source.source}-${index}`}
                data-thumb-source={poster}
                data-video-source={source.source}
                data-poster-source={poster}
                data-downloadable="no"
                data-scrub-at-time-at-first-play={index === safeQueueIndex && playbackRequest.startAtSeconds > 0 ? secondsToFWDUVPTime(playbackRequest.startAtSeconds) : undefined}
              >
                <div data-video-short-description="">
                  <p className="fwduvp-thumbnail-title">{source.title || track?.title || 'BlendBeats'}</p>
                  <p className="fwduvp-thumbnail-description">{subtitle}</p>
                </div>
                <div data-video-long-description="">
                  <p className="fwduvp-video-title">{source.title || track?.title || 'BlendBeats'}</p>
                  <p className="fwduvp-video-main-description">{subtitle}</p>
                </div>
              </li>
            );
          })}
        </ul>
      </div>
    );
  },
);
