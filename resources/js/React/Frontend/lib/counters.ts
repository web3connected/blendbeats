const API_BASE = import.meta.env?.VITE_API_BASE || '/api';

export type CounterAction = 'play' | 'view' | 'click';

export type CounterIncrementResponse = {
  type: string;
  id: number | string;
  key?: string | null;
  action: CounterAction | string;
  count: number;
  label: string;
};

async function parseJson<T>(response: Response): Promise<T> {
  const body = await response.json();

  if (!response.ok) {
    throw new Error(body?.message || 'Unable to update counter.');
  }

  return body as T;
}

export async function incrementCounter(
  type: string,
  id: string | number,
  action: CounterAction = 'view',
): Promise<CounterIncrementResponse> {
  const response = await fetch(`${API_BASE}/counters/${type}/${id}/${action}`, {
    method: 'POST',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });

  return parseJson<CounterIncrementResponse>(response);
}
