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
  Save,
  Settings,
  Swords,
  UserRound,
} from 'lucide-react';
import { type FormEvent, type ElementType } from 'react';
import { Link, Navigate, useNavigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';

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
  const { user, isLoading, logout } = useAuth();

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
  const avatarPreview = user.avatar_url ?? user.avatar;
  const avatarSource = user.avatar_source ?? (user.avatar ? 'uploaded' : 'generated');

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
                    <Field
                      label="Avatar URL"
                      name="avatar"
                      type="url"
                      value={user.avatar}
                      placeholder="https://..."
                    />
                    <label className="flex items-start gap-3 border border-[#333333] bg-[#080808] p-4">
                      <input
                        name="use_gravatar"
                        type="checkbox"
                        defaultChecked={Boolean(user.is_gravatar ?? user.use_gravatar)}
                        className="mt-1 h-4 w-4 accent-primary"
                      />
                      <span>
                        <span className="text-sm font-semibold text-white">Use Gravatar</span>
                        <span className="mt-1 block text-xs leading-5 text-[#888888]">
                          When enabled, your account email controls the avatar. When disabled, the uploaded or URL avatar
                          is used. If neither exists, AvatarTrait generates initials.
                        </span>
                      </span>
                    </label>
                    <label className="flex items-start gap-3 border border-[#333333] bg-[#080808] p-4">
                      <input name="remove_avatar" type="checkbox" className="mt-1 h-4 w-4 accent-primary" />
                      <span>
                        <span className="text-sm font-semibold text-white">Remove Custom Avatar</span>
                        <span className="mt-1 block text-xs leading-5 text-[#888888]">
                          Clears the saved user avatar and falls back to Gravatar or generated initials.
                        </span>
                      </span>
                    </label>
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
