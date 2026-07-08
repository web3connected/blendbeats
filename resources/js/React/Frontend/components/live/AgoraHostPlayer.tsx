import AgoraRTC, {
  type IAgoraRTCClient,
  type ICameraVideoTrack,
  type IMicrophoneAudioTrack,
} from 'agora-rtc-sdk-ng';
import {
  forwardRef,
  useCallback,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from 'react';

import type { AgoraLiveToken } from '@/lib/live';

export interface AgoraHostPlayerHandle {
  leave: () => Promise<void>;
}

interface AgoraHostPlayerProps {
  token: AgoraLiveToken;
  onError?: (message: string) => void;
  onStatusChange?: (status: string) => void;
}

const AgoraHostPlayer = forwardRef<AgoraHostPlayerHandle, AgoraHostPlayerProps>(
  ({ token, onError, onStatusChange }, ref) => {
    const clientRef = useRef<IAgoraRTCClient | null>(null);
    const localTracksRef = useRef<[IMicrophoneAudioTrack, ICameraVideoTrack] | null>(null);
    const videoRef = useRef<HTMLDivElement | null>(null);
    const [status, setStatus] = useState('Connecting');

    const updateStatus = useCallback(
      (nextStatus: string) => {
        setStatus(nextStatus);
        onStatusChange?.(nextStatus);
      },
      [onStatusChange],
    );

    const leave = useCallback(async () => {
      const tracks = localTracksRef.current;
      const client = clientRef.current;

      if (tracks) {
        tracks.forEach((track) => {
          track.stop();
          track.close();
        });
        localTracksRef.current = null;
      }

      videoRef.current?.replaceChildren();

      if (client) {
        client.removeAllListeners();
        await client.leave();
        clientRef.current = null;
      }

      updateStatus('Not connected');
    }, [updateStatus]);

    useImperativeHandle(ref, () => ({ leave }), [leave]);

    useEffect(() => {
      let cancelled = false;

      async function connect() {
        try {
          updateStatus('Connecting');

          const client = AgoraRTC.createClient({ codec: 'vp8', mode: 'live' });
          clientRef.current = client;

          client.on('connection-state-change', (currentState) => {
            if (currentState === 'CONNECTED') updateStatus('Connected');
            if (currentState === 'CONNECTING' || currentState === 'RECONNECTING') {
              updateStatus('Connecting');
            }
          });

          await client.setClientRole('host');
          await client.join(token.appId, token.channelName, token.token, token.uid);

          if (cancelled) {
            await leave();
            return;
          }

          const tracks = await AgoraRTC.createMicrophoneAndCameraTracks();

          if (cancelled) {
            tracks.forEach((track) => {
              track.stop();
              track.close();
            });
            await leave();
            return;
          }

          const videoContainer = videoRef.current;

          if (!videoContainer) {
            tracks.forEach((track) => {
              track.stop();
              track.close();
            });
            throw new Error('Host preview container was not ready.');
          }

          localTracksRef.current = tracks;
          videoContainer.replaceChildren();
          tracks[1].play(videoContainer, { fit: 'cover' });
          await client.publish(tracks);
          updateStatus('Connected');
        } catch (error) {
          await leave();
          updateStatus('Error');
          onError?.(error instanceof Error ? error.message : 'Unable to start Agora host stream.');
        }
      }

      void connect();

      return () => {
        cancelled = true;
        void leave();
      };
    }, [leave, onError, token.appId, token.channelName, token.token, token.uid, updateStatus]);

    return (
      <div className="rounded-lg border border-[#252525] bg-[#101010] p-4">
        <div className="mb-3 flex items-center justify-between gap-3">
          <h2 className="text-2xl text-white">Host Preview</h2>
          <span className="text-xs font-bold uppercase tracking-[0.18em] text-[#9d9d9d]">{status}</span>
        </div>
        <div
          ref={videoRef}
          className="relative aspect-video w-full overflow-hidden rounded-md bg-black [&_video]:!h-full [&_video]:!w-full [&_video]:!object-cover"
        >
          {status !== 'Connected' ? (
            <div className="flex h-full items-center justify-center px-4 text-center text-sm text-[#8d8d8d]">
              Preparing camera and microphone.
            </div>
          ) : null}
        </div>
      </div>
    );
  },
);

AgoraHostPlayer.displayName = 'AgoraHostPlayer';

export default AgoraHostPlayer;
