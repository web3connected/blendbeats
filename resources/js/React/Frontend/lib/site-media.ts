const SITE_MEDIA_ROOT = import.meta.env.VITE_SITE_MEDIA_ROOT?.replace(/\/+$/, '') ?? '/media/site';

export function siteMedia(path: string): string | undefined {
  return `${SITE_MEDIA_ROOT}/${path.replace(/^\/+/, '')}`;
}

export function legacySiteMedia(path: string): string | undefined {
  return siteMedia(path.replace(/^\/?airo-assets\//, ''));
}
