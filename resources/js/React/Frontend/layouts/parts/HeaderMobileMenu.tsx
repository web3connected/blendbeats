import { Link, useLocation } from 'react-router-dom';
import { Clapperboard, Headphones, Music2, Radio, Upload, Users } from 'lucide-react';

import { useAuth } from '@/components/auth/AuthProvider';
import WhosLoggedIn from './WhosLoggedIn';

type HeaderMobileMenuProps = {
  isOpen: boolean;
  onClose: () => void;
};

const navItems = [
  { href: '/battles', label: 'BATTLES' },
  { href: '/mixes', label: 'MIXES' },
  { href: '/pricing', label: 'PRICING' },
  { href: '/merch', label: 'MERCH' },
];

export default function HeaderMobileMenu({ isOpen, onClose }: HeaderMobileMenuProps) {
  const location = useLocation();
  const { user } = useAuth();

  if (!isOpen) return null;

  const djProfileLabel = user?.dj_profile ? 'EDIT DJ PROFILE' : 'START DJ CAREER';
  const djProfileHref = user?.dj_profile ? '/dj/edit' : '/dj/start';

  const djNavItems = [
    { href: '/djs', label: 'DJ HUB', icon: Users },
    { href: '/djs/scratches', label: 'SCRATCH ROUTINES', icon: Clapperboard },
    { href: '/dj-lounge', label: 'DJ LOUNGE', icon: Headphones },
    { href: '/dj/portfolio', label: 'DJ PORTFOLIO', icon: Music2 },
    { href: djProfileHref, label: djProfileLabel, icon: Radio },
  ];

  return (
    <div className="border-t border-[#2a2a2a] bg-[#0a0a0a] py-4 xl:hidden">
      <nav className="flex flex-col gap-1">
        {navItems.map((item) => (
          <Link
            key={item.href}
            to={item.href}
            className={`border-b border-[#1a1a1a] px-2 py-3 text-base font-bold tracking-widest transition-colors hover:text-primary ${
              location.pathname === item.href ? 'text-primary' : 'text-[#aaaaaa]'
            }`}
            style={{ fontFamily: 'var(--font-heading)' }}
            onClick={onClose}
          >
            {item.label}
          </Link>
        ))}

        <a
          href="/news"
          className={`border-b border-[#1a1a1a] px-2 py-3 text-base font-bold tracking-widest transition-colors hover:text-primary ${
            location.pathname.startsWith('/news') ? 'text-primary' : ''
          }`}
          style={{ fontFamily: 'var(--font-heading)' }}
          onClick={onClose}
        >
          <span className={location.pathname.startsWith('/news') ? 'text-primary' : 'text-[#ffffff]'}>
            BLEND
          </span>
          <span className="text-accent">NEWS</span>
        </a>

        <div className="mt-2 border border-[#222222] bg-[#0d0d0d] p-2">
          <p
            className="px-2 py-2 text-[11px] font-bold uppercase tracking-widest text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            DJ
          </p>

          {djNavItems.map((item) => {
            const Icon = item.icon;

            return (
              <Link
                key={item.href}
                to={item.href}
                className={`flex items-center gap-2 border-b border-[#1a1a1a] px-2 py-3 text-base font-bold tracking-widest transition-colors last:border-b-0 hover:text-primary ${
                  location.pathname === item.href ? 'text-primary' : 'text-[#aaaaaa]'
                }`}
                style={{ fontFamily: 'var(--font-heading)' }}
                onClick={onClose}
              >
                <Icon size={15} />
                {item.label}
              </Link>
            );
          })}
        </div>

        <Link
          to="/dj/portfolio?upload=1"
          className="mt-3 inline-flex items-center justify-center gap-2 border border-[#333333] px-5 py-3 text-sm font-bold uppercase tracking-widest text-[#dddddd]"
          style={{ fontFamily: 'var(--font-heading)' }}
          onClick={onClose}
        >
          <Upload size={15} />
          Upload A Mix
        </Link>

        <Link
          to="/battles"
          className="mt-3 inline-flex items-center justify-center bg-primary px-5 py-3 text-sm font-bold uppercase tracking-widest text-white"
          style={{ fontFamily: 'var(--font-heading)' }}
          onClick={onClose}
        >
          ENTER BATTLE
        </Link>

        <WhosLoggedIn variant="mobile" onNavigate={onClose} />
      </nav>
    </div>
  );
}
