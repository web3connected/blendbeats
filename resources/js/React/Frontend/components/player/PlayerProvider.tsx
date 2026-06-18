import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

import {
  FWDUVPPlayerHost,
  type FWDUVPPlaybackRequest,
  type FWDUVPPlayerHostHandle,
} from './FWDUVPPlayerHost';
import { LegacyAudioPlayerHost, type LegacyAudioPlayerHandle, resolvePlayableSource } from './LegacyAudioPlayerHost';
import type { PlayerMode, PlayerQueueOptions, PlayerTrack } from './player-types';

export type { PlayerMode, PlayerQueueOptions, PlayerTrack } from './player-types';

type PlayerContextValue = {
  currentTrack: PlayerTrack | null;
  isPlaying: boolean;
  error: string | null;
  mode: PlayerMode;
  playbackBlocked: boolean;
  playTrack: (track: PlayerTrack) => void;
  updateCurrentTrack: (patch: Partial<PlayerTrack>) => void;
  loadQueue: (options: PlayerQueueOptions) => void;
  togglePlay: () => void;
  stop: () => void;
};

type PlayerEngine = 'legacy' | 'fwduvp';

const PLAYER_ENGINE: PlayerEngine = import.meta.env.VITE_PLAYER_ENGINE === 'fwduvp' ? 'fwduvp' : 'legacy';

const PlayerContext = createContext<PlayerContextValue | undefined>(undefined);

function clampVolume(volume: number) {
  return Math.min(1, Math.max(0, volume));
}

function normalizeTrack(track: PlayerTrack): PlayerTrack {
  return {
    ...track,
    src: resolvePlayableSource(track.src),
    artwork: track.artwork ? resolvePlayableSource(track.artwork) : track.artwork,
  };
}

export function PlayerProvider({ children }: { children: ReactNode }) {
  const legacyPlayerRef = useRef<LegacyAudioPlayerHandle | null>(null);
  const fwduvpPlayerRef = useRef<FWDUVPPlayerHostHandle | null>(null);
  const currentTrackRef = useRef<PlayerTrack | null>(null);
  const queueRef = useRef<PlayerTrack[]>([]);
  const queueIndexRef = useRef(0);
  const currentTimeRef = useRef(0);
  const modeRef = useRef<PlayerMode>('standard');
  const playlistVersionRef = useRef<string | null>(null);
  const [currentTrack, setCurrentTrack] = useState<PlayerTrack | null>(null);
  const [queue, setQueue] = useState<PlayerTrack[]>([]);
  const [queueIndex, setQueueIndex] = useState(0);
  const [mode, setMode] = useState<PlayerMode>('standard');
  const [playlistVersion, setPlaylistVersion] = useState<string | null>(null);
  const [playbackBlocked, setPlaybackBlocked] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [, setDuration] = useState(0);
  const [currentTime, setCurrentTime] = useState(0);
  const [volume, setVolume] = useState(0.85);
  const [fwduvpPlaybackRequest, setFwduvpPlaybackRequest] = useState<FWDUVPPlaybackRequest>({
    autoplay: false,
    revision: 0,
    startAtSeconds: 0,
  });

  useEffect(() => {
    currentTrackRef.current = currentTrack;
  }, [currentTrack]);

  useEffect(() => {
    queueRef.current = queue;
  }, [queue]);

  useEffect(() => {
    queueIndexRef.current = queueIndex;
  }, [queueIndex]);

  useEffect(() => {
    currentTimeRef.current = currentTime;
  }, [currentTime]);

  useEffect(() => {
    modeRef.current = mode;
  }, [mode]);

  useEffect(() => {
    playlistVersionRef.current = playlistVersion;
  }, [playlistVersion]);

  const handlePlay = useCallback(() => {
    setIsPlaying(true);
    setPlaybackBlocked(false);
  }, []);

  const handlePause = useCallback(() => {
    setIsPlaying(false);
  }, []);

  const handlePlaybackBlocked = useCallback(() => {
    setIsPlaying(false);
    setPlaybackBlocked(true);
  }, []);

  const handleError = useCallback((message: string) => {
    setIsPlaying(false);
    setError(message);
  }, []);

  const handleTimeUpdate = useCallback((time: number) => {
    setCurrentTime(time);
  }, []);

  const handleDurationChange = useCallback((nextDuration: number) => {
    setDuration(nextDuration);
  }, []);

  const resetPlayerState = useCallback(() => {
    setCurrentTrack(null);
    setQueue([]);
    setQueueIndex(0);
    setMode('standard');
    setPlaylistVersion(null);
    setPlaybackBlocked(false);
    setIsPlaying(false);
    setError(null);
    setCurrentTime(0);
    setDuration(0);
  }, []);

  const playQueuedTrack = useCallback((track: PlayerTrack, startAtSeconds = 0, autoplay = true) => {
    const playableTrack = normalizeTrack(track);
    const safeStartAtSeconds = Math.max(0, startAtSeconds);

    setError(null);
    setPlaybackBlocked(false);
    setCurrentTrack(playableTrack);
    setCurrentTime(safeStartAtSeconds);
    setDuration(playableTrack.duration ?? 0);

    if (PLAYER_ENGINE === 'fwduvp') {
      setFwduvpPlaybackRequest((currentRequest) => ({
        autoplay,
        revision: currentRequest.revision + 1,
        startAtSeconds: safeStartAtSeconds,
      }));
      return;
    }

    legacyPlayerRef.current?.loadTrack(playableTrack, safeStartAtSeconds, autoplay);
  }, []);

  const playTrack = useCallback((track: PlayerTrack) => {
    setQueue([]);
    setQueueIndex(0);
    setMode('standard');
    setPlaylistVersion(null);
    setPlaybackBlocked(false);
    playQueuedTrack(track, 0, true);
  }, [playQueuedTrack]);

  const updateCurrentTrack = useCallback((patch: Partial<PlayerTrack>) => {
    setCurrentTrack((track) => (track ? { ...track, ...patch } : track));
    setQueue((currentQueue) =>
      currentQueue.map((track) =>
        currentTrackRef.current && String(track.id) === String(currentTrackRef.current.id) ? { ...track, ...patch } : track,
      ),
    );
  }, []);

  const loadQueue = useCallback(({
    tracks,
    mode: nextMode = 'standard',
    currentTrackId = null,
    currentPositionSeconds = 0,
    playlistVersion: nextPlaylistVersion = null,
    volume: nextVolume,
    autoplay = true,
  }: PlayerQueueOptions) => {
    const playableTracks = tracks
      .filter((track) => track.src)
      .map(normalizeTrack);

    if (playableTracks.length === 0) return;

    const foundIndex = playableTracks.findIndex((track) => String(track.id) === String(currentTrackId ?? playableTracks[0].id));
    const nextIndex = foundIndex >= 0 ? foundIndex : 0;
    const nextTrack = playableTracks[nextIndex] ?? playableTracks[0];
    const isSameLiveState = nextMode === modeRef.current
      && nextPlaylistVersion
      && nextPlaylistVersion === playlistVersionRef.current
      && currentTrackRef.current
      && String(currentTrackRef.current.id) === String(nextTrack.id);

    setQueue(playableTracks);
    setQueueIndex(nextIndex);
    setMode(nextMode);
    setPlaylistVersion(nextPlaylistVersion);

    if (typeof nextVolume === 'number') {
      const safeVolume = clampVolume(nextVolume);
      setVolume(safeVolume);
      fwduvpPlayerRef.current?.setVolume(safeVolume);
    }

    if (isSameLiveState) {
      const drift = Math.abs(currentTimeRef.current - currentPositionSeconds);

      if (drift > 8) {
        if (PLAYER_ENGINE === 'fwduvp') {
          fwduvpPlayerRef.current?.seekToSeconds(currentPositionSeconds);
        } else {
          legacyPlayerRef.current?.seekToSeconds(currentPositionSeconds);
        }

        setCurrentTime(Math.max(0, currentPositionSeconds));
      }

      return;
    }

    setPlaybackBlocked(false);
    playQueuedTrack(nextTrack, currentPositionSeconds, autoplay);
  }, [playQueuedTrack]);

  const playNextQueuedTrack = useCallback(() => {
    const currentQueue = queueRef.current;

    if (currentQueue.length <= 1) {
      setIsPlaying(false);
      return;
    }

    const nextIndex = (queueIndexRef.current + 1) % currentQueue.length;
    const nextTrack = currentQueue[nextIndex];

    setQueueIndex(nextIndex);
    playQueuedTrack(nextTrack, 0, true);
  }, [playQueuedTrack]);

  const togglePlay = useCallback(() => {
    if (!currentTrackRef.current) return;

    if (PLAYER_ENGINE === 'fwduvp') {
      if (isPlaying) {
        fwduvpPlayerRef.current?.pause();
        setIsPlaying(false);
        return;
      }

      fwduvpPlayerRef.current?.play();
      return;
    }

    if (isPlaying) {
      legacyPlayerRef.current?.pause();
      setIsPlaying(false);
      return;
    }

    legacyPlayerRef.current?.play();
  }, [isPlaying]);

  const stop = useCallback(() => {
    if (PLAYER_ENGINE === 'fwduvp') {
      fwduvpPlayerRef.current?.stop();
    } else {
      legacyPlayerRef.current?.stop();
    }

    resetPlayerState();
  }, [resetPlayerState]);

  const handleFWDUVPTrackChange = useCallback((track: PlayerTrack, index: number) => {
    setCurrentTrack(track);
    setQueueIndex(index);
    setCurrentTime(0);
    setDuration(track.duration ?? 0);
  }, []);

  const value = useMemo(
    () => ({ currentTrack, isPlaying, error, mode, playbackBlocked, playTrack, updateCurrentTrack, loadQueue, togglePlay, stop }),
    [currentTrack, isPlaying, error, mode, playbackBlocked, playTrack, updateCurrentTrack, loadQueue, togglePlay, stop],
  );

  const fwduvpQueue = useMemo(() => (queue.length > 0 ? queue : currentTrack ? [currentTrack] : []), [currentTrack, queue]);
  const fwduvpQueueIndex = queue.length > 0 ? queueIndex : 0;

  return (
    <PlayerContext.Provider value={value}>
      {children}

      {PLAYER_ENGINE === 'fwduvp' ? (
        <FWDUVPPlayerHost
          ref={fwduvpPlayerRef}
          currentTrack={currentTrack}
          mode={mode}
          playbackRequest={fwduvpPlaybackRequest}
          queue={fwduvpQueue}
          queueIndex={fwduvpQueueIndex}
          volume={volume}
          onDurationChange={handleDurationChange}
          onError={handleError}
          onPause={handlePause}
          onPlay={handlePlay}
          onPlaybackBlocked={handlePlaybackBlocked}
          onStop={resetPlayerState}
          onTimeUpdate={handleTimeUpdate}
          onTrackChange={handleFWDUVPTrackChange}
        />
      ) : (
        <LegacyAudioPlayerHost
          ref={legacyPlayerRef}
          currentTrack={currentTrack}
          mode={mode}
          volume={volume}
          isPlaying={isPlaying}
          error={error}
          playbackBlocked={playbackBlocked}
          onDurationChange={handleDurationChange}
          onEnded={playNextQueuedTrack}
          onError={handleError}
          onPause={handlePause}
          onPlaybackBlocked={handlePlaybackBlocked}
          onPlay={handlePlay}
          onStop={resetPlayerState}
          onTimeUpdate={handleTimeUpdate}
          onTogglePlay={togglePlay}
          onVolumeChange={(nextVolume) => setVolume(clampVolume(nextVolume))}
        />
      )}
    </PlayerContext.Provider>
  );
}

export function usePlayer() {
  const context = useContext(PlayerContext);
  if (!context) throw new Error('usePlayer must be used within PlayerProvider');
  return context;
}
