import { ExternalLink, Megaphone } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { getDisplayAdvertisement, trackAdvertisementEvent, type UniversalAdvertisement } from '@/lib/advertisements';

type UniversalAdCardProps = {
  placement: string;
  title?: string;
};

export default function UniversalAdCard({ placement, title = 'Featured Ad' }: UniversalAdCardProps) {
  const [ad, setAd] = useState<UniversalAdvertisement | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const containerRef = useRef<HTMLElement | null>(null);
  const isInViewRef = useRef(false);
  const impressionTrackedRef = useRef(false);

  useEffect(() => {
    let cancelled = false;

    setIsLoading(true);
    getDisplayAdvertisement(placement)
      .then((nextAd) => {
        if (!cancelled) setAd(nextAd);
      })
      .catch(() => {
        if (!cancelled) setAd(null);
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [placement]);

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
    <section ref={containerRef} className="border border-[#2a2a2a] bg-[#111111] p-5">
      <div className="mb-4 flex items-center gap-2">
        <Megaphone size={18} className="text-primary" />
        <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          {title}
        </h2>
      </div>

      <a
        href={ad.url}
        onClick={() => trackAdvertisementEvent(ad, placement, 'click')}
        className="group block border border-[#282828] bg-[#090909] transition-colors hover:border-primary"
      >
        <div className="aspect-square overflow-hidden bg-[#050505]">
          {ad.image_url ? (
            <img src={ad.image_url} alt={ad.title || title} className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" />
          ) : (
            <div className="flex h-full w-full items-center justify-center bg-primary text-4xl font-black uppercase text-white">
              {(ad.title || 'Ad').slice(0, 1)}
            </div>
          )}
        </div>
        <div className="p-4">
          <p className="text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">
            Group {ad.campaign.group} / Slot {ad.campaign.slot}
          </p>
          <h3 className="mt-2 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {ad.title}
          </h3>
          {ad.subtitle && <p className="mt-1 text-sm text-[#cccccc]">{ad.subtitle}</p>}
          {ad.description && <p className="mt-3 line-clamp-3 text-sm leading-6 text-[#888888]">{ad.description}</p>}
          <span
            className="mt-4 inline-flex h-10 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white"
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
