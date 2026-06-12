import { Helmet } from '@dr.pogodin/react-helmet';
import {
  AtSign,
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
} from 'lucide-react';
import { type FormEvent, type ElementType, useEffect, useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { saveAccountAvatar } from '@/lib/account';

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

export default function AccountPage() {
  const navigate = useNavigate();
  const { user, isLoading, logout, refreshUser } = useAuth();
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [avatarFilePreview, setAvatarFilePreview] = useState<string | null>(null);
  const [useGravatar, setUseGravatar] = useState(false);
  const [useGeneratedInitials, setUseGeneratedInitials] = useState(false);
  const [isAvatarSaving, setIsAvatarSaving] = useState(false);
  const [avatarMessage, setAvatarMessage] = useState('');
  const [avatarError, setAvatarError] = useState('');

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
  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
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
    setAvatarMessage('');
    setAvatarError('');
    setIsAvatarSaving(true);

    try {
      await saveAccountAvatar({
        useGravatar,
        avatarUrl: '',
        avatarFile,
        removeAvatar: useGeneratedInitials,
      });
      await refreshUser();
      setAvatarMessage('Avatar updated.');
    } catch (error) {
      setAvatarError(error instanceof Error ? error.message : 'Avatar could not be updated.');
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
                          setAvatarMessage('');
                          setAvatarError('');
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
                          setAvatarMessage('');
                          setAvatarError('');
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
                          setAvatarMessage('');
                          setAvatarError('');
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

                    {avatarMessage && (
                      <div className="border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
                        {avatarMessage}
                      </div>
                    )}
                    {avatarError && (
                      <div className="border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-primary">
                        {avatarError}
                      </div>
                    )}

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
                  <Field
                    label="Timezone"
                    name="timezone"
                    value={profile?.timezone}
                    placeholder="America/New_York"
                  />
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
                  className="inline-flex h-11 items-center justify-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <Settings size={15} />
                  Cancel
                </button>
                <button
                  type="submit"
                  className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <Save size={15} />
                  Save Profile
                </button>
              </div>
            </form>
          </div>
        </section>
      </main>
    </>
  );
}
