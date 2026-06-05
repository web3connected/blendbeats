import { Link, useLocation } from 'react-router-dom';
import { Menu, X } from 'lucide-react';
import { useState } from 'react';

import WhosLoggedIn from './WhosLoggedIn';

export default function Header() {
  const location = useLocation();
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  const navItems = [
    { href: '/battles', label: 'BATTLES' },
    { href: '/mixes', label: 'MIXES' },
    { href: '/merch', label: 'MERCH' },
    { href: '/gear', label: 'GEAR' },
    { href: '/djs', label: 'DJS' },
  ];

  return (
    <header className="sticky top-0 z-50 bg-[#0a0a0a]/95 backdrop-blur-sm border-b border-[#2a2a2a]">
      <div className="container mx-auto px-4">
        <div className="flex h-20 items-center justify-between">
          {/* Logo */}
          <Link to="/" className="flex items-center shrink-0">
            <img
              src="/airo-assets/images/logo/horizontal"
              alt="The Blend Battlegrounds"
              className="h-10 w-auto object-contain shrink-0"
            />
          </Link>

          {/* Desktop Nav */}
          <nav className="hidden md:flex items-center gap-8">
            {navItems.map((item) => (
              <Link
                key={item.href}
                to={item.href}
                className={`text-sm font-bold tracking-widest transition-colors hover:text-primary ${
                  location.pathname === item.href
                    ? 'text-primary border-b-2 border-primary pb-0.5'
                    : 'text-[#aaaaaa]'
                }`}
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                {item.label}
              </Link>
            ))}
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
                  className={`text-sm font-bold tracking-widest py-3 px-2 border-b border-[#1a1a1a] transition-colors hover:text-primary ${
                    location.pathname === item.href ? 'text-primary' : 'text-[#aaaaaa]'
                  }`}
                  style={{ fontFamily: 'var(--font-heading)' }}
                  onClick={() => setIsMobileMenuOpen(false)}
                >
                  {item.label}
                </Link>
              ))}
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
