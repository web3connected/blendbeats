import AgoraRTC, {
  type IAgoraRTCClient,
  type IAgoraRTCRemoteUser,
  type IRemoteAudioTrack,
} from 'agora-rtc-sdk-ng';
import { Volume2, VolumeX } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import type { AgoraLiveToken } from '@/lib/live';

interface AgoraAudiencePlayerProps {
  token: AgoraLiveToken;
  onError?: (message: string) => void;
}

export default function AgoraAudiencePlayer({ token, onError }: AgoraAudiencePlayerProps) {
  const clientRef = useRef<IAgoraRTCClient | null>(null);
  const remoteVideoRef = useRef<HTMLDivElement | null>(null);
  const remoteAudioRef = useRef<IRemoteAudioTrack | null>(null);
  const volumeRef = useRef(80);
  const isMutedRef = useRef(false);
  const [remoteUid, setRemoteUid] = useState<string | null>(null);
  const [status, setStatus] = useState('Connecting');
  const [volume, setVolume] = useState(80);
  const [isMuted, setIsMuted] = useState(false);
  const [audioAvailable, setAudioAvailable] = useState(false);
  const [autoplayBlocked, setAutoplayBlocked] = useState(false);

  const playRemoteVideo = useCallback((user: IAgoraRTCRemoteUser) => {
    if (!remoteVideoRef.current || !user.videoTrack) {
      return;
    }

    const videoContainer = remoteVideoRef.current;

    videoContainer.replaceChildren();
    user.videoTrack.play(videoContainer, { fit: 'cover' });
    setRemoteUid(String(user.uid));
  }, []);

  const leave = useCallback(async () => {
    const client = clientRef.current;

    remoteVideoRef.current?.replaceChildren();
    remoteAudioRef.current = null;
    setRemoteUid(null);
    setAudioAvailable(false);

    if (client) {
      client.removeAllListeners();
      await client.leave();
      clientRef.current = null;
    }

    setStatus('Not connected');
  }, []);

  const applyVolume = useCallback((nextVolume: number, muted = false) => {
    const safeVolume = Math.min(100, Math.max(0, nextVolume));

    setVolume(safeVolume);
    setIsMuted(muted || safeVolume === 0);
    volumeRef.current = safeVolume;
    isMutedRef.current = muted || safeVolume === 0;
    remoteAudioRef.current?.setVolume(muted ? 0 : safeVolume);

    if (!muted && safeVolume > 0 && remoteAudioRef.current) {
      void AgoraRTC.resumeAudioContext();
      remoteAudioRef.current.play();
      setAutoplayBlocked(false);
    }
  }, []);

  useEffect(() => {
    let cancelled = false;

    async function connect() {
      try {
        setStatus('Connecting');
        AgoraRTC.onAutoplayFailed = () => setAutoplayBlocked(true);

        const client = AgoraRTC.createClient({ codec: 'vp8', mode: 'live' });
        clientRef.current = client;

        client.on('user-published', async (user, mediaType) => {
          await client.subscribe(user, mediaType);

          if (mediaType === 'video') {
            playRemoteVideo(user);
          }

          if (mediaType === 'audio') {
            const audioTrack = user.audioTrack;

            if (audioTrack) {
              remoteAudioRef.current = audioTrack;
              audioTrack.setVolume(isMutedRef.current ? 0 : volumeRef.current);
              audioTrack.play();
              setAudioAvailable(true);
            }
          }
        });

        client.on('user-unpublished', (_user, mediaType) => {
          if (mediaType === 'video') {
            remoteVideoRef.current?.replaceChildren();
            setRemoteUid(null);
          }

          if (mediaType === 'audio') {
            remoteAudioRef.current = null;
            setAudioAvailable(false);
          }
        });

        client.on('user-left', () => {
          remoteVideoRef.current?.replaceChildren();
          setRemoteUid(null);
        });

        client.on('connection-state-change', (currentState) => {
          if (currentState === 'CONNECTED') setStatus('Connected');
          if (currentState === 'CONNECTING' || currentState === 'RECONNECTING') setStatus('Connecting');
        });

        await client.setClientRole('audience');
        await client.join(token.appId, token.channelName, token.token, token.uid);

        if (cancelled) {
          await leave();
          return;
        }

        setStatus('Connected');
      } catch (error) {
        await leave();
        setStatus('Error');
        onError?.(error instanceof Error ? error.message : 'Unable to join Agora audience stream.');
      }
    }

    void connect();

    return () => {
      cancelled = true;
      AgoraRTC.onAutoplayFailed = undefined;
      void leave();
    };
  }, [leave, onError, playRemoteVideo, token.appId, token.channelName, token.token, token.uid]);

  return (
    <div className="rounded-lg border border-[#252525] bg-[#101010] p-4">
      <div className="mb-3 flex items-center justify-between gap-3">
        <h2 className="text-2xl text-white">Live Stream</h2>
        <span className="text-xs font-bold uppercase tracking-[0.18em] text-[#9d9d9d]">
          {remoteUid ? `UID ${remoteUid}` : status}
        </span>
      </div>
      <div className="relative aspect-video w-full overflow-hidden rounded-md bg-black">
        <div
          ref={remoteVideoRef}
          className="absolute inset-0 [&_video]:!h-full [&_video]:!w-full [&_video]:!object-cover"
        />
        {!remoteUid ? (
          <div className="pointer-events-none absolute inset-0 flex items-center justify-center px-4 text-center text-sm text-[#8d8d8d]">
            Waiting for the DJ stream.
          </div>
        ) : null}
      </div>
      <div className="mt-3 flex flex-wrap items-center gap-3 rounded-md border border-[#292929] bg-black/40 px-3 py-2">
        <button
          type="button"
          onClick={() => applyVolume(volume || 80, !isMuted)}
          disabled={!audioAvailable}
          className="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-white disabled:cursor-not-allowed disabled:text-[#666]"
        >
          {isMuted ? <VolumeX className="h-4 w-4" /> : <Volume2 className="h-4 w-4" />}
          {autoplayBlocked ? 'Enable Sound' : isMuted ? 'Unmute' : 'Sound'}
        </button>
        <input
          aria-label="Live stream volume"
          type="range"
          min="0"
          max="100"
          value={isMuted ? 0 : volume}
          disabled={!audioAvailable}
          onChange={(event) => applyVolume(Number(event.target.value))}
          className="h-1.5 min-w-36 flex-1 cursor-pointer accent-primary disabled:cursor-not-allowed"
        />
        <span className="w-9 text-right text-xs tabular-nums text-[#aaa]">
          {isMuted ? 0 : volume}%
        </span>
      </div>
    </div>
  );
}
