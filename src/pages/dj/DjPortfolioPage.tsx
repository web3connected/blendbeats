import { Helmet } from '@dr.pogodin/react-helmet';
import {
  Archive,
  ArrowRight,
  CheckCircle2,
  CircleStop,
  Disc3,
  Edit3,
  Eye,
  FileAudio,
  Filter,
  Globe2,
  HardDrive,
  Lock,
  LockKeyhole,
  MoreHorizontal,
  Music2,
  Pause,
  Play,
  Plus,
  Radio,
  Search,
  Trash2,
  Upload,
} from 'lucide-react';
import { type ChangeEvent, useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  activateMediaSetup,
  getMediaSetup,
  MediaSetupApiError,
  type MediaAccount,
} from '@/lib/media-setup';
import {
  deleteMediaFile,
  listMediaLibrary,
  MediaManagerApiError,
  type MediaFileRecord,
  type MediaStorageQuota,
  uploadMediaFile,
} from '@/lib/media-manager';
import MediaSetupSection from './comps/MediaSetupSection';

const statusFilters = ['All', 'Published', 'Drafts', 'Unlisted', 'Private', 'Archived'];
const mediaFilters = ['Mixes', 'Tracks', 'Videos', 'Battle Entries'];

const storageTiers = [
  {
    key: 'starter',
    name: 'Free',
    price: '$0',
    storage: '500 MB',
    active: true,
    description: 'Start your portfolio with public media storage for mixes, photos, and videos.',
  },
  {
    key: 'growth',
    name: 'Growth',
    price: 'Coming soon',
    storage: '3 GB',
    active: false,
    description: 'More room for active DJs publishing sets, galleries, and promo assets.',
  },
  {
    key: 'pro',
    name: 'Pro',
    price: 'Coming soon',
    storage: '5 GB',
    active: false,
    description: 'Expanded media storage for heavy portfolio, video, and press kit use.',
  },
];

const storageNotes = [
  'Public uploads save under storage/app/public/media/accounts/{account_slug}.',
  'Drafts can be saved before audio or video is attached.',
  'Publishing will require a local upload or an external media URL.',
];

function formatBytes(size: number) {
  if (size === 0) return '0 B';

  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  const index = Math.floor(Math.log(size) / Math.log(1024));
  const value = size / 1024 ** index;

  return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[index]}`;
}

export default function DjPortfolioPage() {
  const { user, isLoading } = useAuth();
  const [mediaAccount, setMediaAccount] = useState<MediaAccount | null>(null);
  const [mediaFiles, setMediaFiles] = useState<MediaFileRecord[]>([]);
  const [storageQuota, setStorageQuota] = useState<MediaStorageQuota | null>(null);
  const [isSetupLoading, setIsSetupLoading] = useState(false);
  const [isActivatingSetup, setIsActivatingSetup] = useState(false);
  const [isMediaLoading, setIsMediaLoading] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [deletingFileId, setDeletingFileId] = useState<number | null>(null);
  const [activeAudioFile, setActiveAudioFile] = useState<MediaFileRecord | null>(null);
  const [error, setError] = useState('');

  const portfolioStats = useMemo(
    () => [
      { label: 'Media Files', value: String(mediaFiles.length) },
      { label: 'Audio', value: String(mediaFiles.filter((file) => file.is_audio).length) },
      { label: 'Storage Used', value: storageQuota?.used_formatted ?? formatBytes(mediaFiles.reduce((total, file) => total + file.size, 0)) },
      { label: 'Storage Limit', value: storageQuota?.limit_formatted ?? '500 MB' },
    ],
    [mediaFiles, storageQuota],
  );

  useEffect(() => {
    if (!user?.dj_profile) return;

    setIsSetupLoading(true);
    setError('');

    getMediaSetup()
      .then((setup) => {
        setMediaAccount(setup.media_account);
        setStorageQuota(setup.quota);
      })
      .catch((setupError) => {
        setError(
          setupError instanceof MediaSetupApiError ? setupError.message : 'Unable to load media setup right now.',
        );
      })
      .finally(() => setIsSetupLoading(false));
  }, [user?.dj_profile]);

  useEffect(() => {
    if (!user?.dj_profile || !mediaAccount) return;

    setIsMediaLoading(true);
    setError('');

    listMediaLibrary('dj_media')
      .then((mediaLibrary) => {
        setMediaFiles(mediaLibrary.files);
        setStorageQuota(mediaLibrary.quota);
      })
      .catch((loadError) => {
        setError(
          loadError instanceof MediaManagerApiError
            ? loadError.message
            : 'Unable to load portfolio media right now.',
        );
      })
      .finally(() => setIsMediaLoading(false));
  }, [user?.dj_profile, mediaAccount]);

  const handleActivateMedia = () => {
    setIsActivatingSetup(true);
    setError('');

    activateMediaSetup()
      .then((setup) => {
        setMediaAccount(setup.media_account);
        setStorageQuota(setup.quota);
      })
      .catch((setupError) => {
        setError(
          setupError instanceof MediaSetupApiError
            ? setupError.message
            : 'Unable to activate media storage right now.',
        );
      })
      .finally(() => setIsActivatingSetup(false));
  };

  const handleUpload = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) return;

    setIsUploading(true);
    setError('');

    uploadMediaFile(file, 'dj_media')
      .then((uploadResponse) => {
        setMediaFiles((currentFiles) => [uploadResponse.file, ...currentFiles]);
        setStorageQuota(uploadResponse.quota);
      })
      .catch((uploadError) => {
        const validationMessage =
          uploadError instanceof MediaManagerApiError ? Object.values(uploadError.errors)[0]?.[0] : null;

        setError(
          validationMessage ||
            (uploadError instanceof MediaManagerApiError
            ? uploadError.message
            : 'Unable to upload media right now.'),
        );
      })
      .finally(() => setIsUploading(false));
  };

  const handleDelete = (file: MediaFileRecord) => {
    setDeletingFileId(file.id);
    setError('');

    deleteMediaFile(file.id)
      .then((deleteResponse) => {
        setMediaFiles((currentFiles) => currentFiles.filter((mediaFile) => mediaFile.id !== file.id));
        setStorageQuota(deleteResponse.quota);
        if (activeAudioFile?.id === file.id) setActiveAudioFile(null);
      })
      .catch((deleteError) => {
        setError(
          deleteError instanceof MediaManagerApiError ? deleteError.message : 'Unable to delete media right now.',
        );
      })
      .finally(() => setDeletingFileId(null));
  };

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
  if (!user.dj_profile) return <Navigate to="/dj/start" replace />;

  if (isSetupLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20">
        <div className="container mx-auto max-w-6xl">
          <div className="h-48 animate-pulse bg-[#141414]" />
        </div>
      </main>
    );
  }

  if (!mediaAccount) {
    return (
      <>
        <Helmet>
          <title>Activate Media Library | The Blend Battlegrounds</title>
          <meta name="description" content="Activate media storage for your DJ portfolio." />
        </Helmet>
        <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
          <MediaSetupSection user={user} />

        </main>
      </>
    );
  }

  return (
    <>
      <Helmet>
        <title>My DJ Portfolio | The Blend Battlegrounds</title>
        <meta name="description" content="Manage your DJ mixes, tracks, videos, and creator media." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p
                  className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  DJ Studio
                </p>
                <h1
                  className="text-white uppercase leading-none"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.75rem, 9vw, 7rem)' }}
                >
                  My DJ Portfolio
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  Manage the mixes, tracks, videos, and future battle entries attached to {user.dj_profile.dj_name}.
                </p>
              </div>
              <div className="flex flex-col gap-3 sm:flex-row">
                <Link
                  to="/dj/edit"
                  className="inline-flex h-12 items-center justify-center gap-2 border border-[#444444] px-5 text-sm font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <Edit3 size={16} />
                  Edit DJ Profile
                </Link>
                <label
                  className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <Upload size={16} />
                  {isUploading ? 'Uploading' : 'Upload Media'}
                  <input
                    type="file"
                    accept="audio/*,video/*,image/*"
                    onChange={handleUpload}
                    disabled={isUploading}
                    className="sr-only"
                  />
                </label>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-8 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-5 lg:grid-cols-[300px_minmax(0,1fr)]">
            <aside className="grid gap-5 self-start">
              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <div className="flex items-center gap-3">
                  <div className="flex h-12 w-12 items-center justify-center bg-primary text-white">
                    <Radio size={20} />
                  </div>
                  <div className="min-w-0">
                    <p className="truncate text-lg font-semibold text-white">{user.dj_profile.dj_name}</p>
                    <p className="truncate text-sm text-[#888888]">@{user.dj_profile.handle}</p>
                  </div>
                </div>
                <div className="mt-5 grid gap-2 border-t border-[#252525] pt-5">
                  {storageQuota && (
                    <div className="mb-3 border border-[#333333] bg-[#080808] p-3">
                      <div className="mb-2 flex items-center justify-between gap-3 text-xs font-bold uppercase tracking-widest">
                        <span className="text-[#bbbbbb]">{storageQuota.tier_label} Storage</span>
                        <span className="text-primary">{storageQuota.usage_percent}%</span>
                      </div>
                      <div className="h-2 bg-[#1f1f1f]">
                        <div
                          className="h-full bg-primary"
                          style={{ width: `${Math.min(storageQuota.usage_percent, 100)}%` }}
                        />
                      </div>
                      <p className="mt-2 text-xs text-[#888888]">
                        {storageQuota.used_formatted} used of {storageQuota.limit_formatted}.{' '}
                        {storageQuota.remaining_formatted} remaining.
                      </p>
                    </div>
                  )}
                  {storageNotes.map((note) => (
                    <p key={note} className="text-xs leading-5 text-[#888888]">
                      {note}
                    </p>
                  ))}
                </div>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <div className="mb-4 flex items-center gap-2">
                  <Filter size={16} className="text-primary" />
                  <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Filters
                  </h2>
                </div>
                <div className="grid gap-2">
                  {statusFilters.map((filter, index) => (
                    <button
                      key={filter}
                      type="button"
                      className={`h-10 border px-3 text-left text-xs font-bold uppercase tracking-widest transition-colors ${
                        index === 0
                          ? 'border-primary bg-primary text-white'
                          : 'border-[#333333] text-[#bbbbbb] hover:border-primary hover:text-primary'
                      }`}
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      {filter}
                    </button>
                  ))}
                </div>
              </section>
            </aside>

            <div className="grid gap-5">
              <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {portfolioStats.map((stat) => (
                  <div key={stat.label} className="border border-[#2a2a2a] bg-[#111111] p-4">
                    <p className="text-xs font-bold uppercase tracking-widest text-[#777777]">{stat.label}</p>
                    <p className="mt-2 text-4xl text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      {stat.value}
                    </p>
                  </div>
                ))}
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                <div className="flex flex-col gap-4 border-b border-[#252525] pb-5 xl:flex-row xl:items-center xl:justify-between">
                  <div>
                    <div className="flex items-center gap-3">
                      <Music2 size={18} className="text-primary" />
                      <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        Portfolio Media
                      </h2>
                    </div>
                    <p className="mt-2 text-sm text-[#888888]">Uploads are now stored through the media manager.</p>
                  </div>
                  <div className="flex h-11 items-center gap-2 border border-[#333333] bg-[#080808] px-3">
                    <Search size={15} className="text-[#777777]" />
                    <input
                      type="search"
                      placeholder="Search media"
                      className="h-full min-w-0 bg-transparent text-sm text-white outline-none placeholder:text-[#555555]"
                    />
                  </div>
                </div>

                <div className="mt-5 flex flex-wrap gap-2">
                  {mediaFilters.map((filter) => (
                    <button
                      key={filter}
                      type="button"
                      className="h-9 border border-[#333333] px-3 text-xs font-bold uppercase tracking-widest text-[#bbbbbb] transition-colors hover:border-primary hover:text-primary"
                      style={{ fontFamily: 'var(--font-heading)' }}
                    >
                      {filter}
                    </button>
                  ))}
                </div>

                {error && (
                  <div className="mt-5 border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
                    {error}
                  </div>
                )}

                <div className="mt-6 overflow-hidden border border-[#252525]">
                  <div className="hidden grid-cols-[72px_minmax(0,1.5fr)_120px_120px_120px_160px] border-b border-[#252525] bg-[#080808] px-4 py-3 text-[11px] font-bold uppercase tracking-widest text-[#777777] lg:grid">
                    <span>Media</span>
                    <span>Title</span>
                    <span>Type</span>
                    <span>Status</span>
                    <span>Size</span>
                    <span>Actions</span>
                  </div>

                  {isMediaLoading && (
                    <div className="px-5 py-10 text-sm text-[#888888]">Loading portfolio media...</div>
                  )}

                  {!isMediaLoading && mediaFiles.length > 0 && (
                    <div className="divide-y divide-[#252525]">
                      {mediaFiles.map((file) => (
                        <div
                          key={file.id}
                          className="grid gap-4 px-4 py-4 lg:grid-cols-[72px_minmax(0,1.5fr)_120px_120px_120px_160px] lg:items-center"
                        >
                          <div className="flex h-14 w-14 items-center justify-center border border-[#333333] bg-[#080808] text-primary">
                            <FileAudio size={22} />
                          </div>
                          <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-white">{file.original_name ?? file.name}</p>
                            <p className="mt-1 truncate text-xs text-[#777777]">{file.path}</p>
                          </div>
                          <p className="text-sm text-[#cccccc]">
                            {file.is_audio ? 'Audio' : file.is_video ? 'Video' : file.is_image ? 'Image' : 'File'}
                          </p>
                          <p className="text-sm text-[#cccccc]">Uploaded</p>
                          <p className="text-sm text-[#cccccc]">{file.formatted_size}</p>
                          <div className="flex items-center gap-2">
                            <button
                              type="button"
                              onClick={() => file.is_audio && setActiveAudioFile(file)}
                              disabled={!file.is_audio}
                              title={file.is_audio ? 'Play audio' : 'Player supports audio files'}
                              className="inline-flex h-9 w-9 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40"
                            >
                              {activeAudioFile?.id === file.id ? <Pause size={15} /> : <Play size={15} />}
                            </button>
                            <button
                              type="button"
                              title="Public visibility"
                              className="inline-flex h-9 w-9 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                            >
                              <Globe2 size={15} />
                            </button>
                            <button
                              type="button"
                              title="Private visibility"
                              className="inline-flex h-9 w-9 items-center justify-center border border-[#333333] text-[#777777] transition-colors hover:border-primary hover:text-primary"
                            >
                              <LockKeyhole size={15} />
                            </button>
                            <button
                              type="button"
                              onClick={() => handleDelete(file)}
                              disabled={deletingFileId === file.id}
                              title="Delete media"
                              className="inline-flex h-9 w-9 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary disabled:opacity-40"
                            >
                              <Trash2 size={15} />
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}

                  {!isMediaLoading && mediaFiles.length === 0 && (
                    <div className="grid place-items-center px-5 py-14 text-center">
                    <div className="flex h-16 w-16 items-center justify-center border border-[#333333] bg-[#080808] text-primary">
                      <FileAudio size={28} />
                    </div>
                    <h3 className="mt-5 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Upload Your First Mix
                    </h3>
                    <p className="mt-3 max-w-md text-sm leading-6 text-[#888888]">
                      Your portfolio is ready. The next build step will connect uploads, drafts, publishing, and
                      edit/delete actions to the database.
                    </p>
                    <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                      <label
                        className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        <Plus size={15} />
                        {isUploading ? 'Uploading' : 'Upload Media'}
                        <input
                          type="file"
                          accept="audio/*,video/*,image/*"
                          onChange={handleUpload}
                          disabled={isUploading}
                          className="sr-only"
                        />
                      </label>
                      <Link
                        to="/mixes"
                        className="inline-flex h-11 items-center justify-center gap-2 border border-[#444444] px-5 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        View Public Mixes
                        <ArrowRight size={15} />
                      </Link>
                    </div>
                  </div>
                  )}
                </div>
              </section>

              <section className="grid gap-4 md:grid-cols-3">
                {[
                  { icon: Disc3, title: 'Draft Metadata', body: 'Save title, genre, tags, visibility, and notes before a media file is ready.' },
                  { icon: Eye, title: 'Publish Control', body: 'Published, unlisted, private, and archived states will control where content appears.' },
                  { icon: Archive, title: 'Manage Library', body: 'Edit, preview, archive, delete, and later attach items to battles or DJLounge posts.' },
                ].map((item) => {
                  const Icon = item.icon;
                  return (
                    <div key={item.title} className="border border-[#2a2a2a] bg-[#111111] p-5">
                      <div className="mb-4 flex items-center justify-between">
                        <Icon size={20} className="text-primary" />
                        <MoreHorizontal size={18} className="text-[#555555]" />
                      </div>
                      <h3 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        {item.title}
                      </h3>
                      <p className="mt-2 text-sm leading-6 text-[#888888]">{item.body}</p>
                    </div>
                  );
                })}
              </section>
            </div>
          </div>
        </section>
      </main>

      {activeAudioFile && (
        <div className="fixed inset-x-0 bottom-0 z-50 border-t border-[#2a2a2a] bg-[#080808]/95 px-4 py-3 backdrop-blur lg:px-8">
          <div className="container mx-auto flex max-w-6xl flex-col gap-3 lg:flex-row lg:items-center">
            <div className="flex min-w-0 flex-1 items-center gap-3">
              <div className="flex h-11 w-11 shrink-0 items-center justify-center bg-primary text-white">
                <FileAudio size={20} />
              </div>
              <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-white">
                  {activeAudioFile.original_name ?? activeAudioFile.name}
                </p>
                <p className="truncate text-xs text-[#777777]">{activeAudioFile.path}</p>
              </div>
            </div>
            <audio key={activeAudioFile.id} src={activeAudioFile.url} controls autoPlay className="h-10 w-full lg:w-[420px]">
              <track kind="captions" />
            </audio>
            <button
              type="button"
              onClick={() => setActiveAudioFile(null)}
              className="inline-flex h-10 items-center justify-center gap-2 border border-[#333333] px-3 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <CircleStop size={15} />
              Close
            </button>
          </div>
        </div>
      )}
    </>
  );
}
