import { ExternalLink, Megaphone } from 'lucide-react';
import { useEffect, useRef } from 'react';

import { useDisplayAdvertisement } from '@/hooks/useDisplayAdvertisement';
import { trackAdvertisementEvent } from '@/lib/advertisements';

type UniversalAdCardProps = {
  placement: string;
  title?: string;
  compact?: boolean;
};

export default function UniversalAdCard({ placement, title = 'Featured Ad', compact = false }: UniversalAdCardProps) {
  const { ad, isLoading } = useDisplayAdvertisement({ placement });
  const containerRef = useRef<HTMLElement | null>(null);
  const isInViewRef = useRef(false);
  const impressionTrackedRef = useRef(false);

  useEffect(() => {
    impressionTrackedRef.current = false;
    isInViewRef.current = false;
  }, [ad?.id, placement]);

  useEffect(() => {
    if (!ad || !containerRef.current || impressionTrackedRef.current) return;

    const trackIfFocused = () => {
      if (!ad || impressionTrackedRef.current || !isInViewRef.current || document.visibilityState !== 'visible') return;

      impressionTrackedRef.current = true;
      trackAdvertisementEvent(ad, placement, 'impression');
    };

    const observer = new IntersectionObserver(
      ([entry]) => {
        isInViewRef.current = Boolean(entry?.isIntersecting && entry.intersectionRatio >= 0.5);
        trackIfFocused();
      },
      { threshold: [0.5] },
    );

    observer.observe(containerRef.current);
    document.addEventListener('visibilitychange', trackIfFocused);

    return () => {
      observer.disconnect();
      document.removeEventListener('visibilitychange', trackIfFocused);
    };
  }, [ad, placement]);

  if (isLoading || !ad) {
    return null;
  }

  return (
    <section ref={containerRef} className={`border border-[#2a2a2a] bg-[#111111] ${compact ? 'p-4' : 'p-5'}`}>
      <div className={`${compact ? 'mb-3' : 'mb-4'} flex items-center gap-2`}>
        <Megaphone size={compact ? 16 : 18} className="text-primary" />
        <h2 className={`${compact ? 'text-xl' : 'text-2xl'} uppercase text-white`} style={{ fontFamily: 'var(--font-heading)' }}>
          {title}
        </h2>
      </div>

      <a
        href={ad.url}
        onClick={() => trackAdvertisementEvent(ad, placement, 'click')}
        className="group block border border-[#282828] bg-[#090909] transition-colors hover:border-primary"
      >
        <div className={`${compact ? 'flex h-28 items-center justify-center' : 'aspect-square'} overflow-hidden bg-[#050505]`}>
          {ad.image_url ? (
            <img
              src={ad.image_url}
              alt={ad.title || title}
              className={`${compact ? 'h-20 w-20 rounded-full border-2 border-[#f2f2f2]' : 'h-full w-full'} bg-[#090909] object-cover transition-transform duration-300 group-hover:scale-105`}
            />
          ) : (
            <div className={`${compact ? 'h-20 w-20 rounded-full text-2xl' : 'h-full w-full text-4xl'} flex items-center justify-center bg-primary font-black uppercase text-white`}>
              {(ad.title || 'Ad').slice(0, 1)}
            </div>
          )}
        </div>
        <div className={compact ? 'p-3' : 'p-4'}>
          <p className="text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
            Group {ad.campaign.group} / Slot {ad.campaign.slot}
          </p>
          <h3 className={`${compact ? 'text-xl' : 'text-2xl'} mt-2 uppercase text-white`} style={{ fontFamily: 'var(--font-heading)' }}>
            {ad.title}
          </h3>
          {ad.subtitle && <p className="mt-1 text-sm text-[#cccccc]">{ad.subtitle}</p>}
          {ad.description && <p className={`${compact ? 'line-clamp-2' : 'line-clamp-3'} mt-3 text-sm leading-6 text-[#888888]`}>{ad.description}</p>}
          <span
            className={`${compact ? 'h-9' : 'h-10'} mt-4 inline-flex items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white`}
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            View Ad
            <ExternalLink size={14} />
          </span>
        </div>
      </a>
    </section>
  );
}
