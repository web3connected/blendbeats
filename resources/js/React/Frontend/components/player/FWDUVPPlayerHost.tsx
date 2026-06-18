import { forwardRef, useEffect, useImperativeHandle, useMemo, useRef, useState } from 'react';

import type { PlayerMode, PlayerTrack } from './player-types';
import { FWDUVP_CONTENT_PATH, loadFWDUVPlayer } from './fwduvp-loader';
import { toFWDUVPTrackSources } from './fwduvp-playlist';
import type { FWDUVPEvent, FWDUVPInstance } from './fwduvp-types';

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
  mode: PlayerMode;
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
};

function toElementId(value: string | number) {
  return String(value).replace(/[^a-zA-Z0-9_-]/g, '-');
}

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

export const FWDUVPPlayerHost = forwardRef<FWDUVPPlayerHostHandle, FWDUVPPlayerHostProps>(
  function FWDUVPPlayerHost({
    currentTrack,
    mode,
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
  }, ref) {
    const idPrefix = useRef(`bb-fwduvp-${Math.random().toString(36).slice(2)}`);
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

      const parent = document.getElementById(parentId);
      if (parent) parent.innerHTML = '';
    };

    useImperativeHandle(ref, () => ({
      pause() {
        playerRef.current?.pause();
        onPause();
      },
      play() {
        playerRef.current?.play();
      },
      seekToSeconds(seconds) {
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
      },
      setVolume(nextVolume) {
        playerRef.current?.setVolume?.(Math.min(1, Math.max(0, nextVolume)));
      },
      stop() {
        playerRef.current?.stop();
        onStop();
      },
    }), [onPause, onStop, onTimeUpdate]);

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

          const player = new window.FWDUVPlayer({
            instanceName: idPrefix.current,
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

          const listeners: Array<[string | undefined, (event: FWDUVPEvent) => void]> = [
            [window.FWDUVPlayer.READY, () => {
              if (playbackRequest.startAtSeconds > 0) {
                player.scrubbAtTime?.(secondsToFWDUVPTime(playbackRequest.startAtSeconds));
              }

              if (playbackRequest.autoplay) {
                try {
                  player.play();
                } catch {
                  onPlaybackBlocked();
                }
              }
            }],
            [window.FWDUVPlayer.PLAY, onPlay],
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
            listeners.forEach(([eventName, handler]) => {
              if (eventName) player.removeListener?.(eventName, handler);
            });
          };
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

    return (
      <div className="fixed inset-x-0 bottom-0 z-50 border-t border-[#2a2a2a] bg-[#050505]/96 px-3 py-3 text-white shadow-2xl shadow-black/60 backdrop-blur lg:px-8">
        <div className="mx-auto max-w-5xl">
          {mode === 'lounge_live' && (
            <p
              className="mb-2 text-[10px] font-bold uppercase tracking-widest text-[#FFB800]"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              DJ Lounge Live
            </p>
          )}
          {loadError ? (
            <div className="border border-[#303030] bg-[#080808] p-4 text-sm text-[#dddddd]">
              {loadError}
            </div>
          ) : (
            <div id={parentId} className="w-full overflow-hidden" style={{ height: playerHeight }} />
          )}
        </div>

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
