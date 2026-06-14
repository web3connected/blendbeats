import { Helmet } from '@dr.pogodin/react-helmet';
import { Loader2, ShieldCheck } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

import apiClient from '@/lib/api-client';

type PreviewStatus = {
  maintenance_enabled: boolean;
  can_preview: boolean;
  admin: {
    name: string;
    email: string;
    roles: string[];
  } | null;
};

type MaintenanceGateProps = {
  children: ReactNode;
  title?: string;
  eyebrow?: string;
  message?: string;
  statusPath?: string;
};

function MaintenanceScreen({
  title = 'We Are Tuning The Decks',
  eyebrow = 'Maintenance Mode',
  message = 'The Blend Battlegrounds is being updated right now. Admin preview access is available while the public site is under maintenance.',
}: Pick<MaintenanceGateProps, 'title' | 'eyebrow' | 'message'>) {
  return (
    <>
      <Helmet>
        <title>Under Maintenance | The Blend Battlegrounds</title>
        <meta name="description" content="The Blend Battlegrounds is currently under maintenance." />
      </Helmet>
      <main className="min-h-screen bg-[#050505] px-4 py-16 text-white">
        <div className="mx-auto flex min-h-[70vh] max-w-3xl items-center justify-center">
          <section className="w-full border border-[#2a2a2a] bg-[#0d0d0d] p-8 text-center">
            <div className="mx-auto mb-6 flex h-14 w-14 items-center justify-center bg-primary text-white">
              <ShieldCheck size={24} />
            </div>
            <p className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
              {eyebrow}
            </p>
            <h1 className="uppercase leading-none text-white" style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3rem, 8vw, 6rem)' }}>
              {title}
            </h1>
            <p className="mx-auto mt-6 max-w-xl text-base leading-7 text-[#aaaaaa]">
              {message}
            </p>
            <a
              href="/admin/login"
              className="mt-8 inline-flex h-11 items-center justify-center border border-[#333333] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Admin Login
            </a>
          </section>
        </div>
      </main>
    </>
  );
}

function LoadingScreen() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-[#050505] text-[#888888]">
      <Loader2 className="mr-3 animate-spin text-primary" size={20} />
      Checking preview access
    </main>
  );
}

export default function MaintenanceGate({
  children,
  title,
  eyebrow,
  message,
  statusPath = '/featured-ads/preview-status',
}: MaintenanceGateProps) {
  const [status, setStatus] = useState<PreviewStatus | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    apiClient
      .get<PreviewStatus>(statusPath)
      .then((response) => {
        if (!cancelled) setStatus(response.data);
      })
      .catch(() => {
        if (!cancelled) {
          setStatus({
            maintenance_enabled: true,
            can_preview: false,
            admin: null,
          });
        }
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [statusPath]);

  if (isLoading) return <LoadingScreen />;

  if (status?.maintenance_enabled && !status.can_preview) {
    return <MaintenanceScreen title={title} eyebrow={eyebrow} message={message} />;
  }

  return children;
}
