import { Link } from 'react-router-dom';
import { Instagram, Twitter, Youtube, Facebook } from 'lucide-react';

export default function Footer() {
  const currentYear = new Date().getFullYear();

  return (
    <footer className="bg-[#0a0a0a] border-t-2 border-primary mt-auto">
      {/* Gold accent line */}
      <div className="h-px bg-gradient-to-r from-transparent via-[#FFB800] to-transparent" />

      <div className="container mx-auto px-4 py-12">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-10">
          {/* Brand */}
          <div className="md:col-span-1">
            <Link to="/" className="inline-block mb-4">
              <img
                src="/airo-assets/images/logo/horizontal"
                alt="The Blend Battlegrounds"
                className="h-10 w-auto object-contain shrink-0"
              />
            </Link>
            <p className="text-[#888888] text-sm leading-relaxed mb-4">
              The premier underground DJ battle platform. Where the culture lives, the craft is tested, and legends are made.
            </p>
            <div className="flex gap-4">
              <a href="#" aria-label="Instagram" className="text-[#888888] hover:text-primary transition-colors">
                <Instagram size={18} />
              </a>
              <a href="#" aria-label="Twitter" className="text-[#888888] hover:text-primary transition-colors">
                <Twitter size={18} />
              </a>
              <a href="#" aria-label="YouTube" className="text-[#888888] hover:text-primary transition-colors">
                <Youtube size={18} />
              </a>
              <a href="#" aria-label="Facebook" className="text-[#888888] hover:text-primary transition-colors">
                <Facebook size={18} />
              </a>
            </div>
          </div>

          {/* Platform */}
          <div>
            <h4 className="text-white text-xs font-bold tracking-widest uppercase mb-4 border-b border-[#2a2a2a] pb-2" style={{ fontFamily: 'var(--font-heading)' }}>
              PLATFORM
            </h4>
            <ul className="space-y-2">
              {[
                { href: '/battles', label: 'DJ Battles' },
                { href: '/mixes', label: 'Mix Submissions' },
                { href: '/djs', label: 'DJ Profiles' },
                { href: '/leaderboard', label: 'Leaderboard' },
              ].map((item) => (
                <li key={item.href}>
                  <Link to={item.href} className="text-[#888888] text-sm hover:text-primary transition-colors">
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* Shop */}
          <div>
            <h4 className="text-white text-xs font-bold tracking-widest uppercase mb-4 border-b border-[#2a2a2a] pb-2" style={{ fontFamily: 'var(--font-heading)' }}>
              SHOP
            </h4>
            <ul className="space-y-2">
              {[
                { href: '/merch', label: 'Merchandise' },
                { href: '/gear', label: 'DJ Gear' },
                { href: '/gear/turntables', label: 'Turntables' },
                { href: '/gear/mixers', label: 'Mixers' },
              ].map((item) => (
                <li key={item.href}>
                  <Link to={item.href} className="text-[#888888] text-sm hover:text-primary transition-colors">
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* Support */}
          <div>
            <h4 className="text-white text-xs font-bold tracking-widest uppercase mb-4 border-b border-[#2a2a2a] pb-2" style={{ fontFamily: 'var(--font-heading)' }}>
              SUPPORT
            </h4>
            <ul className="space-y-2">
              {[
                { href: '/about', label: 'About Us' },
                { href: '/contact', label: 'Contact' },
                { href: '/privacy', label: 'Privacy Policy' },
                { href: '/terms', label: 'Terms of Service' },
              ].map((item) => (
                <li key={item.href}>
                  <Link to={item.href} className="text-[#888888] text-sm hover:text-primary transition-colors">
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        </div>

        {/* Bottom bar */}
        <div className="mt-10 pt-6 border-t border-[#1a1a1a] flex flex-col md:flex-row items-center justify-between gap-4">
          <p className="text-[#555555] text-xs tracking-widest uppercase" style={{ fontFamily: 'var(--font-heading)' }}>
            THE CULTURE. THE CRAFT. THE BATTLE.
          </p>
          <p className="text-[#444444] text-xs">
            © {currentYear} The Blend Battlegrounds USA. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  );
}
