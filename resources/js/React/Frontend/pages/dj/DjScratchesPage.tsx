import { Helmet } from '@dr.pogodin/react-helmet';
import {
  Clock3,
  Disc3,
  LogIn,
  Play,
  Search,
  SlidersHorizontal,
  Upload,
  UserRound,
  Video,
  X,
  Youtube,
} from 'lucide-react';
import { type ChangeEvent, type FormEvent, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  getDjScratches,
  type DjScratch,
  type DjScratchStats,
  type DjScratchesQuery,
} from '@/lib/dj-scratches';
import { linkYoutubeMediaFile, MediaManagerApiError, uploadMediaFile } from '@/lib/media-manager';

const MAX_SCRATCH_DURATION_SECONDS = 300;
type UploadSource = 'upload' | 'youtube';

const genreOptions = ['Scratch Sets', 'Hip-Hop', 'Open Format', 'House', 'Drum & Bass', 'Techno', 'R&B', 'Afrobeats'];

const emptyStats: DjScratchStats = {
  scratch_count: 0,
  dj_count: 0,
  genre_count: 0,
};

function formatDuration(seconds: number | null | undefined) {
  const safeSeconds = Math.max(0, Math.floor(seconds || 0));
  const minutes = Math.floor(safeSeconds / 60);
  const remainingSeconds = safeSeconds % 60;

  return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

function isOverScratchDurationLimit(seconds: number) {
  return Math.floor(seconds) > MAX_SCRATCH_DURATION_SECONDS;
}

function isYoutubeUrl(value: string) {
  try {
    const url = new URL(value);
    const host = url.hostname.replace(/^www\./, '').toLowerCase();

    return host === 'youtu.be' || host === 'youtube.com' || host === 'm.youtube.com' || host === 'music.youtube.com';
  } catch {
    return false;
  }
}

function durationFromParts(minutesValue: string, secondsValue: string) {
  const minutes = Number(minutesValue);
  const seconds = Number(secondsValue);

  if (!Number.isFinite(minutes) || !Number.isFinite(seconds) || minutes < 0 || seconds < 0 || seconds > 59) {
    return null;
  }

  return minutes * 60 + seconds;
}

function formatDate(value: string | null) {
  if (!value) return 'Recently';

  return new Intl.DateTimeFormat('en', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(value));
}

function getVideoDuration(file: File) {
  return new Promise<number>((resolve, reject) => {
    const video = document.createElement('video');
    const objectUrl = URL.createObjectURL(file);

    video.preload = 'metadata';
    video.onloadedmetadata = () => {
      URL.revokeObjectURL(objectUrl);
      const duration = video.duration;

      if (!Number.isFinite(duration) || duration <= 0) {
        reject(new Error('Video duration could not be read.'));
        return;
      }

      resolve(duration);
    };
    video.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      reject(new Error('Video duration could not be read.'));
    };
    video.src = objectUrl;
  });
}

function ScratchRailItem({
  scratch,
  isActive,
  onSelect,
}: {
  scratch: DjScratch;
  isActive: boolean;
  onSelect: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={`grid w-full grid-cols-[116px_minmax(0,1fr)] gap-3 border p-2 text-left transition-colors ${
        isActive ? 'border-primary bg-[#171717]' : 'border-[#2a2a2a] bg-[#101010] hover:border-[#444444]'
      }`}
    >
      <div className="relative aspect-video overflow-hidden bg-[#050505]">
        {scratch.cover_image_url ? (
          <img src={scratch.cover_image_url} alt={scratch.title} className="h-full w-full object-cover" loading="lazy" />
        ) : (
          <video src={scratch.url} muted preload="metadata" className="h-full w-full object-cover">
            <track kind="captions" />
          </video>
        )}
        <span className="absolute bottom-1 right-1 bg-black/80 px-1.5 py-0.5 text-[10px] font-bold text-white">
          {formatDuration(scratch.duration_seconds)}
        </span>
      </div>
      <div className="min-w-0">
        <p className="line-clamp-2 text-sm font-semibold leading-5 text-white">{scratch.title}</p>
        <p className="mt-1 truncate text-xs text-[#888888]">{scratch.dj.name}</p>
        <p className="mt-1 text-xs text-[#666666]">{formatDate(scratch.created_at)}</p>
      </div>
    </button>
  );
}

function UploadModal({
  onClose,
  onUploaded,
}: {
  onClose: () => void;
  onUploaded: () => void;
}) {
  const [source, setSource] = useState<UploadSource>('upload');
  const [videoFile, setVideoFile] = useState<File | null>(null);
  const [coverFile, setCoverFile] = useState<File | null>(null);
  const [durationSeconds, setDurationSeconds] = useState<number | null>(null);
  const [youtubeUrl, setYoutubeUrl] = useState('');
  const [youtubeMinutes, setYoutubeMinutes] = useState('0');
  const [youtubeSeconds, setYoutubeSeconds] = useState('0');
  const [title, setTitle] = useState('');
  const [genre, setGenre] = useState('Scratch Sets');
  const [visibility, setVisibility] = useState('public');
  const [description, setDescription] = useState('');
  const [localError, setLocalError] = useState('');
  const [isReadingDuration, setIsReadingDuration] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSourceChange = (nextSource: UploadSource) => {
    setSource(nextSource);
    setLocalError('');

    if (nextSource === 'upload') {
      setYoutubeUrl('');
      setYoutubeMinutes('0');
      setYoutubeSeconds('0');
    } else {
      setVideoFile(null);
      setDurationSeconds(null);
    }
  };

  const handleVideoChange = async (event: ChangeEvent<HTMLInputElement>) => {
    const input = event.currentTarget;
    const file = input.files?.[0] ?? null;
    setLocalError('');
    setVideoFile(null);
    setDurationSeconds(null);

    if (!file) return;

    if (!file.type.startsWith('video/')) {
      setLocalError('Choose a video file.');
      input.value = '';
      return;
    }

    try {
      setIsReadingDuration(true);
      const duration = await getVideoDuration(file);

      if (isOverScratchDurationLimit(duration)) {
        setLocalError(`This video is ${formatDuration(duration)}. Scratch routine videos must be 5:00 or less.`);
        input.value = '';
        return;
      }

      setVideoFile(file);
      setDurationSeconds(duration);
      setTitle((currentTitle) => currentTitle || file.name.replace(/\.[^/.]+$/, ''));
    } catch (durationError) {
      setLocalError(durationError instanceof Error ? durationError.message : 'Video duration could not be read.');
      input.value = '';
    } finally {
      setIsReadingDuration(false);
    }
  };

  const submitUpload = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setLocalError('');

    if (source === 'upload' && (!videoFile || !durationSeconds)) {
      setLocalError('Choose a scratch routine video first.');
      return;
    }

    const youtubeDurationSeconds = durationFromParts(youtubeMinutes, youtubeSeconds);

    if (source === 'youtube' && !isYoutubeUrl(youtubeUrl.trim())) {
      setLocalError('Enter a valid YouTube video link.');
      return;
    }

    if (source === 'youtube' && (!youtubeDurationSeconds || isOverScratchDurationLimit(youtubeDurationSeconds))) {
      setLocalError('Scratch routine videos must be 5:00 or less.');
      return;
    }

    try {
      setIsSubmitting(true);

      if (source === 'youtube') {
        await linkYoutubeMediaFile('dj_media', {
          title,
          description,
          genre,
          visibility,
          mediaKind: 'scratch',
          externalUrl: youtubeUrl.trim(),
          durationSeconds: youtubeDurationSeconds,
          coverImage: coverFile,
        });
      } else if (videoFile && durationSeconds) {
        await uploadMediaFile(videoFile, 'dj_media', {
          title,
          description,
          genre,
          visibility,
          mediaKind: 'scratch',
          durationSeconds,
          coverImage: coverFile,
        });
      }

      onUploaded();
    } catch (uploadError) {
      const validationMessage =
        uploadError instanceof MediaManagerApiError ? Object.values(uploadError.errors)[0]?.[0] : null;

      setLocalError(
        validationMessage ||
          (uploadError instanceof MediaManagerApiError
            ? uploadError.message
            : 'Unable to upload scratch routine video right now.'),
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-4 py-6 backdrop-blur-sm">
      <form
        onSubmit={submitUpload}
        className="max-h-[92vh] w-full max-w-2xl overflow-y-auto border border-[#2a2a2a] bg-[#0d0d0d] p-5 shadow-2xl shadow-black/60 sm:p-6"
      >
        <div className="mb-5 flex items-start justify-between gap-4 border-b border-[#252525] pb-4">
          <div>
            <p className="text-[11px] font-bold uppercase tracking-widest text-primary">Scratch Routines</p>
            <h2 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              Upload Routine
            </h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="inline-flex h-10 w-10 shrink-0 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            aria-label="Close upload modal"
          >
            <X size={18} />
          </button>
        </div>

        <div className="grid gap-4">
          <div className="grid grid-cols-2 gap-2">
            {[
              { value: 'upload' as const, label: 'Upload', icon: Upload },
              { value: 'youtube' as const, label: 'YouTube', icon: Youtube },
            ].map((option) => {
              const Icon = option.icon;

              return (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => handleSourceChange(option.value)}
                  className={`inline-flex h-11 items-center justify-center gap-2 border px-4 text-xs font-bold uppercase tracking-widest transition-colors ${
                    source === option.value
                      ? 'border-primary bg-primary text-white'
                      : 'border-[#333333] text-[#dddddd] hover:border-primary hover:text-primary'
                  }`}
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <Icon size={15} />
                  {option.label}
                </button>
              );
            })}
          </div>

          {source === 'upload' ? (
            <label className="grid gap-2">
              <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Scratch Routine Video</span>
              <input
                type="file"
                accept="video/*"
                onChange={handleVideoChange}
                className="w-full border border-[#333333] bg-[#080808] px-4 py-3 text-sm text-[#bbbbbb] file:mr-4 file:border-0 file:bg-primary file:px-4 file:py-2 file:text-xs file:font-bold file:uppercase file:tracking-widest file:text-white"
                style={{ fontFamily: 'var(--font-heading)' }}
              />
              <span className="text-xs text-[#888888]">
                {isReadingDuration
                  ? 'Reading duration'
                  : videoFile
                    ? `${videoFile.name} | ${formatDuration(durationSeconds)}`
                    : 'Video only | 5:00 max'}
              </span>
              <span className="text-xs text-[#666666]">
                Monthly limit: Free 3, Plus 50, Pro 150, Elite unlimited.
              </span>
            </label>
          ) : (
            <div className="grid gap-4">
              <label className="grid gap-2">
                <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">YouTube Video Link</span>
                <input
                  type="url"
                  value={youtubeUrl}
                  onChange={(event) => setYoutubeUrl(event.target.value)}
                  placeholder="https://www.youtube.com/watch?v=..."
                  className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none placeholder:text-[#555555] focus:border-primary"
                />
                <span className="text-xs text-[#666666]">
                  Monthly limit: Free 3, Plus 50, Pro 150, Elite unlimited.
                </span>
              </label>

              <div className="grid gap-4 sm:grid-cols-2">
                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Minutes</span>
                  <input
                    type="number"
                    min="0"
                    max="5"
                    value={youtubeMinutes}
                    onChange={(event) => setYoutubeMinutes(event.target.value)}
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                  />
                </label>
                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Seconds</span>
                  <input
                    type="number"
                    min="0"
                    max="59"
                    value={youtubeSeconds}
                    onChange={(event) => setYoutubeSeconds(event.target.value)}
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                  />
                </label>
              </div>
            </div>
          )}

          <label className="grid gap-2">
            <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Cover Image</span>
            <input
              type="file"
              accept="image/*"
              onChange={(event) => setCoverFile(event.target.files?.[0] ?? null)}
              className="w-full border border-[#333333] bg-[#080808] px-4 py-3 text-sm text-[#bbbbbb] file:mr-4 file:border-0 file:bg-primary file:px-4 file:py-2 file:text-xs file:font-bold file:uppercase file:tracking-widest file:text-white"
              style={{ fontFamily: 'var(--font-heading)' }}
            />
            {coverFile && <span className="text-xs text-[#888888]">Selected cover: {coverFile.name}</span>}
          </label>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="grid gap-2">
              <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Title</span>
              <input
                value={title}
                onChange={(event) => setTitle(event.target.value)}
                placeholder="3 Minute Routine"
                className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none placeholder:text-[#555555] focus:border-primary"
              />
            </label>

            <label className="grid gap-2">
              <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Genre</span>
              <select
                value={genre}
                onChange={(event) => setGenre(event.target.value)}
                className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
              >
                {genreOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </label>

            <label className="grid gap-2">
              <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Visibility</span>
              <select
                value={visibility}
                onChange={(event) => setVisibility(event.target.value)}
                className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
              >
                <option value="public">Public</option>
                <option value="unlisted">Unlisted</option>
                <option value="draft">Draft</option>
                <option value="private">Private</option>
              </select>
            </label>
          </div>

          <label className="grid gap-2">
            <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Description</span>
            <textarea
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              placeholder="Describe the routine, cuts, setup, or sounds."
              className="min-h-28 resize-none border border-[#333333] bg-[#080808] p-3 text-sm leading-6 text-white outline-none placeholder:text-[#555555] focus:border-primary"
            />
          </label>
        </div>

        {localError && (
          <div className="mt-5 border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
            {localError}
          </div>
        )}

        <div className="mt-6 flex flex-col gap-3 border-t border-[#252525] pt-5 sm:flex-row sm:justify-end">
          <button
            type="button"
            onClick={onClose}
            className="inline-flex h-11 items-center justify-center border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting || isReadingDuration || (source === 'upload' ? !videoFile : !youtubeUrl.trim())}
            className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:opacity-60"
            style={{ fontFamily: 'var(--font-heading)' }}
          >
            <Upload size={15} />
            {isSubmitting ? 'Uploading' : 'Upload Routine'}
          </button>
        </div>
      </form>
    </div>
  );
}

export default function DjScratchesPage() {
  const { user, isLoading: isAuthLoading } = useAuth();
  const [scratches, setScratches] = useState<DjScratch[]>([]);
  const [activeScratchId, setActiveScratchId] = useState<number | null>(null);
  const [stats, setStats] = useState<DjScratchStats>(emptyStats);
  const [genres, setGenres] = useState<string[]>([]);
  const [query, setQuery] = useState<DjScratchesQuery>({});
  const [searchInput, setSearchInput] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);
  const [error, setError] = useState('');

  const activeScratch = useMemo(
    () => scratches.find((scratch) => scratch.id === activeScratchId) ?? scratches[0] ?? null,
    [activeScratchId, scratches],
  );

  const loadScratches = async (nextQuery = query) => {
    setIsLoading(true);
    setError('');

    try {
      const response = await getDjScratches(nextQuery);
      setScratches(response.scratches);
      setStats(response.stats);
      setGenres(response.genres);
      setActiveScratchId((currentId) => {
        if (currentId && response.scratches.some((scratch) => scratch.id === currentId)) return currentId;
        return response.scratches[0]?.id ?? null;
      });
    } catch (loadError) {
      setError(loadError instanceof Error ? loadError.message : 'Unable to load Scratch Routines.');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadScratches(query);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query]);

  const submitSearch = () => {
    setQuery((current) => ({ ...current, search: searchInput.trim() || undefined }));
  };

  const handleUploaded = async () => {
    setError('');
    setIsUploadModalOpen(false);
    await loadScratches(query);
  };

  const uploadAction = user?.dj_profile ? (
    <button
      type="button"
      onClick={() => setIsUploadModalOpen(true)}
      className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
      style={{ fontFamily: 'var(--font-heading)' }}
    >
      <Upload size={16} />
      Upload Routine
    </button>
  ) : user ? (
    <Link
      to="/dj/start"
      className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
      style={{ fontFamily: 'var(--font-heading)' }}
    >
      <UserRound size={16} />
      Start DJ Profile
    </Link>
  ) : (
    <Link
      to="/login"
      className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
      style={{ fontFamily: 'var(--font-heading)' }}
    >
      <LogIn size={16} />
      Login To Upload
    </Link>
  );

  return (
    <>
      <Helmet>
        <title>Scratch Routines | The Blend Battlegrounds</title>
        <meta name="description" content="Watch short DJ scratch routine videos from BlendBeats DJs." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-6 text-white lg:px-8">
        <div className="container mx-auto max-w-7xl">
          <div className="mb-5 flex flex-col gap-4 border-b border-[#1f1f1f] pb-5 xl:flex-row xl:items-center xl:justify-between">
            <div className="min-w-0">
              <p
                className="text-[11px] font-bold uppercase tracking-[0.25em] text-primary"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                DJ Hub
              </p>
              <h1 className="mt-2 text-4xl uppercase leading-none text-white sm:text-5xl" style={{ fontFamily: 'var(--font-heading)' }}>
                Scratch Routines
              </h1>
            </div>

            <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px_auto] xl:w-[700px] xl:items-center">
              <div className="flex h-11 items-center gap-2 border border-[#333333] bg-[#080808] px-3">
                <Search size={16} className="text-[#777777]" />
                <input
                  value={searchInput}
                  onChange={(event) => setSearchInput(event.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter') submitSearch();
                  }}
                  placeholder="Search"
                  className="h-full min-w-0 flex-1 bg-transparent text-sm text-white outline-none placeholder:text-[#555555]"
                />
              </div>

              <label className="flex h-11 items-center gap-2 border border-[#333333] bg-[#080808] px-3">
                <SlidersHorizontal size={16} className="text-[#777777]" />
                <select
                  value={query.genre ?? ''}
                  onChange={(event) => setQuery((current) => ({ ...current, genre: event.target.value || undefined }))}
                  className="h-full min-w-0 flex-1 bg-transparent text-sm text-white outline-none"
                  aria-label="Filter by genre"
                >
                  <option value="">All genres</option>
                  {genres.map((genre) => (
                    <option key={genre} value={genre}>
                      {genre}
                    </option>
                  ))}
                </select>
              </label>

              <button
                type="button"
                onClick={submitSearch}
                className="inline-flex h-11 items-center justify-center bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Search
              </button>
            </div>

            <div className="shrink-0">{!isAuthLoading && uploadAction}</div>
          </div>

          <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_390px]">
            <div className="min-w-0">
              {error && <div className="mb-5 border border-primary/40 bg-primary/10 p-4 text-sm text-primary">{error}</div>}

              {isLoading ? (
                <div className="aspect-video animate-pulse border border-[#2a2a2a] bg-[#111111]" />
              ) : activeScratch ? (
                <>
                  <div className="overflow-hidden bg-black">
                    {activeScratch.external_provider === 'youtube' && activeScratch.embed_url ? (
                      <iframe
                        key={activeScratch.id}
                        src={activeScratch.embed_url}
                        title={activeScratch.title}
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowFullScreen
                        className="aspect-video w-full bg-black"
                      />
                    ) : (
                      <video
                        key={activeScratch.id}
                        src={activeScratch.url}
                        poster={activeScratch.cover_image_url ?? undefined}
                        controls
                        playsInline
                        preload="metadata"
                        className="aspect-video w-full bg-black object-contain"
                      >
                        <track kind="captions" />
                      </video>
                    )}
                  </div>

                  <div className="mt-5 border-b border-[#242424] pb-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                      <div className="min-w-0">
                        <h2 className="text-4xl uppercase leading-none text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                          {activeScratch.title}
                        </h2>
                        <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-[#888888]">
                          <span className="inline-flex items-center gap-2">
                            <Clock3 size={15} className="text-primary" />
                            {formatDuration(activeScratch.duration_seconds)}
                          </span>
                          {activeScratch.genre && (
                            <span className="inline-flex items-center gap-2">
                              <Disc3 size={15} className="text-primary" />
                              {activeScratch.genre}
                            </span>
                          )}
                          <span>{formatDate(activeScratch.created_at)}</span>
                        </div>
                      </div>
                      {activeScratch.dj.handle && (
                        <Link
                          to={`/djs/${activeScratch.dj.handle}`}
                          className="inline-flex h-11 items-center justify-center border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                          style={{ fontFamily: 'var(--font-heading)' }}
                        >
                          View DJ
                        </Link>
                      )}
                    </div>

                    <div className="mt-5 flex items-start gap-3">
                      {activeScratch.dj.avatar_url ? (
                        <img
                          src={activeScratch.dj.avatar_url}
                          alt={activeScratch.dj.name}
                          className="h-12 w-12 shrink-0 border border-[#333333] bg-[#080808] object-cover"
                        />
                      ) : (
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center bg-primary text-white">
                          <UserRound size={20} />
                        </div>
                      )}
                      <div className="min-w-0">
                        <p className="font-semibold text-white">{activeScratch.dj.name}</p>
                        <p className="text-sm text-[#888888]">
                          {activeScratch.dj.handle ? `@${activeScratch.dj.handle}` : 'BlendBeats DJ'}
                        </p>
                      </div>
                    </div>

                    {activeScratch.description && (
                      <p className="mt-5 whitespace-pre-wrap text-sm leading-6 text-[#bbbbbb]">{activeScratch.description}</p>
                    )}
                  </div>
                </>
              ) : (
                <div className="grid aspect-video place-items-center bg-black text-center">
                  <div className="px-5">
                    <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full border border-[#333333] bg-[#111111] text-primary">
                      <Play size={34} fill="currentColor" />
                    </div>
                    <h2 className="mt-5 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      No Videos Yet
                    </h2>
                    <p className="mx-auto mt-3 max-w-md text-sm leading-6 text-[#888888]">
                      No public Scratch Routine uploads are available yet.
                    </p>
                    <div className="mt-6 flex justify-center">{!isAuthLoading && uploadAction}</div>
                  </div>
                </div>
              )}
            </div>

            <aside className="grid gap-5 self-start">
              <section className="border-b border-[#242424] pb-4">
                <div className="flex items-center justify-between gap-3">
                  <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Up Next
                  </h2>
                  <span className="text-xs text-[#777777]">{stats.scratch_count} videos</span>
                </div>
                <div className="mt-4 grid grid-cols-3 border border-[#2a2a2a] bg-[#080808]">
                  {[
                    { label: 'Videos', value: stats.scratch_count },
                    { label: 'DJs', value: stats.dj_count },
                    { label: 'Genres', value: stats.genre_count },
                  ].map((stat) => (
                    <div key={stat.label} className="border-r border-[#2a2a2a] p-3 text-center last:border-r-0">
                      <p className="text-2xl text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                        {stat.value}
                      </p>
                      <p className="mt-1 text-[10px] uppercase tracking-widest text-[#777777]">{stat.label}</p>
                    </div>
                  ))}
                </div>
              </section>

              <section className="grid gap-3">
                {isLoading ? (
                  Array.from({ length: 5 }).map((_, index) => (
                    <div key={index} className="h-24 animate-pulse border border-[#2a2a2a] bg-[#111111]" />
                  ))
                ) : scratches.length > 0 ? (
                  scratches.map((scratch) => (
                    <ScratchRailItem
                      key={scratch.id}
                      scratch={scratch}
                      isActive={activeScratch?.id === scratch.id}
                      onSelect={() => setActiveScratchId(scratch.id)}
                    />
                  ))
                ) : (
                  <div className="grid place-items-center border border-[#2a2a2a] bg-[#111111] p-8 text-center">
                    <Video size={24} className="text-primary" />
                    <p className="mt-3 text-sm text-[#888888]">No videos available</p>
                  </div>
                )}
              </section>
            </aside>
          </div>
        </div>
      </main>

      {isUploadModalOpen && (
        <UploadModal
          onClose={() => setIsUploadModalOpen(false)}
          onUploaded={() => void handleUploaded()}
        />
      )}
    </>
  );
}
