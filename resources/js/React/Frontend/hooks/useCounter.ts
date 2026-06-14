import { useCallback, useState } from 'react';

import { incrementCounter, type CounterAction, type CounterIncrementResponse } from '@/lib/counters';

type CountTarget = {
  type: string;
  id: string | number;
  action?: CounterAction;
};

type UseCounterOptions = {
  onCounted?: (response: CounterIncrementResponse, target: CountTarget) => void;
};

export function useCounter(options: UseCounterOptions = {}) {
  const { onCounted } = options;
  const [isCounting, setIsCounting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const count = useCallback(
    async (target: CountTarget) => {
      setIsCounting(true);
      setError(null);

      try {
        const response = await incrementCounter(target.type, target.id, target.action);
        onCounted?.(response, target);
        return response;
      } catch (countError) {
        const message = countError instanceof Error ? countError.message : 'Unable to update counter.';
        setError(message);
        throw countError;
      } finally {
        setIsCounting(false);
      }
    },
    [onCounted],
  );

  return { count, isCounting, error };
}
