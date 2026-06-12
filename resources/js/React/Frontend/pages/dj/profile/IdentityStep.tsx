import { Image, RotateCcw, Upload, UserRound } from 'lucide-react';
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
        : user.custom_avatar_url || user.avatar
          ? 'uploaded'
          : 'uploaded'
      : 'generated';
  const hasSavedCustomAvatar = Boolean(user.custom_avatar_url || user.avatar);
  const canResetAvatar = Boolean(selectedUploadPreviewUrl || hasSavedCustomAvatar || avatarSettings.useGravatar);

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
            <input name="is_gravatar" type="hidden" value={avatarSettings.useGravatar ? '1' : '0'} />
            <input name="remove_avatar" type="hidden" value={avatarSettings.removeCustomAvatar ? '1' : '0'} />

            <div className="grid gap-4">
              <div className="border border-[#333333] bg-[#080808] p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <p className="text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">User Avatar</p>
                    <p className="mt-2 text-sm leading-6 text-[#888888]">
                      This is the single avatar used for your account, DJ profile, battles, and lounge activity.
                    </p>
                  </div>
                  <span className="w-fit border border-[#333333] px-3 py-2 text-[11px] font-bold uppercase tracking-widest text-[#aaaaaa]">
                    {avatarSource}
                  </span>
                </div>
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
                    updateAvatarField('mode', 'custom');
                    updateAvatarField('useGravatar', false);
                    updateAvatarField('avatarFile', event.target.files?.[0] ?? null);
                    updateAvatarField('avatarUrl', '');
                    updateAvatarField('removeCustomAvatar', false);
                  }}
                  className="w-full border border-[#333333] bg-[#050505] px-4 py-3 text-sm text-[#bbbbbb] file:mr-4 file:border-0 file:bg-primary file:px-4 file:py-2 file:text-xs file:font-bold file:uppercase file:tracking-widest file:text-white"
                  style={{ fontFamily: 'var(--font-heading)' }}
                />
                <span className="text-xs leading-5 text-[#888888]">
                  Uploading a file sets the avatar for your user account.
                </span>
              </label>

              <div className="grid gap-3 sm:grid-cols-2">
                <button
                  type="button"
                  onClick={() => {
                    updateAvatarField('mode', 'gravatar');
                    updateAvatarField('useGravatar', true);
                    updateAvatarField('avatarFile', null);
                    updateAvatarField('avatarUrl', '');
                    updateAvatarField('removeCustomAvatar', false);
                  }}
                  className={`flex min-h-28 flex-col items-start justify-between border p-4 text-left transition-colors ${
                    avatarSettings.useGravatar
                      ? 'border-primary bg-primary/10'
                      : 'border-[#333333] bg-[#080808] hover:border-primary'
                  }`}
                >
                  <span className="text-[11px] font-bold uppercase tracking-widest text-primary">Use Gravatar</span>
                  <span className="mt-3 break-all text-sm leading-6 text-[#aaaaaa]">{user.email}</span>
                </button>

                <button
                  type="button"
                  disabled={!canResetAvatar}
                  onClick={() => {
                    updateAvatarField('mode', 'custom');
                    updateAvatarField('useGravatar', false);
                    updateAvatarField('avatarFile', null);
                    updateAvatarField('avatarUrl', '');
                    updateAvatarField('removeCustomAvatar', true);
                  }}
                  className="flex min-h-28 flex-col items-start justify-between border border-[#333333] bg-[#080808] p-4 text-left transition-colors hover:border-primary disabled:cursor-not-allowed disabled:opacity-45"
                >
                  <span className="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-[#bbbbbb]">
                    <RotateCcw size={15} className="text-primary" />
                    Use Generated Initials
                  </span>
                  <span className="mt-3 text-sm leading-6 text-[#888888]">
                    Fall back to the initials avatar created from your display name.
                  </span>
                </button>
              </div>
            </div>
          </section>
        </div>
      )}
    </div>
  );
}
