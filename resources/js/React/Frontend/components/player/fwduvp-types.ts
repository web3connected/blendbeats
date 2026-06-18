export type FWDUVPStatus = 'idle' | 'loading' | 'ready' | 'error';

export type FWDUVPSourceType = 'audio' | 'video' | 'youtube' | 'vimeo' | 'hls' | 'dash';

export type FWDUVPTrackSource = {
  source: string;
  sourceType: FWDUVPSourceType;
  poster?: string | null;
  title?: string | null;
  subtitle?: string | null;
};

export type FWDUVPInstance = {
  play: () => void;
  pause: () => void;
  stop: () => void;
  playNext?: () => void;
  playPrev?: () => void;
  playVideo?: (videoId: number) => void;
  loadPlaylist?: (playlistId: number) => void;
  scrub?: (percent: number) => void;
  setVolume?: (volume: number) => void;
  destroy?: () => void;
};

export type FWDUVPConstructorOptions = Record<string, unknown>;

type FWDUVPConstructor = new (
  options: FWDUVPConstructorOptions,
) => FWDUVPInstance;

declare global {
  interface Window {
    FWDUVPlayer?: FWDUVPConstructor;
    FWDUVPUtils?: unknown;
  }
}
