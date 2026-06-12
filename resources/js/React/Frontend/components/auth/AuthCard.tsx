import { Link } from 'react-router-dom';
import { siteMedia } from '@/lib/site-media';

interface AuthCardProps {
  children: React.ReactNode;
  eyebrow: string;
  title: string;
  subtitle: string;
  footerPrompt: string;
  footerAction: string;
  footerHref: string;
}

export default function AuthCard({
  children,
  eyebrow,
  title,
  subtitle,
  footerPrompt,
  footerAction,
  footerHref,
}: AuthCardProps) {
  const backgroundImage = siteMedia('images/pages/home/crowd-energy');

  return (
    <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
      <section className="relative overflow-hidden border-b border-[#1a1a1a]">
        <div className="absolute inset-0">
          {backgroundImage ? (
            <img src={backgroundImage} alt="" className="h-full w-full object-cover opacity-20" />
          ) : (
            <div className="h-full w-full bg-[radial-gradient(circle_at_28%_35%,rgba(255,26,26,0.2),transparent_30%),linear-gradient(135deg,#141414,#050505)]" />
          )}
          <div className="absolute inset-0 bg-gradient-to-r from-[#0a0a0a] via-[#0a0a0a]/90 to-[#0a0a0a]/65" />
          <div className="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-transparent to-transparent" />
        </div>

        <div className="container relative z-10 mx-auto grid min-h-[calc(100vh-5rem)] grid-cols-1 items-center gap-10 px-4 py-16 lg:grid-cols-[minmax(0,1fr)_440px] lg:px-8">
          <div className="max-w-2xl">
            <p className="mb-4 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
              {eyebrow}
            </p>
            <h1
              className="max-w-xl text-white uppercase leading-none"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(4rem, 11vw, 9rem)' }}
            >
              {title}
            </h1>
            <p className="mt-6 max-w-md text-base leading-7 text-[#b8b8b8]">{subtitle}</p>
          </div>

          <div className="border border-[#2a2a2a] bg-[#111111]/95 p-6 shadow-2xl shadow-black/40 sm:p-8">
            {children}
            <p className="mt-6 text-center text-sm text-[#888888]">
              {footerPrompt}{' '}
              <Link to={footerHref} className="font-semibold text-primary hover:text-primary/80">
                {footerAction}
              </Link>
            </p>
          </div>
        </div>
      </section>
    </main>
  );
}
