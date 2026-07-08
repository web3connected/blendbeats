import { useEffect, useRef, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { ChevronDown, Clapperboard, Headphones, Music2, Radio, Users } from 'lucide-react';

import { useAuth } from '@/components/auth/AuthProvider';

const navItems = [
  { href: '/live', label: 'LIVE' },
  { href: '/battles', label: 'BATTLES' },
  { href: '/mixes', label: 'MIXES' },
  { href: '/pricing', label: 'PRICING' },
  { href: '/merch', label: 'MERCH' },
];

export default function HeaderNavigation() {
  const location = useLocation();
  const { user } = useAuth();

  const [isDjMenuOpen, setIsDjMenuOpen] = useState(false);
  const djMenuRef = useRef<HTMLDivElement | null>(null);

  const djProfileLabel = user?.dj_profile ? 'EDIT DJ PROFILE' : 'START DJ CAREER';
  const djProfileHref = user?.dj_profile ? '/dj/edit' : '/dj/start';

  const djNavItems = [
    { href: '/djs', label: 'DJ HUB', description: 'Browse public DJ profiles.', icon: Users },
    { href: '/djs/scratches', label: 'SCRATCH ROUTINES', description: 'Watch short DJ skill videos.', icon: Clapperboard },
    { href: '/dj-lounge', label: 'DJ LOUNGE', description: 'Post and connect with the community.', icon: Headphones },
    { href: '/dj/portfolio', label: 'DJ PORTFOLIO', description: 'Manage mixes, tracks, and media.', icon: Music2 },
    { href: djProfileHref, label: djProfileLabel, description: 'Build and update your DJ presence.', icon: Radio },
  ];

  const isDjNavActive =
    djNavItems.some((item) => location.pathname === item.href) ||
    location.pathname.startsWith('/djs/') ||
    location.pathname.startsWith('/dj/');

  useEffect(() => {
    if (!isDjMenuOpen) return;

    const handlePointerDown = (event: PointerEvent) => {
      if (!djMenuRef.current?.contains(event.target as Node)) {
        setIsDjMenuOpen(false);
      }
    };

    document.addEventListener('pointerdown', handlePointerDown);
    return () => document.removeEventListener('pointerdown', handlePointerDown);
  }, [isDjMenuOpen]);

  return (
    <nav className="hidden items-center gap-5 xl:flex 2xl:gap-8">
      {navItems.map((item) => (
        <Link
          key={item.href}
          to={item.href}
          className={`text-sm font-bold tracking-widest transition-colors hover:text-primary 2xl:text-base ${
            location.pathname === item.href
              ? 'border-b-2 border-primary pb-0.5 text-primary'
              : 'text-[#aaaaaa]'
          }`}
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          {item.label}
        </Link>
      ))}

      <a
        href="/news"
        className={`text-sm font-bold tracking-widest transition-colors hover:text-primary 2xl:text-base ${
          location.pathname.startsWith('/news') ? 'border-b-2 border-primary pb-0.5' : ''
        }`}
        style={{ fontFamily: 'var(--font-heading)' }}
      >
        <span className={location.pathname.startsWith('/news') ? 'text-primary' : 'text-[#ffffff]'}>
          BLEND
        </span>
        <span className="text-accent">NEWS</span>
      </a>

      <div ref={djMenuRef} className="relative">
        <button
          type="button"
          onClick={() => setIsDjMenuOpen((current) => !current)}
          className={`inline-flex items-center gap-1 text-sm font-bold tracking-widest transition-colors hover:text-primary 2xl:text-base ${
            isDjNavActive ? 'border-b-2 border-primary pb-0.5 text-primary' : 'text-[#aaaaaa]'
          }`}
          style={{ fontFamily: 'var(--font-heading)' }}
          aria-expanded={isDjMenuOpen}
          aria-haspopup="menu"
        >
          DJ
          <ChevronDown size={14} className="text-current" />
        </button>

        {isDjMenuOpen && (
          <div
            className="absolute left-1/2 top-9 z-50 w-72 -translate-x-1/2 border border-[#2a2a2a] bg-[#0d0d0d] p-2 shadow-2xl shadow-black/50"
            role="menu"
          >
            {djNavItems.map((item) => {
              const Icon = item.icon;

              return (
                <Link
                  key={item.href}
                  to={item.href}
                  onClick={() => setIsDjMenuOpen(false)}
                  className={`flex gap-3 p-3 transition-colors hover:bg-[#171717] hover:text-primary ${
                    location.pathname === item.href ? 'text-primary' : 'text-[#dddddd]'
                  }`}
                  role="menuitem"
                >
                  <Icon size={17} className="mt-0.5 shrink-0" />
                  <span className="min-w-0">
                    <span
                      className="block text-sm font-bold uppercase tracking-widest"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      {item.label}
                    </span>
                    <span className="mt-1 block text-sm leading-5 text-[#888888]">
                      {item.description}
                    </span>
                  </span>
                </Link>
              );
            })}
          </div>
        )}
      </div>
    </nav>
  );
}
