import { Helmet } from '@dr.pogodin/react-helmet';
import { ExternalLink, Radio } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

import { getLiveDirectory, type LiveStream } from '@/lib/live';

export default function LiveDirectoryPage() {
  const [streams, setStreams] = useState<LiveStream[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState('');

  useEffect(() => {
    let mounted = true;

    getLiveDirectory()
      .then((payload) => {
        if (!mounted) return;
        setStreams(payload.streams);
        setErrorMessage('');
      })
      .catch((error) => {
        if (!mounted) return;
        setErrorMessage(error instanceof Error ? error.message : 'Unable to load live streams.');
      })
      .finally(() => {
        if (mounted) setIsLoading(false);
      });

    return () => {
      mounted = false;
    };
  }, []);

  return (
    <main className="min-h-screen bg-[#070707] text-white">
      <Helmet>
        <title>Live DJs - Blend Battlegrounds</title>
        <meta name="description" content="Watch DJs currently live on Blend Battlegrounds." />
      </Helmet>

      <section className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
        <div className="flex flex-col gap-4 border-b border-[#262626] pb-6 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="mb-2 text-xs font-bold uppercase tracking-[0.24em] text-primary">BlendBeats Live</p>
            <h1 className="text-4xl text-white sm:text-5xl">Live DJs</h1>
          </div>
          <Link
            to="/dashboard/live"
            className="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-5 py-3 text-sm font-bold uppercase tracking-wide text-white transition hover:bg-red-500"
          >
            <Radio size={17} />
            Live Studio
          </Link>
        </div>

        {errorMessage ? (
          <div className="rounded-lg border border-red-500/40 bg-red-950/40 px-4 py-3 text-sm text-red-100">
            {errorMessage}
          </div>
        ) : null}

        {isLoading ? (
          <div className="rounded-lg border border-[#252525] bg-[#101010] p-6 text-sm text-[#c9c9c9]">
            Loading live streams.
          </div>
        ) : null}

        {!isLoading && streams.length === 0 ? (
          <div className="rounded-lg border border-[#252525] bg-[#101010] p-6">
            <h2 className="text-2xl text-white">No DJs are live right now</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-[#c9c9c9]">
              When a paid DJ starts a live set, the stream will appear here.
            </p>
          </div>
        ) : null}

        {streams.length > 0 ? (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {streams.map((stream) => {
              const username = stream.channel?.username_slug;

              return (
                <Link
                  key={stream.id}
                  to={username ? `/live/${username}` : '/live'}
                  className="rounded-lg border border-[#252525] bg-[#101010] p-5 transition hover:border-primary"
                >
                  <div className="mb-4 flex items-center justify-between gap-3">
                    <span className="rounded-full bg-primary px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-white">
                      Live
                    </span>
                    <ExternalLink size={16} className="text-[#9d9d9d]" />
                  </div>
                  <h2 className="text-2xl text-white">{stream.title}</h2>
                  <p className="mt-2 text-sm text-[#c9c9c9]">
                    {stream.dj?.dj_name ?? stream.dj?.name ?? 'BlendBeats DJ'}
                  </p>
                  {stream.started_at ? (
                    <p className="mt-4 text-xs uppercase tracking-[0.18em] text-[#8d8d8d]">
                      Started {new Date(stream.started_at).toLocaleTimeString()}
                    </p>
                  ) : null}
                </Link>
              );
            })}
          </div>
        ) : null}
      </section>
    </main>
  );
}
