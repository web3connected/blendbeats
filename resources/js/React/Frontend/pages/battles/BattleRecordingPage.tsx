import { Helmet } from '@dr.pogodin/react-helmet';
import {
  AlertTriangle,
  ArrowLeft,
  BadgeCheck,
  Camera,
  CheckCircle2,
  Clock,
  Download,
  Loader2,
  Mic,
  Play,
  RefreshCcw,
  Send,
  ShieldCheck,
  Trophy,
  UploadCloud,
  Video,
  Volume2,
  X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  duplicateBattleEntryForTesting,
  getBattle,
  submitBattleEntry,
  type BattleEntry,
  type BattleRecord,
} from '@/lib/battles';

type RecordingMode = 'idle' | 'ready' | 'countdown' | 'recording' | 'preview' | 'uploading' | 'submitted';

const RECORDING_COUNTDOWN_SECONDS = 10;

const MAX_RERECORDS = 3;

const TEST_SAMPLES = [
  { name: 'Test Drum Break', detail: 'Required rhythm layer', url: '' },
  { name: 'Test Bass Loop', detail: 'Required low-end phrase', url: '' },
  { name: 'Test Vocal Chop', detail: 'Required accent sample', url: '' },
];

function formatTokens(value: number): string {
  return new Intl.NumberFormat('en-US').format(value);
}

function formatSeconds(value: number): string {
  const minutes = Math.floor(Math.max(0, value) / 60);
  const seconds = Math.max(0, value) % 60;
  return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

function formatCountdown(target: string | null): string {
  if (!target) return 'Not set';

  const remaining = Math.max(0, new Date(target).getTime() - Date.now());
  const totalMinutes = Math.floor(remaining / 60000);
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;

  if (hours <= 0 && minutes <= 0) return 'Closing now';

  return `${hours}h ${minutes}m`;
}

function useMinuteTick(): number {
  const [tick, setTick] = useState(0);

  useEffect(() => {
    const timer = window.setInterval(() => setTick((value) => value + 1), 30000);
    return () => window.clearInterval(timer);
  }, []);

  return tick;
}

function actionErrorMessage(error: unknown): string {
  if (error instanceof Error && 'response' in error) {
    const response = error.response as { data?: { message?: string; errors?: Record<string, string[]> } };
    const [first] = Object.values(response.data?.errors || {}).flat();
    return first || response.data?.message || 'Unable to update this battle.';
  }

  return error instanceof Error ? error.message : 'Unable to update this battle.';
}

function isLocalSecureHost(): boolean {
  if (typeof window === 'undefined') return false;

  return ['localhost', '127.0.0.1', '[::1]'].includes(window.location.hostname);
}

function cameraSupportMessage(): string {
  if (typeof window !== 'undefined' && !window.isSecureContext && !isLocalSecureHost()) {
    return `Camera access is blocked on ${window.location.protocol}//${window.location.host}. Use HTTPS for blendbeats.test or run the app on localhost for local recording tests.`;
  }

  return 'Camera and microphone access is not available in this browser.';
}

function recordingSupportMessage(): string {
  return 'Camera preview is available, but this browser does not support MediaRecorder for battle recording.';
}

function BattleStatusHeader({ battle }: { battle: BattleRecord }) {
  useMinuteTick();

  return (
    <section className="border border-[#2a2a2a] bg-[#111111] p-5">
      <div className="flex flex-wrap items-start justify-between gap-5">
        <div className="min-w-0">
          <div className="mb-3 flex flex-wrap gap-2">
            <span className="inline-flex h-7 items-center border border-primary/50 px-2 text-[10px] font-bold uppercase tracking-widest text-primary">
              Recording Phase
            </span>
            <span className="inline-flex h-7 items-center border border-[#333333] px-2 text-[10px] font-bold uppercase tracking-widest text-[#999999]">
              {battle.voting_duration_hours} Hour Vote
            </span>
          </div>
          <h1 className="text-4xl uppercase leading-none text-white md:text-6xl" style={{ fontFamily: 'var(--font-heading)' }}>
            {battle.title}
          </h1>
          <p className="mt-3 text-sm text-[#aaaaaa]">
            {battle.challenger.dj_name} vs {battle.opponent.dj_name}
          </p>
        </div>

        <div className="grid min-w-72 grid-cols-2 border border-[#242424] bg-[#050505]">
          <div className="border-r border-[#242424] p-3">
            <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Recording Time</p>
            <p className="mt-1 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatCountdown(battle.recording_ends_at)}
            </p>
          </div>
          <div className="p-3">
            <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Stake</p>
            <p className="mt-1 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatTokens(battle.stake_amount)}
            </p>
          </div>
          <div className="border-r border-t border-[#242424] p-3">
            <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Fan Rewards</p>
            <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatTokens(battle.fan_reward_pool_amount)}
            </p>
          </div>
          <div className="border-t border-[#242424] p-3">
            <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Prize Pool</p>
            <p className="mt-1 text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {formatTokens(battle.prize_pool_amount)}
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}

function BattleSampleList({ battle }: { battle: BattleRecord }) {
  const metadataSamples = Array.isArray(battle.sample_pack_metadata?.samples)
    ? battle.sample_pack_metadata.samples as Array<{ name?: string; detail?: string; url?: string }>
    : [];
  const samples = metadataSamples.length > 0 ? metadataSamples : TEST_SAMPLES;
  const isBypassed = battle.sample_pack_status === 'bypassed';

  return (
    <section className="border border-[#2a2a2a] bg-[#111111] p-5">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Required Battle Samples</p>
          <h2 className="mt-1 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            Sample Pack
          </h2>
        </div>
        <span className="inline-flex h-8 items-center border border-primary/50 px-3 text-[10px] font-bold uppercase tracking-widest text-primary">
          {isBypassed ? 'Testing Bypass' : battle.sample_pack_status}
        </span>
      </div>

      <div className="grid gap-3 md:grid-cols-3">
        {samples.map((sample, index) => (
          <article key={`${sample.name || 'sample'}-${index}`} className="border border-[#242424] bg-[#080808] p-4">
            <div className="mb-4 flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="truncate text-sm font-bold uppercase tracking-wide text-white">{sample.name || `Battle Sample ${index + 1}`}</p>
                <p className="mt-1 text-xs text-[#888888]">{sample.detail || 'Required sample'}</p>
              </div>
              <span className="inline-flex h-7 shrink-0 items-center gap-1 border border-emerald-500/40 px-2 text-[10px] font-bold uppercase tracking-widest text-emerald-300">
                <BadgeCheck size={13} />
                Required
              </span>
            </div>

            <div className="flex gap-2">
              <button
                type="button"
                disabled={!sample.url}
                className="inline-flex h-9 flex-1 items-center justify-center gap-2 border border-[#333333] px-3 text-[10px] font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-45"
              >
                <Volume2 size={14} />
                Preview
              </button>
              <a
                href={sample.url || undefined}
                aria-disabled={!sample.url}
                className={`inline-flex h-9 w-10 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors ${
                  sample.url ? 'hover:border-primary hover:text-primary' : 'pointer-events-none opacity-45'
                }`}
              >
                <Download size={14} />
              </a>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}

function BattleRules({ rules }: { rules: string | null }) {
  const lines = (rules || '')
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);

  return (
    <section className="border border-[#2a2a2a] bg-[#111111] p-5">
      <p className="mb-4 text-[10px] font-bold uppercase tracking-widest text-primary">Battle Rules</p>
      <div className="grid gap-3 md:grid-cols-2">
        {lines.map((rule) => (
          <div key={rule} className="flex items-start gap-3 border border-[#242424] bg-[#080808] p-3 text-sm leading-6 text-[#dddddd]">
            <ShieldCheck size={16} className="mt-1 shrink-0 text-primary" />
            <span>{rule}</span>
          </div>
        ))}
        <div className="flex items-start gap-3 border border-[#242424] bg-[#080808] p-3 text-sm leading-6 text-[#dddddd]">
          <ShieldCheck size={16} className="mt-1 shrink-0 text-primary" />
          <span>Opponent submissions remain hidden until fan voting opens.</span>
        </div>
        <div className="flex items-start gap-3 border border-[#242424] bg-[#080808] p-3 text-sm leading-6 text-[#dddddd]">
          <ShieldCheck size={16} className="mt-1 shrink-0 text-primary" />
          <span>Final submission locks the entry for this battle.</span>
        </div>
      </div>
    </section>
  );
}

function RecordingTimer({ elapsed, maxSeconds }: { elapsed: number; maxSeconds: number }) {
  const remaining = Math.max(0, maxSeconds - elapsed);
  const percent = Math.min(100, Math.round((elapsed / maxSeconds) * 100));

  return (
    <div className="border border-[#242424] bg-[#050505] p-4">
      <div className="mb-3 flex items-center justify-between gap-3">
        <p className="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-[#aaaaaa]">
          <Clock size={14} className="text-primary" />
          Recording Timer
        </p>
        <p className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          {formatSeconds(remaining)}
        </p>
      </div>
      <div className="h-2 bg-[#1a1a1a]">
        <div className="h-full bg-primary transition-all" style={{ width: `${percent}%` }} />
      </div>
    </div>
  );
}

function DevicePermissionCheck({
  audioDevices,
  videoDevices,
  selectedAudioDeviceId,
  selectedVideoDeviceId,
  onAudioChange,
  onVideoChange,
  onEnable,
  hasStream,
  permissionError,
  supportMessage,
  canCapture,
  canRecord,
  isBusy,
}: {
  audioDevices: MediaDeviceInfo[];
  videoDevices: MediaDeviceInfo[];
  selectedAudioDeviceId: string;
  selectedVideoDeviceId: string;
  onAudioChange: (value: string) => void;
  onVideoChange: (value: string) => void;
  onEnable: () => void;
  hasStream: boolean;
  permissionError: string;
  supportMessage: string;
  canCapture: boolean;
  canRecord: boolean;
  isBusy: boolean;
}) {
  return (
    <div className="grid gap-3 border border-[#242424] bg-[#080808] p-4 md:grid-cols-[1fr_1fr_auto]">
      <label className="grid gap-2 text-xs font-bold uppercase tracking-widest text-[#888888]">
        <span className="inline-flex items-center gap-2">
          <Camera size={14} className="text-primary" />
          Camera
        </span>
        <select
          value={selectedVideoDeviceId}
          onChange={(event) => onVideoChange(event.target.value)}
          className="h-11 border border-[#333333] bg-[#050505] px-3 text-sm normal-case tracking-normal text-white outline-none focus:border-primary"
        >
          <option value="">Default camera</option>
          {videoDevices.map((device, index) => (
            <option key={device.deviceId || index} value={device.deviceId}>
              {device.label || `Camera ${index + 1}`}
            </option>
          ))}
        </select>
      </label>

      <label className="grid gap-2 text-xs font-bold uppercase tracking-widest text-[#888888]">
        <span className="inline-flex items-center gap-2">
          <Mic size={14} className="text-primary" />
          Microphone
        </span>
        <select
          value={selectedAudioDeviceId}
          onChange={(event) => onAudioChange(event.target.value)}
          className="h-11 border border-[#333333] bg-[#050505] px-3 text-sm normal-case tracking-normal text-white outline-none focus:border-primary"
        >
          <option value="">Default microphone</option>
          {audioDevices.map((device, index) => (
            <option key={device.deviceId || index} value={device.deviceId}>
              {device.label || `Microphone ${index + 1}`}
            </option>
          ))}
        </select>
      </label>

      <button
        type="button"
        onClick={onEnable}
        disabled={isBusy || !canCapture}
        className="inline-flex h-11 items-center justify-center gap-2 self-end bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
        style={{ fontFamily: 'var(--font-heading)' }}
      >
        {isBusy ? <Loader2 size={16} className="animate-spin" /> : <Video size={16} />}
        {hasStream ? 'Apply Devices' : 'Enable Camera & Mic'}
      </button>

      {(supportMessage || permissionError) && (
        <div className="grid gap-2 md:col-span-3">
          {supportMessage && <p className="text-sm text-primary">{supportMessage}</p>}
          {permissionError && <p className="text-sm text-primary">{permissionError}</p>}
        </div>
      )}

      <div className="flex flex-wrap gap-2 md:col-span-3">
        <span className={`inline-flex h-7 items-center gap-2 border px-2 text-[10px] font-bold uppercase tracking-widest ${
          canCapture ? 'border-emerald-500/50 text-emerald-300' : 'border-primary/50 text-primary'
        }`}>
          {canCapture ? <CheckCircle2 size={13} /> : <AlertTriangle size={13} />}
          Camera API {canCapture ? 'Ready' : 'Blocked'}
        </span>
        <span className={`inline-flex h-7 items-center gap-2 border px-2 text-[10px] font-bold uppercase tracking-widest ${
          canRecord ? 'border-emerald-500/50 text-emerald-300' : 'border-primary/50 text-primary'
        }`}>
          {canRecord ? <CheckCircle2 size={13} /> : <AlertTriangle size={13} />}
          Recorder {canRecord ? 'Ready' : 'Unavailable'}
        </span>
        {videoDevices.length > 0 && (
          <span className="inline-flex h-7 items-center border border-[#333333] px-2 text-[10px] font-bold uppercase tracking-widest text-[#aaaaaa]">
            {videoDevices.length} Camera{videoDevices.length === 1 ? '' : 's'}
          </span>
        )}
        {audioDevices.length > 0 && (
          <span className="inline-flex h-7 items-center border border-[#333333] px-2 text-[10px] font-bold uppercase tracking-widest text-[#aaaaaa]">
            {audioDevices.length} Mic{audioDevices.length === 1 ? '' : 's'}
          </span>
        )}
      </div>
    </div>
  );
}

function RecordingPreview({
  previewUrl,
  durationSeconds,
  rerecordsUsed,
  maxRerecords,
  onReRecord,
  onSubmit,
}: {
  previewUrl: string;
  durationSeconds: number;
  rerecordsUsed: number;
  maxRerecords: number;
  onReRecord: () => void;
  onSubmit: () => void;
}) {
  const rerecordsRemaining = Math.max(0, maxRerecords - rerecordsUsed);

  return (
    <div className="grid gap-4">
      <video src={previewUrl} controls className="aspect-video w-full bg-black" />
      <div className="flex flex-wrap items-center justify-between gap-3 border border-[#242424] bg-[#050505] p-4">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Recorded Duration</p>
          <p className="mt-1 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {formatSeconds(durationSeconds)}
          </p>
        </div>
        <div className="flex flex-wrap gap-3">
          <button
            type="button"
            onClick={onReRecord}
            disabled={rerecordsRemaining <= 0}
            className="inline-flex h-11 items-center justify-center gap-2 border border-[#444444] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-50"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <RefreshCcw size={16} />
            Re-record
          </button>
          <button
            type="button"
            onClick={onSubmit}
            className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <Send size={16} />
            Submit Battle Entry
          </button>
        </div>
      </div>
      <p className="text-xs font-bold uppercase tracking-widest text-[#888888]">
        {rerecordsRemaining > 0
          ? `${rerecordsRemaining} re-record${rerecordsRemaining === 1 ? '' : 's'} remaining`
          : 'No re-records remaining'}
      </p>
    </div>
  );
}

function UploadProgress({ progress, label }: { progress: number; label: string }) {
  return (
    <div className="border border-[#242424] bg-[#050505] p-4">
      <div className="mb-3 flex items-center justify-between gap-3">
        <p className="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest text-[#aaaaaa]">
          <UploadCloud size={15} className="text-primary" />
          {label}
        </p>
        <p className="text-sm font-bold text-white">{progress}%</p>
      </div>
      <div className="h-2 bg-[#1a1a1a]">
        <div className="h-full bg-primary transition-all" style={{ width: `${progress}%` }} />
      </div>
    </div>
  );
}

function SubmitBattleEntryModal({
  isOpen,
  isSubmitting,
  onClose,
  onSubmit,
}: {
  isOpen: boolean;
  isSubmitting: boolean;
  onClose: () => void;
  onSubmit: (title: string, notes: string) => void;
}) {
  const [title, setTitle] = useState('');
  const [notes, setNotes] = useState('');

  useEffect(() => {
    if (!isOpen) {
      setTitle('');
      setNotes('');
    }
  }, [isOpen]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-4">
      <div className="w-full max-w-xl border border-[#333333] bg-[#101010] p-5 shadow-2xl">
        <div className="mb-5 flex items-start justify-between gap-4">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Submit Battle Entry</p>
            <h2 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              Lock Final Recording
            </h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            disabled={isSubmitting}
            className="inline-flex h-10 w-10 items-center justify-center border border-[#444444] text-[#dddddd] transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-50"
          >
            <X size={18} />
          </button>
        </div>

        <div className="grid gap-4">
          <label className="grid gap-2 text-xs font-bold uppercase tracking-widest text-[#888888]">
            Entry Title
            <input
              value={title}
              onChange={(event) => setTitle(event.target.value)}
              maxLength={255}
              className="h-11 border border-[#333333] bg-[#050505] px-3 text-sm normal-case tracking-normal text-white outline-none focus:border-primary"
              placeholder="Battle entry"
            />
          </label>
          <label className="grid gap-2 text-xs font-bold uppercase tracking-widest text-[#888888]">
            Notes
            <textarea
              value={notes}
              onChange={(event) => setNotes(event.target.value)}
              maxLength={1000}
              rows={4}
              className="resize-none border border-[#333333] bg-[#050505] p-3 text-sm normal-case tracking-normal text-white outline-none focus:border-primary"
              placeholder="Optional notes"
            />
          </label>
          <div className="border border-primary/40 bg-primary/10 p-3 text-sm leading-6 text-[#eeeeee]">
            Final submission locks this entry for the battle.
          </div>
          <button
            type="button"
            onClick={() => onSubmit(title.trim(), notes.trim())}
            disabled={isSubmitting}
            className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            {isSubmitting ? <Loader2 size={16} className="animate-spin" /> : <Send size={16} />}
            Submit Entry
          </button>
        </div>
      </div>
    </div>
  );
}

function SubmissionStatusCard({
  battle,
  ownEntry,
  opponentEntry,
}: {
  battle: BattleRecord;
  ownEntry: BattleEntry | null;
  opponentEntry: BattleEntry | null;
}) {
  const ownSubmitted = ownEntry?.status === 'submitted';
  const opponentSubmitted = opponentEntry?.status === 'submitted';

  return (
    <section className="grid gap-4 md:grid-cols-2">
      <article className="border border-[#2a2a2a] bg-[#111111] p-5">
        <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Your Submission</p>
        <div className="mt-3 flex items-center justify-between gap-3">
          <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {ownSubmitted ? 'Entry Submitted' : 'Entry Pending'}
          </h2>
          {ownSubmitted ? <CheckCircle2 className="text-emerald-300" /> : <Clock className="text-primary" />}
        </div>
        {ownEntry?.submitted_at && <p className="mt-2 text-sm text-[#888888]">{new Date(ownEntry.submitted_at).toLocaleString()}</p>}
      </article>

      <article className="border border-[#2a2a2a] bg-[#111111] p-5">
        <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">Opponent Submission</p>
        <div className="mt-3 flex items-center justify-between gap-3">
          <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {opponentSubmitted ? 'Entry Locked' : 'Hidden'}
          </h2>
          {opponentSubmitted ? <CheckCircle2 className="text-emerald-300" /> : <ShieldCheck className="text-primary" />}
        </div>
        <p className="mt-2 text-sm text-[#888888]">
          {battle.status === 'recording' ? 'Opponent video opens in voting.' : 'Voting phase is open.'}
        </p>
      </article>
    </section>
  );
}

function VideoRecorderPanel({
  battle,
  ownEntry,
  onBattleUpdated,
}: {
  battle: BattleRecord;
  ownEntry: BattleEntry | null;
  onBattleUpdated: (battle: BattleRecord) => void;
}) {
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const recorderRef = useRef<MediaRecorder | null>(null);
  const chunksRef = useRef<BlobPart[]>([]);
  const startedAtRef = useRef(0);
  const [mode, setMode] = useState<RecordingMode>(ownEntry?.status === 'submitted' ? 'submitted' : 'idle');
  const [stream, setStream] = useState<MediaStream | null>(null);
  const [audioDevices, setAudioDevices] = useState<MediaDeviceInfo[]>([]);
  const [videoDevices, setVideoDevices] = useState<MediaDeviceInfo[]>([]);
  const [selectedAudioDeviceId, setSelectedAudioDeviceId] = useState('');
  const [selectedVideoDeviceId, setSelectedVideoDeviceId] = useState('');
  const [permissionError, setPermissionError] = useState('');
  const [isPermissionBusy, setIsPermissionBusy] = useState(false);
  const [countdownSeconds, setCountdownSeconds] = useState(RECORDING_COUNTDOWN_SECONDS);
  const [elapsedSeconds, setElapsedSeconds] = useState(0);
  const [recordedBlob, setRecordedBlob] = useState<Blob | null>(null);
  const [previewUrl, setPreviewUrl] = useState('');
  const [recordedDuration, setRecordedDuration] = useState(0);
  const [rerecordsUsed, setRerecordsUsed] = useState(0);
  const [submitModalOpen, setSubmitModalOpen] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [uploadLabel, setUploadLabel] = useState('Preparing upload');
  const [error, setError] = useState('');

  const canCapture = typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices?.getUserMedia);
  const supportsRecording = typeof MediaRecorder !== 'undefined';
  const supportMessage = !canCapture
    ? cameraSupportMessage()
    : !supportsRecording
      ? recordingSupportMessage()
      : '';

  useEffect(() => {
    if (ownEntry?.status === 'submitted') {
      setMode('submitted');
    }
  }, [ownEntry?.status]);

  useEffect(() => {
    if (!videoRef.current || !stream || mode === 'preview') return;
    videoRef.current.srcObject = stream;
    videoRef.current.play().catch(() => undefined);
  }, [stream, mode]);

  useEffect(() => {
    if (!canCapture) return;

    refreshDevices().catch(() => undefined);
  }, [canCapture]);

  useEffect(() => () => {
    stream?.getTracks().forEach((track) => track.stop());
  }, [stream]);

  useEffect(() => () => {
    if (previewUrl) URL.revokeObjectURL(previewUrl);
  }, [previewUrl]);

  useEffect(() => {
    if (mode !== 'countdown') return;

    setCountdownSeconds(RECORDING_COUNTDOWN_SECONDS);

    const timer = window.setInterval(() => {
      setCountdownSeconds((current) => {
        if (current <= 1) {
          window.clearInterval(timer);
          beginRecording();
          return 0;
        }

        return current - 1;
      });
    }, 1000);

    return () => window.clearInterval(timer);
  }, [mode]);

  useEffect(() => {
    if (mode !== 'recording') return;

    const timer = window.setInterval(() => {
      const nextElapsed = Math.min(
        battle.duration_seconds,
        Math.floor((Date.now() - startedAtRef.current) / 1000),
      );
      setElapsedSeconds(nextElapsed);

      if (nextElapsed >= battle.duration_seconds) {
        finishRecording();
      }
    }, 500);

    return () => window.clearInterval(timer);
  }, [battle.duration_seconds, mode]);

  useEffect(() => {
    const shouldWarn = mode === 'countdown' || mode === 'recording' || mode === 'preview';
    const listener = (event: BeforeUnloadEvent) => {
      if (!shouldWarn) return;
      event.preventDefault();
      event.returnValue = '';
    };

    window.addEventListener('beforeunload', listener);
    return () => window.removeEventListener('beforeunload', listener);
  }, [mode]);

  const refreshDevices = async () => {
    if (!navigator.mediaDevices?.enumerateDevices) return;

    const devices = await navigator.mediaDevices.enumerateDevices();
    setAudioDevices(devices.filter((device) => device.kind === 'audioinput'));
    setVideoDevices(devices.filter((device) => device.kind === 'videoinput'));
  };

  const requestPermissions = async () => {
    if (!canCapture) {
      setPermissionError(cameraSupportMessage());
      return;
    }

    setIsPermissionBusy(true);
    setPermissionError('');
    setError('');

    try {
      stream?.getTracks().forEach((track) => track.stop());

      const nextStream = await navigator.mediaDevices.getUserMedia({
        audio: selectedAudioDeviceId ? { deviceId: { exact: selectedAudioDeviceId } } : true,
        video: selectedVideoDeviceId ? { deviceId: { exact: selectedVideoDeviceId } } : true,
      });

      setStream(nextStream);
      setMode('ready');
      await refreshDevices();
    } catch (requestError) {
      const errorName = requestError instanceof DOMException ? requestError.name : '';

      if (errorName === 'NotAllowedError' || errorName === 'PermissionDeniedError') {
        setPermissionError('Camera and microphone permission was denied. Allow camera access in the browser permissions panel, then try again.');
      } else if (errorName === 'NotFoundError' || errorName === 'DevicesNotFoundError') {
        setPermissionError('No camera or microphone was found on this device.');
      } else if (errorName === 'NotReadableError' || errorName === 'TrackStartError') {
        setPermissionError('The camera or microphone is already in use by another app.');
      } else {
        setPermissionError('Camera and microphone permission is required before recording.');
      }
    } finally {
      setIsPermissionBusy(false);
    }
  };

  const startRecording = () => {
    if (!stream) {
      setPermissionError('Enable camera and microphone before recording.');
      return;
    }

    if (!supportsRecording) {
      setPermissionError(recordingSupportMessage());
      return;
    }

    setError('');
    setPermissionError('');
    setCountdownSeconds(RECORDING_COUNTDOWN_SECONDS);
    setMode('countdown');
  };

  const beginRecording = () => {
    if (!stream || !supportsRecording) {
      setMode(stream ? 'ready' : 'idle');
      return;
    }

    chunksRef.current = [];

    const mimeType = [
      'video/webm;codecs=vp9,opus',
      'video/webm;codecs=vp8,opus',
      'video/webm',
      'video/mp4',
    ].find((type) => typeof MediaRecorder.isTypeSupported !== 'function' || MediaRecorder.isTypeSupported(type));
    let recorder: MediaRecorder;

    try {
      recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
    } catch {
      setPermissionError('The browser could not start the recorder for this camera stream.');
      setMode('ready');
      return;
    }

    recorder.ondataavailable = (event) => {
      if (event.data.size > 0) chunksRef.current.push(event.data);
    };

    recorder.onstop = () => {
      const duration = Math.min(
        battle.duration_seconds,
        Math.max(1, Math.round((Date.now() - startedAtRef.current) / 1000)),
      );
      const blob = new Blob(chunksRef.current, { type: recorder.mimeType || 'video/webm' });
      const url = URL.createObjectURL(blob);

      setRecordedBlob(blob);
      setPreviewUrl((currentUrl) => {
        if (currentUrl) URL.revokeObjectURL(currentUrl);
        return url;
      });
      setRecordedDuration(duration);
      setMode('preview');
    };

    recorderRef.current = recorder;
    startedAtRef.current = Date.now();
    setElapsedSeconds(0);

    try {
      recorder.start(1000);
      setMode('recording');
    } catch {
      setPermissionError('The browser could not start recording. Try applying the camera and microphone again.');
      setMode('ready');
    }
  };

  const finishRecording = () => {
    const recorder = recorderRef.current;
    if (!recorder || recorder.state === 'inactive') return;
    recorder.stop();
  };

  const reRecord = () => {
    if (rerecordsUsed >= MAX_RERECORDS) return;

    if (previewUrl) URL.revokeObjectURL(previewUrl);
    setPreviewUrl('');
    setRecordedBlob(null);
    setRecordedDuration(0);
    setElapsedSeconds(0);
    setCountdownSeconds(RECORDING_COUNTDOWN_SECONDS);
    setRerecordsUsed((value) => value + 1);
    setError('');
    setMode(stream ? 'ready' : 'idle');
  };

  const submitRecording = async (title: string, notes: string) => {
    if (!recordedBlob) return;

    setMode('uploading');
    setSubmitModalOpen(false);
    setUploadProgress(18);
    setUploadLabel('Preparing upload');
    setError('');

    try {
      await new Promise((resolve) => window.setTimeout(resolve, 250));
      setUploadProgress(42);
      setUploadLabel('Uploading video');

      const updatedBattle = await submitBattleEntry(battle.uuid, {
        media: recordedBlob,
        title,
        notes,
        duration_seconds: recordedDuration,
        filename: 'blendbeat-battle-entry.webm',
      });

      setUploadProgress(86);
      setUploadLabel('Locking entry');
      await new Promise((resolve) => window.setTimeout(resolve, 200));
      setUploadProgress(100);
      setUploadLabel('Entry submitted');
      setMode('submitted');
      onBattleUpdated(updatedBattle);
    } catch (requestError) {
      setMode('preview');
      setError(actionErrorMessage(requestError));
    }
  };

  if (mode === 'submitted') {
    return (
      <section className="border border-[#2a2a2a] bg-[#111111] p-5">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Recording Submitted</p>
            <h2 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              Entry Locked
            </h2>
          </div>
          <CheckCircle2 size={34} className="text-emerald-300" />
        </div>
      </section>
    );
  }

  return (
    <section className="grid gap-4 border border-[#2a2a2a] bg-[#111111] p-5">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Battle Recorder</p>
          <h2 className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            Record Entry
          </h2>
        </div>
        <span className="inline-flex h-8 items-center border border-[#333333] px-3 text-[10px] font-bold uppercase tracking-widest text-[#dddddd]">
          Max {formatSeconds(battle.duration_seconds)}
        </span>
      </div>

      <DevicePermissionCheck
        audioDevices={audioDevices}
        videoDevices={videoDevices}
        selectedAudioDeviceId={selectedAudioDeviceId}
        selectedVideoDeviceId={selectedVideoDeviceId}
        onAudioChange={setSelectedAudioDeviceId}
        onVideoChange={setSelectedVideoDeviceId}
        onEnable={requestPermissions}
        hasStream={Boolean(stream)}
        permissionError={permissionError}
        supportMessage={supportMessage}
        canCapture={canCapture}
        canRecord={supportsRecording}
        isBusy={isPermissionBusy}
      />

      {mode === 'uploading' && <UploadProgress progress={uploadProgress} label={uploadLabel} />}

      {mode !== 'preview' && mode !== 'uploading' && (
        <div className="grid gap-4">
          <div className="relative aspect-video overflow-hidden border border-[#242424] bg-black">
            {stream ? (
              <video ref={videoRef} autoPlay muted playsInline className="h-full w-full object-cover" />
            ) : (
              <div className="flex h-full flex-col items-center justify-center gap-3 text-[#777777]">
                <Camera size={44} />
                <p className="text-xs font-bold uppercase tracking-widest">Camera Preview</p>
              </div>
            )}
            {mode === 'recording' && (
              <div className="absolute left-4 top-4 inline-flex h-8 items-center gap-2 bg-primary px-3 text-[10px] font-bold uppercase tracking-widest text-white">
                <span className="h-2 w-2 rounded-full bg-white" />
                Recording
              </div>
            )}
            {mode === 'countdown' && (
              <div className="absolute inset-0 flex flex-col items-center justify-center bg-black/55 text-white">
                <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Recording Starts In</p>
                <p className="mt-2 text-8xl uppercase leading-none" style={{ fontFamily: 'var(--font-heading)' }}>
                  {countdownSeconds}
                </p>
                <p className="mt-3 text-xs font-bold uppercase tracking-widest text-[#dddddd]">Get in position</p>
              </div>
            )}
          </div>

          {mode === 'recording' && <RecordingTimer elapsed={elapsedSeconds} maxSeconds={battle.duration_seconds} />}

          <div className="flex flex-wrap gap-3">
            {mode === 'countdown' ? (
              <button
                type="button"
                disabled
                className="inline-flex h-12 flex-1 cursor-not-allowed items-center justify-center gap-2 border border-primary/50 bg-primary/10 px-5 text-xs font-bold uppercase tracking-widest text-primary"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                <Clock size={16} />
                Starting in {countdownSeconds}
              </button>
            ) : mode === 'recording' ? (
              <button
                type="button"
                disabled
                className="inline-flex h-12 flex-1 cursor-not-allowed items-center justify-center gap-2 border border-primary/50 bg-primary/10 px-5 text-xs font-bold uppercase tracking-widest text-primary"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                <Clock size={16} />
                Recording Locked Until Timer Ends
              </button>
            ) : (
              <button
                type="button"
                onClick={startRecording}
                disabled={!stream || !supportsRecording}
                className="inline-flex h-12 flex-1 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-55"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                <Play size={16} />
                Start Recording
              </button>
            )}
          </div>
        </div>
      )}

      {mode === 'preview' && previewUrl && (
        <RecordingPreview
          previewUrl={previewUrl}
          durationSeconds={recordedDuration}
          rerecordsUsed={rerecordsUsed}
          maxRerecords={MAX_RERECORDS}
          onReRecord={reRecord}
          onSubmit={() => setSubmitModalOpen(true)}
        />
      )}

      {error && <p className="text-sm text-primary">{error}</p>}

      <SubmitBattleEntryModal
        isOpen={submitModalOpen}
        isSubmitting={mode === 'uploading'}
        onClose={() => setSubmitModalOpen(false)}
        onSubmit={submitRecording}
      />
    </section>
  );
}

function TestModeDuplicateSubmissionButton({
  battle,
  canShow,
  isBusy,
  onDuplicate,
}: {
  battle: BattleRecord;
  canShow: boolean;
  isBusy: boolean;
  onDuplicate: () => void;
}) {
  if (!canShow) return null;

  return (
    <section className="border border-primary/40 bg-primary/10 p-5">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-widest text-primary">Test Mode</p>
          <h2 className="mt-1 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            Duplicate DJ 1 submission for DJ 2
          </h2>
          <p className="mt-2 text-sm text-[#dddddd]">Test Mode: Duplicate DJ 1 submission for DJ 2.</p>
        </div>
        <button
          type="button"
          onClick={onDuplicate}
          disabled={isBusy || battle.status !== 'recording'}
          className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          {isBusy ? <Loader2 size={16} className="animate-spin" /> : <RefreshCcw size={16} />}
          Run Test Duplicate
        </button>
      </div>
    </section>
  );
}

export default function BattleRecordingPage() {
  const { uuid } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const [battle, setBattle] = useState<BattleRecord | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [duplicateLoading, setDuplicateLoading] = useState(false);
  const [duplicateError, setDuplicateError] = useState('');

  useEffect(() => {
    if (!uuid) return;

    let cancelled = false;
    setIsLoading(true);
    setError('');

    getBattle(uuid)
      .then((record) => {
        if (!cancelled) setBattle(record);
      })
      .catch(() => {
        if (!cancelled) setError('Unable to load this battle recorder.');
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [uuid]);

  const viewerProfileId = user?.dj_profile?.id ?? null;
  const ownEntry = useMemo(() => (
    battle?.entries.find((entry) => entry.dj_profile_id === viewerProfileId) ?? null
  ), [battle?.entries, viewerProfileId]);
  const opponentEntry = useMemo(() => (
    battle?.entries.find((entry) => entry.dj_profile_id !== viewerProfileId) ?? null
  ), [battle?.entries, viewerProfileId]);
  const isParticipant = Boolean(ownEntry);
  const ownSubmitted = ownEntry?.status === 'submitted';
  const opponentSubmitted = opponentEntry?.status === 'submitted';
  const isLocalHost = typeof window !== 'undefined'
    && ['localhost', '127.0.0.1', 'blendbeats.test'].includes(window.location.hostname);
  const canShowTestDuplicate = Boolean(
    isLocalHost
    && battle?.status === 'recording'
    && ownEntry?.dj_profile_id === battle.challenger.id
    && ownSubmitted
    && !opponentSubmitted,
  );

  const runTestDuplicate = async () => {
    if (!battle) return;

    setDuplicateLoading(true);
    setDuplicateError('');

    try {
      const updatedBattle = await duplicateBattleEntryForTesting(battle.uuid);
      setBattle(updatedBattle);
    } catch (requestError) {
      setDuplicateError(actionErrorMessage(requestError));
    } finally {
      setDuplicateLoading(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>{battle ? `${battle.title} Recorder` : 'Battle Recorder'} | The Blend Battlegrounds</title>
        <meta name="description" content="Record a DJ battle entry." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-10 lg:px-8">
        <div className="container mx-auto max-w-6xl">
          <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <Link
              to={battle ? `/battles/${battle.uuid}` : '/battles'}
              className="inline-flex h-10 items-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              Battle
            </Link>
            {battle?.status === 'voting' && (
              <button
                type="button"
                onClick={() => navigate(`/battles/${battle.uuid}`)}
                className="inline-flex h-10 items-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                <Trophy size={15} />
                Voting Open
              </button>
            )}
          </div>

          {isLoading && (
            <div className="flex min-h-96 items-center justify-center border border-[#2a2a2a] bg-[#111111] text-[#dddddd]">
              <Loader2 size={26} className="animate-spin text-primary" />
            </div>
          )}

          {!isLoading && error && (
            <div className="border border-primary/40 bg-primary/10 p-5 text-sm text-primary">{error}</div>
          )}

          {!isLoading && battle && (
            <div className="grid gap-5">
              <BattleStatusHeader battle={battle} />

              {battle.status !== 'recording' && battle.status !== 'voting' && (
                <section className="flex items-start gap-3 border border-primary/40 bg-primary/10 p-5 text-sm leading-6 text-[#eeeeee]">
                  <AlertTriangle size={18} className="mt-1 shrink-0 text-primary" />
                  <span>This recorder opens after both DJs are ready and the battle enters recording.</span>
                </section>
              )}

              {!isParticipant && (
                <section className="flex items-start gap-3 border border-primary/40 bg-primary/10 p-5 text-sm leading-6 text-[#eeeeee]">
                  <AlertTriangle size={18} className="mt-1 shrink-0 text-primary" />
                  <span>Only battle participants can record entries.</span>
                </section>
              )}

              {isParticipant && <SubmissionStatusCard battle={battle} ownEntry={ownEntry} opponentEntry={opponentEntry} />}
              <BattleSampleList battle={battle} />
              <BattleRules rules={battle.rules} />

              {battle.status === 'recording' && isParticipant && (
                <VideoRecorderPanel battle={battle} ownEntry={ownEntry} onBattleUpdated={setBattle} />
              )}

              <TestModeDuplicateSubmissionButton
                battle={battle}
                canShow={canShowTestDuplicate}
                isBusy={duplicateLoading}
                onDuplicate={runTestDuplicate}
              />

              {duplicateError && <p className="text-sm text-primary">{duplicateError}</p>}
            </div>
          )}
        </div>
      </main>
    </>
  );
}
