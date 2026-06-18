import type { PlayerTrack } from './PlayerProvider';
import type { FWDUVPSourceType, FWDUVPTrackSource } from './fwduvp-types';

function normalizeSource(src: string) {
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

function inferSourceType(src: string): FWDUVPSourceType {
  const normalizedSrc = src.toLowerCase();

  if (normalizedSrc.includes('youtube.com') || normalizedSrc.includes('youtu.be')) return 'youtube';
  if (normalizedSrc.includes('vimeo.com')) return 'vimeo';
  if (normalizedSrc.includes('.m3u8')) return 'hls';
  if (normalizedSrc.includes('.mpd')) return 'dash';
  if (/\.(mp4|webm|mov)(\?|#|$)/.test(normalizedSrc)) return 'video';

  return 'audio';
}

export function toFWDUVPTrackSource(track: PlayerTrack): FWDUVPTrackSource {
  const source = normalizeSource(track.src);

  return {
    source,
    sourceType: inferSourceType(source),
    poster: track.artwork ? normalizeSource(track.artwork) : null,
    title: track.title,
    subtitle: track.artist ?? track.meta ?? null,
  };
}

export function toFWDUVPTrackSources(tracks: PlayerTrack[]) {
  return tracks.map(toFWDUVPTrackSource);
}
