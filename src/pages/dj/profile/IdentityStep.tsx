import { Image, UserRound } from 'lucide-react';
import { useEffect, useState } from 'react';

import type { AuthUser } from '@/lib/auth';

import { Field, TextAreaField } from './FormControls';
import type {
  AccountAvatarFormState,
  DjProfileFormState,
  UpdateAccountAvatarField,
  UpdateDjProfileField,
} from './types';

function formatHandleInput(handle: string) {
  return handle
    .replace(/^@+/, '')
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9_-]/g, '');
}

export function IdentityStep({
  form,
  updateField,
  identityTab,
  setIdentityTab,
  user,
  avatarSettings,
  updateAvatarField,
}: {
  form: DjProfileFormState;
  updateField: UpdateDjProfileField;
  identityTab: 'profile' | 'avatar';
  setIdentityTab: (tab: 'profile' | 'avatar') => void;
  user: AuthUser;
  avatarSettings: AccountAvatarFormState;
  updateAvatarField: UpdateAccountAvatarField;
}) {
  const [selectedUploadPreviewUrl, setSelectedUploadPreviewUrl] = useState<string | null>(null);
  const avatarInitial = user.name.charAt(0);
  const customAvatarUrl = avatarSettings.removeCustomAvatar
    ? null
    : selectedUploadPreviewUrl || avatarSettings.avatarUrl.trim() || user.custom_avatar_url || user.avatar;
  const generatedAvatarUrl = user.generated_avatar_url;
  const avatarPreview = avatarSettings.useGravatar
    ? user.gravatar_url || user.avatar_url
    : customAvatarUrl || generatedAvatarUrl;
  const avatarSource = avatarSettings.useGravatar
    ? 'gravatar'
    : customAvatarUrl
      ? selectedUploadPreviewUrl
        ? 'selected upload'
        : avatarSettings.avatarUrl.trim() || user.custom_avatar_url || user.avatar
          ? 'custom'
          : 'uploaded'
      : 'generated';

  useEffect(() => {
    if (!avatarSettings.avatarFile) {
      setSelectedUploadPreviewUrl(null);
      return;
    }

    const objectUrl = URL.createObjectURL(avatarSettings.avatarFile);
    setSelectedUploadPreviewUrl(objectUrl);

    return () => URL.revokeObjectURL(objectUrl);
  }, [avatarSettings.avatarFile]);

  return (
    <div className="grid gap-5">
      <div className="grid grid-cols-2 border border-[#333333] bg-[#080808] p-1 sm:max-w-sm">
        <button
          type="button"
          onClick={() => setIdentityTab('profile')}
          className={`inline-flex h-10 items-center justify-center gap-2 text-xs font-bold uppercase tracking-widest transition-colors ${
            identityTab === 'profile' ? 'bg-primary text-white' : 'text-[#aaaaaa] hover:text-primary'
          }`}
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          <UserRound size={15} />
          DJ Profile
        </button>
        <button
          type="button"
          onClick={() => setIdentityTab('avatar')}
          className={`inline-flex h-10 items-center justify-center gap-2 text-xs font-bold uppercase tracking-widest transition-colors ${
            identityTab === 'avatar' ? 'bg-primary text-white' : 'text-[#aaaaaa] hover:text-primary'
          }`}
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          <Image size={15} />
          Avatar
        </button>
      </div>

      {identityTab === 'profile' ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field
              label="DJ Name"
              value={form.djName}
              onChange={(value) => updateField('djName', value)}
              placeholder="DJ SiliconOne"
              required
            />
            <Field
              label="Handle"
              value={form.handle}
              onChange={(value) => updateField('handle', formatHandleInput(value))}
              placeholder="siliconone"
              required
            />
          </div>
          <Field
            label="Profile Headline"
            value={form.profileHeadline}
            onChange={(value) => updateField('profileHeadline', value)}
            placeholder="Open format DJ built for battles and clean blends"
          />
          <TextAreaField
            label="Bio"
            value={form.bio}
            onChange={(value) => updateField('bio', value)}
            placeholder="Tell fans, promoters, and other DJs what sound you bring."
            required
          />
          <Field
            label="Banner URL"
            value={form.bannerUrl}
            onChange={(value) => updateField('bannerUrl', value)}
            placeholder="https://..."
            type="url"
          />
        </>
      ) : (
        <div className="grid gap-5 lg:grid-cols-[minmax(220px,0.72fr)_minmax(0,1.28fr)]">
          <section className="border border-[#333333] bg-[#080808] p-4">
            <div className="aspect-square w-full max-w-64 border border-[#333333] bg-[#111111]">
              {avatarPreview ? (
                <img src={avatarPreview} alt={user.name} className="h-full w-full object-cover" />
              ) : (
                <div className="flex h-full w-full items-center justify-center bg-primary text-6xl font-black uppercase text-white">
                  {avatarInitial}
                </div>
              )}
            </div>
            <div className="mt-4 grid gap-2">
              <p className="text-[11px] font-bold uppercase tracking-widest text-[#777777]">Preview</p>
              <p className="text-xl font-semibold text-white">{user.name}</p>
              <p className="break-all text-sm text-[#888888]">{user.email}</p>
              <p className="text-[11px] uppercase tracking-widest text-[#777777]">Source: {avatarSource}</p>
            </div>
          </section>

          <section className="grid gap-4">
            <div className="grid grid-cols-2 border border-[#333333] bg-[#080808] p-1">
              <button
                type="button"
                onClick={() => {
                  updateAvatarField('mode', 'custom');
                  updateAvatarField('useGravatar', false);
                }}
                className={`h-10 text-xs font-bold uppercase tracking-widest transition-colors ${
                  avatarSettings.mode === 'custom' ? 'bg-primary text-white' : 'text-[#aaaaaa] hover:text-primary'
                }`}
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Custom Avatar
              </button>
              <button
                type="button"
                onClick={() => updateAvatarField('mode', 'gravatar')}
                className={`h-10 text-xs font-bold uppercase tracking-widest transition-colors ${
                  avatarSettings.mode === 'gravatar' ? 'bg-primary text-white' : 'text-[#aaaaaa] hover:text-primary'
                }`}
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Gravatar
              </button>
            </div>

            <input name="is_gravatar" type="hidden" value={avatarSettings.useGravatar ? '1' : '0'} />
            <input name="remove_avatar" type="hidden" value={avatarSettings.removeCustomAvatar ? '1' : '0'} />

            {avatarSettings.mode === 'custom' ? (
              <div className="grid gap-4">
                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">Upload Avatar</span>
                  <input
                    name="avatar_upload"
                    type="file"
                    accept="image/*"
                    onChange={(event) => {
                      updateAvatarField('avatarFile', event.target.files?.[0] ?? null);
                      updateAvatarField('removeCustomAvatar', false);
                    }}
                    className="w-full border border-[#333333] bg-[#080808] px-4 py-3 text-sm text-[#bbbbbb] file:mr-4 file:border-0 file:bg-primary file:px-4 file:py-2 file:text-xs file:font-bold file:uppercase file:tracking-widest file:text-white"
                    style={{ fontFamily: 'var(--font-heading)' }}
                  />
                </label>

                <label className="grid gap-2">
                  <span className="text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">Avatar URL</span>
                  <input
                    name="avatar"
                    type="url"
                    value={avatarSettings.avatarUrl}
                    onChange={(event) => {
                      updateAvatarField('avatarUrl', event.target.value);
                      updateAvatarField('removeCustomAvatar', false);
                    }}
                    placeholder="https://..."
                    className="h-12 w-full border border-[#333333] bg-[#080808] px-4 text-sm text-white outline-none transition-colors placeholder:text-[#555555] focus:border-primary"
                  />
                </label>

                <label className="flex items-start gap-3 border border-[#333333] bg-[#080808] p-4">
                  <input
                    type="checkbox"
                    checked={avatarSettings.removeCustomAvatar}
                    onChange={(event) => updateAvatarField('removeCustomAvatar', event.target.checked)}
                    className="mt-1 h-4 w-4 accent-primary"
                  />
                  <span>
                    <span className="text-sm font-semibold text-white">Remove Custom Avatar</span>
                    <span className="mt-1 block text-xs leading-5 text-[#888888]">
                      Clears the saved uploaded or URL avatar.
                    </span>
                  </span>
                </label>
              </div>
            ) : (
              <div className="grid gap-4">
                <div className="flex items-start justify-between gap-4 border border-[#333333] bg-[#080808] p-4">
                  <span>
                    <span className="block text-sm font-semibold text-white">Enable Gravatar</span>
                    <span className="mt-1 block text-xs leading-5 text-[#888888]">
                      Turn this on to use the Gravatar connected to your account email.
                    </span>
                  </span>
                  <button
                    type="button"
                    role="switch"
                    aria-checked={avatarSettings.useGravatar}
                    onClick={() => updateAvatarField('useGravatar', !avatarSettings.useGravatar)}
                    className={`relative h-7 w-12 shrink-0 border transition-colors ${
                      avatarSettings.useGravatar
                        ? 'border-primary bg-primary'
                        : 'border-[#444444] bg-[#151515]'
                    }`}
                    aria-label="Enable Gravatar"
                  >
                    <span
                      className={`absolute top-1 h-5 w-5 bg-white transition-transform ${
                        avatarSettings.useGravatar ? 'translate-x-5' : 'translate-x-1'
                      }`}
                    />
                  </button>
                </div>

                <div className="border border-[#333333] bg-[#080808] p-4">
                  <p className="text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">Account Email</p>
                  <p className="mt-2 break-all text-lg font-semibold text-white">{user.email}</p>
                  <p className="mt-2 text-xs leading-5 text-[#888888]">
                    This is the email Gravatar checks. To use a different Gravatar, update the account email first.
                  </p>
                </div>
                <div
                  className={`border p-4 text-sm leading-6 ${
                    avatarSettings.useGravatar
                      ? 'border-primary/40 bg-primary/10 text-[#dddddd]'
                      : 'border-[#333333] bg-[#080808] text-[#888888]'
                  }`}
                >
                  {avatarSettings.useGravatar
                    ? 'Gravatar is enabled for this save. Custom upload and URL fields are ignored while this mode is selected.'
                    : 'Gravatar is disabled. The account will use a custom avatar or generated initials.'}
                </div>
              </div>
            )}
          </section>
        </div>
      )}
    </div>
  );
}
