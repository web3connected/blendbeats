import {
  Bell,
  BookOpen,
  ChevronDown,
  CreditCard,
  LayoutDashboard,
  ListMusic,
  LogIn,
  LogOut,
  Music2,
  Radio,
  Settings,
  User,
  UserPlus,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import type { AuthUser } from '@/lib/auth';

interface WhosLoggedInProps {
  onNavigate?: () => void;
  variant?: 'desktop' | 'mobile';
}

type MenuItem = {
  label: string;
  to: string;
  icon: React.ElementType;
  show?: boolean;
};

type MenuSection = {
  items: MenuItem[];
};

function UserAvatar({ user, className }: { user: AuthUser; className: string }) {
  const avatarUrl = user.avatar_url || user.custom_avatar_url || user.gravatar_url || user.generated_avatar_url;

  if (avatarUrl) {
    return <img src={avatarUrl} alt={user.name} className={`${className} object-cover`} />;
  }

  return (
    <span className={`${className} flex items-center justify-center bg-primary font-black uppercase text-white`}>
      {user.name.charAt(0)}
    </span>
  );
}

export default function WhosLoggedIn({ onNavigate, variant = 'desktop' }: WhosLoggedInProps) {
  const { user, isLoading, logout } = useAuth();
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement | null>(null);
  const isMobile = variant === 'mobile';

  const menuSections = useMemo<MenuSection[]>(() => {
    if (!user) return [];

    const hasDjProfile = Boolean(user.dj_profile);

    return [
      {
        items: [
          { label: 'Dashboard', to: '/account', icon: LayoutDashboard },
          { label: 'Wallet', to: '/account/wallet', icon: CreditCard },
        ],
      },
      {
        items: [
          { label: 'Start DJ Career', to: '/dj/start', icon: Radio, show: !hasDjProfile },
          { label: 'DJ Profile', to: '/dj/edit', icon: Radio, show: hasDjProfile },
          { label: 'DJ Portfolio', to: '/dj/portfolio', icon: Music2 },
          { label: 'My Playlist', to: '/account/playlist', icon: ListMusic },
        ],
      },
      {
        items: [
          { label: 'Profile', to: '/account/profile', icon: User },
          { label: 'Notifications', to: '/account/notifications', icon: Bell },
          { label: 'Settings', to: '/account/settings', icon: Settings },
          { label: 'Documentation', to: '/account/docs', icon: BookOpen },
        ],
      },
    ];
  }, [user]);

  useEffect(() => {
    if (!isOpen) return;

    const handlePointerDown = (event: PointerEvent) => {
      if (!menuRef.current?.contains(event.target as Node)) setIsOpen(false);
    };

    document.addEventListener('pointerdown', handlePointerDown);
    return () => document.removeEventListener('pointerdown', handlePointerDown);
  }, [isOpen]);

  const closeMenu = () => {
    setIsOpen(false);
    onNavigate?.();
  };

  const handleLogout = async () => {
    await logout();
    closeMenu();
  };

  if (isLoading) {
    return <div className={isMobile ? 'h-12 w-full bg-[#141414]' : 'h-9 w-28 bg-[#141414]'} />;
  }

  if (!user) {
    return (
      <div className={isMobile ? 'grid grid-cols-2 gap-2 pt-3' : 'hidden items-center gap-1.5 md:flex 2xl:gap-2'}>
        <Link
          to="/login"
          onClick={onNavigate}
          className="inline-flex h-10 items-center justify-center gap-1.5 whitespace-nowrap border border-[#444444] px-2.5 text-[11px] font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary lg:px-3 2xl:px-4 2xl:text-xs"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          <LogIn size={15} />
          Login
        </Link>
        <Link
          to="/register"
          onClick={onNavigate}
          className="inline-flex h-10 items-center justify-center gap-1.5 whitespace-nowrap bg-primary px-2.5 text-[11px] font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 lg:px-3 2xl:px-4 2xl:text-xs"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          <UserPlus size={15} />
          Register
        </Link>
      </div>
    );
  }

  const renderMenuSections = (mobile = false) => (
    <>
      {menuSections.map((section, sectionIndex) => {
        const visibleItems = section.items.filter((item) => item.show !== false);

        if (!visibleItems.length) return null;

        return (
          <div
            key={sectionIndex}
            className={sectionIndex > 0 ? 'border-t border-[#202020] pt-1' : undefined}
          >
            {visibleItems.map((item) => {
              const Icon = item.icon;

              return (
                <Link
                  key={item.to}
                  to={item.to}
                  onClick={mobile ? closeMenu : () => setIsOpen(false)}
                  className={
                    mobile
                      ? 'flex h-9 items-center gap-2 px-2 text-sm text-[#dddddd] hover:text-primary'
                      : 'flex h-8 items-center gap-2 px-3 text-sm text-[#dddddd] hover:bg-[#171717] hover:text-primary'
                  }
                  role="menuitem"
                >
                  <Icon size={14} />
                  {item.label}
                </Link>
              );
            })}
          </div>
        );
      })}

      <div className="border-t border-[#202020] pt-1">
        <button
          type="button"
          onClick={() => void handleLogout()}
          className={
            mobile
              ? 'flex h-9 w-full items-center gap-2 px-2 text-left text-sm text-[#dddddd] hover:text-primary'
              : 'flex h-8 w-full items-center gap-2 px-3 text-left text-sm text-[#dddddd] hover:bg-[#171717] hover:text-primary'
          }
          role="menuitem"
        >
          <LogOut size={14} />
          Logout
        </button>
      </div>
    </>
  );

  if (isMobile) {
    return (
      <div className="mt-3 border border-[#2a2a2a] bg-[#111111] p-3">
        <div className="mb-3 flex items-center gap-3">
          <UserAvatar user={user} className="h-9 w-9 text-sm" />
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-white">{user.name}</p>
            <p className="truncate text-xs text-[#888888]">{user.email}</p>
          </div>
        </div>

        <div className="grid gap-1">{renderMenuSections(true)}</div>
      </div>
    );
  }

  return (
    <div ref={menuRef} className="relative hidden md:block">
      <button
        type="button"
        onClick={() => setIsOpen((value) => !value)}
        className="inline-flex h-10 items-center gap-1.5 border border-[#333333] bg-[#111111] px-2 text-left transition-colors hover:border-primary lg:px-3"
        aria-expanded={isOpen}
        aria-haspopup="menu"
        aria-label="Account menu"
      >
        <UserAvatar user={user} className="h-7 w-7 text-xs" />
        <span className="hidden max-w-20 truncate text-sm font-semibold text-white lg:inline 2xl:max-w-28">{user.name}</span>
        <ChevronDown size={15} className="text-[#888888]" />
      </button>

      {isOpen && (
        <div
          className="absolute right-0 top-12 w-56 border border-[#2a2a2a] bg-[#0d0d0d] p-2 shadow-xl shadow-black/40"
          role="menu"
        >
          <div className="mb-1 flex items-center gap-3 border-b border-[#202020] px-3 py-2">
            <UserAvatar user={user} className="h-9 w-9 shrink-0 text-sm" />
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-white">{user.name}</p>
              <p className="truncate text-xs text-[#888888]">{user.email}</p>
            </div>
          </div>

          <div className="grid gap-1">{renderMenuSections()}</div>
        </div>
      )}
    </div>
  );
}
