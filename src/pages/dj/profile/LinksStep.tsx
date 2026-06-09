import { Field } from './FormControls';
import type { DjProfileFormState, UpdateDjProfileField } from './types';

export function LinksStep({ form, updateField }: { form: DjProfileFormState; updateField: UpdateDjProfileField }) {
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <Field label="Website" value={form.website} onChange={(value) => updateField('website', value)} type="url" />
      <Field label="Instagram" value={form.instagram} onChange={(value) => updateField('instagram', value)} type="url" />
      <Field label="TikTok" value={form.tiktok} onChange={(value) => updateField('tiktok', value)} type="url" />
      <Field label="YouTube" value={form.youtube} onChange={(value) => updateField('youtube', value)} type="url" />
      <Field label="SoundCloud" value={form.soundcloud} onChange={(value) => updateField('soundcloud', value)} type="url" />
      <Field label="Mixcloud" value={form.mixcloud} onChange={(value) => updateField('mixcloud', value)} type="url" />
      <Field label="Twitch" value={form.twitch} onChange={(value) => updateField('twitch', value)} type="url" />
      <Field label="Spotify" value={form.spotify} onChange={(value) => updateField('spotify', value)} type="url" />
    </div>
  );
}
