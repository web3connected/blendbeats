import { Helmet } from '@dr.pogodin/react-helmet';
import { Radio } from 'lucide-react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';

export default function StartDjCareerPage() {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20">
        <div className="container mx-auto h-48 max-w-4xl animate-pulse bg-[#141414]" />
      </main>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  return (
    <>
      <Helmet>
        <title>Start DJ Career | The Blend Battlegrounds</title>
        <meta name="description" content="Start your DJ career on The Blend Battlegrounds." />
      </Helmet>
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="px-4 py-16 lg:px-8">
          <div className="container mx-auto max-w-4xl border border-[#2a2a2a] bg-[#111111] p-6 sm:p-8">
            <div className="mb-5 flex h-14 w-14 items-center justify-center bg-primary text-white">
              <Radio size={24} />
            </div>
            <p
              className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              DJ Career
            </p>
            <h1
              className="text-white uppercase leading-none"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 9vw, 7rem)' }}
            >
              Start Your DJ Profile
            </h1>
            <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
              This setup flow is the next build step. It will collect your stage name, handle, genre, bio, avatar, and banner before creating your DJ Dashboard.
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <Link
                to="/dashboard"
                className="inline-flex h-12 items-center justify-center border border-[#444444] px-5 text-sm font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Back To Dashboard
              </Link>
            </div>
          </div>
        </section>
      </main>
    </>
  );
}
