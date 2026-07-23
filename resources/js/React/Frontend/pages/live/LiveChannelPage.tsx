import { Helmet } from '@dr.pogodin/react-helmet';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';

import AgoraAudiencePlayer from '@/components/live/AgoraAudiencePlayer';
import {
  getLiveChannel,
  getLiveToken,
  heartbeatLiveViewer,
  leaveLiveViewer,
  type AgoraLiveToken,
  type LiveChannel,
  type LiveViewerPresence,
} from '@/lib/live';

export default function LiveChannelPage() {
  const { username } = useParams();
  const activeStreamIdRef = useRef<number | null>(null);
  const viewerIdRef = useRef(window.crypto.randomUUID());
  const [channel, setChannel] = useState<LiveChannel | null>(null);
  const [token, setToken] = useState<AgoraLiveToken | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState('');
  const [presence, setPresence] = useState<LiveViewerPresence>({ count: 0, viewers: [] });

  const loadChannel = useCallback(async () => {
    if (!username) return;

    try {
      const payload = await getLiveChannel(username);
      const activeStream = payload.channel.active_stream;

      setChannel(payload.channel);
      setErrorMessage('');

      if (!activeStream) {
        activeStreamIdRef.current = null;
        setToken(null);
        return;
      }

      if (activeStreamIdRef.current !== activeStream.id) {
        const nextToken = await getLiveToken({
          role: 'audience',
          live_stream_id: activeStream.id,
        });

        activeStreamIdRef.current = activeStream.id;
        setToken(nextToken);
      }
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Unable to load this live channel.');
    } finally {
      setIsLoading(false);
    }
  }, [username]);

  useEffect(() => {
    void loadChannel();
    const intervalId = window.setInterval(() => void loadChannel(), 10000);

    return () => window.clearInterval(intervalId);
  }, [loadChannel]);

  useEffect(() => {
    const streamId = channel?.active_stream?.id;

    if (!streamId || !token) {
      setPresence({ count: 0, viewers: [] });
      return;
    }

    let cancelled = false;

    const heartbeat = async () => {
      try {
        const nextPresence = await heartbeatLiveViewer(streamId, viewerIdRef.current);
        if (!cancelled) setPresence(nextPresence);
      } catch {
        // Viewer presence should never interrupt playback.
      }
    };

    void heartbeat();
    const intervalId = window.setInterval(() => void heartbeat(), 10000);

    return () => {
      cancelled = true;
      window.clearInterval(intervalId);
      void leaveLiveViewer(streamId, viewerIdRef.current).catch(() => undefined);
    };
  }, [channel?.active_stream?.id, token]);

  const title = channel?.title ?? 'Live Channel';
  const activeStream = channel?.active_stream ?? null;

  return (
    <main className="min-h-screen bg-[#070707] text-white">
      <Helmet>
        <title>{title} - Blend Battlegrounds Live</title>
      </Helmet>

      <section className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
        <div className="border-b border-[#262626] pb-6">
          <p className="mb-2 text-xs font-bold uppercase tracking-[0.24em] text-primary">Live Channel</p>
          <h1 className="text-4xl text-white sm:text-5xl">{title}</h1>
          {channel?.description ? (
            <p className="mt-3 max-w-3xl text-sm leading-6 text-[#c9c9c9]">{channel.description}</p>
          ) : null}
        </div>

        {errorMessage ? (
          <div className="rounded-lg border border-red-500/40 bg-red-950/40 px-4 py-3 text-sm text-red-100">
            {errorMessage}
          </div>
        ) : null}

        {isLoading ? (
          <div className="rounded-lg border border-[#252525] bg-[#101010] p-6 text-sm text-[#c9c9c9]">
            Loading live channel.
          </div>
        ) : null}

        {!isLoading && !activeStream ? (
          <div className="rounded-lg border border-[#252525] bg-[#101010] p-6">
            <h2 className="text-2xl text-white">Offline</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-[#c9c9c9]">
              This DJ does not have an active live stream right now.
            </p>
            <Link
              to="/live"
              className="mt-5 inline-flex rounded-md border border-[#3a3a3a] px-4 py-2 text-sm font-bold uppercase tracking-wide text-[#d6d6d6] transition hover:border-white hover:text-white"
            >
              Back To Live
            </Link>
          </div>
        ) : null}

        {activeStream && token ? (
          <div className="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
            <AgoraAudiencePlayer
              key={`${activeStream.id}-${token.channelName}`}
              token={token}
              onError={setErrorMessage}
            />
            <aside className="rounded-lg border border-[#252525] bg-[#101010] p-5">
              <span className="rounded-full bg-primary px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-white">
                Live
              </span>
              <h2 className="mt-4 text-2xl text-white">{activeStream.title}</h2>
              <p className="mt-2 text-sm text-[#c9c9c9]">
                {channel?.dj.dj_name ?? channel?.dj.name ?? 'BlendBeats DJ'}
              </p>
              {activeStream.started_at ? (
                <p className="mt-5 text-xs uppercase tracking-[0.18em] text-[#8d8d8d]">
                  Started {new Date(activeStream.started_at).toLocaleString()}
                </p>
              ) : null}
              <div className="mt-6 border-t border-[#292929] pt-5">
                <h3 className="text-sm font-bold uppercase tracking-[0.16em] text-white">
                  {presence.count} {presence.count === 1 ? 'Viewer' : 'Viewers'} Watching
                </h3>
                <div className="mt-3 flex flex-col gap-2">
                  {presence.viewers.map((viewer) => (
                    <div
                      key={`${viewer.user_id ?? 'guest'}-${viewer.name}`}
                      className="flex items-center gap-2 text-sm text-[#c9c9c9]"
                    >
                      <span className="h-2 w-2 rounded-full bg-emerald-400" />
                      <span>{viewer.name}</span>
                      {viewer.is_guest ? <span className="text-xs text-[#777]">Guest</span> : null}
                    </div>
                  ))}
                  {presence.count === 0 ? (
                    <p className="text-sm text-[#777]">Waiting for viewers.</p>
                  ) : null}
                </div>
              </div>
            </aside>
          </div>
        ) : null}
      </section>
    </main>
  );
}
