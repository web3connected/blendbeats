import { Globe, Mail, Shield } from 'lucide-react';

import { visibilityOptions } from './constants';
import { Field, SelectField } from './FormControls';
import type { DjProfileFormState, UpdateDjProfileField } from './types';

export function BookingStep({
  form,
  updateField,
  defaultBookingEmail,
}: {
  form: DjProfileFormState;
  updateField: UpdateDjProfileField;
  defaultBookingEmail: string;
}) {
  return (
    <div className="grid gap-5">
      <label className="flex items-start gap-3 border border-[#333333] bg-[#080808] p-4">
        <input
          type="checkbox"
          checked={form.availableForBookings}
          onChange={(event) => updateField('availableForBookings', event.target.checked)}
          className="mt-1 h-4 w-4 accent-primary"
        />
        <span>
          <span className="flex items-center gap-2 text-sm font-semibold text-white">
            <Mail size={15} className="text-primary" />
            Available For Bookings
          </span>
          <span className="mt-1 block text-xs leading-5 text-[#888888]">
            Promoters and event organizers can request booking info.
          </span>
        </span>
      </label>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field
          label="Booking Email"
          value={form.bookingEmail}
          onChange={(value) => updateField('bookingEmail', value)}
          placeholder={defaultBookingEmail}
          type="email"
        />
        <SelectField
          label="Profile Visibility"
          value={form.visibility}
          onChange={(value) => updateField('visibility', value)}
          options={visibilityOptions}
        />
      </div>
      <div className="grid gap-3 border border-[#333333] bg-[#080808] p-4 text-sm text-[#888888]">
        <div className="flex items-center gap-2">
          <Shield size={16} className="text-primary" />
          <span>Future-ready defaults: draft status, unverified profile, booking and battle flags.</span>
        </div>
        <div className="flex items-center gap-2">
          <Globe size={16} className="text-primary" />
          <span>Public profiles can power search, rankings, mentions, and marketplace tools.</span>
        </div>
      </div>
    </div>
  );
}
