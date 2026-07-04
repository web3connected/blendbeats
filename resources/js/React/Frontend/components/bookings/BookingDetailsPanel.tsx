import { CalendarCheck, CheckCircle2, Clock, MessageSquare, WalletCards, XCircle } from 'lucide-react';
import { useState } from 'react';

import { BookingPaymentStatusBadge } from '@/components/bookings/BookingPaymentStatusBadge';
import { BookingStatusBadge } from '@/components/bookings/BookingStatusBadge';
import type { BookingPaymentStatus, BookingRecord } from '@/lib/bookings';

function formatDate(value: string | null) {
  if (!value) return 'Not set';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(`${value}T00:00:00`));
}

function formatMoney(value: number | null, currency: string | null) {
  if (value === null) return 'Not estimated';

  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: currency || 'USD',
  }).format(value);
}

function DetailRow({ label, value }: { label: string; value: string | number | null | undefined }) {
  return (
    <div className="border border-[#2a2a2a] bg-[#080808] p-4">
      <p className="text-[10px] font-bold uppercase tracking-widest text-[#777777]">{label}</p>
      <p className="mt-2 text-sm text-white">{value || 'Not listed'}</p>
    </div>
  );
}

export function BookingDetailsPanel({
  booking,
  isSaving,
  onAction,
  onPaymentStatus,
}: {
  booking: BookingRecord;
  isSaving: boolean;
  onAction: (action: 'accept' | 'decline' | 'needs-discussion' | 'cancel' | 'complete', internalNotes?: string) => Promise<void>;
  onPaymentStatus: (status: BookingPaymentStatus, internalNotes?: string) => Promise<void>;
}) {
  const [internalNotes, setInternalNotes] = useState(booking.internal_notes ?? '');
  const canAccept = booking.status === 'pending' || booking.status === 'needs_discussion';
  const canDiscuss = booking.status === 'pending' || booking.status === 'accepted';
  const canCancel = booking.status === 'pending' || booking.status === 'needs_discussion' || booking.status === 'accepted';
  const canComplete = booking.status === 'accepted';
  const canMarkPayment = !['declined', 'cancelled', 'expired'].includes(booking.status);

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
      <section className="grid gap-5">
        <div className="border border-[#2a2a2a] bg-[#111111] p-5">
          <div className="flex flex-wrap gap-2">
            <BookingStatusBadge status={booking.status} />
            <BookingPaymentStatusBadge status={booking.payment_status} />
          </div>
          <h1 className="mt-4 text-5xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {booking.event_name}
          </h1>
          <p className="mt-2 text-sm text-[#999999]">{booking.event_type}</p>
        </div>

        <div className="grid gap-3 md:grid-cols-2">
          <DetailRow label="Event Date" value={formatDate(booking.event_date)} />
          <DetailRow label="Schedule" value={`${booking.start_time ?? 'TBD'} - ${booking.end_time ?? 'TBD'} ${booking.timezone ?? ''}`.trim()} />
          <DetailRow label="Location" value={booking.location_name || booking.city} />
          <DetailRow label="Address" value={[booking.location_address, booking.city, booking.state, booking.postal_code, booking.country].filter(Boolean).join(', ')} />
          <DetailRow label="Crowd Size" value={booking.expected_crowd_size?.toLocaleString()} />
          <DetailRow label="Music Style" value={booking.music_style} />
          <DetailRow label="Estimated Hours" value={booking.estimated_hours} />
          <DetailRow label="Estimated Value" value={formatMoney(booking.estimated_total_amount, booking.currency)} />
        </div>

        <div className="border border-[#2a2a2a] bg-[#111111] p-5">
          <p className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Requested Services</p>
          {booking.requested_services.length > 0 ? (
            <div className="mt-3 flex flex-wrap gap-2">
              {booking.requested_services.map((service) => (
                <span key={service} className="border border-[#333333] bg-[#080808] px-3 py-2 text-xs text-[#dddddd]">
                  {service}
                </span>
              ))}
            </div>
          ) : (
            <p className="mt-3 text-sm text-[#888888]">No services selected.</p>
          )}
        </div>

        <div className="border border-[#2a2a2a] bg-[#111111] p-5">
          <p className="text-[11px] font-bold uppercase tracking-widest text-[#888888]">Message</p>
          <p className="mt-3 whitespace-pre-line text-sm leading-7 text-[#cccccc]">{booking.message || 'No message included.'}</p>
        </div>
      </section>

      <aside className="grid gap-5 self-start">
        <section className="border border-[#2a2a2a] bg-[#111111] p-5">
          <p className="text-[11px] font-bold uppercase tracking-widest text-primary">Customer Contact</p>
          <div className="mt-4 grid gap-3">
            <DetailRow label="Name" value={booking.contact_name} />
            <DetailRow label="Email" value={booking.contact_email} />
            <DetailRow label="Phone" value={booking.contact_phone} />
          </div>
        </section>

        <section className="border border-[#2a2a2a] bg-[#111111] p-5">
          <p className="text-[11px] font-bold uppercase tracking-widest text-primary">DJ Notes</p>
          <textarea
            value={internalNotes}
            onChange={(event) => setInternalNotes(event.target.value)}
            className="mt-3 min-h-32 w-full border border-[#333333] bg-[#050505] px-3 py-3 text-sm text-white outline-none focus:border-primary"
            maxLength={3000}
          />
        </section>

        <section className="border border-[#2a2a2a] bg-[#111111] p-5">
          <p className="text-[11px] font-bold uppercase tracking-widest text-primary">Actions</p>
          <div className="mt-4 grid gap-2">
            <button
              type="button"
              disabled={!canAccept || isSaving}
              onClick={() => onAction('accept', internalNotes)}
              className="inline-flex h-11 items-center justify-center gap-2 bg-[#22c55e] px-4 text-xs font-bold uppercase tracking-widest text-white disabled:cursor-not-allowed disabled:opacity-40"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <CheckCircle2 size={15} />
              Accept
            </button>
            <button
              type="button"
              disabled={!canDiscuss || isSaving}
              onClick={() => onAction('needs-discussion', internalNotes)}
              className="inline-flex h-11 items-center justify-center gap-2 border border-[#38bdf8] px-4 text-xs font-bold uppercase tracking-widest text-[#38bdf8] disabled:cursor-not-allowed disabled:opacity-40"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <MessageSquare size={15} />
              Needs Discussion
            </button>
            <button
              type="button"
              disabled={!canCancel || isSaving}
              onClick={() => onAction('cancel', internalNotes)}
              className="inline-flex h-11 items-center justify-center gap-2 border border-primary px-4 text-xs font-bold uppercase tracking-widest text-primary disabled:cursor-not-allowed disabled:opacity-40"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <XCircle size={15} />
              Cancel
            </button>
            <button
              type="button"
              disabled={!canComplete || isSaving}
              onClick={() => onAction('complete', internalNotes)}
              className="inline-flex h-11 items-center justify-center gap-2 border border-white px-4 text-xs font-bold uppercase tracking-widest text-white disabled:cursor-not-allowed disabled:opacity-40"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <CalendarCheck size={15} />
              Complete
            </button>
            <button
              type="button"
              disabled={!canAccept || isSaving}
              onClick={() => onAction('decline', internalNotes)}
              className="inline-flex h-11 items-center justify-center gap-2 border border-[#555555] px-4 text-xs font-bold uppercase tracking-widest text-[#bbbbbb] disabled:cursor-not-allowed disabled:opacity-40"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <Clock size={15} />
              Decline
            </button>
          </div>
        </section>

        <section className="border border-[#2a2a2a] bg-[#111111] p-5">
          <p className="text-[11px] font-bold uppercase tracking-widest text-primary">External Payment</p>
          <div className="mt-4 grid gap-2">
            {[
              ['paid_external', 'Mark Paid'],
              ['pending_external_payment', 'Pending Payment'],
              ['not_required', 'Not Required'],
              ['refunded_external', 'Refunded'],
              ['unpaid', 'Unpaid'],
            ].map(([status, label]) => (
              <button
                key={status}
                type="button"
                disabled={!canMarkPayment || isSaving}
                onClick={() => onPaymentStatus(status as BookingPaymentStatus, internalNotes)}
                className="inline-flex h-10 items-center justify-center gap-2 border border-[#444444] px-3 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                <WalletCards size={14} />
                {label}
              </button>
            ))}
          </div>
        </section>
      </aside>
    </div>
  );
}
