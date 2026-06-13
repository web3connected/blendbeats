const API_BASE = import.meta.env?.VITE_AUTH_API_BASE || '/api';

export interface LoungeLiveTrack {
  id: string;
  media_file_id: number;
  title: string;
  artist?: string | null;
  src: string;
  artwork?: string | null;
  genre?: string | null;
  duration?: number | null;
  featured?: boolean;
}

export interface LoungeLiveState {
  current_track: LoungeLiveTrack | null;
  next_track: LoungeLiveTrack | null;
  playlist: LoungeLiveTrack[];
  current_position_seconds: number;
  server_time: string;
  playlist_version: string;
  mode: 'lounge_live';
}

export async function getLoungeLiveState(): Promise<LoungeLiveState> {
  const response = await fetch(`${API_BASE}/lounge/live-state`, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error('DJ Lounge music is not available right now.');
  }

  return response.json() as Promise<LoungeLiveState>;
}
