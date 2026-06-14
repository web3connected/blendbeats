import { Helmet } from '@dr.pogodin/react-helmet';
import { ArrowLeft, ArrowRight, Bell, CheckCheck, Loader2, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  deleteNotification,
  getNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  type NotificationQuery,
  type NotificationRecord,
  type NotificationsResponse,
} from '@/lib/notifications';

function formatDate(value: string | null) {
  if (!value) return 'Recently';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(value));
}

function formatCategory(value: string) {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function NotificationsPage() {
  const { user, isLoading } = useAuth();
  const [data, setData] = useState<NotificationsResponse | null>(null);
  const [status, setStatus] = useState<NonNullable<NotificationQuery['status']>>('all');
  const [category, setCategory] = useState('');
  const [isNotificationsLoading, setIsNotificationsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!user) return;

    let cancelled = false;

    setIsNotificationsLoading(true);
    setError('');
    getNotifications({ status, category })
      .then((response) => {
        if (!cancelled) setData(response);
      })
      .catch((loadError) => {
        if (!cancelled) setError(loadError instanceof Error ? loadError.message : 'Unable to load notifications.');
      })
      .finally(() => {
        if (!cancelled) setIsNotificationsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [category, status, user]);

  const notifications = data?.notifications ?? [];
  const categories = data?.filters.categories ?? [];
  const summaryLabel = useMemo(() => {
    const total = notifications.length;
    const unread = data?.unread_count ?? 0;

    return `${unread} unread / ${total} shown`;
  }, [data?.unread_count, notifications.length]);

  const updateNotification = (notification: NotificationRecord, unreadCount: number) => {
    setData((current) => {
      if (!current) return current;

      return {
        ...current,
        unread_count: unreadCount,
        notifications: current.notifications.map((item) => (item.id === notification.id ? notification : item)),
      };
    });
  };

  const removeNotification = (id: string, unreadCount: number) => {
    setData((current) => {
      if (!current) return current;

      return {
        ...current,
        unread_count: unreadCount,
        notifications: current.notifications.filter((item) => item.id !== id),
      };
    });
  };

  if (isLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20">
        <div className="container mx-auto max-w-6xl">
          <div className="h-48 animate-pulse bg-[#141414]" />
        </div>
      </main>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  return (
    <>
      <Helmet>
        <title>Notifications | The Blend Battlegrounds</title>
        <meta name="description" content="Review BlendBeats account, upload, billing, promotion, and system notifications." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-6xl">
            <Link
              to="/account/settings"
              className="mb-10 inline-flex h-11 items-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              Settings
            </Link>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
              <div>
                <p className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                  Account / Notifications
                </p>
                <h1
                  className="uppercase leading-none text-white"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 8vw, 6.5rem)' }}
                >
                  Notifications
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  Review account updates, platform notices, uploads, billing, featured ads, and system messages.
                </p>
              </div>

              <div className="border border-[#303030] bg-[#111111] p-5">
                <div className="mb-4 flex h-12 w-12 items-center justify-center bg-primary text-white">
                  <Bell size={20} />
                </div>
                <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Inbox</p>
                <p className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {summaryLabel}
                </p>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            <div className="mb-5 flex flex-col gap-3 border border-[#2a2a2a] bg-[#111111] p-4 md:flex-row md:items-center md:justify-between">
              <div className="flex flex-wrap gap-2">
                {(['all', 'unread', 'read'] as const).map((item) => (
                  <button
                    key={item}
                    type="button"
                    onClick={() => setStatus(item)}
                    className={`h-10 px-4 text-xs font-bold uppercase tracking-widest ${
                      status === item ? 'bg-primary text-white' : 'border border-[#333333] text-[#dddddd] hover:border-primary hover:text-primary'
                    }`}
                    style={{ fontFamily: 'var(--font-heading)' }}
                  >
                    {item}
                  </button>
                ))}
              </div>

              <div className="flex flex-wrap gap-2">
                <select
                  value={category}
                  onChange={(event) => setCategory(event.target.value)}
                  className="h-10 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none"
                >
                  <option value="">All categories</option>
                  {categories.map((item) => (
                    <option key={item} value={item}>{formatCategory(item)}</option>
                  ))}
                </select>
                <button
                  type="button"
                  onClick={() => {
                    void markAllNotificationsRead().then((response) => {
                      setData((current) => current ? {
                        ...current,
                        unread_count: response.unread_count,
                        notifications: current.notifications.map((item) => ({
                          ...item,
                          read_at: item.read_at ?? new Date().toISOString(),
                        })),
                      } : current);
                    });
                  }}
                  className="inline-flex h-10 items-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <CheckCheck size={15} />
                  Mark All Read
                </button>
              </div>
            </div>

            {isNotificationsLoading && (
              <div className="flex min-h-40 items-center justify-center border border-[#2a2a2a] bg-[#080808] text-[#888888]">
                <Loader2 className="mr-3 animate-spin text-primary" size={20} />
                Loading notifications
              </div>
            )}

            {!isNotificationsLoading && error && (
              <div className="border border-primary bg-[#160808] p-4 text-sm leading-6 text-[#dddddd]">{error}</div>
            )}

            {!isNotificationsLoading && !error && notifications.length === 0 && (
              <div className="border border-[#2a2a2a] bg-[#111111] p-8 text-center">
                <Bell className="mx-auto text-primary" size={32} />
                <h2 className="mt-5 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  No notifications yet.
                </h2>
                <p className="mx-auto mt-3 max-w-xl text-sm leading-6 text-[#888888]">
                  Account updates, uploads, billing events, and featured ad messages will appear here.
                </p>
              </div>
            )}

            {!isNotificationsLoading && !error && notifications.length > 0 && (
              <div className="grid gap-3">
                {notifications.map((notification) => {
                  const isUnread = !notification.read_at;

                  return (
                    <article
                      key={notification.id}
                      className={`grid gap-4 border p-4 md:grid-cols-[1fr_auto] md:items-center ${
                        isUnread ? 'border-primary/60 bg-[#160808]' : 'border-[#2a2a2a] bg-[#111111]'
                      }`}
                    >
                      <div className="flex gap-4">
                        <div className={`mt-1 flex h-10 w-10 shrink-0 items-center justify-center ${isUnread ? 'bg-primary text-white' : 'bg-[#080808] text-[#888888]'}`}>
                          <Bell size={18} />
                        </div>
                        <div>
                          <div className="flex flex-wrap items-center gap-2">
                            <p className="text-xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                              {notification.title}
                            </p>
                            <span className="text-[10px] font-bold uppercase tracking-widest text-[#FFB800]">{formatCategory(notification.category)}</span>
                          </div>
                          <p className="mt-2 text-sm leading-6 text-[#aaaaaa]">{notification.message}</p>
                          <p className="mt-2 text-xs text-[#777777]">{formatDate(notification.created_at)}</p>
                        </div>
                      </div>

                      <div className="flex flex-wrap gap-2 md:justify-end">
                        {notification.action_url && (
                          <Link
                            to={notification.action_url}
                            className="inline-flex h-10 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white"
                            style={{ fontFamily: 'var(--font-heading)' }}
                          >
                            {notification.action_label || 'Open'}
                            <ArrowRight size={14} />
                          </Link>
                        )}
                        {isUnread && (
                          <button
                            type="button"
                            onClick={() => {
                              void markNotificationRead(notification.id).then((response) => updateNotification(response.notification, response.unread_count));
                            }}
                            className="inline-flex h-10 items-center justify-center gap-2 border border-[#333333] px-3 text-xs font-bold uppercase tracking-widest text-[#dddddd] hover:border-primary hover:text-primary"
                            style={{ fontFamily: 'var(--font-heading)' }}
                          >
                            <CheckCheck size={14} />
                            Read
                          </button>
                        )}
                        <button
                          type="button"
                          onClick={() => {
                            void deleteNotification(notification.id).then((response) => removeNotification(notification.id, response.unread_count));
                          }}
                          className="inline-flex h-10 items-center justify-center border border-[#333333] px-3 text-[#888888] hover:border-primary hover:text-primary"
                          aria-label="Delete notification"
                        >
                          <Trash2 size={15} />
                        </button>
                      </div>
                    </article>
                  );
                })}
              </div>
            )}
          </div>
        </section>
      </main>
    </>
  );
}
