import { Helmet } from '@dr.pogodin/react-helmet';
import {
  Archive,
  ArrowRight,
  Disc3,
  Edit3,
  Eye,
  FileAudio,
  FileImage,
  FileVideo,
  Filter,
  Globe2,
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
  X,
} from 'lucide-react';
import { type ChangeEvent, type FormEvent, useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { usePlayer } from '@/components/player/PlayerProvider';
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
const genreOptions = ['Hip-Hop', 'House', 'Drum & Bass', 'Techno', 'Scratch Sets', 'Open Format', 'R&B', 'Afrobeats'];
const kindOptions = [
  { value: 'mix', label: 'Mix' },
  { value: 'track', label: 'Track' },
  { value: 'video', label: 'Video' },
  { value: 'battle_entry', label: 'Battle Entry' },
  { value: 'image', label: 'Image' },
];
const visibilityOptions = [
  { value: 'draft', label: 'Draft' },
  { value: 'public', label: 'Public' },
  { value: 'unlisted', label: 'Unlisted' },
  { value: 'private', label: 'Private' },
];

const storageNotes = [
  'Uploads save in Laravel storage and render through /api/media/files/{id}/stream.',
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

function mediaTypeLabel(file: MediaFileRecord) {
  if (file.is_audio) return 'Audio';
  if (file.is_video) return 'Video';
  if (file.is_image) return 'Image';
  if (file.is_pdf) return 'PDF';
  return 'File';
}

function MediaPreview({ file }: { file: MediaFileRecord }) {
  if (file.is_image) {
    return (
      <img
        src={file.url}
        alt={file.original_name ?? file.name}
        loading="lazy"
        className="h-14 w-14 border border-[#333333] bg-[#080808] object-cover"
      />
    );
  }

  if (file.is_video) {
    return (
      <video
        src={file.url}
        muted
        playsInline
        preload="metadata"
        className="h-14 w-14 border border-[#333333] bg-[#080808] object-cover"
      >
        <track kind="captions" />
      </video>
    );
  }

  const Icon = file.is_audio ? FileAudio : file.is_pdf ? FileImage : FileVideo;

  return (
    <div className="flex h-14 w-14 items-center justify-center border border-[#333333] bg-[#080808] text-primary">
      <Icon size={22} />
    </div>
  );
}

export default function DjPortfolioPage() {
  const { user, isLoading } = useAuth();
  const { currentTrack, isPlaying, playTrack, togglePlay } = usePlayer();
  const [mediaAccount, setMediaAccount] = useState<MediaAccount | null>(null);
  const [mediaFiles, setMediaFiles] = useState<MediaFileRecord[]>([]);
  const [storageQuota, setStorageQuota] = useState<MediaStorageQuota | null>(null);
  const [isSetupLoading, setIsSetupLoading] = useState(false);
  const [isActivatingSetup, setIsActivatingSetup] = useState(false);
  const [isMediaLoading, setIsMediaLoading] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [deletingFileId, setDeletingFileId] = useState<number | null>(null);
  const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [uploadForm, setUploadForm] = useState({
    title: '',
    description: '',
    genre: '',
    visibility: 'draft',
    mediaKind: 'mix',
  });
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

  const handleUploadFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];

    if (!file) return;
    setUploadFile(file);
    setUploadForm((currentForm) => ({
      ...currentForm,
      title: currentForm.title || file.name.replace(/\.[^/.]+$/, ''),
    }));
  };

  const closeUploadModal = () => {
    setIsUploadModalOpen(false);
    setUploadFile(null);
    setUploadForm({
      title: '',
      description: '',
      genre: '',
      visibility: 'draft',
      mediaKind: 'mix',
    });
  };

  const handleUploadSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const file = uploadFile;
    setIsUploading(true);
    setError('');

    if (!file) {
      setError('Choose a media file before uploading.');
      setIsUploading(false);
      return;
    }

    uploadMediaFile(file, 'dj_media', uploadForm)
      .then((uploadResponse) => {
        setMediaFiles((currentFiles) => [uploadResponse.file, ...currentFiles]);
        setStorageQuota(uploadResponse.quota);
        setIsUploadModalOpen(false);
        setUploadFile(null);
        setUploadForm({
          title: '',
          description: '',
          genre: '',
          visibility: 'draft',
          mediaKind: 'mix',
        });
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

  const avatarUrl = user.avatar_url || user.custom_avatar_url || user.gravatar_url || user.generated_avatar_url;

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
          <MediaSetupSection
            user={user}
            error={error}
            isActivating={isActivatingSetup}
            onActivate={handleActivateMedia}
          />
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
                <button
                  type="button"
                  onClick={() => setIsUploadModalOpen(true)}
                  className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-sm font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <Upload size={16} />
                  {isUploading ? 'Uploading' : 'Upload Media'}
                </button>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-8 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-5 lg:grid-cols-[300px_minmax(0,1fr)]">
            <aside className="grid gap-5 self-start">
              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <div className="flex items-center gap-3">
                  {avatarUrl ? (
                    <img
                      src={avatarUrl}
                      alt={user.dj_profile.dj_name}
                      className="h-12 w-12 shrink-0 border border-[#333333] bg-[#080808] object-cover"
                    />
                  ) : (
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center bg-primary text-white">
                      <Radio size={20} />
                    </div>
                  )}
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
                    <p className="mt-2 text-sm text-[#888888]">Uploads are stored through the media manager.</p>
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
                          <MediaPreview file={file} />
                          <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-white">
                              {file.portfolio_title || file.original_name || file.name}
                            </p>
                            <p className="mt-1 truncate text-xs text-[#777777]">
                              {file.portfolio_description || file.path}
                            </p>
                          </div>
                          <p className="text-sm text-[#cccccc]">
                            {file.portfolio_genre || mediaTypeLabel(file)}
                          </p>
                          <p className="text-sm capitalize text-[#cccccc]">{file.portfolio_visibility || 'Uploaded'}</p>
                          <p className="text-sm text-[#cccccc]">{file.formatted_size}</p>
                          <div className="flex items-center gap-2">
                            <button
                              type="button"
                              onClick={() => {
                                if (!file.is_audio) return;
                                if (currentTrack?.id === `portfolio-${file.id}`) {
                                  togglePlay();
                                  return;
                                }

                                playTrack({
                                  id: `portfolio-${file.id}`,
                                  title: file.portfolio_title || file.original_name || file.name,
                                  artist: user.dj_profile?.dj_name,
                                  src: file.url,
                                  meta: file.portfolio_genre || mediaTypeLabel(file),
                                });
                              }}
                              disabled={!file.is_audio}
                              title={file.is_audio ? 'Play audio' : 'Player supports audio files'}
                              className="inline-flex h-9 w-9 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40"
                            >
                              {currentTrack?.id === `portfolio-${file.id}` && isPlaying ? <Pause size={15} /> : <Play size={15} />}
                            </button>
                            <a
                              href={file.url}
                              target="_blank"
                              rel="noreferrer"
                              title="Open media"
                              className="inline-flex h-9 w-9 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                            >
                              <Eye size={15} />
                            </a>
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
                        Your portfolio is ready. Upload mixes, videos, images, and future battle media here.
                      </p>
                      <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                        <button
                          type="button"
                          onClick={() => setIsUploadModalOpen(true)}
                          className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                          style={{ fontFamily: 'var(--font-heading)' }}
                        >
                          <Plus size={15} />
                          {isUploading ? 'Uploading' : 'Upload Media'}
                        </button>
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

      {isUploadModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 px-4 py-6 backdrop-blur-sm">
          <form
            onSubmit={handleUploadSubmit}
            className="max-h-[92vh] w-full max-w-2xl overflow-y-auto border border-[#2a2a2a] bg-[#0d0d0d] p-5 shadow-2xl shadow-black/60 sm:p-6"
          >
            <div className="mb-5 flex items-start justify-between gap-4 border-b border-[#252525] pb-4">
              <div>
                <p className="text-[11px] font-bold uppercase tracking-widest text-primary">Portfolio Upload</p>
                <h2 className="mt-2 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Add Media Details
                </h2>
              </div>
              <button
                type="button"
                onClick={closeUploadModal}
                className="inline-flex h-10 w-10 shrink-0 items-center justify-center border border-[#333333] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                aria-label="Close upload modal"
              >
                <X size={18} />
              </button>
            </div>

            <div className="grid gap-4">
              <label className="grid gap-2">
                <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Media File</span>
                <input
                  type="file"
                  accept="audio/*,video/*,image/*"
                  onChange={handleUploadFileChange}
                  className="w-full border border-[#333333] bg-[#080808] px-4 py-3 text-sm text-[#bbbbbb] file:mr-4 file:border-0 file:bg-primary file:px-4 file:py-2 file:text-xs file:font-bold file:uppercase file:tracking-widest file:text-white"
                  style={{ fontFamily: 'var(--font-heading)' }}
                />
                {uploadFile && (
                  <span className="text-xs text-[#888888]">
                    Selected: {uploadFile.name}
                  </span>
                )}
              </label>

              <div className="grid gap-4 sm:grid-cols-2">
                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Track Title</span>
                  <input
                    value={uploadForm.title}
                    onChange={(event) => setUploadForm((current) => ({ ...current, title: event.target.value }))}
                    placeholder="Late Night Blend Vol. 1"
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none placeholder:text-[#555555] focus:border-primary"
                  />
                </label>

                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Genre</span>
                  <select
                    value={uploadForm.genre}
                    onChange={(event) => setUploadForm((current) => ({ ...current, genre: event.target.value }))}
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                  >
                    <option value="">Choose genre</option>
                    {genreOptions.map((genre) => (
                      <option key={genre} value={genre}>
                        {genre}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Media Type</span>
                  <select
                    value={uploadForm.mediaKind}
                    onChange={(event) => setUploadForm((current) => ({ ...current, mediaKind: event.target.value }))}
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                  >
                    {kindOptions.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </label>

                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Visibility</span>
                  <select
                    value={uploadForm.visibility}
                    onChange={(event) => setUploadForm((current) => ({ ...current, visibility: event.target.value }))}
                    className="h-11 border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none focus:border-primary"
                  >
                    {visibilityOptions.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </label>
              </div>

              <label className="grid gap-2">
                <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Description</span>
                <textarea
                  value={uploadForm.description}
                  onChange={(event) => setUploadForm((current) => ({ ...current, description: event.target.value }))}
                  placeholder="Describe the mix, track, routine, featured sounds, or battle context."
                  className="min-h-28 resize-none border border-[#333333] bg-[#080808] p-3 text-sm leading-6 text-white outline-none placeholder:text-[#555555] focus:border-primary"
                />
              </label>
            </div>

            <div className="mt-6 flex flex-col gap-3 border-t border-[#252525] pt-5 sm:flex-row sm:justify-end">
              <button
                type="button"
                onClick={closeUploadModal}
                className="inline-flex h-11 items-center justify-center border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isUploading || !uploadFile}
                className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:opacity-60"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                <Upload size={15} />
                {isUploading ? 'Uploading' : 'Upload To Portfolio'}
              </button>
            </div>
          </form>
        </div>
      )}
    </>
  );
}
