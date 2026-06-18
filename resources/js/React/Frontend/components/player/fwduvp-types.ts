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
  addListener?: (eventName: string, handler: (event: FWDUVPEvent) => void) => void;
  destroy?: () => void;
  getCurrentTime?: (format?: string) => string;
  getPosterSource?: () => string;
  getTotalTime?: (format?: string) => string;
  getVideoSource?: () => string;
  hide?: () => void;
  loadPlaylist?: (playlistId: number) => void;
  play: () => void;
  playNext?: () => void;
  playPrev?: () => void;
  playVideo?: (videoId: number) => void;
  pause: () => void;
  removeListener?: (eventName: string, handler: (event: FWDUVPEvent) => void) => void;
  scrubbAtTime?: (time: string) => void;
  scrub?: (percent: number) => void;
  setVolume?: (volume: number) => void;
  show?: () => void;
  stop: () => void;
};

export type FWDUVPEvent = {
  currentTime?: string | number;
  percent?: number;
  source?: string;
  target?: unknown;
  totalTime?: string | number;
  type?: string;
};

export type FWDUVPConstructorOptions = Record<string, unknown>;

type FWDUVPConstructor = {
  new (options: FWDUVPConstructorOptions): FWDUVPInstance;
  ERROR?: string;
  LOAD_PLAYLIST_COMPLETE?: string;
  PAUSE?: string;
  PLAY?: string;
  PLAY_COMPLETE?: string;
  READY?: string;
  SAFE_TO_SCRUB?: string;
  START_TO_LOAD_PLAYLIST?: string;
  STOP?: string;
  UPDATE?: string;
  UPDATE_POSTER_SOURCE?: string;
  UPDATE_TIME?: string;
  UPDATE_VIDEO_SOURCE?: string;
};

declare global {
  interface Window {
    FWDUVPlayer?: FWDUVPConstructor;
    FWDUVPUtils?: unknown;
  }
}
