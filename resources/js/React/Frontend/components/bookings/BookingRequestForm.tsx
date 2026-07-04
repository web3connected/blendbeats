import { Loader2, Send } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

import type { BookingRequestPayload, BookingSettings } from '@/lib/bookings';

type FormState = BookingRequestPayload;

const baseInputClass = 'h-11 w-full border border-[#333333] bg-[#050505] px-3 text-sm text-white outline-none focus:border-primary';
const baseTextareaClass = 'min-h-28 w-full border border-[#333333] bg-[#050505] px-3 py-3 text-sm text-white outline-none focus:border-primary';

function todayString() {
  return new Date().toISOString().slice(0, 10);
}

export function BookingRequestForm({
  settings,
  onSubmit,
  isSubmitting,
  errors = {},
}: {
  settings: BookingSettings;
  onSubmit: (payload: BookingRequestPayload) => Promise<void> | void;
  isSubmitting: boolean;
  errors?: Record<string, string[]>;
}) {
  const [form, setForm] = useState<FormState>({
    event_name: '',
    event_type: settings.event_types[0] ?? 'Private Party',
    event_date: todayString(),
    start_time: '20:00',
    end_time: '23:00',
    timezone: settings.timezone,
    location_name: '',
    location_address: '',
    city: '',
    state: '',
    postal_code: '',
    country: 'US',
    expected_crowd_size: '',
    music_style: '',
    requested_services: ['DJ Set'],
    message: '',
    contact_name: '',
    contact_email: '',
    contact_phone: '',
    booking_website: '',
  });

  const estimatedRate = useMemo(() => {
    if (!settings.hourly_rate_amount) return null;

    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: settings.currency || 'USD',
    }).format(settings.hourly_rate_amount);
  }, [settings.currency, settings.hourly_rate_amount]);

  function update<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function toggleService(service: string) {
    setForm((current) => {
      const services = current.requested_services ?? [];
      const nextServices = services.includes(service)
        ? services.filter((item) => item !== service)
        : [...services, service];

      return { ...current, requested_services: nextServices };
    });
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();

    const payload: BookingRequestPayload = {
      ...form,
      expected_crowd_size: form.expected_crowd_size === '' ? undefined : Number(form.expected_crowd_size),
    };

    if (!payload.booking_website) {
      delete payload.booking_website;
    }

    await onSubmit(payload);
  }

  function fieldError(field: string) {
    const message = errors[field]?.[0];
    if (!message) return null;

    return <p className="mt-2 text-xs text-primary">{message}</p>;
  }

  return (
    <form onSubmit={handleSubmit} className="grid gap-5">
      <input
        type="text"
        tabIndex={-1}
        autoComplete="off"
        value={form.booking_website}
        onChange={(event) => update('booking_website', event.target.value)}
        className="hidden"
        aria-hidden="true"
      />

      <div className="grid gap-4 md:grid-cols-2">
        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Event Name</span>
          <input className={baseInputClass} value={form.event_name} onChange={(event) => update('event_name', event.target.value)} required />
          {fieldError('event_name')}
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Event Type</span>
          <select className={baseInputClass} value={form.event_type} onChange={(event) => update('event_type', event.target.value)} required>
            {settings.event_types.map((type) => (
              <option key={type} value={type}>{type}</option>
            ))}
          </select>
          {fieldError('event_type')}
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Event Date</span>
          <input className={baseInputClass} type="date" value={form.event_date} onChange={(event) => update('event_date', event.target.value)} required />
          {fieldError('event_date')}
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Timezone</span>
          <input className={baseInputClass} value={form.timezone ?? ''} onChange={(event) => update('timezone', event.target.value)} />
          {fieldError('timezone')}
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Start Time</span>
          <input className={baseInputClass} type="time" value={form.start_time} onChange={(event) => update('start_time', event.target.value)} required />
          {fieldError('start_time')}
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">End Time</span>
          <input className={baseInputClass} type="time" value={form.end_time} onChange={(event) => update('end_time', event.target.value)} required />
          {fieldError('end_time')}
        </label>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Location Name</span>
          <input className={baseInputClass} value={form.location_name ?? ''} onChange={(event) => update('location_name', event.target.value)} />
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Address</span>
          <input className={baseInputClass} value={form.location_address ?? ''} onChange={(event) => update('location_address', event.target.value)} />
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">City</span>
          <input className={baseInputClass} value={form.city ?? ''} onChange={(event) => update('city', event.target.value)} />
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">State</span>
          <input className={baseInputClass} value={form.state ?? ''} onChange={(event) => update('state', event.target.value)} />
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Postal Code</span>
          <input className={baseInputClass} value={form.postal_code ?? ''} onChange={(event) => update('postal_code', event.target.value)} />
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Country</span>
          <input className={baseInputClass} value={form.country ?? ''} onChange={(event) => update('country', event.target.value)} />
        </label>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Crowd Size</span>
          <input
            className={baseInputClass}
            type="number"
            min={1}
            value={form.expected_crowd_size}
            onChange={(event) => update('expected_crowd_size', event.target.value === '' ? '' : Number(event.target.value))}
          />
          {fieldError('expected_crowd_size')}
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Music Style</span>
          <input className={baseInputClass} value={form.music_style ?? ''} onChange={(event) => update('music_style', event.target.value)} />
        </label>
      </div>

      <div className="grid gap-3">
        <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Requested Services</span>
        <div className="grid gap-2 sm:grid-cols-2">
          {settings.requested_services.map((service) => (
            <label key={service} className="flex items-center gap-3 border border-[#333333] bg-[#080808] px-3 py-3 text-sm text-[#dddddd]">
              <input
                type="checkbox"
                checked={(form.requested_services ?? []).includes(service)}
                onChange={() => toggleService(service)}
                className="h-4 w-4 accent-primary"
              />
              {service}
            </label>
          ))}
        </div>
      </div>

      <label className="grid gap-2">
        <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Message</span>
        <textarea className={baseTextareaClass} value={form.message ?? ''} onChange={(event) => update('message', event.target.value)} maxLength={3000} />
      </label>

      <div className="grid gap-4 md:grid-cols-3">
        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Contact Name</span>
          <input className={baseInputClass} value={form.contact_name} onChange={(event) => update('contact_name', event.target.value)} required />
          {fieldError('contact_name')}
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Contact Email</span>
          <input className={baseInputClass} type="email" value={form.contact_email} onChange={(event) => update('contact_email', event.target.value)} required />
          {fieldError('contact_email')}
        </label>

        <label className="grid gap-2">
          <span className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Contact Phone</span>
          <input className={baseInputClass} value={form.contact_phone ?? ''} onChange={(event) => update('contact_phone', event.target.value)} />
        </label>
      </div>

      <div className="flex flex-col gap-3 border-t border-[#242424] pt-5 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-sm text-[#999999]">
          {estimatedRate ? `${estimatedRate} / hour listed rate. Payment is arranged outside BlendBeat.` : 'Payment is arranged outside BlendBeat.'}
        </p>
        <button
          type="submit"
          disabled={isSubmitting}
          className="inline-flex h-12 items-center justify-center gap-2 bg-primary px-5 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:cursor-wait disabled:opacity-70"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          {isSubmitting ? <Loader2 size={15} className="animate-spin" /> : <Send size={15} />}
          Send Request
        </button>
      </div>
    </form>
  );
}
