import { ArrowRight, CalendarClock, MapPin, Users } from 'lucide-react';
import { Link } from 'react-router-dom';

import { BookingPaymentStatusBadge } from '@/components/bookings/BookingPaymentStatusBadge';
import { BookingStatusBadge } from '@/components/bookings/BookingStatusBadge';
import type { BookingRecord } from '@/lib/bookings';

function formatDate(value: string | null) {
  if (!value) return 'Date pending';

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

export function BookingRequestCard({ booking }: { booking: BookingRecord }) {
  return (
    <article className="border border-[#2a2a2a] bg-[#111111] p-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap gap-2">
            <BookingStatusBadge status={booking.status} />
            <BookingPaymentStatusBadge status={booking.payment_status} />
          </div>
          <h3 className="mt-4 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
            {booking.event_name}
          </h3>
          <p className="mt-1 text-sm text-[#999999]">{booking.event_type} / {booking.contact_name}</p>
        </div>
        <Link
          to={`/account/bookings/${booking.uuid}`}
          className="inline-flex h-11 items-center justify-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
          style={{ fontFamily: 'var(--font-heading)' }}
        >
          Open
          <ArrowRight size={15} />
        </Link>
      </div>

      <div className="mt-5 grid gap-3 text-sm text-[#bbbbbb] md:grid-cols-4">
        <span className="inline-flex items-center gap-2">
          <CalendarClock size={15} className="text-primary" />
          {formatDate(booking.event_date)} / {booking.start_time} - {booking.end_time}
        </span>
        <span className="inline-flex items-center gap-2">
          <MapPin size={15} className="text-primary" />
          {booking.location_name || booking.city || 'Location pending'}
        </span>
        <span className="inline-flex items-center gap-2">
          <Users size={15} className="text-primary" />
          {booking.expected_crowd_size ? `${booking.expected_crowd_size.toLocaleString()} expected` : 'Crowd size pending'}
        </span>
        <span className="font-semibold text-white">
          {formatMoney(booking.estimated_total_amount, booking.currency)}
        </span>
      </div>
    </article>
  );
}
