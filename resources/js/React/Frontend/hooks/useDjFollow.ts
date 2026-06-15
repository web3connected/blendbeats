import { useEffect, useState } from 'react';

import { followDj, unfollowDj } from '@/lib/dj-hub';

type UseDjFollowOptions = {
  handle: string;
  initialIsFollowing: boolean;
  initialFollowersCount: number;
  onUnauthenticated?: () => void;
  onChange?: (state: { isFollowing: boolean; followersCount: number }) => void;
};

export function useDjFollow({
  handle,
  initialIsFollowing,
  initialFollowersCount,
  onUnauthenticated,
  onChange,
}: UseDjFollowOptions) {
  const [isFollowing, setIsFollowing] = useState(initialIsFollowing);
  const [followersCount, setFollowersCount] = useState(initialFollowersCount);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    setIsFollowing(initialIsFollowing);
    setFollowersCount(initialFollowersCount);
    setError('');
  }, [handle, initialIsFollowing, initialFollowersCount]);

  async function toggleFollow() {
    if (isSaving) return;

    setIsSaving(true);
    setError('');

    try {
      const nextState = isFollowing ? await unfollowDj(handle) : await followDj(handle);
      setIsFollowing(nextState.is_following);
      setFollowersCount(nextState.followers_count);
      onChange?.({
        isFollowing: nextState.is_following,
        followersCount: nextState.followers_count,
      });
    } catch (followError) {
      const status = followError instanceof Error && 'response' in followError
        ? (followError as Error & { response?: { status: number } }).response?.status
        : null;

      if (status === 401) {
        onUnauthenticated?.();
        return;
      }

      setError(followError instanceof Error ? followError.message : 'Unable to update follow state.');
    } finally {
      setIsSaving(false);
    }
  }

  return {
    isFollowing,
    followersCount,
    isSaving,
    error,
    toggleFollow,
  };
}
