import AgoraRTC, { type IAgoraRTCClient, type IAgoraRTCRemoteUser } from 'agora-rtc-sdk-ng';
import { useCallback, useEffect, useRef, useState } from 'react';

import type { AgoraLiveToken } from '@/lib/live';

interface AgoraAudiencePlayerProps {
  token: AgoraLiveToken;
  onError?: (message: string) => void;
}

export default function AgoraAudiencePlayer({ token, onError }: AgoraAudiencePlayerProps) {
  const clientRef = useRef<IAgoraRTCClient | null>(null);
  const remoteVideoRef = useRef<HTMLDivElement | null>(null);
  const [remoteUid, setRemoteUid] = useState<string | null>(null);
  const [status, setStatus] = useState('Connecting');

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
    setRemoteUid(null);

    if (client) {
      client.removeAllListeners();
      await client.leave();
      clientRef.current = null;
    }

    setStatus('Not connected');
  }, []);

  useEffect(() => {
    let cancelled = false;

    async function connect() {
      try {
        setStatus('Connecting');

        const client = AgoraRTC.createClient({ codec: 'vp8', mode: 'live' });
        clientRef.current = client;

        client.on('user-published', async (user, mediaType) => {
          await client.subscribe(user, mediaType);

          if (mediaType === 'video') {
            playRemoteVideo(user);
          }

          if (mediaType === 'audio') {
            user.audioTrack?.play();
          }
        });

        client.on('user-unpublished', (_user, mediaType) => {
          if (mediaType === 'video') {
            remoteVideoRef.current?.replaceChildren();
            setRemoteUid(null);
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
      <div
        ref={remoteVideoRef}
        className="relative aspect-video w-full overflow-hidden rounded-md bg-black [&_video]:!h-full [&_video]:!w-full [&_video]:!object-cover"
      >
        {!remoteUid ? (
          <div className="flex h-full items-center justify-center px-4 text-center text-sm text-[#8d8d8d]">
            Waiting for the DJ stream.
          </div>
        ) : null}
      </div>
    </div>
  );
}
