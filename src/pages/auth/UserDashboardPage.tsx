import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowRight,
  Headphones,
  ListMusic,
  Radio,
  Settings,
  ShieldCheck,
  Swords,
  User,
  Users,
} from 'lucide-react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';

const dashboardActions = [
  {
    title: 'Enter DJLounge',
    description: 'Post, react, and keep up with the DJ community wall.',
    href: '/dj-lounge',
    icon: Users,
    accent: 'text-primary',
  },
  {
    title: 'Start DJ Career',
    description: 'Create your DJ profile, claim a handle, and unlock creator tools.',
    href: '/dj/start',
    icon: Radio,
    accent: 'text-primary',
  },
  {
    title: 'Explore Battles',
    description: 'Vote on live battles and follow the DJs making noise right now.',
    href: '/battles',
    icon: Swords,
    accent: 'text-[#FFB800]',
  },
  {
    title: 'Listen To Mixes',
    description: 'Find top-rated mixes and start building your taste profile.',
    href: '/mixes',
    icon: Headphones,
    accent: 'text-primary',
  },
  {
    title: 'Account Settings',
    description: 'Manage profile details, email, password, and account preferences.',
    href: '/account',
    icon: Settings,
    accent: 'text-[#FFB800]',
  },
];

export default function UserDashboardPage() {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20">
        <div className="container mx-auto max-w-6xl">
          <div className="h-48 animate-pulse bg-[#141414]" />
        </div>
      </main>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  return (
    <>
      <Helmet>
        <title>Dashboard | The Blend Battlegrounds</title>
        <meta name="description" content="Your Blend Battlegrounds dashboard." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-6xl">
            <p
              className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              User Dashboard
            </p>
            <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <h1
                  className="text-white uppercase leading-none"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(4rem, 10vw, 8rem)' }}
                >
                  Welcome, {user.name}
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  This is your home base as a fan, listener, and future DJ. Start a DJ career when you are ready to publish mixes, battle, and build your channel.
                </p>
              </div>
              <Link
                to="/dj/start"
                className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Start DJ Career
                <ArrowRight size={17} />
              </Link>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
            <aside className="border border-[#2a2a2a] bg-[#111111] p-5">
              <div className="mb-5 flex h-16 w-16 items-center justify-center bg-primary text-2xl font-black uppercase text-white">
                {user.name.charAt(0)}
              </div>
              <p className="text-lg font-semibold text-white">{user.name}</p>
              <p className="mt-1 break-all text-sm text-[#888888]">{user.email}</p>
              <div className="mt-6 grid gap-3 border-t border-[#252525] pt-5">
                <div className="flex items-center gap-3 text-sm text-[#cccccc]">
                  <ShieldCheck size={16} className="text-primary" />
                  Listener account active
                </div>
                <div className="flex items-center gap-3 text-sm text-[#cccccc]">
                  <User size={16} className="text-[#FFB800]" />
                  DJ profile not started
                </div>
              </div>
            </aside>

            <div className="grid gap-5">
              <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                <div className="mb-5 flex items-center gap-3">
                  <ListMusic size={18} className="text-primary" />
                  <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Next Steps
                  </h2>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                  {dashboardActions.map((action) => {
                    const Icon = action.icon;
                    return (
                      <Link
                        key={action.title}
                        to={action.href}
                        className="group min-h-40 border border-[#303030] bg-[#0b0b0b] p-4 transition-colors hover:border-primary"
                      >
                        <div className="mb-4 flex items-center justify-between gap-3">
                          <Icon size={22} className={action.accent} />
                          <ArrowRight size={16} className="text-[#555555] transition-colors group-hover:text-primary" />
                        </div>
                        <h3
                          className="text-2xl uppercase text-white"
                          style={{ fontFamily: 'var(--font-heading)' }}
                        >
                          {action.title}
                        </h3>
                        <p className="mt-2 text-sm leading-6 text-[#888888]">{action.description}</p>
                      </Link>
                    );
                  })}
                </div>
              </section>
            </div>
          </div>
        </section>
      </main>
    </>
  );
}
