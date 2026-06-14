import apiClient from '@/lib/api-client';

export type NotificationRecord = {
  id: string;
  type: string;
  title: string;
  message: string;
  category: string;
  action_label: string | null;
  action_url: string | null;
  icon: string;
  read_at: string | null;
  created_at: string | null;
};

export type NotificationsResponse = {
  notifications: NotificationRecord[];
  unread_count: number;
  filters: {
    categories: string[];
  };
};

export type NotificationQuery = {
  status?: 'all' | 'read' | 'unread';
  category?: string;
  limit?: number;
};

export async function getNotifications(query: NotificationQuery = {}): Promise<NotificationsResponse> {
  const response = await apiClient.get<NotificationsResponse>('/notifications', {
    params: query,
  });

  return response.data;
}

export async function getUnreadNotificationCount(): Promise<number> {
  const response = await apiClient.get<{ unread_count: number }>('/notifications/unread-count');

  return response.data.unread_count;
}

export async function markNotificationRead(id: string): Promise<{ notification: NotificationRecord; unread_count: number }> {
  const response = await apiClient.patch<{ notification: NotificationRecord; unread_count: number }>(`/notifications/${id}/read`);

  return response.data;
}

export async function markAllNotificationsRead(): Promise<{ ok: boolean; unread_count: number }> {
  const response = await apiClient.patch<{ ok: boolean; unread_count: number }>('/notifications/read-all');

  return response.data;
}

export async function deleteNotification(id: string): Promise<{ deleted: boolean; unread_count: number }> {
  const response = await apiClient.delete<{ deleted: boolean; unread_count: number }>(`/notifications/${id}`);

  return response.data;
}
