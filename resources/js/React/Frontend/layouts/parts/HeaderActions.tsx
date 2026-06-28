import { Link } from 'react-router-dom';
import { Menu, Upload, X } from 'lucide-react';

import HeaderCartDrawer from './HeaderCartDrawer';
import NotificationHeaderBell from './NotificationHeaderBell';
import WhosLoggedIn from './WhosLoggedIn';

type HeaderActionsProps = {
  isMobileMenuOpen: boolean;
  onToggleMobileMenu: () => void;
};

export default function HeaderActions({
  isMobileMenuOpen,
  onToggleMobileMenu,
}: HeaderActionsProps) {
  return (
    <div className="flex items-center gap-3">
      <Link
        to="/battles"
        className="hidden items-center bg-primary px-5 py-2 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 md:inline-flex"
        style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.1em' }}
      >
        ENTER BATTLE
      </Link>

      <Link
        to="/dj/portfolio?upload=1"
        className="hidden h-10 w-10 items-center justify-center border border-[#333333] bg-[#111111] text-[#dddddd] transition-colors hover:border-primary hover:text-primary md:inline-flex"
        aria-label="Upload a mix"
      >
        <Upload size={16} />
      </Link>

      <HeaderCartDrawer />
      <NotificationHeaderBell />
      <WhosLoggedIn />

      <button
        onClick={onToggleMobileMenu}
        className="p-2 text-foreground transition-colors hover:text-primary md:hidden"
        aria-label="Toggle menu"
      >
        {isMobileMenuOpen ? <X size={22} /> : <Menu size={22} />}
      </button>
    </div>
  );
}
