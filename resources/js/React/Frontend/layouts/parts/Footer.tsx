import { Link } from 'react-router-dom';
import logoImage from '../../../../../assets/logo.png';

type FooterLink = {
  href: string;
  label: string;
  reload?: boolean;
};

const footerSections: Array<{
  title: string;
  links: FooterLink[];
}> = [
  {
    title: 'Explore',
    links: [
      { href: '/', label: 'Home' },
      { href: '/battles', label: 'DJ Battles' },
      { href: '/mixes', label: 'Mixes' },
      { href: '/news', label: 'BlendNews', reload: true },
      { href: '/merch', label: 'Merchandise' },
    ],
  },
  {
    title: 'DJ Community',
    links: [
      { href: '/djs', label: 'DJ Hub' },
      { href: '/djs/scratches', label: 'Scratch Routines' },
      { href: '/dj-lounge', label: 'DJ Lounge' },
      { href: '/dj/start', label: 'Start DJ Career' },
    ],
  },
  {
    title: 'Programs',
    links: [
      { href: '/pricing', label: 'Pricing' },
      { href: '/subscription', label: 'Membership' },
      { href: '/affiliate', label: 'Affiliate Program' },
    ],
  },
  {
    title: 'Company',
    links: [
      { href: '/about', label: 'About' },
      { href: '/contact', label: 'Contact' },
      { href: '/privacy', label: 'Privacy Policy' },
      { href: '/terms', label: 'Terms of Service' },
    ],
  },
];

function FooterNavLink({ href, label, reload = false }: FooterLink) {
  const className = 'text-[#888888] text-sm hover:text-primary transition-colors';

  return reload ? (
    <a href={href} className={className}>
      {label}
    </a>
  ) : (
    <Link to={href} className={className}>
      {label}
    </Link>
  );
}

export default function Footer() {
  const currentYear = new Date().getFullYear();

  return (
    <footer className="bg-[#0a0a0a] border-t-2 border-primary mt-auto">
      <div className="h-px bg-gradient-to-r from-transparent via-[#FFB800] to-transparent" />

      <div className="container mx-auto px-4 py-12">
        <div className="grid grid-cols-1 gap-10 md:grid-cols-5">
          <div>
            <Link to="/" className="mb-4 inline-flex max-w-[390px] items-center overflow-hidden">
              <img
                src={logoImage}
                alt="The Blend Battlegrounds"
                className="h-24 w-auto max-w-full object-contain shrink-0"
              />
            </Link>
            <p className="text-[#888888] text-sm leading-relaxed mb-4">
              The premier underground DJ battle platform. Where the culture lives, the craft is tested, and legends are made.
            </p>
          </div>

          {footerSections.map((section) => (
            <div key={section.title}>
              <h4 className="text-white text-xs font-bold tracking-widest uppercase mb-4 border-b border-[#2a2a2a] pb-2" style={{ fontFamily: 'var(--font-heading)' }}>
                {section.title}
              </h4>
              <ul className="space-y-2">
                {section.links.map((item) => (
                  <li key={item.href}>
                    <FooterNavLink {...item} />
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-10 pt-6 border-t border-[#1a1a1a] flex flex-col md:flex-row items-center justify-between gap-4">
          <p className="text-[#555555] text-xs tracking-widest uppercase" style={{ fontFamily: 'var(--font-heading)' }}>
            THE CULTURE. THE CRAFT. THE BATTLE.
          </p>
          <p className="text-[#444444] text-xs">
            &copy; {currentYear} The Blend Battlegrounds USA. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  );
}
