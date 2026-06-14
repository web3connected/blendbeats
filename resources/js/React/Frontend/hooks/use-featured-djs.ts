import { useEffect, useMemo, useState } from 'react';

import { getDjHubDjs, type DjHubDj } from '@/lib/dj-hub';

export const FEATURED_DJ_SLOT_COUNT = 24;
export const FEATURED_DJ_GROUP_SIZE = 4;
export const FEATURED_DJ_GROUP_WEIGHTS = [100, 80, 64, 51, 41, 33];

export type FeaturedDjSlot = {
  number: number;
  group: number;
  position: number;
  dj: DjHubDj | null;
};

function buildFeaturedSlots(djs: DjHubDj[]): FeaturedDjSlot[] {
  const djsBySlot = new Map(
    djs
      .filter((dj) => dj.featured_slot && dj.featured_slot >= 1 && dj.featured_slot <= FEATURED_DJ_SLOT_COUNT)
      .map((dj) => [dj.featured_slot as number, dj]),
  );

  return Array.from({ length: FEATURED_DJ_SLOT_COUNT }, (_, index) => ({
    number: index + 1,
    group: Math.floor(index / FEATURED_DJ_GROUP_SIZE) + 1,
    position: (index % FEATURED_DJ_GROUP_SIZE) + 1,
    dj: djsBySlot.get(index + 1) ?? djs[index] ?? null,
  }));
}

function pickWeightedGroupIndex() {
  const totalWeight = FEATURED_DJ_GROUP_WEIGHTS.reduce((total, weight) => total + weight, 0);
  let threshold = Math.random() * totalWeight;

  for (let index = 0; index < FEATURED_DJ_GROUP_WEIGHTS.length; index += 1) {
    threshold -= FEATURED_DJ_GROUP_WEIGHTS[index];

    if (threshold <= 0) return index;
  }

  return 0;
}

export function useFeaturedDjs() {
  const [featuredDjs, setFeaturedDjs] = useState<DjHubDj[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedGroupIndex] = useState(() => pickWeightedGroupIndex());

  useEffect(() => {
    let isMounted = true;

    setIsLoading(true);
    setError(null);

    getDjHubDjs({ sort: 'featured' })
      .then((data) => {
        if (!isMounted) return;
        setFeaturedDjs((data.featured_djs ?? []).slice(0, FEATURED_DJ_SLOT_COUNT));
      })
      .catch(() => {
        if (!isMounted) return;
        setError('Unable to load featured DJs.');
        setFeaturedDjs([]);
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const slots = useMemo(() => buildFeaturedSlots(featuredDjs), [featuredDjs]);
  const groups = useMemo(
    () =>
      Array.from({ length: FEATURED_DJ_SLOT_COUNT / FEATURED_DJ_GROUP_SIZE }, (_, index) =>
        slots.slice(index * FEATURED_DJ_GROUP_SIZE, (index + 1) * FEATURED_DJ_GROUP_SIZE),
      ),
    [slots],
  );

  return {
    featuredDjs,
    slots,
    groups,
    selectedGroup: groups[selectedGroupIndex] ?? groups[0] ?? [],
    selectedGroupNumber: selectedGroupIndex + 1,
    isLoading,
    error,
    getSlot: (slotNumber: number) => slots[slotNumber - 1] ?? null,
  };
}
