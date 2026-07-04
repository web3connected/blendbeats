import { CalendarClock, CheckCircle2, DollarSign, MessageSquare, WalletCards, XCircle } from 'lucide-react';

import type { BookingAnalytics } from '@/lib/bookings';

function formatMoney(value: number) {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
  }).format(value);
}

export function BookingAnalyticsCards({ analytics }: { analytics: BookingAnalytics }) {
  const cards = [
    { label: 'Total Requests', value: analytics.total_booking_requests.toLocaleString(), icon: CalendarClock, tone: 'text-primary' },
    { label: 'Pending', value: analytics.pending_requests.toLocaleString(), icon: MessageSquare, tone: 'text-[#FFB800]' },
    { label: 'Accepted', value: analytics.accepted_bookings.toLocaleString(), icon: CheckCircle2, tone: 'text-[#22c55e]' },
    { label: 'Completed', value: analytics.completed_bookings.toLocaleString(), icon: CheckCircle2, tone: 'text-white' },
    { label: 'Cancelled', value: analytics.cancelled_bookings.toLocaleString(), icon: XCircle, tone: 'text-primary' },
    { label: 'Estimated Value', value: formatMoney(analytics.estimated_booking_value), icon: DollarSign, tone: 'text-[#22c55e]' },
    { label: 'Paid External', value: formatMoney(analytics.paid_external), icon: WalletCards, tone: 'text-[#38bdf8]' },
    { label: 'Upcoming Events', value: analytics.upcoming_events.toLocaleString(), icon: CalendarClock, tone: 'text-[#FFB800]' },
  ];

  return (
    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      {cards.map((card) => {
        const Icon = card.icon;

        return (
          <div key={card.label} className="border border-[#2a2a2a] bg-[#111111] p-5">
            <Icon size={20} className={card.tone} />
            <p className="mt-4 text-[10px] font-bold uppercase tracking-widest text-[#777777]">{card.label}</p>
            <p className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              {card.value}
            </p>
          </div>
        );
      })}
    </div>
  );
}
