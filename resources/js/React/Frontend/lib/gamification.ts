import apiClient from '@/lib/api-client';

export type AccountGamificationSummary = {
  dj_xp: number;
  fan_xp: number;
  total_xp: number;
  dj_level: number;
  fan_level: number;
  total_level: number;
  dj_rank: string | null;
  fan_rank: string | null;
  last_activity_at: string | null;
};

export type AccountGamificationEvent = {
  action_key: string;
  xp_awarded: number;
  role_context: 'dj' | 'fan' | string;
  metadata: Record<string, unknown>;
  created_at: string | null;
};

export async function getAccountGamification(): Promise<AccountGamificationSummary> {
  const response = await apiClient.get<AccountGamificationSummary>('/account/gamification');

  return response.data;
}

export async function getAccountGamificationEvents(): Promise<AccountGamificationEvent[]> {
  const response = await apiClient.get<AccountGamificationEvent[]>('/account/gamification/events');

  return response.data;
}
