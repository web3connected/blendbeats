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
  badges: AccountGamificationBadge[];
};

export type AccountGamificationBadge = {
  badge_key: string | null;
  name: string | null;
  description: string | null;
  icon: string | null;
  rarity: string | null;
  unlocked_at: string | null;
};

export type GamificationBadgeCatalogItem = {
  badge_key: string;
  name: string;
  description: string | null;
  role_context: string;
  icon: string | null;
  rarity: string;
  unlock_action_key: string | null;
  unlock_threshold: number;
  unlock_condition: string;
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

export async function getGamificationBadges(): Promise<GamificationBadgeCatalogItem[]> {
  const response = await apiClient.get<GamificationBadgeCatalogItem[]>('/gamification/badges');

  return response.data;
}
