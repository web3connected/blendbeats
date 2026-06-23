import { Bell, BookOpen, ChevronDown, LayoutDashboard, ListMusic, LogIn, LogOut, Music2, Radio, Settings, User, UserPlus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import type { AuthUser } from '@/lib/auth';

interface WhosLoggedInProps {
  onNavigate?: () => void;
  variant?: 'desktop' | 'mobile';
}

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
  const djProfileLabel = user?.dj_profile ? 'Go To DJ Profile' : 'Start DJ Career';

  useEffect(() => {
    if (!isOpen) return;

    const handlePointerDown = (event: PointerEvent) => {
      if (!menuRef.current?.contains(event.target as Node)) setIsOpen(false);
    };

    document.addEventListener('pointerdown', handlePointerDown);
    return () => document.removeEventListener('pointerdown', handlePointerDown);
  }, [isOpen]);

  const handleLogout = async () => {
    await logout();
    setIsOpen(false);
    onNavigate?.();
  };

  if (isLoading) {
    return <div className={isMobile ? 'h-12 w-full bg-[#141414]' : 'h-9 w-28 bg-[#141414]'} />;
  }

  if (!user) {
    return (
      <div className={isMobile ? 'grid grid-cols-2 gap-2 pt-3' : 'hidden items-center gap-2 md:flex'}>
        <Link
          to="/login"
          onClick={onNavigate}
          className="inline-flex h-10 items-center justify-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          <LogIn size={15} />
          Login
        </Link>
        <Link
          to="/register"
          onClick={onNavigate}
          className="inline-flex h-10 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          <UserPlus size={15} />
          Register
        </Link>
      </div>
    );
  }

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
        <div className="grid gap-2">
          <Link
            to="/account"
            onClick={onNavigate}
            className="inline-flex h-10 items-center gap-2 px-2 text-sm text-[#dddddd] hover:text-primary"
          >
            <LayoutDashboard size={15} />
            Account
          </Link>
          <Link
            to={user?.dj_profile ? '/dj/edit' : '/dj/start'}
            onClick={onNavigate}
            className="inline-flex h-10 items-center gap-2 px-2 text-sm text-[#dddddd] hover:text-primary"
          >
            <Radio size={15} />
            {djProfileLabel}
          </Link>
          <Link
            to="/dj/portfolio"
            onClick={onNavigate}
            className="inline-flex h-10 items-center gap-2 px-2 text-sm text-[#dddddd] hover:text-primary"
          >
            <Music2 size={15} />
            DJ Portfolio
          </Link>
          <Link
            to="/account/playlist"
            onClick={onNavigate}
            className="inline-flex h-10 items-center gap-2 px-2 text-sm text-[#dddddd] hover:text-primary"
          >
            <ListMusic size={15} />
            My Playlist
          </Link>
          <Link
            to="/account/profile"
            onClick={onNavigate}
            className="inline-flex h-10 items-center gap-2 px-2 text-sm text-[#dddddd] hover:text-primary"
          >
            <User size={15} />
            Profile
          </Link>
          <Link
            to="/account/notifications"
            onClick={onNavigate}
            className="inline-flex h-10 items-center gap-2 px-2 text-sm text-[#dddddd] hover:text-primary"
          >
            <span className="relative">
              <Bell size={15} />
            </span>
            Notifications
          </Link>
          <Link
            to="/account/docs"
            onClick={onNavigate}
            className="inline-flex h-10 items-center gap-2 px-2 text-sm text-[#dddddd] hover:text-primary"
          >
            <BookOpen size={15} />
            Documentation
          </Link>
          <Link
            to="/account/settings"
            onClick={onNavigate}
            className="inline-flex h-10 items-center gap-2 px-2 text-sm text-[#dddddd] hover:text-primary"
          >
            <Settings size={15} />
            Settings
          </Link>
          <button
            type="button"
            onClick={() => void handleLogout()}
            className="inline-flex h-10 items-center gap-2 px-2 text-left text-sm text-[#dddddd] hover:text-primary"
          >
            <LogOut size={15} />
            Logout
          </button>
        </div>
      </div>
    );
  }

  return (
    <div ref={menuRef} className="relative hidden md:block">
      <button
        type="button"
        onClick={() => setIsOpen((value) => !value)}
        className="inline-flex h-10 items-center gap-2 border border-[#333333] bg-[#111111] px-3 text-left transition-colors hover:border-primary"
        aria-expanded={isOpen}
        aria-haspopup="menu"
      >
        <UserAvatar user={user} className="h-7 w-7 text-xs" />
        <span className="max-w-28 truncate text-sm font-semibold text-white">{user.name}</span>
        <ChevronDown size={15} className="text-[#888888]" />
      </button>

      {isOpen && (
        <div className="absolute right-0 top-12 w-56 border border-[#2a2a2a] bg-[#0d0d0d] p-2 shadow-xl shadow-black/40" role="menu">
          <div className="flex items-center gap-3 border-b border-[#202020] px-3 py-2">
            <UserAvatar user={user} className="h-10 w-10 shrink-0 text-sm" />
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-white">{user.name}</p>
              <p className="truncate text-xs text-[#888888]">{user.email}</p>
            </div>
          </div>
          <Link
            to="/account"
            onClick={() => setIsOpen(false)}
            className="mt-2 flex h-10 items-center gap-2 px-3 text-sm text-[#dddddd] hover:bg-[#171717] hover:text-primary"
            role="menuitem"
          >
            <LayoutDashboard size={15} />
            Account
          </Link>
          <Link
            to={user?.dj_profile ? '/dj/edit' : '/dj/start'}
            onClick={() => setIsOpen(false)}
            className="flex h-10 items-center gap-2 px-3 text-sm text-[#dddddd] hover:bg-[#171717] hover:text-primary"
            role="menuitem"
          >
            <Radio size={15} />
            {djProfileLabel}
          </Link>
          <Link
            to="/dj/portfolio"
            onClick={() => setIsOpen(false)}
            className="flex h-10 items-center gap-2 px-3 text-sm text-[#dddddd] hover:bg-[#171717] hover:text-primary"
            role="menuitem"
          >
            <Music2 size={15} />
            DJ Portfolio
          </Link>
          <Link
            to="/account/playlist"
            onClick={() => setIsOpen(false)}
            className="flex h-10 items-center gap-2 px-3 text-sm text-[#dddddd] hover:bg-[#171717] hover:text-primary"
            role="menuitem"
          >
            <ListMusic size={15} />
            My Playlist
          </Link>
          <Link
            to="/account/profile"
            onClick={() => setIsOpen(false)}
            className="flex h-10 items-center gap-2 px-3 text-sm text-[#dddddd] hover:bg-[#171717] hover:text-primary"
            role="menuitem"
          >
            <User size={15} />
            Profile
          </Link>
          <Link
            to="/account/notifications"
            onClick={() => setIsOpen(false)}
            className="flex h-10 items-center gap-2 px-3 text-sm text-[#dddddd] hover:bg-[#171717] hover:text-primary"
            role="menuitem"
          >
            <span className="relative">
              <Bell size={15} />
            </span>
            Notifications
          </Link>
          <Link
            to="/account/docs"
            onClick={() => setIsOpen(false)}
            className="flex h-10 items-center gap-2 px-3 text-sm text-[#dddddd] hover:bg-[#171717] hover:text-primary"
            role="menuitem"
          >
            <BookOpen size={15} />
            Documentation
          </Link>
          <Link
            to="/account/settings"
            onClick={() => setIsOpen(false)}
            className="flex h-10 items-center gap-2 px-3 text-sm text-[#dddddd] hover:bg-[#171717] hover:text-primary"
            role="menuitem"
          >
            <Settings size={15} />
            Settings
          </Link>
          <button
            type="button"
            onClick={() => void handleLogout()}
            className="flex h-10 w-full items-center gap-2 px-3 text-left text-sm text-[#dddddd] hover:bg-[#171717] hover:text-primary"
            role="menuitem"
          >
            <LogOut size={15} />
            Logout
          </button>
        </div>
      )}
    </div>
  );
}
