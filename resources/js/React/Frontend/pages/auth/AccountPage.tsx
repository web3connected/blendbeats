import { Helmet } from '@dr.pogodin/react-helmet';
import {
  AlertTriangle,
  AtSign,
  CheckCircle2,
  Image,
  Globe,
  LayoutDashboard,
  LogOut,
  Mail,
  MapPin,
  Megaphone,
  LoaderCircle,
  RotateCcw,
  Save,
  Settings,
  Swords,
  Upload,
  UserRound,
  X,
} from 'lucide-react';
import { type FormEvent, type ElementType, useEffect, useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { saveAccountAvatar, saveAccountProfile, type SaveAccountProfilePayload } from '@/lib/account';
import type { AuthUser } from '@/lib/auth';

function Field({
  label,
  name,
  type = 'text',
  value,
  placeholder,
  onChange,
}: {
  label: string;
  name: string;
  type?: string;
  value: string;
  placeholder?: string;
  onChange: (value: string) => void;
}) {
  return (
    <label className="grid gap-2">
      <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">{label}</span>
      <input
        name={name}
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        className="h-11 w-full border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
      />
    </label>
  );
}

function TextAreaField({
  label,
  name,
  value,
  placeholder,
  onChange,
}: {
  label: string;
  name: string;
  value: string;
  placeholder?: string;
  onChange: (value: string) => void;
}) {
  return (
    <label className="grid gap-2">
      <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">{label}</span>
      <textarea
        name={name}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        className="min-h-32 w-full resize-none border border-[#333333] bg-[#080808] p-3 text-sm leading-6 text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
      />
    </label>
  );
}

const TIMEZONE_OPTIONS = [
  { value: 'America/New_York', label: 'Eastern Time - New York' },
  { value: 'America/Chicago', label: 'Central Time - Chicago' },
  { value: 'America/Denver', label: 'Mountain Time - Denver' },
  { value: 'America/Phoenix', label: 'Arizona Time - Phoenix' },
  { value: 'America/Los_Angeles', label: 'Pacific Time - Los Angeles' },
  { value: 'America/Anchorage', label: 'Alaska Time - Anchorage' },
  { value: 'Pacific/Honolulu', label: 'Hawaii Time - Honolulu' },
  { value: 'America/Puerto_Rico', label: 'Atlantic Time - Puerto Rico' },
  { value: 'America/Toronto', label: 'Eastern Time - Toronto' },
  { value: 'America/Vancouver', label: 'Pacific Time - Vancouver' },
  { value: 'America/Mexico_City', label: 'Central Time - Mexico City' },
  { value: 'Europe/London', label: 'London' },
  { value: 'Europe/Paris', label: 'Central Europe - Paris' },
  { value: 'Europe/Berlin', label: 'Central Europe - Berlin' },
  { value: 'Africa/Lagos', label: 'West Africa - Lagos' },
  { value: 'Africa/Johannesburg', label: 'South Africa - Johannesburg' },
  { value: 'Asia/Dubai', label: 'Gulf Time - Dubai' },
  { value: 'Asia/Tokyo', label: 'Japan - Tokyo' },
  { value: 'Australia/Sydney', label: 'Australia Eastern - Sydney' },
  { value: 'UTC', label: 'UTC' },
];

function TimezoneField({ value, onChange }: { value: string; onChange: (value: string) => void }) {
  const hasSavedOption = TIMEZONE_OPTIONS.some((option) => option.value === value);

  return (
    <label className="grid gap-2">
      <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Timezone</span>
      <select
        name="timezone"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="h-11 w-full border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none transition-colors focus:border-primary"
      >
        <option value="">Select timezone</option>
        {value && !hasSavedOption && <option value={value}>{value}</option>}
        {TIMEZONE_OPTIONS.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  );
}

function SectionTitle({ icon: Icon, title }: { icon: ElementType; title: string }) {
  return (
    <div className="mb-5 flex items-center gap-3 border-b border-[#242424] pb-3">
      <Icon size={18} className="text-primary" />
      <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
        {title}
      </h2>
    </div>
  );
}

type AccountToast = {
  id: number;
  type: 'success' | 'error';
  message: string;
};

type AccountTab = 'avatar' | 'identity' | 'contact' | 'location' | 'social' | 'profile';

type AccountProfileFormState = SaveAccountProfilePayload;

const accountTabs: Array<{ id: AccountTab; label: string; icon: ElementType }> = [
  { id: 'avatar', label: 'Avatar', icon: Image },
  { id: 'identity', label: 'Identity', icon: UserRound },
  { id: 'contact', label: 'Contact', icon: Mail },
  { id: 'location', label: 'Location', icon: MapPin },
  { id: 'social', label: 'Social', icon: Globe },
  { id: 'profile', label: 'Profile', icon: AtSign },
];

const emptyAccountProfileForm: AccountProfileFormState = {
  first_name: '',
  last_name: '',
  name: '',
  email: '',
  contact_email: '',
  phone: '',
  birthdate: '',
  timezone: '',
  city: '',
  state: '',
  country: '',
  postal_code: '',
  website_url: '',
  instagram_url: '',
  youtube_url: '',
  soundcloud_url: '',
  spotify_url: '',
  bio: '',
  marketing_opt_in: false,
};

function profileFormFromUser(user: AuthUser): AccountProfileFormState {
  const profile = user.profile;

  return {
    first_name: user.first_name ?? user.name.split(' ')[0] ?? '',
    last_name: user.last_name ?? user.name.split(' ').slice(1).join(' '),
    name: user.name,
    email: user.email,
    contact_email: profile?.contact_email ?? user.email,
    phone: profile?.phone ?? '',
    birthdate: profile?.birthdate ?? '',
    timezone: profile?.timezone ?? '',
    city: profile?.city ?? '',
    state: profile?.state ?? '',
    country: profile?.country ?? '',
    postal_code: profile?.postal_code ?? '',
    website_url: profile?.website_url ?? '',
    instagram_url: profile?.instagram_url ?? '',
    youtube_url: profile?.youtube_url ?? '',
    soundcloud_url: profile?.soundcloud_url ?? '',
    spotify_url: profile?.spotify_url ?? '',
    bio: profile?.bio ?? '',
    marketing_opt_in: Boolean(profile?.marketing_opt_in),
  };
}

function AccountToastBanner({
  toast,
  onClose,
}: {
  toast: AccountToast;
  onClose: () => void;
}) {
  const Icon = toast.type === 'success' ? CheckCircle2 : AlertTriangle;

  return (
    <div className="pointer-events-none fixed inset-x-0 top-4 z-[70] flex justify-center px-4">
      <div
        className={`pointer-events-auto flex min-h-12 w-full max-w-md items-center gap-3 border px-4 py-3 text-sm text-white shadow-2xl shadow-black/50 ${
          toast.type === 'success'
            ? 'border-[#2a2a2a] bg-[#101010]'
            : 'border-primary/50 bg-[#180909]'
        }`}
        style={{
          animation: 'blendbeats-toast-slide-down 220ms ease-out both',
        }}
        role="status"
        aria-live="polite"
      >
        <Icon size={18} className={toast.type === 'success' ? 'text-[#FFB800]' : 'text-primary'} />
        <span className="min-w-0 flex-1">{toast.message}</span>
        <button
          type="button"
          onClick={onClose}
          className="inline-flex h-7 w-7 shrink-0 items-center justify-center border border-[#333333] text-[#aaaaaa] transition-colors hover:border-primary hover:text-primary"
          aria-label="Close message"
        >
          <X size={14} />
        </button>
      </div>
    </div>
  );
}

export default function AccountPage() {
  const navigate = useNavigate();
  const { user, isLoading, logout, refreshUser } = useAuth();
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [avatarFilePreview, setAvatarFilePreview] = useState<string | null>(null);
  const [useGravatar, setUseGravatar] = useState(false);
  const [useGeneratedInitials, setUseGeneratedInitials] = useState(false);
  const [isAvatarSaving, setIsAvatarSaving] = useState(false);
  const [isProfileSaving, setIsProfileSaving] = useState(false);
  const [toast, setToast] = useState<AccountToast | null>(null);
  const [activeTab, setActiveTab] = useState<AccountTab>('identity');
  const [profileForm, setProfileForm] = useState<AccountProfileFormState>(emptyAccountProfileForm);

  const showToast = (type: AccountToast['type'], message: string) => {
    setToast({ id: Date.now(), type, message });
  };

  useEffect(() => {
    if (!user) return;

    setProfileForm(profileFormFromUser(user));
  }, [user?.id]);

  useEffect(() => {
    if (!user) return;

    setUseGravatar(Boolean(user.is_gravatar ?? user.use_gravatar));
    setUseGeneratedInitials(false);
    setAvatarFile(null);
  }, [user?.id, user?.avatar, user?.is_gravatar, user?.use_gravatar]);

  useEffect(() => {
    if (!avatarFile) {
      setAvatarFilePreview(null);
      return;
    }

    const objectUrl = URL.createObjectURL(avatarFile);
    setAvatarFilePreview(objectUrl);

    return () => URL.revokeObjectURL(objectUrl);
  }, [avatarFile]);

  useEffect(() => {
    if (!toast) return;

    const timeout = window.setTimeout(() => setToast(null), 4200);

    return () => window.clearTimeout(timeout);
  }, [toast]);

  if (isLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20">
        <div className="container mx-auto h-48 max-w-3xl animate-pulse bg-[#141414]" />
      </main>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  const handleLogout = async () => {
    await logout();
    navigate('/');
  };
  const updateProfileField = <FieldName extends keyof AccountProfileFormState>(
    field: FieldName,
    value: AccountProfileFormState[FieldName],
  ) => {
    setProfileForm((currentForm) => ({ ...currentForm, [field]: value }));
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    await handleProfileSave();
  };

  const handleProfileSave = async () => {
    const payload: SaveAccountProfilePayload = {
      ...profileForm,
      first_name: profileForm.first_name.trim(),
      last_name: profileForm.last_name.trim(),
      name: profileForm.name.trim(),
      email: profileForm.email.trim(),
      contact_email: profileForm.contact_email.trim(),
      phone: profileForm.phone.trim(),
      birthdate: profileForm.birthdate.trim(),
      timezone: profileForm.timezone.trim(),
      city: profileForm.city.trim(),
      state: profileForm.state.trim(),
      country: profileForm.country.trim(),
      postal_code: profileForm.postal_code.trim(),
      website_url: profileForm.website_url.trim(),
      instagram_url: profileForm.instagram_url.trim(),
      youtube_url: profileForm.youtube_url.trim(),
      soundcloud_url: profileForm.soundcloud_url.trim(),
      spotify_url: profileForm.spotify_url.trim(),
      bio: profileForm.bio.trim(),
    };

    setIsProfileSaving(true);

    try {
      await saveAccountProfile(payload);
      await refreshUser();
      showToast('success', 'Profile updated.');
    } catch (error) {
      showToast('error', error instanceof Error ? error.message : 'Profile could not be saved.');
    } finally {
      setIsProfileSaving(false);
    }
  };

  const avatarPreview = avatarFilePreview
    || (useGeneratedInitials
      ? user.generated_avatar_url
      : useGravatar
        ? user.gravatar_url || user.avatar_url
        : user.custom_avatar_url || user.avatar_url || user.generated_avatar_url);
  const avatarSource = avatarFilePreview
    ? 'selected upload'
    : useGeneratedInitials
      ? 'generated'
      : useGravatar
        ? 'gravatar'
        : user.avatar_source ?? (user.avatar ? 'uploaded' : 'generated');
  const activeTabMeta = accountTabs.find((tab) => tab.id === activeTab) ?? accountTabs[1];

  const handleAvatarSave = async () => {
    setIsAvatarSaving(true);

    try {
      await saveAccountAvatar({
        useGravatar,
        avatarUrl: '',
        avatarFile,
        removeAvatar: useGeneratedInitials,
      });
      await refreshUser();
      showToast('success', 'Avatar updated.');
    } catch (error) {
      showToast('error', error instanceof Error ? error.message : 'Avatar could not be updated.');
    } finally {
      setIsAvatarSaving(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Profile | The Blend Battlegrounds</title>
        <meta name="description" content="Manage your Blend Battlegrounds profile data." />
      </Helmet>
      <style>
        {`
          @keyframes blendbeats-toast-slide-down {
            0% { opacity: 0; transform: translateY(-18px); }
            100% { opacity: 1; transform: translateY(0); }
          }
        `}
      </style>
      {toast && <AccountToastBanner toast={toast} onClose={() => setToast(null)} />}
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-16 lg:px-8">
          <div className="container mx-auto max-w-4xl">
            <p
              className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Account / Profile
            </p>
            <h1
              className="text-white uppercase leading-none"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(4rem, 10vw, 8rem)' }}
            >
              Profile
            </h1>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-5 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside className="border border-[#2a2a2a] bg-[#111111] p-5">
              {avatarPreview ? (
                <img
                  src={avatarPreview}
                  alt={user.name}
                  className="mb-4 h-16 w-16 object-cover"
                />
              ) : (
                <div className="mb-4 flex h-16 w-16 items-center justify-center bg-primary text-2xl font-black uppercase text-white">
                  {user.name.charAt(0)}
                </div>
              )}
              <p className="text-lg font-semibold text-white">{user.name}</p>
              <p className="mt-1 break-all text-sm text-[#888888]">{user.email}</p>
                <div className="mt-6 grid gap-3">
                  <Link
                    to="/account"
                    className="inline-flex h-12 items-center gap-3 border border-[#333333] px-4 text-sm text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  >
                    <LayoutDashboard size={16} />
                    Go to account
                  </Link>
                <Link
                  to="/battles"
                  className="inline-flex h-12 items-center gap-3 border border-[#333333] px-4 text-sm text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                >
                  <Swords size={16} />
                  Go to battles
                </Link>
                <button
                  type="button"
                  onClick={() => void handleLogout()}
                  className="inline-flex h-12 items-center gap-3 border border-[#333333] px-4 text-left text-sm text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                >
                  <LogOut size={16} />
                  Logout
                </button>
              </div>
            </aside>

            <form onSubmit={handleSubmit} className="grid gap-5">
              <section className="border border-[#2a2a2a] bg-[#111111]">
                <div className="grid border-b border-[#2a2a2a] bg-[#080808] sm:grid-cols-3 lg:grid-cols-6">
                  {accountTabs.map((tab) => {
                    const Icon = tab.icon;

                    return (
                      <button
                        key={tab.id}
                        type="button"
                        onClick={() => setActiveTab(tab.id)}
                        className={`inline-flex h-12 items-center justify-center gap-2 border-b border-r border-[#2a2a2a] px-3 text-xs font-bold uppercase tracking-widest transition-colors last:border-r-0 ${
                          activeTab === tab.id
                            ? 'bg-primary text-white'
                            : 'text-[#aaaaaa] hover:bg-[#111111] hover:text-primary'
                        }`}
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        <Icon size={15} />
                        {tab.label}
                      </button>
                    );
                  })}
                </div>

                <div className="p-5 sm:p-6">
                  <SectionTitle icon={activeTabMeta.icon} title={activeTabMeta.label === 'Social' ? 'Social Links' : activeTabMeta.label} />

                  {activeTab === 'avatar' && (
                    <div className="grid gap-5 md:grid-cols-[160px_minmax(0,1fr)]">
                      <div>
                        {avatarPreview ? (
                          <img
                            src={avatarPreview}
                            alt={user.name}
                            className="h-36 w-36 border border-[#333333] object-cover"
                          />
                        ) : (
                          <div className="flex h-36 w-36 items-center justify-center border border-[#333333] bg-primary text-4xl font-black uppercase text-white">
                            {user.name.charAt(0)}
                          </div>
                        )}
                        <p className="mt-3 text-[11px] uppercase tracking-widest text-[#777777]">
                          Source: {avatarSource}
                        </p>
                      </div>

                      <div className="grid gap-4">
                        <div className="border border-[#333333] bg-[#080808] p-4">
                          <p className="text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">Avatar Source</p>
                          <p className="mt-2 text-sm leading-6 text-[#888888]">
                            Your account uses one avatar everywhere. Upload an image, use Gravatar from your email, or fall
                            back to generated initials.
                          </p>
                        </div>

                        <label className="grid gap-2 border border-[#333333] bg-[#080808] p-4">
                          <span className="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">
                            <Upload size={15} className="text-primary" />
                            Upload Avatar
                          </span>
                          <input
                            name="avatar_upload"
                            type="file"
                            accept="image/*"
                            onChange={(event) => {
                              setAvatarFile(event.target.files?.[0] ?? null);
                              setUseGravatar(false);
                              setUseGeneratedInitials(false);
                            }}
                            className="w-full border border-[#333333] bg-[#050505] px-4 py-3 text-sm text-[#bbbbbb] file:mr-4 file:border-0 file:bg-primary file:px-4 file:py-2 file:text-xs file:font-bold file:uppercase file:tracking-widest file:text-white"
                            style={{ fontFamily: 'var(--font-heading)' }}
                          />
                          <span className="text-xs leading-5 text-[#888888]">Stores the uploaded image as your user avatar.</span>
                        </label>

                        <div className="grid gap-3 sm:grid-cols-2">
                          <button
                            type="button"
                            onClick={() => {
                              setUseGravatar(true);
                              setUseGeneratedInitials(false);
                              setAvatarFile(null);
                            }}
                            className={`min-h-24 border p-4 text-left transition-colors ${
                              useGravatar
                                ? 'border-primary bg-primary/10'
                                : 'border-[#333333] bg-[#080808] hover:border-primary'
                            }`}
                          >
                            <span className="text-[11px] font-bold uppercase tracking-widest text-primary">Use Gravatar</span>
                            <span className="mt-3 block break-all text-sm leading-6 text-[#aaaaaa]">{user.email}</span>
                          </button>

                          <button
                            type="button"
                            onClick={() => {
                              setUseGravatar(false);
                              setUseGeneratedInitials(true);
                              setAvatarFile(null);
                            }}
                            className={`min-h-24 border p-4 text-left transition-colors ${
                              useGeneratedInitials
                                ? 'border-primary bg-primary/10'
                                : 'border-[#333333] bg-[#080808] hover:border-primary'
                            }`}
                          >
                            <span className="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">
                              <RotateCcw size={15} className="text-primary" />
                              Use Generated Initials
                            </span>
                            <span className="mt-3 block text-sm leading-6 text-[#888888]">
                              Use the initials avatar from your display name.
                            </span>
                          </button>
                        </div>

                        <button
                          type="button"
                          onClick={() => void handleAvatarSave()}
                          disabled={isAvatarSaving}
                          className="inline-flex h-11 w-fit items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:opacity-60"
                          style={{ fontFamily: 'var(--font-heading)' }}
                        >
                          {isAvatarSaving ? <LoaderCircle size={15} className="animate-spin" /> : <Save size={15} />}
                          {isAvatarSaving ? 'Saving Avatar' : 'Save Avatar'}
                        </button>
                      </div>
                    </div>
                  )}

                  {activeTab === 'identity' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                      <Field label="First Name" name="first_name" value={profileForm.first_name} onChange={(value) => updateProfileField('first_name', value)} />
                      <Field label="Last Name" name="last_name" value={profileForm.last_name} onChange={(value) => updateProfileField('last_name', value)} />
                      <Field label="Display Name" name="name" value={profileForm.name} onChange={(value) => updateProfileField('name', value)} />
                      <Field label="Login Email" name="email" type="email" value={profileForm.email} onChange={(value) => updateProfileField('email', value)} />
                    </div>
                  )}

                  {activeTab === 'contact' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                      <Field label="Contact Email" name="contact_email" type="email" value={profileForm.contact_email} onChange={(value) => updateProfileField('contact_email', value)} />
                      <Field label="Phone" name="phone" type="tel" value={profileForm.phone} placeholder="555-0100" onChange={(value) => updateProfileField('phone', value)} />
                      <Field label="Birthdate" name="birthdate" type="date" value={profileForm.birthdate} onChange={(value) => updateProfileField('birthdate', value)} />
                      <TimezoneField value={profileForm.timezone} onChange={(value) => updateProfileField('timezone', value)} />
                    </div>
                  )}

                  {activeTab === 'location' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                      <Field label="City" name="city" value={profileForm.city} onChange={(value) => updateProfileField('city', value)} />
                      <Field label="State" name="state" value={profileForm.state} onChange={(value) => updateProfileField('state', value)} />
                      <Field label="Country" name="country" value={profileForm.country} onChange={(value) => updateProfileField('country', value)} />
                      <Field label="Postal Code" name="postal_code" value={profileForm.postal_code} onChange={(value) => updateProfileField('postal_code', value)} />
                    </div>
                  )}

                  {activeTab === 'social' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                      <Field label="Website" name="website_url" type="url" value={profileForm.website_url} onChange={(value) => updateProfileField('website_url', value)} />
                      <Field label="Instagram" name="instagram_url" type="url" value={profileForm.instagram_url} onChange={(value) => updateProfileField('instagram_url', value)} />
                      <Field label="YouTube" name="youtube_url" type="url" value={profileForm.youtube_url} onChange={(value) => updateProfileField('youtube_url', value)} />
                      <Field label="SoundCloud" name="soundcloud_url" type="url" value={profileForm.soundcloud_url} onChange={(value) => updateProfileField('soundcloud_url', value)} />
                      <Field label="Spotify" name="spotify_url" type="url" value={profileForm.spotify_url} onChange={(value) => updateProfileField('spotify_url', value)} />
                    </div>
                  )}

                  {activeTab === 'profile' && (
                    <div>
                      <TextAreaField
                        label="Bio"
                        name="bio"
                        value={profileForm.bio}
                        onChange={(value) => updateProfileField('bio', value)}
                        placeholder="Tell the community who you are and what sound you bring."
                      />
                      <label className="mt-5 flex items-start gap-3 border border-[#333333] bg-[#080808] p-4">
                        <input
                          name="marketing_opt_in"
                          type="checkbox"
                          checked={profileForm.marketing_opt_in}
                          onChange={(event) => updateProfileField('marketing_opt_in', event.target.checked)}
                          className="mt-1 h-4 w-4 accent-primary"
                        />
                        <span>
                          <span className="flex items-center gap-2 text-sm font-semibold text-white">
                            <Megaphone size={15} className="text-primary" />
                            Marketing Opt-In
                          </span>
                          <span className="mt-1 block text-xs leading-5 text-[#888888]">
                            Receive event, battle, and platform updates.
                          </span>
                        </span>
                      </label>
                    </div>
                  )}
                </div>
              </section>

              {activeTab !== 'avatar' && (
                <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
                  <button
                    type="button"
                    onClick={() => {
                      setToast(null);
                      setProfileForm(profileFormFromUser(user));
                    }}
                    className="inline-flex h-11 items-center justify-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                    style={{ fontFamily: 'var(--font-heading)' }}
                  >
                    <Settings size={15} />
                    Reset Changes
                  </button>
                  <button
                    type="submit"
                    disabled={isProfileSaving}
                    className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:opacity-60"
                    style={{ fontFamily: 'var(--font-heading)' }}
                  >
                    {isProfileSaving ? <LoaderCircle size={15} className="animate-spin" /> : <Save size={15} />}
                    {isProfileSaving ? `Saving ${activeTabMeta.label}` : `Save ${activeTabMeta.label}`}
                  </button>
                </div>
              )}
            </form>
          </div>
        </section>
      </main>
    </>
  );
}
