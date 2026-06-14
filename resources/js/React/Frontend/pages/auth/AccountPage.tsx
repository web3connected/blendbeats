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

function Field({
  label,
  name,
  type = 'text',
  value,
  placeholder,
}: {
  label: string;
  name: string;
  type?: string;
  value?: string | null;
  placeholder?: string;
}) {
  return (
    <label className="grid gap-2">
      <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">{label}</span>
      <input
        name={name}
        type={type}
        defaultValue={value ?? ''}
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
}: {
  label: string;
  name: string;
  value?: string | null;
  placeholder?: string;
}) {
  return (
    <label className="grid gap-2">
      <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">{label}</span>
      <textarea
        name={name}
        defaultValue={value ?? ''}
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

function TimezoneField({ value }: { value?: string | null }) {
  const savedValue = value ?? '';
  const hasSavedOption = TIMEZONE_OPTIONS.some((option) => option.value === savedValue);

  return (
    <label className="grid gap-2">
      <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Timezone</span>
      <select
        name="timezone"
        defaultValue={savedValue}
        className="h-11 w-full border border-[#333333] bg-[#080808] px-3 text-sm text-white outline-none transition-colors focus:border-primary"
      >
        <option value="">Select timezone</option>
        {savedValue && !hasSavedOption && <option value={savedValue}>{savedValue}</option>}
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

  const showToast = (type: AccountToast['type'], message: string) => {
    setToast({ id: Date.now(), type, message });
  };

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
  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const formData = new FormData(event.currentTarget);
    const value = (name: keyof SaveAccountProfilePayload) => String(formData.get(name) ?? '').trim();
    const payload: SaveAccountProfilePayload = {
      first_name: value('first_name'),
      last_name: value('last_name'),
      name: value('name'),
      email: value('email'),
      contact_email: value('contact_email'),
      phone: value('phone'),
      birthdate: value('birthdate'),
      timezone: value('timezone'),
      city: value('city'),
      state: value('state'),
      country: value('country'),
      postal_code: value('postal_code'),
      website_url: value('website_url'),
      instagram_url: value('instagram_url'),
      youtube_url: value('youtube_url'),
      soundcloud_url: value('soundcloud_url'),
      spotify_url: value('spotify_url'),
      bio: value('bio'),
      marketing_opt_in: formData.has('marketing_opt_in'),
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

  const profile = user.profile;
  const firstName = user.first_name ?? user.name.split(' ')[0] ?? '';
  const lastName = user.last_name ?? user.name.split(' ').slice(1).join(' ');
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
        <title>Account | The Blend Battlegrounds</title>
        <meta name="description" content="Manage your Blend Battlegrounds account." />
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
              Account
            </p>
            <h1
              className="text-white uppercase leading-none"
              style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(4rem, 10vw, 8rem)' }}
            >
              Your Profile
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
                  to="/dashboard"
                  className="inline-flex h-12 items-center gap-3 border border-[#333333] px-4 text-sm text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                >
                  <LayoutDashboard size={16} />
                  Go to dashboard
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
              <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                <SectionTitle icon={Image} title="Account Avatar" />
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
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                <SectionTitle icon={UserRound} title="Identity" />
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="First Name" name="first_name" value={firstName} />
                  <Field label="Last Name" name="last_name" value={lastName} />
                  <Field label="Display Name" name="name" value={user.name} />
                  <Field label="Login Email" name="email" type="email" value={user.email} />
                </div>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                <SectionTitle icon={Mail} title="Contact" />
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field
                    label="Contact Email"
                    name="contact_email"
                    type="email"
                    value={profile?.contact_email ?? user.email}
                  />
                  <Field label="Phone" name="phone" type="tel" value={profile?.phone} placeholder="555-0100" />
                  <Field label="Birthdate" name="birthdate" type="date" value={profile?.birthdate} />
                  <TimezoneField value={profile?.timezone} />
                </div>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                <SectionTitle icon={MapPin} title="Location" />
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="City" name="city" value={profile?.city} />
                  <Field label="State" name="state" value={profile?.state} />
                  <Field label="Country" name="country" value={profile?.country} />
                  <Field label="Postal Code" name="postal_code" value={profile?.postal_code} />
                </div>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                <SectionTitle icon={Globe} title="Social Links" />
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="Website" name="website_url" type="url" value={profile?.website_url} />
                  <Field label="Instagram" name="instagram_url" type="url" value={profile?.instagram_url} />
                  <Field label="YouTube" name="youtube_url" type="url" value={profile?.youtube_url} />
                  <Field label="SoundCloud" name="soundcloud_url" type="url" value={profile?.soundcloud_url} />
                  <Field label="Spotify" name="spotify_url" type="url" value={profile?.spotify_url} />
                </div>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5 sm:p-6">
                <SectionTitle icon={AtSign} title="Profile" />
                <TextAreaField
                  label="Bio"
                  name="bio"
                  value={profile?.bio}
                  placeholder="Tell the community who you are and what sound you bring."
                />
                <label className="mt-5 flex items-start gap-3 border border-[#333333] bg-[#080808] p-4">
                  <input
                    name="marketing_opt_in"
                    type="checkbox"
                    defaultChecked={Boolean(profile?.marketing_opt_in)}
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
              </section>

              <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <button
                  type="button"
                  onClick={() => {
                    setToast(null);
                    refreshUser();
                  }}
                  className="inline-flex h-11 items-center justify-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <Settings size={15} />
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isProfileSaving}
                  className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  {isProfileSaving ? <LoaderCircle size={15} className="animate-spin" /> : <Save size={15} />}
                  {isProfileSaving ? 'Saving Profile' : 'Save Profile'}
                </button>
              </div>
            </form>
          </div>
        </section>
      </main>
    </>
  );
}
