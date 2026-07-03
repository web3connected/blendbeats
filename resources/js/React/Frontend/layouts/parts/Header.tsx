// Header.tsx
import { useState } from 'react';

import HeaderLogo from './HeaderLogo';
import HeaderNavigation from './HeaderNavigation';
import HeaderActions from './HeaderActions';
import HeaderMobileMenu from './HeaderMobileMenu';

export default function Header() {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  return (
    <header className="fixed inset-x-0 top-0 z-50 border-b border-[#2a2a2a] bg-[#0a0a0a]/95 backdrop-blur-sm">
      <div className="container mx-auto px-4">
        <div className="flex h-20 items-center justify-between gap-3">
          <HeaderLogo />

          <HeaderNavigation />

          <HeaderActions
            isMobileMenuOpen={isMobileMenuOpen}
            onToggleMobileMenu={() => setIsMobileMenuOpen((current) => !current)}
          />
        </div>

        <HeaderMobileMenu
          isOpen={isMobileMenuOpen}
          onClose={() => setIsMobileMenuOpen(false)}
        />
      </div>
    </header>
  );
}
