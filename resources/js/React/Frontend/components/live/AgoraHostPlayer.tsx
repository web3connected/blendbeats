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
import { isBrowserSecureContext, secureMediaContextMessage } from '@/lib/secure-context';

export interface AgoraHostPlayerHandle {
  leave: () => Promise<void>;
  stopRecording: () => Promise<Blob | null>;
}

interface AgoraHostPlayerProps {
  token: AgoraLiveToken;
  onError?: (message: string) => void;
  onStatusChange?: (status: string) => void;
  record?: boolean;
}

const AgoraHostPlayer = forwardRef<AgoraHostPlayerHandle, AgoraHostPlayerProps>(
  ({ token, onError, onStatusChange, record = false }, ref) => {
    const clientRef = useRef<IAgoraRTCClient | null>(null);
    const localTracksRef = useRef<[IMicrophoneAudioTrack, ICameraVideoTrack] | null>(null);
    const videoRef = useRef<HTMLDivElement | null>(null);
    const [status, setStatus] = useState('Connecting');
    const recorderRef = useRef<MediaRecorder | null>(null);
    const recordingChunksRef = useRef<Blob[]>([]);

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

    const stopRecording = useCallback(async (): Promise<Blob | null> => {
      const recorder = recorderRef.current;
      if (!recorder) return null;
      if (recorder.state !== 'inactive') {
        await new Promise<void>((resolve) => {
          recorder.addEventListener('stop', () => resolve(), { once: true });
          recorder.stop();
        });
      }
      recorderRef.current = null;
      const chunks = recordingChunksRef.current;
      recordingChunksRef.current = [];
      return chunks.length ? new Blob(chunks, { type: recorder.mimeType || 'video/webm' }) : null;
    }, []);

    useImperativeHandle(ref, () => ({ leave, stopRecording }), [leave, stopRecording]);

    useEffect(() => {
      let cancelled = false;

      async function connect() {
        try {
          updateStatus('Connecting');

          if (!isBrowserSecureContext()) {
            throw new Error(secureMediaContextMessage('Live camera and microphone access'));
          }

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
          if (record && typeof MediaRecorder !== 'undefined') {
            const mediaStream = new MediaStream([
              tracks[1].getMediaStreamTrack(),
              tracks[0].getMediaStreamTrack(),
            ]);
            const preferredType = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm']
              .find((type) => MediaRecorder.isTypeSupported(type));
            const recorder = new MediaRecorder(mediaStream, preferredType ? { mimeType: preferredType } : undefined);
            recordingChunksRef.current = [];
            recorder.addEventListener('dataavailable', (event) => {
              if (event.data.size > 0) recordingChunksRef.current.push(event.data);
            });
            recorder.start(1000);
            recorderRef.current = recorder;
          }
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
    }, [leave, onError, record, token.appId, token.channelName, token.token, token.uid, updateStatus]);

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
