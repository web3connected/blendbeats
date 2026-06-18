export type PlayerTrack = {
  id: string | number;
  title: string;
  artist?: string | null;
  src: string;
  artwork?: string | null;
  meta?: string | null;
  duration?: number | null;
  countLabel?: string | null;
  countValue?: number | null;
};

export type PlayerMode = 'standard' | 'lounge_live';

export type PlayerQueueOptions = {
  tracks: PlayerTrack[];
  mode?: PlayerMode;
  currentTrackId?: string | number | null;
  currentPositionSeconds?: number;
  playlistVersion?: string | null;
  volume?: number;
  autoplay?: boolean;
};
