import { Bell, CheckCheck, Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  getNotifications,
  getUnreadNotificationCount,
  markAllNotificationsRead,
  markNotificationRead,
  type NotificationRecord,
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

export default function NotificationHeaderBell() {
  const { user } = useAuth();
  const [unreadCount, setUnreadCount] = useState(0);
  const [notifications, setNotifications] = useState<NotificationRecord[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const menuRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    if (!user) {
      setUnreadCount(0);
      return;
    }

    let cancelled = false;

    const refreshUnreadCount = () => {
      getUnreadNotificationCount()
        .then((count) => {
          if (!cancelled) setUnreadCount(count);
        })
        .catch(() => {
          if (!cancelled) setUnreadCount(0);
        });
    };

    refreshUnreadCount();
    const interval = window.setInterval(refreshUnreadCount, 60_000);

    return () => {
      cancelled = true;
      window.clearInterval(interval);
    };
  }, [user]);

  useEffect(() => {
    if (!isOpen) return;

    const handlePointerDown = (event: PointerEvent) => {
      if (!menuRef.current?.contains(event.target as Node)) setIsOpen(false);
    };

    document.addEventListener('pointerdown', handlePointerDown);
    return () => document.removeEventListener('pointerdown', handlePointerDown);
  }, [isOpen]);

  useEffect(() => {
    if (!user || !isOpen) return;

    let cancelled = false;

    setIsLoading(true);
    getNotifications({ limit: 10 })
      .then((response) => {
        if (cancelled) return;
        setNotifications(response.notifications);
        setUnreadCount(response.unread_count);
      })
      .catch(() => {
        if (!cancelled) setNotifications([]);
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [isOpen, user]);

  if (!user) return null;

  const markNotificationAsRead = (notification: NotificationRecord) => {
    if (notification.read_at) return;

    const readAt = new Date().toISOString();
    setNotifications((current) => current.map((item) => (item.id === notification.id ? { ...item, read_at: readAt } : item)));
    setUnreadCount((current) => Math.max(0, current - 1));

    void markNotificationRead(notification.id)
      .then((response) => {
        setUnreadCount(response.unread_count);
        setNotifications((current) => current.map((item) => (item.id === response.notification.id ? response.notification : item)));
      })
      .catch(() => {
        setNotifications((current) => current.map((item) => (item.id === notification.id ? notification : item)));
        setUnreadCount((current) => current + 1);
      });
  };

  return (
    <div ref={menuRef} className="relative hidden md:block">
      <button
        type="button"
        onClick={() => setIsOpen((current) => !current)}
        className="relative inline-flex h-10 w-10 items-center justify-center border border-[#333333] bg-[#111111] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
        aria-label={unreadCount > 0 ? `${unreadCount} unread notifications` : 'Notifications'}
        aria-expanded={isOpen}
        aria-haspopup="menu"
      >
        <Bell size={17} />
        {unreadCount > 0 && (
          <span className="absolute -right-1 -top-1 h-5 min-w-5 bg-primary px-1 text-center text-[10px] font-bold leading-5 text-white">
            {Math.min(unreadCount, 99)}
          </span>
        )}
      </button>

      {isOpen && (
        <div className="absolute right-0 top-12 z-50 w-80 border border-[#2a2a2a] bg-[#0d0d0d] p-2 shadow-xl shadow-black/40" role="menu">
          <div className="flex items-center justify-between gap-3 border-b border-[#202020] px-3 py-3">
            <div>
              <p className="text-sm font-semibold uppercase tracking-widest text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                Notifications
              </p>
              <p className="mt-1 text-xs text-[#888888]">{unreadCount} unread</p>
            </div>
            <button
              type="button"
              onClick={() => {
                void markAllNotificationsRead().then((response) => {
                  setUnreadCount(response.unread_count);
                  setNotifications((current) => current.map((item) => ({ ...item, read_at: item.read_at ?? new Date().toISOString() })));
                });
              }}
              className="inline-flex h-9 items-center gap-2 border border-[#333333] px-3 text-[10px] font-bold uppercase tracking-widest text-[#dddddd] hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <CheckCheck size={13} />
              Read All
            </button>
          </div>

          <div className="max-h-[360px] overflow-y-auto py-2">
            {isLoading && (
              <div className="flex h-24 items-center justify-center text-sm text-[#888888]">
                <Loader2 className="mr-2 animate-spin text-primary" size={16} />
                Loading
              </div>
            )}

            {!isLoading && notifications.length === 0 && (
              <div className="px-3 py-8 text-center text-sm text-[#888888]">No notifications yet.</div>
            )}

            {!isLoading && notifications.map((notification) => {
              const isUnread = !notification.read_at;

              return (
                <Link
                  key={notification.id}
                  to={notification.action_url || '/account/notifications'}
                  onClick={() => {
                    markNotificationAsRead(notification);
                    setIsOpen(false);
                  }}
                  className={`block border-b border-[#1f1f1f] px-3 py-3 transition-colors last:border-b-0 hover:bg-[#171717] ${
                    isUnread ? 'bg-primary/10' : ''
                  }`}
                  role="menuitem"
                >
                  <div className="flex items-start gap-3">
                    <span className={`mt-1 h-2 w-2 shrink-0 ${isUnread ? 'bg-primary' : 'bg-[#444444]'}`} />
                    <span className="min-w-0">
                      <span className="block truncate text-sm font-semibold text-white">{notification.title}</span>
                      <span className="mt-1 line-clamp-2 block text-xs leading-5 text-[#999999]">{notification.message}</span>
                      <span className="mt-2 block text-[10px] uppercase tracking-widest text-[#666666]">{formatDate(notification.created_at)}</span>
                    </span>
                  </div>
                </Link>
              );
            })}
          </div>

          <Link
            to="/account/notifications"
            onClick={() => setIsOpen(false)}
            className="mt-2 flex h-10 items-center justify-center border border-[#333333] text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
            role="menuitem"
          >
            View All
          </Link>
        </div>
      )}
    </div>
  );
}
