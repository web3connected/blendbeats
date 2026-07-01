import apiClient from '@/lib/api-client';

export type BattleProfile = {
  id: number;
  dj_name: string;
  handle: string;
  headline: string | null;
  avatar_url: string | null;
  city: string | null;
  state: string | null;
  country: string | null;
  battle_enabled: boolean;
};

export type BattleEntry = {
  id: number;
  dj_profile_id: number;
  status: string;
  title: string | null;
  notes: string | null;
  duration_seconds: number | null;
  media_file_id: number | null;
  media_url: string | null;
  submitted_at: string | null;
};

export type BattleViewerVote = {
  id: number;
  reward_eligible: boolean;
  submitted_at: string | null;
  prediction_dj_profile_id: number | null;
  scores: Array<{
    dj_profile_id: number;
    entry_id: number;
    total_score: number;
    category_scores: Record<string, number>;
  }>;
};

export type BattleRecord = {
  uuid: string;
  status: string;
  battle_type: string;
  title: string;
  theme: string | null;
  description: string | null;
  rules: string | null;
  duration_seconds: number;
  voting_duration_hours: number;
  minimum_votes: number;
  stake_amount: number;
  currency: string;
  sample_pack_status: 'pending' | 'ready' | 'bypassed' | string;
  sample_pack_ready_at: string | null;
  sample_pack_bypassed_at: string | null;
  sample_pack_metadata: Record<string, unknown>;
  challenge_message: string | null;
  fan_reward_pool_amount: number;
  prize_pool_amount: number;
  vote_count: number;
  viewer_vote: BattleViewerVote | null;
  challenger: BattleProfile;
  opponent: BattleProfile;
  winner: BattleProfile | null;
  readiness: {
    challenger_ready: boolean;
    opponent_ready: boolean;
    both_ready: boolean;
  };
  entries: BattleEntry[];
  result: {
    winner_dj_profile_id: number | null;
    challenger_score: number;
    opponent_score: number;
    total_votes: number;
    is_draw: boolean;
    calculated_at: string | null;
  } | null;
  response_due_at: string | null;
  ready_due_at: string | null;
  challenger_ready_at: string | null;
  opponent_ready_at: string | null;
  accepted_at: string | null;
  recording_started_at: string | null;
  recording_ends_at: string | null;
  voting_started_at: string | null;
  voting_ends_at: string | null;
  completed_at: string | null;
  declined_at: string | null;
  cancelled_at: string | null;
  created_at: string | null;
};

export type BattleCreatePayload = {
  opponent_dj_profile_id: number;
  battle_type: 'mix' | 'scratch' | 'open_format' | 'theme';
  title: string;
  theme?: string;
  description?: string;
  rules?: string;
  duration_seconds?: number;
  voting_duration_hours?: 24 | 48 | 72;
  minimum_votes?: number;
  stake_amount?: number;
  challenge_message?: string;
};

export type BattleEntrySubmitPayload = {
  media: Blob;
  title?: string;
  notes?: string;
  duration_seconds: number;
  filename?: string;
};

export type BattleVoteScores = {
  sample_integration: number;
  scratching_ability: number;
  mixing_ability: number;
  blending: number;
  creativity: number;
  technical_execution: number;
  music_selection: number;
  battle_composition: number;
  entertainment_value: number;
  overall_performance: number;
};

export type BattleVoteSubmitPayload = {
  watch_order: number[];
  scores: Array<{
    dj_profile_id: number;
    scores: BattleVoteScores;
  }>;
};

export type BattleLeaderboardCategory =
  | 'overall'
  | 'sample_integration'
  | 'scratching_ability'
  | 'mixing_ability'
  | 'blending'
  | 'creativity'
  | 'technical_execution'
  | 'music_selection'
  | 'battle_composition'
  | 'entertainment_value'
  | 'overall_performance';

export type BattleLeaderboardPeriod = 'all_time' | 'week' | 'month' | 'season';

export type BattleLeaderboardCategoryOption = {
  value: BattleLeaderboardCategory;
  label: string;
  max_score: number;
};

export type BattleLeaderboardRow = {
  dj_id: number;
  dj_name: string;
  handle: string;
  headline: string | null;
  avatar_url: string | null;
  rank: number;
  qualified: boolean;
  selected_category: BattleLeaderboardCategory;
  selected_category_label: string;
  selected_category_score: number;
  selected_category_max_score: number;
  completed_battles_count: number;
  scored_battles_count: number;
  score_count: number;
  wins: number;
  losses: number;
  average_total_score: number;
  last_battle_date: string | null;
};

export type BattleLeaderboardResponse = {
  category: BattleLeaderboardCategory;
  category_label: string;
  categories: BattleLeaderboardCategoryOption[];
  period: BattleLeaderboardPeriod;
  minimum_battles: number;
  leaderboard: BattleLeaderboardRow[];
  new_competitors: BattleLeaderboardRow[];
};

export type BattleQuery = {
  status?: 'accepted' | 'recording' | 'voting' | 'completed';
  battle_type?: BattleCreatePayload['battle_type'];
  limit?: number;
};

export type BattleLeaderboardQuery = {
  category?: BattleLeaderboardCategory;
  period?: BattleLeaderboardPeriod;
  verified?: boolean;
  active?: boolean;
  min_battles?: number;
  limit?: number;
};

export class BattleApiError extends Error {
  errors: Record<string, string[]>;
  status: number;

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'BattleApiError';
    this.status = status;
    this.errors = errors;
  }
}

function normalizeError(error: unknown): BattleApiError {
  if (error instanceof Error && 'response' in error) {
    const response = error.response as { status?: number; data?: { message?: string; errors?: Record<string, string[]> } };
    return new BattleApiError(
      response.data?.message || 'Battle request failed.',
      response.status || 500,
      response.data?.errors || {},
    );
  }

  return error instanceof BattleApiError ? error : new BattleApiError('Battle request failed.', 500);
}

export async function getBattles(query: BattleQuery = {}): Promise<BattleRecord[]> {
  const response = await apiClient.get<{ battles: BattleRecord[] }>('/battles', {
    params: {
      status: query.status,
      battle_type: query.battle_type,
      limit: query.limit,
    },
  });
  return response.data.battles;
}

export async function getBattle(uuid: string): Promise<BattleRecord> {
  const response = await apiClient.get<{ battle: BattleRecord }>(`/battles/${uuid}`);
  return response.data.battle;
}

export async function getBattleLeaderboard(query: BattleLeaderboardQuery = {}): Promise<BattleLeaderboardResponse> {
  const response = await apiClient.get<BattleLeaderboardResponse>('/battles/leaderboards', {
    params: {
      category: query.category,
      period: query.period,
      verified: query.verified ? 1 : undefined,
      active: query.active ? 1 : undefined,
      min_battles: query.min_battles,
      limit: query.limit,
    },
  });
  return response.data;
}

export async function getAccountBattles(): Promise<BattleRecord[]> {
  const response = await apiClient.get<{ battles: BattleRecord[] }>('/account/battles');
  return response.data.battles;
}

export async function createBattle(payload: BattleCreatePayload): Promise<BattleRecord> {
  try {
    const response = await apiClient.post<{ battle: BattleRecord }>('/battles', payload);
    return response.data.battle;
  } catch (error) {
    throw normalizeError(error);
  }
}

export async function acceptBattle(uuid: string): Promise<BattleRecord> {
  const response = await apiClient.post<{ battle: BattleRecord }>(`/battles/${uuid}/accept`);
  return response.data.battle;
}

export async function declineBattle(uuid: string): Promise<BattleRecord> {
  const response = await apiClient.post<{ battle: BattleRecord }>(`/battles/${uuid}/decline`);
  return response.data.battle;
}

export async function cancelBattle(uuid: string): Promise<BattleRecord> {
  const response = await apiClient.post<{ battle: BattleRecord }>(`/battles/${uuid}/cancel`);
  return response.data.battle;
}

export async function extendBattle(uuid: string): Promise<BattleRecord> {
  const response = await apiClient.post<{ battle: BattleRecord }>(`/battles/${uuid}/extend`);
  return response.data.battle;
}

export async function readyBattle(uuid: string): Promise<BattleRecord> {
  const response = await apiClient.post<{ battle: BattleRecord }>(`/battles/${uuid}/ready`);
  return response.data.battle;
}

export async function readyBattleOpponentForTesting(uuid: string): Promise<BattleRecord> {
  try {
    const response = await apiClient.post<{ battle: BattleRecord }>(`/battles/${uuid}/ready/test-opponent`);
    return response.data.battle;
  } catch (error) {
    throw normalizeError(error);
  }
}

export async function bypassBattleSamplePack(uuid: string): Promise<BattleRecord> {
  const response = await apiClient.post<{ battle: BattleRecord }>(`/battles/${uuid}/sample-pack/bypass`);
  return response.data.battle;
}

export async function submitBattleEntry(uuid: string, payload: BattleEntrySubmitPayload): Promise<BattleRecord> {
  try {
    const formData = new FormData();
    formData.append('media', payload.media, payload.filename || 'battle-entry.webm');
    formData.append('duration_seconds', String(payload.duration_seconds));
    formData.append('recorded_in_browser', '1');

    if (payload.title) formData.append('title', payload.title);
    if (payload.notes) formData.append('notes', payload.notes);

    const response = await apiClient.post<{ battle: BattleRecord }>(`/battles/${uuid}/entries`, formData);
    return response.data.battle;
  } catch (error) {
    throw normalizeError(error);
  }
}

export async function duplicateBattleEntryForTesting(uuid: string): Promise<BattleRecord> {
  try {
    const response = await apiClient.post<{ battle: BattleRecord }>(`/battles/${uuid}/entries/test-duplicate`);
    return response.data.battle;
  } catch (error) {
    throw normalizeError(error);
  }
}

export async function submitBattleVote(uuid: string, payload: BattleVoteSubmitPayload): Promise<BattleRecord> {
  try {
    const response = await apiClient.post<{ battle: BattleRecord }>(`/battles/${uuid}/votes`, payload);
    return response.data.battle;
  } catch (error) {
    throw normalizeError(error);
  }
}
