import { MapPin } from 'lucide-react';

import { djTypes } from './constants';
import { Field, GenreTagPicker, SelectField } from './FormControls';
import type { DjProfileFormState, UpdateDjProfileField } from './types';

export function SoundStep({ form, updateField }: { form: DjProfileFormState; updateField: UpdateDjProfileField }) {
  return (
    <div className="grid gap-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <GenreTagPicker
          label="Primary Genre"
          selected={form.primaryGenre ? [form.primaryGenre] : []}
          onChange={(genres) => {
            const primaryGenre = genres[0] ?? '';
            updateField('primaryGenre', primaryGenre);
            updateField(
              'secondaryGenres',
              form.secondaryGenres.filter((genre) => genre !== primaryGenre),
            );
          }}
          required
        />
        <GenreTagPicker
          label="Secondary Genres"
          selected={form.secondaryGenres}
          onChange={(genres) => updateField('secondaryGenres', genres)}
          exclude={form.primaryGenre ? [form.primaryGenre] : []}
          multiple
        />
        <SelectField
          label="DJ Type"
          value={form.djType}
          onChange={(value) => updateField('djType', value)}
          options={djTypes}
        />
        <Field label="City" value={form.city} onChange={(value) => updateField('city', value)} placeholder="Atlanta" />
        <Field label="State" value={form.state} onChange={(value) => updateField('state', value)} placeholder="GA" />
        <Field label="Country" value={form.country} onChange={(value) => updateField('country', value)} placeholder="US" />
      </div>
      <div className="flex items-center gap-2 border border-[#333333] bg-[#080808] p-4 text-sm text-[#888888]">
        <MapPin size={16} className="text-primary" />
        Location fields prepare the future search and booking marketplace.
      </div>
    </div>
  );
}
