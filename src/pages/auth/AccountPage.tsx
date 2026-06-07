import { Helmet } from '@dr.pogodin/react-helmet';
import { LayoutDashboard, LogOut, Settings, Swords } from 'lucide-react';
import { Link, Navigate, useNavigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';

export default function AccountPage() {
  const navigate = useNavigate();
  const { user, isLoading, logout } = useAuth();

  if (isLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20">
        <div className="container mx-auto h-48 max-w-3xl animate-pulse bg-[#141414]" />
      </main>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  const handleLogout = async () => {
    await logout();
    navigate('/');
  };

  return (
    <>
      <Helmet>
        <title>Account | The Blend Battlegrounds</title>
        <meta name="description" content="Manage your Blend Battlegrounds account." />
      </Helmet>
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-16 lg:px-8">
          <div className="container mx-auto max-w-4xl">
            <p
              className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Account
            </p>
            <h1
              className="text-white uppercase leading-none"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(4rem, 10vw, 8rem)' }}
            >
              Your Profile
            </h1>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto grid max-w-4xl gap-5 md:grid-cols-[280px_minmax(0,1fr)]">
            <aside className="border border-[#2a2a2a] bg-[#111111] p-5">
              <div className="mb-4 flex h-16 w-16 items-center justify-center bg-primary text-2xl font-black uppercase text-white">
                {user.name.charAt(0)}
              </div>
              <p className="text-lg font-semibold text-white">{user.name}</p>
              <p className="mt-1 break-all text-sm text-[#888888]">{user.email}</p>
            </aside>

            <div className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
              <div className="mb-5 flex items-center gap-3">
                <Settings size={18} className="text-primary" />
                <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Account Menu
                </h2>
              </div>
              <div className="grid gap-3">
                <Link
                  to="/dashboard"
                  className="inline-flex h-12 items-center gap-3 border border-[#333333] px-4 text-sm text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                >
                  <LayoutDashboard size={16} />
                  Go to dashboard
                </Link>
                <Link
                  to="/battles"
                  className="inline-flex h-12 items-center gap-3 border border-[#333333] px-4 text-sm text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                >
                  <Swords size={16} />
                  Go to battles
                </Link>
                <button
                  type="button"
                  onClick={() => void handleLogout()}
                  className="inline-flex h-12 items-center gap-3 border border-[#333333] px-4 text-left text-sm text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                >
                  <LogOut size={16} />
                  Logout
                </button>
              </div>
            </div>
          </div>
        </section>
      </main>
    </>
  );
}
