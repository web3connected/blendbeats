import type { BookingPaymentStatus } from '@/lib/bookings';

const paymentTone: Record<BookingPaymentStatus, string> = {
  unpaid: 'border-primary text-primary',
  pending_external_payment: 'border-[#FFB800] text-[#FFB800]',
  paid_external: 'border-[#22c55e] text-[#22c55e]',
  refunded_external: 'border-[#38bdf8] text-[#38bdf8]',
  not_required: 'border-[#777777] text-[#aaaaaa]',
};

function label(value: string) {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function BookingPaymentStatusBadge({ status }: { status: BookingPaymentStatus }) {
  return (
    <span
      className={`inline-flex h-8 items-center border px-3 text-[10px] font-bold uppercase tracking-widest ${paymentTone[status]}`}
      style={{ fontFamily: 'var(--font-heading)' }}
    >
      {label(status)}
    </span>
  );
}
