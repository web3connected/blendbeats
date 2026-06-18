import { useEffect, useState } from 'react';

import { getDisplayAdvertisement, type UniversalAdvertisement } from '@/lib/advertisements';

type UseDisplayAdvertisementOptions = {
  placement: string;
  enabled?: boolean;
  refreshKey?: string | number;
};

export function useDisplayAdvertisement({
  placement,
  enabled = true,
  refreshKey = 0,
}: UseDisplayAdvertisementOptions) {
  const [ad, setAd] = useState<UniversalAdvertisement | null>(null);
  const [isLoading, setIsLoading] = useState(enabled);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    if (!enabled || !placement) {
      setAd(null);
      setIsLoading(false);
      setError(null);
      return () => {
        isMounted = false;
      };
    }

    setIsLoading(true);
    setError(null);

    getDisplayAdvertisement(placement)
      .then((nextAd) => {
        if (!isMounted) return;
        setAd(nextAd);
      })
      .catch(() => {
        if (!isMounted) return;
        setAd(null);
        setError('Unable to load advertisement.');
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [enabled, placement, refreshKey]);

  return {
    ad,
    isLoading,
    error,
  };
}
