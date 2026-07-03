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
    <div className="flex min-w-0 shrink-0 items-center gap-1.5 sm:gap-2 2xl:gap-3">
      <Link
        to="/battles"
        className="hidden h-10 items-center justify-center whitespace-nowrap bg-primary px-3 text-[11px] font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 md:inline-flex lg:px-4 2xl:px-5 2xl:text-xs"
        style={{ fontFamily: 'var(--font-heading)' }}
      >
        ENTER BATTLE
      </Link>

      <Link
        to="/dj/portfolio?upload=1"
        className="hidden h-10 items-center justify-center gap-2 whitespace-nowrap border border-[#333333] bg-[#111111] px-3 text-[11px] font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary md:inline-flex lg:px-4 2xl:text-xs"
        aria-label="Upload a mix"
        style={{ fontFamily: 'var(--font-heading)' }}
      >
        <Upload size={16} />
        Upload
      </Link>

      <HeaderCartDrawer />
      <NotificationHeaderBell />
      <WhosLoggedIn />

      <button
        onClick={onToggleMobileMenu}
        className="p-2 text-foreground transition-colors hover:text-primary xl:hidden"
        aria-label="Toggle menu"
      >
        {isMobileMenuOpen ? <X size={22} /> : <Menu size={22} />}
      </button>
    </div>
  );
}
