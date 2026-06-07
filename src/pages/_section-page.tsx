import { Link } from 'react-router-dom';
import { ArrowRight, Disc3, Headphones, Mic2, ShoppingBag, SlidersHorizontal, Trophy } from 'lucide-react';
import HeaderTitle from '@/layouts/HeaderTitle';
import React, { useEffect } from 'react';

type Feature = {
  title: string;
  meta: string;
  body: string;
};

type Stat = {
  label: string;
  value: string;
};

type SectionPageProps = {
  eyebrow: string;
  title: string;
  description: string;
  ctaLabel: string;
  ctaHref: string;
  accent?: 'red' | 'gold';
  features: Feature[];
  stats: Stat[];
};

const iconMap = [Trophy, Disc3, ShoppingBag, SlidersHorizontal, Mic2, Headphones];

export default function SectionPage({
  eyebrow,
  title,
  description,
  ctaLabel,
  ctaHref,
  accent = 'red',
  features,
  stats,
}: SectionPageProps) {
  const accentClass = accent === 'gold' ? 'text-[#FFB800]' : 'text-primary';
  const accentBg = accent === 'gold' ? 'bg-[#FFB800] text-[#0a0a0a]' : 'bg-primary text-white';

  const [pageTitle, setPageTitle] = React.useState(title);
  const [pageDescription, setPageDescription] = React.useState(description);

  useEffect(() => {
    const title = `BlendBeats`;
    const description = `Explore the ${title.toLowerCase()} section of BlendBeats, your ultimate DJ culture destination. Discover top-rated mixes, essential gear, exclusive merch drops, and more. Dive into the heart of the DJ community with curated content and real destinations for every beat.`;  
    setPageTitle(title);
    setPageDescription(description);
  }, [title, description]);

  return (
    <>
      <HeaderTitle title={pageTitle} description={pageDescription} />

      <main className="bg-[#0a0a0a] text-white">
        <section className="border-b border-[#1f1f1f]">
          <div className="container mx-auto grid min-h-[54vh] grid-cols-1 gap-10 px-4 py-16 md:grid-cols-[1.1fr_0.9fr] md:items-center md:py-24">
            <div>
              <p className={`${accentClass} mb-3 text-xs font-bold uppercase tracking-widest`} style={{ fontFamily: 'var(--font-heading)' }}>
                {eyebrow}
              </p>
              <h1
                className="max-w-4xl uppercase leading-none"
                style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3rem, 9vw, 7.5rem)' }}
              >
                {title}
              </h1>
              <p className="mt-6 max-w-2xl text-base leading-7 text-[#c8c8c8] md:text-lg">
                {description}
              </p>
              <Link
                to={ctaHref}
                className={`mt-8 inline-flex items-center gap-3 px-7 py-4 text-xs font-bold uppercase tracking-widest transition-opacity hover:opacity-90 ${accentBg}`}
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                {ctaLabel}
                <ArrowRight size={16} />
              </Link>
            </div>

            <div className="grid grid-cols-2 border border-[#2a2a2a] bg-[#111111]">
              {stats.map((stat) => (
                <div key={stat.label} className="border-b border-r border-[#2a2a2a] p-5 last:border-r-0 md:p-6">
                  <p className={`${accentClass} text-3xl font-black md:text-5xl`} style={{ fontFamily: 'var(--font-heading)' }}>
                    {stat.value}
                  </p>
                  <p className="mt-2 text-xs uppercase tracking-widest text-[#888888]" style={{ fontFamily: 'var(--font-heading)' }}>
                    {stat.label}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="py-14 md:py-20">
          <div className="container mx-auto px-4">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
              {features.map((feature, index) => {
                const Icon = iconMap[index % iconMap.length];
                return (
                  <article key={feature.title} className="border border-[#2a2a2a] bg-[#141414] p-5 transition-colors hover:border-primary/70">
                    <div className="mb-5 flex items-center justify-between">
                      <Icon size={22} className={accentClass} />
                      <span className="text-[10px] font-bold uppercase tracking-widest text-[#777777]" style={{ fontFamily: 'var(--font-heading)' }}>
                        {feature.meta}
                      </span>
                    </div>
                    <h2 className="text-xl uppercase" style={{ fontFamily: 'var(--font-heading)', letterSpacing: '0.03em' }}>
                      {feature.title}
                    </h2>
                    <p className="mt-3 text-sm leading-6 text-[#aaaaaa]">{feature.body}</p>
                  </article>
                );
              })}
            </div>
          </div>
        </section>
      </main>
    </>
  );
}
