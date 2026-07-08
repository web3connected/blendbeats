import { Helmet } from '@dr.pogodin/react-helmet';
import { Play, Square, Video } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import AgoraHostPlayer, { type AgoraHostPlayerHandle } from '@/components/live/AgoraHostPlayer';
import {
  endLive,
  getLiveStudio,
  getLiveToken,
  startLive,
  type AgoraLiveToken,
  type LiveStream,
  type LiveStudioState,
} from '@/lib/live';

export default function LiveStudioPage() {
  const hostPlayerRef = useRef<AgoraHostPlayerHandle | null>(null);
  const { user, isLoading: isAuthLoading } = useAuth();
  const [studioState, setStudioState] = useState<LiveStudioState | null>(null);
  const [activeStream, setActiveStream] = useState<LiveStream | null>(null);
  const [hostToken, setHostToken] = useState<AgoraLiveToken | null>(null);
  const [title, setTitle] = useState('');
  const [recordingEnabled, setRecordingEnabled] = useState(false);
  const [remainingSeconds, setRemainingSeconds] = useState<number | null>(null);
  const [limitMessage, setLimitMessage] = useState('');
  const [status, setStatus] = useState('Not connected');
  const [errorMessage, setErrorMessage] = useState('');
  const [isBusy, setIsBusy] = useState(false);
  const autoEndingRef = useRef(false);

  const loadStudio = useCallback(async () => {
    if (!user) return;

    try {
      const state = await getLiveStudio();
      setStudioState(state);
      setActiveStream(state.active_stream);
      setErrorMessage('');

      if (state.active_stream) {
        const token = await getLiveToken({
          role: 'host',
          live_stream_id: state.active_stream.id,
        });
        setHostToken(token);
      } else {
        setHostToken(null);
      }
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Unable to load Live Studio.');
    }
  }, [user]);

  useEffect(() => {
    void loadStudio();
  }, [loadStudio]);

  const handleEnd = useCallback(async () => {
    setIsBusy(true);
    setErrorMessage('');

    try {
      await hostPlayerRef.current?.leave();
      await endLive();
      setHostToken(null);
      setActiveStream(null);
      setStatus('Not connected');
      setRemainingSeconds(null);
      autoEndingRef.current = false;
      await loadStudio();
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Unable to end live stream.');
    } finally {
      setIsBusy(false);
    }
  }, [loadStudio]);

  useEffect(() => {
    if (!activeStream?.started_at || activeStream.max_duration_minutes === null) {
      setRemainingSeconds(null);
      return undefined;
    }

    const expiresAt = new Date(activeStream.started_at).getTime() + activeStream.max_duration_minutes * 60_000;

    const updateRemaining = () => {
      const nextRemaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
      setRemainingSeconds(nextRemaining);

      if (nextRemaining === 0 && !autoEndingRef.current) {
        autoEndingRef.current = true;
        setLimitMessage("Your plan's stream time limit has ended.");
        void handleEnd();
      }
    };

    updateRemaining();
    const intervalId = window.setInterval(updateRemaining, 1000);

    return () => window.clearInterval(intervalId);
  }, [activeStream?.id, activeStream?.max_duration_minutes, activeStream?.started_at, handleEnd]);

  const handleStart = async () => {
    setIsBusy(true);
    setErrorMessage('');
    setLimitMessage('');

    try {
      const payload = await startLive(title, recordingEnabled);
      setActiveStream(payload.stream);
      setHostToken(payload.token);
      setRecordingEnabled(false);
      setTitle('');
    } catch (error) {
      setErrorMessage(error instanceof Error ? error.message : 'Unable to start live stream.');
    } finally {
      setIsBusy(false);
    }
  };

  if (isAuthLoading) {
    return (
      <main className="min-h-screen bg-[#070707] px-4 py-8 text-white">
        <div className="mx-auto max-w-6xl rounded-lg border border-[#252525] bg-[#101010] p-6 text-sm text-[#c9c9c9]">
          Loading Live Studio.
        </div>
      </main>
    );
  }

  if (!user) {
    return (
      <main className="min-h-screen bg-[#070707] px-4 py-8 text-white">
        <div className="mx-auto max-w-6xl rounded-lg border border-[#252525] bg-[#101010] p-6">
          <h1 className="text-4xl text-white">Live Studio</h1>
          <p className="mt-2 text-sm leading-6 text-[#c9c9c9]">Sign in with a paid DJ account to go live.</p>
          <Link
            to="/login"
            className="mt-5 inline-flex rounded-md bg-primary px-5 py-3 text-sm font-bold uppercase tracking-wide text-white"
          >
            Sign In
          </Link>
        </div>
      </main>
    );
  }

  if (!user.dj_profile) {
    return (
      <main className="min-h-screen bg-[#070707] px-4 py-8 text-white">
        <div className="mx-auto max-w-6xl rounded-lg border border-[#252525] bg-[#101010] p-6">
          <h1 className="text-4xl text-white">Live Studio</h1>
          <p className="mt-2 text-sm leading-6 text-[#c9c9c9]">Create a DJ profile before starting a live stream.</p>
          <Link
            to="/dj/start"
            className="mt-5 inline-flex rounded-md bg-primary px-5 py-3 text-sm font-bold uppercase tracking-wide text-white"
          >
            Start DJ Career
          </Link>
        </div>
      </main>
    );
  }

  const canGoLive = Boolean(studioState?.can_go_live);
  const liveLimits = studioState?.limits;
  const monthlyUsage = studioState?.monthly_usage;
  const canRecordLiveStreams = Boolean(liveLimits?.can_record_live_streams);
  const publicUsername = studioState?.channel?.username_slug ?? activeStream?.channel?.username_slug;
  const publicChannelUrl = publicUsername
    ? `/live/${publicUsername}`
    : null;
  const streamTimeDisplay = activeStream && activeStream.max_duration_minutes === null
    ? 'Unlimited stream time'
    : activeStream && remainingSeconds !== null
      ? `${Math.floor(remainingSeconds / 60).toString().padStart(2, '0')}:${(remainingSeconds % 60).toString().padStart(2, '0')} remaining`
      : liveLimits?.max_stream_minutes === null
        ? 'Unlimited stream time'
        : liveLimits?.max_stream_minutes
        ? `${liveLimits.max_stream_minutes}:00 available`
        : 'Stream time limit loading';
  const monthlyUsageDisplay = monthlyUsage?.limit === null
    ? 'Live streams used this month: Unlimited'
    : `Live streams used this month: ${monthlyUsage?.used ?? 0} / ${monthlyUsage?.limit ?? 0}`;

  return (
    <main className="min-h-screen bg-[#070707] text-white">
      <Helmet>
        <title>Live Studio - Blend Battlegrounds</title>
      </Helmet>

      <section className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
        <div className="flex flex-col gap-4 border-b border-[#262626] pb-6 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="mb-2 text-xs font-bold uppercase tracking-[0.24em] text-primary">DJ Live Studio</p>
            <h1 className="text-4xl text-white sm:text-5xl">Go Live</h1>
            <p className="mt-3 max-w-2xl text-sm leading-6 text-[#c9c9c9]">
              {user.dj_profile.dj_name} can host an Agora live stream from this studio.
            </p>
          </div>
          <div className="flex min-w-48 flex-col gap-2 rounded-lg border border-[#2a2a2a] bg-[#101010] px-4 py-3">
            <span className="text-xs font-bold uppercase tracking-[0.18em] text-[#9d9d9d]">Status</span>
            <span className="text-lg font-bold text-white">{activeStream ? status : 'Offline'}</span>
            {publicChannelUrl ? (
              <Link to={publicChannelUrl} className="text-sm text-accent hover:text-white">
                View public channel
              </Link>
            ) : null}
          </div>
        </div>

        {errorMessage ? (
          <div className="rounded-lg border border-red-500/40 bg-red-950/40 px-4 py-3 text-sm text-red-100">
            {errorMessage}
          </div>
        ) : null}

        {limitMessage ? (
          <div className="rounded-lg border border-amber-500/40 bg-amber-950/30 px-4 py-3 text-sm text-amber-100">
            {limitMessage}
          </div>
        ) : null}

        {!canGoLive ? (
          <div className="rounded-lg border border-[#252525] bg-[#101010] p-6">
            <h2 className="text-2xl text-white">Paid access required</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-[#c9c9c9]">
              Free accounts can watch live streams, but only paid DJ memberships can start one.
            </p>
            <Link
              to="/pricing"
              className="mt-5 inline-flex rounded-md bg-primary px-5 py-3 text-sm font-bold uppercase tracking-wide text-white"
            >
              View Memberships
            </Link>
          </div>
        ) : null}

        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]">
          <section className="rounded-lg border border-[#252525] bg-[#101010] p-5">
            <div className="mb-4 flex items-center gap-3">
              <Video size={20} className="text-primary" />
              <h2 className="text-2xl text-white">Stream Controls</h2>
            </div>

            <label className="block text-xs font-bold uppercase tracking-[0.18em] text-[#9d9d9d]" htmlFor="live-title">
              Stream Title
            </label>
            <input
              id="live-title"
              className="mt-2 w-full rounded-md border border-[#303030] bg-black px-4 py-3 text-sm text-white outline-none focus:border-primary"
              disabled={Boolean(activeStream) || isBusy}
              placeholder={studioState?.channel?.title ?? 'Tonight on BlendBeats'}
              value={title}
              onChange={(event) => setTitle(event.target.value)}
            />

            <div className="mt-4 rounded-md border border-[#252525] bg-black/40 px-4 py-3">
              <p className="text-sm font-semibold text-white">{monthlyUsageDisplay}</p>
              <p className="mt-1 text-sm text-[#c9c9c9]">{streamTimeDisplay}</p>
            </div>

            {canRecordLiveStreams ? (
              <label className="mt-4 flex items-start gap-3 rounded-md border border-[#252525] bg-black/40 px-4 py-3 text-sm text-[#d6d6d6]">
                <input
                  className="mt-1 h-4 w-4 accent-primary"
                  checked={recordingEnabled}
                  disabled={Boolean(activeStream) || isBusy}
                  type="checkbox"
                  onChange={(event) => setRecordingEnabled(event.target.checked)}
                />
                <span>
                  <span className="block font-semibold text-white">Save this live stream to my account</span>
                  <span className="mt-1 block text-[#9d9d9d]">Recording storage is prepared for this plan. Cloud recording will be connected in a later milestone.</span>
                </span>
              </label>
            ) : null}

            <div className="mt-5 flex flex-wrap gap-3">
              <button
                className="inline-flex items-center gap-2 rounded-md bg-primary px-5 py-3 text-sm font-bold uppercase tracking-wide text-white transition hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50"
                disabled={!canGoLive || Boolean(activeStream) || isBusy}
                type="button"
                onClick={() => void handleStart()}
              >
                <Play size={16} />
                Go Live
              </button>
              <button
                className="inline-flex items-center gap-2 rounded-md border border-[#3a3a3a] px-5 py-3 text-sm font-bold uppercase tracking-wide text-[#d6d6d6] transition hover:border-white hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                disabled={!activeStream || isBusy}
                type="button"
                onClick={() => void handleEnd()}
              >
                <Square size={16} />
                End Live
              </button>
            </div>
          </section>

          <aside className="rounded-lg border border-[#252525] bg-[#101010] p-5">
            <h2 className="text-2xl text-white">Current Stream</h2>
            {activeStream ? (
              <>
                <span className="mt-4 inline-flex rounded-full bg-primary px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-white">
                  Live
                </span>
                <p className="mt-4 text-sm font-semibold text-white">{activeStream.title}</p>
                {activeStream.started_at ? (
                  <p className="mt-2 text-sm text-[#c9c9c9]">
                    Started {new Date(activeStream.started_at).toLocaleString()}
                  </p>
                ) : null}
                <p className="mt-2 text-sm text-[#c9c9c9]">{streamTimeDisplay}</p>
                <p className="mt-2 text-sm text-[#c9c9c9]">
                  Recording: {activeStream.recording_enabled ? 'Requested' : 'Off'}
                </p>
              </>
            ) : (
              <p className="mt-2 text-sm leading-6 text-[#c9c9c9]">No active stream.</p>
            )}
          </aside>
        </div>

        {activeStream && hostToken ? (
          <AgoraHostPlayer
            ref={hostPlayerRef}
            key={`${activeStream.id}-${hostToken.channelName}`}
            token={hostToken}
            onError={setErrorMessage}
            onStatusChange={setStatus}
          />
        ) : null}
      </section>
    </main>
  );
}
