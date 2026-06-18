import { useCallback, useEffect, useState } from 'react';
import { loadFWDUVPlayer } from './fwduvp-loader';
import type { FWDUVPStatus } from './fwduvp-types';

export function useFWDUVPlayer({ eager = false }: { eager?: boolean } = {}) {
  const [status, setStatus] = useState<FWDUVPStatus>('idle');
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setStatus('loading');
    setError(null);

    try {
      await loadFWDUVPlayer();
      setStatus('ready');
    } catch (loadError) {
      const message = loadError instanceof Error ? loadError.message : 'Unable to load FWDUVPlayer.';
      setError(message);
      setStatus('error');
    }
  }, []);

  useEffect(() => {
    if (eager && status === 'idle') {
      void load();
    }
  }, [eager, load, status]);

  return {
    status,
    error,
    isReady: status === 'ready',
    isLoading: status === 'loading',
    load,
  };
}
