import apiClient from '@/lib/api-client';

export type WalletSummary = {
  uuid: string;
  available_balance: number;
  locked_balance: number;
  total_balance: number;
  lifetime_earned: number;
  lifetime_spent: number;
  lifetime_withdrawn: number;
  status: string;
};

export type WalletTransaction = {
  uuid: string;
  type: string;
  direction: string;
  status: string;
  amount: number;
  balance_before: number;
  balance_after: number;
  locked_balance_before: number;
  locked_balance_after: number;
  description: string | null;
  metadata: Record<string, unknown>;
  created_by_admin_id: number | null;
  created_at: string | null;
  completed_at: string | null;
};

export type WalletDemoMode = {
  enabled: boolean;
  token_label: string;
  default_beta_tokens: number;
  withdrawals_enabled: boolean;
  withdrawals_disabled_message: string;
};

export type WalletResponse = {
  wallet: WalletSummary;
  demo_mode: WalletDemoMode;
  transactions: WalletTransaction[];
};

export async function getWallet(): Promise<WalletResponse> {
  const response = await apiClient.get<WalletResponse>('/wallet');
  return response.data;
}
