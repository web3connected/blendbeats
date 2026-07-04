import type { BookingStatus } from '@/lib/bookings';

const statusTone: Record<BookingStatus, string> = {
  pending: 'border-[#FFB800] text-[#FFB800]',
  needs_discussion: 'border-[#38bdf8] text-[#38bdf8]',
  accepted: 'border-[#22c55e] text-[#22c55e]',
  declined: 'border-[#777777] text-[#aaaaaa]',
  cancelled: 'border-primary text-primary',
  completed: 'border-white text-white',
  expired: 'border-[#777777] text-[#888888]',
};

function label(value: string) {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function BookingStatusBadge({ status }: { status: BookingStatus }) {
  return (
    <span
      className={`inline-flex h-8 items-center border px-3 text-[10px] font-bold uppercase tracking-widest ${statusTone[status]}`}
      style={{ fontFamily: 'var(--font-heading)' }}
    >
      {label(status)}
    </span>
  );
}
