import { Link, useLocation } from 'react-router-dom';
import { ChevronDown, Headphones, Menu, Music2, Radio, Users, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { useAuth } from '@/components/auth/AuthProvider';
import WhosLoggedIn from './WhosLoggedIn';
import { siteMedia } from '@/lib/site-media';

export default function Header() {
  const location = useLocation();
  const { user } = useAuth();
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isDjMenuOpen, setIsDjMenuOpen] = useState(false);
  const djMenuRef = useRef<HTMLDivElement | null>(null);
  const logoImage = siteMedia('images/logo/horizontal');

  const navItems = [
    { href: '/battles', label: 'BATTLES' },
    { href: '/mixes', label: 'MIXES' },
    { href: '/pricing', label: 'PRICING' },
    { href: '/merch', label: 'MERCH' },
    { href: '/gear', label: 'GEAR' },
  ];
  const djProfileLabel = user?.dj_profile ? 'EDIT DJ PROFILE' : 'START DJ CAREER';
  const djProfileHref = user?.dj_profile ? '/dj/edit' : '/dj/start';
  const djNavItems = [
    { href: '/djs', label: 'DJ HUB', description: 'Browse public DJ profiles.', icon: Users },
    { href: '/dj-lounge', label: 'DJ LOUNGE', description: 'Post and connect with the community.', icon: Headphones },
    { href: '/dj/portfolio', label: 'DJ PORTFOLIO', description: 'Manage mixes, tracks, and media.', icon: Music2 },
    { href: djProfileHref, label: djProfileLabel, description: 'Build and update your DJ presence.', icon: Radio },
  ];
  const isDjNavActive = djNavItems.some((item) => location.pathname === item.href)
    || location.pathname.startsWith('/djs/')
    || location.pathname.startsWith('/dj/');

  useEffect(() => {
    if (!isDjMenuOpen) return;

    const handlePointerDown = (event: PointerEvent) => {
      if (!djMenuRef.current?.contains(event.target as Node)) setIsDjMenuOpen(false);
    };

    document.addEventListener('pointerdown', handlePointerDown);
    return () => document.removeEventListener('pointerdown', handlePointerDown);
  }, [isDjMenuOpen]);

  return (
    <header className="fixed inset-x-0 top-0 z-50 bg-[#0a0a0a]/95 backdrop-blur-sm border-b border-[#2a2a2a]">
      <div className="container mx-auto px-4">
        <div className="flex h-20 items-center justify-between">
          {/* Logo */}
          <Link to="/" className="flex items-center shrink-0">
            {logoImage ? (
              <img
                src={logoImage}
                alt="The Blend Battlegrounds"
                className="h-10 w-auto object-contain shrink-0"
              />
            ) : (
              <span className="text-2xl uppercase leading-none text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                Blend<span className="text-primary">Beats</span>
              </span>
            )}
          </Link>

          {/* Desktop Nav */}
          <nav className="hidden md:flex items-center gap-8">
            {navItems.map((item) => (
              <Link
                key={item.href}
                to={item.href}
                className={`text-base font-bold tracking-widest transition-colors hover:text-primary ${
                  location.pathname === item.href
                    ? 'text-primary border-b-2 border-primary pb-0.5'
                    : 'text-[#aaaaaa]'
                }`}
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                {item.label}
              </Link>
            ))}
            <div ref={djMenuRef} className="relative">
              <button
                type="button"
                onClick={() => setIsDjMenuOpen((current) => !current)}
                className={`inline-flex items-center gap-1 text-base font-bold tracking-widest transition-colors hover:text-primary ${
                  isDjNavActive ? 'text-primary border-b-2 border-primary pb-0.5' : 'text-[#aaaaaa]'
                }`}
                style={{ fontFamily: 'var(--font-heading)' }}
                aria-expanded={isDjMenuOpen}
                aria-haspopup="menu"
              >
                DJ
                <ChevronDown size={14} className="text-current" />
              </button>
              {isDjMenuOpen && (
                <div className="absolute left-1/2 top-9 z-50 w-72 -translate-x-1/2 border border-[#2a2a2a] bg-[#0d0d0d] p-2 shadow-2xl shadow-black/50" role="menu">
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
                          <span className="block text-sm font-bold uppercase tracking-widest" style={{ fontFamily: 'var(--font-heading)' }}>
                            {item.label}
                          </span>
                          <span className="mt-1 block text-sm leading-5 text-[#888888]">{item.description}</span>
                        </span>
                      </Link>
                    );
                  })}
                </div>
              )}
            </div>
          </nav>

          {/* CTA + Mobile Toggle */}
          <div className="flex items-center gap-3">
            <Link
              to="/battles"
              className="hidden md:inline-flex items-center px-5 py-2 bg-primary text-white text-xs font-bold tracking-widest uppercase hover:bg-primary/90 transition-colors"
              style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.1em' }}
            >
              ENTER BATTLE
            </Link>
            <WhosLoggedIn />
            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="md:hidden p-2 text-foreground hover:text-primary transition-colors"
              aria-label="Toggle menu"
            >
              {isMobileMenuOpen ? <X size={22} /> : <Menu size={22} />}
            </button>
          </div>
        </div>

        {/* Mobile Menu */}
        {isMobileMenuOpen && (
          <div className="md:hidden border-t border-[#2a2a2a] py-4 bg-[#0a0a0a]">
            <nav className="flex flex-col gap-1">
              {navItems.map((item) => (
                <Link
                  key={item.href}
                  to={item.href}
                  className={`text-base font-bold tracking-widest py-3 px-2 border-b border-[#1a1a1a] transition-colors hover:text-primary ${
                    location.pathname === item.href ? 'text-primary' : 'text-[#aaaaaa]'
                  }`}
                  style={{ fontFamily: 'var(--font-heading)' }}
                  onClick={() => setIsMobileMenuOpen(false)}
                >
                  {item.label}
                </Link>
              ))}
              <div className="mt-2 border border-[#222222] bg-[#0d0d0d] p-2">
                <p className="px-2 py-2 text-[11px] font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
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
                      onClick={() => setIsMobileMenuOpen(false)}
                    >
                      <Icon size={15} />
                      {item.label}
                    </Link>
                  );
                })}
              </div>
              <Link
                to="/battles"
                className="mt-3 inline-flex items-center justify-center px-5 py-3 bg-primary text-white text-sm font-bold tracking-widest uppercase"
                style={{ fontFamily: 'var(--font-heading)' }}
                onClick={() => setIsMobileMenuOpen(false)}
              >
                ENTER BATTLE
              </Link>
              <WhosLoggedIn variant="mobile" onNavigate={() => setIsMobileMenuOpen(false)} />
            </nav>
          </div>
        )}
      </div>
    </header>
  );
}
