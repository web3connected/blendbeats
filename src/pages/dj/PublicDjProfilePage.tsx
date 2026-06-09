import { Helmet } from '@dr.pogodin/react-helmet';
import { ArrowLeft, CalendarCheck, Headphones, MapPin, Star, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';

import { getDjHubDj, type DjHubDj } from '@/lib/dj-hub';

export default function PublicDjProfilePage() {
  const { handle } = useParams();
  const [dj, setDj] = useState<DjHubDj | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!handle) return;

    setIsLoading(true);
    setError('');

    getDjHubDj(handle)
      .then(setDj)
      .catch(() => setError('Unable to load this DJ profile.'))
      .finally(() => setIsLoading(false));
  }, [handle]);

  if (isLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-16">
        <div className="container mx-auto h-96 max-w-6xl animate-pulse border border-[#2a2a2a] bg-[#111111]" />
      </main>
    );
  }

  if (error || !dj) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-16">
        <div className="container mx-auto max-w-3xl border border-[#2a2a2a] bg-[#111111] p-8 text-center">
          <Headphones size={28} className="mx-auto text-primary" />
          <h1 className="mt-4 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            DJ Not Found
          </h1>
          <p className="mt-3 text-sm text-[#888888]">{error || 'This profile is not public right now.'}</p>
          <Link
            to="/djs"
            className="mt-6 inline-flex h-11 items-center justify-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <ArrowLeft size={15} />
            Back To DJ Hub
          </Link>
        </div>
      </main>
    );
  }

  return (
    <>
      <Helmet>
        <title>{dj.dj_name} | DJ Hub</title>
        <meta name="description" content={dj.headline || `Discover ${dj.dj_name} on BlendBeats.`} />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            <Link
              to="/djs"
              className="mb-8 inline-flex h-10 items-center gap-2 border border-[#333333] px-3 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              DJ Hub
            </Link>

            <div className="grid gap-8 lg:grid-cols-[280px_minmax(0,1fr)] lg:items-end">
              <div className="aspect-square border border-[#333333] bg-[#111111]">
                {dj.avatar_url ? (
                  <img src={dj.avatar_url} alt={dj.dj_name} className="h-full w-full object-cover" />
                ) : (
                  <div className="flex h-full w-full items-center justify-center bg-primary text-7xl font-black uppercase text-white">
                    {dj.dj_name.charAt(0)}
                  </div>
                )}
              </div>

              <div>
                <p
                  className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  {dj.primary_genre ?? 'Open Format'}
                </p>
                <h1
                  className="text-white uppercase leading-none"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(4rem, 10vw, 8rem)' }}
                >
                  {dj.dj_name}
                </h1>
                <p className="mt-5 max-w-2xl text-lg leading-7 text-[#bbbbbb]">
                  {dj.headline || 'BlendBeats DJ building a public portfolio.'}
                </p>

                <div className="mt-6 flex flex-wrap gap-3 text-sm text-[#dddddd]">
                  {dj.location && (
                    <span className="inline-flex h-10 items-center gap-2 border border-[#333333] px-3">
                      <MapPin size={15} className="text-primary" />
                      {dj.location}
                    </span>
                  )}
                  <span className="inline-flex h-10 items-center gap-2 border border-[#333333] px-3">
                    <Users size={15} className="text-primary" />
                    {dj.followers_count.toLocaleString()} followers
                  </span>
                  {dj.open_for_bookings && (
                    <span className="inline-flex h-10 items-center gap-2 border border-primary/50 px-3 text-primary">
                      <CalendarCheck size={15} />
                      Open for bookings
                    </span>
                  )}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-8 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
            <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
              <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                About
              </h2>
              <p className="mt-4 whitespace-pre-line text-sm leading-7 text-[#aaaaaa]">
                {dj.bio || 'This DJ has not added a public biography yet.'}
              </p>
            </section>

            <aside className="grid gap-5">
              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Portfolio
                </h2>
                <div className="mt-4 border border-[#333333] bg-[#080808] p-3">
                  <p className="mb-2 text-[10px] font-bold uppercase tracking-widest text-[#777777]">Featured Mix</p>
                  {dj.featured_mix ? (
                    <div className="grid gap-2">
                      <p className="truncate text-sm font-semibold text-white">{dj.featured_mix.title}</p>
                      <audio src={dj.featured_mix.url} controls className="h-9 w-full" />
                    </div>
                  ) : (
                    <p className="text-sm text-[#777777]">No public mix featured yet.</p>
                  )}
                </div>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Status
                </h2>
                <div className="mt-4 flex flex-wrap gap-2">
                  {dj.featured_statuses.length > 0 ? (
                    dj.featured_statuses.map((status) => (
                      <span
                        key={status}
                        className="inline-flex h-8 items-center gap-2 bg-primary px-3 text-[10px] font-bold uppercase tracking-widest text-white"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        <Star size={13} />
                        {status}
                      </span>
                    ))
                  ) : (
                    <span className="text-sm text-[#777777]">Standard listing</span>
                  )}
                </div>
              </section>
            </aside>
          </div>
        </section>
      </main>
    </>
  );
}
