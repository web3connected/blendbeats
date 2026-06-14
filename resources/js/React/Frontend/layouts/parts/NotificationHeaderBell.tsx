import { Bell } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { getUnreadNotificationCount } from '@/lib/notifications';

export default function NotificationHeaderBell() {
  const { user } = useAuth();
  const [unreadCount, setUnreadCount] = useState(0);

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

  if (!user) return null;

  return (
    <Link
      to="/account/notifications"
      className="relative hidden h-10 w-10 items-center justify-center border border-[#333333] bg-[#111111] text-[#dddddd] transition-colors hover:border-primary hover:text-primary md:inline-flex"
      aria-label={unreadCount > 0 ? `${unreadCount} unread notifications` : 'Notifications'}
    >
      <Bell size={17} />
      {unreadCount > 0 && (
        <span className="absolute -right-1 -top-1 h-5 min-w-5 bg-primary px-1 text-center text-[10px] font-bold leading-5 text-white">
          {Math.min(unreadCount, 99)}
        </span>
      )}
    </Link>
  );
}
