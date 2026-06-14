import { Helmet } from '@dr.pogodin/react-helmet';
import {
  ArrowLeft,
  ArrowRight,
  Disc3,
  FileAudio,
  FileImage,
  FileVideo,
  HardDrive,
  Loader2,
  UploadCloud,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { listMediaLibrary, type MediaFileRecord, type MediaStorageQuota } from '@/lib/media-manager';
import { getMediaSetup, type MediaAccount, type MediaSetupApiError } from '@/lib/media-setup';

function formatDate(value: string | null) {
  if (!value) return 'Not activated yet';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(value));
}

function countBy(files: MediaFileRecord[], predicate: (file: MediaFileRecord) => boolean) {
  return files.filter(predicate).length;
}

export default function StoragePage() {
  const { user, isLoading } = useAuth();
  const [mediaAccount, setMediaAccount] = useState<MediaAccount | null>(null);
  const [quota, setQuota] = useState<MediaStorageQuota | null>(null);
  const [files, setFiles] = useState<MediaFileRecord[]>([]);
  const [isStorageLoading, setIsStorageLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!user) return;

    let cancelled = false;

    setIsStorageLoading(true);
    setError('');

    Promise.all([getMediaSetup(), listMediaLibrary()])
      .then(([setup, library]) => {
        if (cancelled) return;

        setMediaAccount(setup.media_account);
        setQuota(library.quota ?? setup.quota);
        setFiles(library.files);
      })
      .catch((loadError: unknown) => {
        if (cancelled) return;

        setError(loadError instanceof Error ? loadError.message : 'Unable to load storage details right now.');
      })
      .finally(() => {
        if (!cancelled) setIsStorageLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [user]);

  const fileStats = useMemo(
    () => [
      { label: 'Audio', value: countBy(files, (file) => file.is_audio), icon: FileAudio },
      { label: 'Images', value: countBy(files, (file) => file.is_image), icon: FileImage },
      { label: 'Videos', value: countBy(files, (file) => file.is_video), icon: FileVideo },
      { label: 'Total Files', value: files.length, icon: Disc3 },
    ],
    [files],
  );

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

  const usagePercent = Math.min(quota?.usage_percent ?? 0, 100);
  const isNearLimit = usagePercent >= 80;

  return (
    <>
      <Helmet>
        <title>Storage | The Blend Battlegrounds</title>
        <meta name="description" content="Review BlendBeats storage usage, limits, uploads, and storage upgrade options." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-6xl">
            <Link
              to="/account/settings"
              className="mb-10 inline-flex h-11 items-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              Settings
            </Link>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
              <div>
                <p
                  className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Account / Storage
                </p>
                <h1
                  className="uppercase leading-none text-white"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 8vw, 6.5rem)' }}
                >
                  Storage
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  Track media usage, upload limits, remaining space, and the storage tier attached to your BlendBeats account.
                </p>
              </div>

              <div className="border border-[#303030] bg-[#111111] p-5">
                <div className="mb-4 flex h-12 w-12 items-center justify-center bg-primary text-white">
                  <HardDrive size={20} />
                </div>
                <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Current Tier</p>
                <p className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  {quota?.tier_label ?? user.media_storage_tier ?? 'Free'}
                </p>
                <p className="mt-2 text-sm leading-6 text-[#888888]">
                  {quota ? `${quota.limit_formatted} included. ${quota.remaining_formatted} remaining.` : 'Loading account storage limit.'}
                </p>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            {isStorageLoading && (
              <div className="flex min-h-40 items-center justify-center border border-[#2a2a2a] bg-[#080808] text-[#888888]">
                <Loader2 className="mr-3 animate-spin text-primary" size={20} />
                Loading storage details
              </div>
            )}

            {!isStorageLoading && error && (
              <div className="border border-primary bg-[#160808] p-4 text-sm leading-6 text-[#dddddd]">{error}</div>
            )}

            {!isStorageLoading && !error && (
              <div className="grid gap-6">
                <section className="grid gap-5 border border-[#2a2a2a] bg-[#111111] p-5 lg:grid-cols-[minmax(0,1fr)_280px]">
                  <div>
                    <div className="flex flex-wrap items-end justify-between gap-4">
                      <div>
                        <p className="text-[11px] font-bold uppercase tracking-widest text-[#FFB800]">Storage Usage</p>
                        <h2 className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {quota?.used_formatted ?? '0 B'} Used
                        </h2>
                      </div>
                      <p className={`text-4xl uppercase ${isNearLimit ? 'text-primary' : 'text-[#FFB800]'}`} style={{ fontFamily: 'var(--font-heading)' }}>
                        {quota?.usage_percent ?? 0}%
                      </p>
                    </div>

                    <div className="mt-6 h-6 border border-[#333333] bg-[#080808] p-1">
                      <div className={`h-full ${isNearLimit ? 'bg-primary' : 'bg-[#FFB800]'}`} style={{ width: `${usagePercent}%` }} />
                    </div>

                    <div className="mt-4 grid gap-3 text-sm text-[#aaaaaa] sm:grid-cols-3">
                      <div className="border border-[#262626] bg-[#080808] p-4">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Limit</p>
                        <p className="mt-2 text-xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {quota?.limit_formatted ?? 'Unknown'}
                        </p>
                      </div>
                      <div className="border border-[#262626] bg-[#080808] p-4">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Remaining</p>
                        <p className="mt-2 text-xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {quota?.remaining_formatted ?? 'Unknown'}
                        </p>
                      </div>
                      <div className="border border-[#262626] bg-[#080808] p-4">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Status</p>
                        <p className="mt-2 text-xl capitalize text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {mediaAccount?.status ?? 'Ready'}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="border border-[#2a2a2a] bg-[#080808] p-5">
                    <UploadCloud className="text-primary" size={24} />
                    <p className="mt-4 text-[11px] font-bold uppercase tracking-widest text-[#777777]">Workspace</p>
                    <p className="mt-2 break-all text-sm leading-6 text-[#aaaaaa]">
                      {mediaAccount?.root_path ?? 'Media workspace will activate when uploads are enabled.'}
                    </p>
                    <p className="mt-4 text-[11px] font-bold uppercase tracking-widest text-[#777777]">Activated</p>
                    <p className="mt-2 text-sm text-[#dddddd]">{formatDate(mediaAccount?.activated_at ?? null)}</p>
                  </div>
                </section>

                <section className="grid gap-4 md:grid-cols-4">
                  {fileStats.map((item) => {
                    const Icon = item.icon;

                    return (
                      <article key={item.label} className="border border-[#2a2a2a] bg-[#111111] p-5">
                        <Icon className="text-primary" size={22} />
                        <p className="mt-5 text-[10px] font-bold uppercase tracking-widest text-[#777777]">{item.label}</p>
                        <p className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {item.value.toLocaleString()}
                        </p>
                      </article>
                    );
                  })}
                </section>

                <section className="grid gap-5 lg:grid-cols-2">
                  <article className="border border-[#2a2a2a] bg-[#111111] p-5">
                    <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Upload Management
                    </h2>
                    <p className="mt-3 text-sm leading-6 text-[#888888]">
                      Portfolio uploads, cover images, audio, video, and future media assets count toward this storage limit.
                    </p>
                    <Link
                      to="/dj/portfolio"
                      className="mt-6 inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-[#d91515]"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      Manage Uploads
                      <ArrowRight size={15} />
                    </Link>
                  </article>

                  <article className="border border-[#2a2a2a] bg-[#111111] p-5">
                    <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Need More Space?
                    </h2>
                    <p className="mt-3 text-sm leading-6 text-[#888888]">
                      Higher membership tiers unlock larger storage limits, stronger promotion groups, and more growth tools.
                    </p>
                    <Link
                      to="/pricing"
                      className="mt-6 inline-flex h-11 items-center justify-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      View Plans
                      <ArrowRight size={15} />
                    </Link>
                  </article>
                </section>
              </div>
            )}
          </div>
        </section>
      </main>
    </>
  );
}
