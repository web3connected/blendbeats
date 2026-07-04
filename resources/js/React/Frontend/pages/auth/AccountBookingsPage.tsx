import { Helmet } from '@dr.pogodin/react-helmet';
import { ArrowLeft, CalendarDays, Loader2, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link, Navigate, useLocation, useParams } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import { BookingAnalyticsCards } from '@/components/bookings/BookingAnalyticsCards';
import { BookingCalendar } from '@/components/bookings/BookingCalendar';
import { BookingDetailsPanel } from '@/components/bookings/BookingDetailsPanel';
import { BookingRequestCard } from '@/components/bookings/BookingRequestCard';
import {
  getAccountBooking,
  getAccountBookings,
  updateBookingPaymentStatus,
  updateBookingStatus,
  type BookingAnalytics,
  type BookingCalendarEvent,
  type BookingPaymentStatus,
  type BookingRecord,
  type BookingStatus,
} from '@/lib/bookings';

const emptyAnalytics: BookingAnalytics = {
  total_booking_requests: 0,
  pending_requests: 0,
  accepted_bookings: 0,
  needs_discussion: 0,
  completed_bookings: 0,
  cancelled_bookings: 0,
  estimated_booking_value: 0,
  paid_external: 0,
  unpaid_accepted_bookings: 0,
  upcoming_events: 0,
};

export default function AccountBookingsPage() {
  const { user, isLoading: isAuthLoading } = useAuth();
  const { uuid } = useParams();
  const location = useLocation();
  const isCalendar = location.pathname.endsWith('/calendar');
  const [bookings, setBookings] = useState<BookingRecord[]>([]);
  const [calendarEvents, setCalendarEvents] = useState<BookingCalendarEvent[]>([]);
  const [analytics, setAnalytics] = useState<BookingAnalytics>(emptyAnalytics);
  const [booking, setBooking] = useState<BookingRecord | null>(null);
  const [statusFilter, setStatusFilter] = useState<BookingStatus | 'all'>('all');
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!user) {
      setIsLoading(false);
      return;
    }

    let cancelled = false;
    setIsLoading(true);
    setError('');

    if (uuid && !isCalendar) {
      getAccountBooking(uuid)
        .then((response) => {
          if (!cancelled) setBooking(response);
        })
        .catch((loadError) => {
          if (!cancelled) setError(loadError instanceof Error ? loadError.message : 'Booking could not be loaded.');
        })
        .finally(() => {
          if (!cancelled) setIsLoading(false);
        });

      return () => {
        cancelled = true;
      };
    }

    getAccountBookings({ status: statusFilter })
      .then((response) => {
        if (cancelled) return;

        setBookings(response.bookings);
        setCalendarEvents(response.calendar_events);
        setAnalytics(response.analytics);
      })
      .catch((loadError) => {
        if (!cancelled) setError(loadError instanceof Error ? loadError.message : 'Bookings could not be loaded.');
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [isCalendar, statusFilter, user, uuid]);

  if (isAuthLoading) {
    return (
      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-16 text-white">
        <div className="container mx-auto h-48 max-w-6xl animate-pulse bg-[#111111]" />
      </main>
    );
  }

  if (!user) return <Navigate to="/login" replace />;

  async function runAction(action: 'accept' | 'decline' | 'needs-discussion' | 'cancel' | 'complete', internalNotes?: string) {
    if (!booking) return;

    setIsSaving(true);
    setError('');

    try {
      setBooking(await updateBookingStatus(booking.uuid, action, { internal_notes: internalNotes }));
    } catch (actionError) {
      setError(actionError instanceof Error ? actionError.message : 'Booking could not be updated.');
    } finally {
      setIsSaving(false);
    }
  }

  async function runPayment(status: BookingPaymentStatus, internalNotes?: string) {
    if (!booking) return;

    setIsSaving(true);
    setError('');

    try {
      setBooking(await updateBookingPaymentStatus(booking.uuid, { payment_status: status, internal_notes: internalNotes }));
    } catch (paymentError) {
      setError(paymentError instanceof Error ? paymentError.message : 'Payment status could not be updated.');
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <>
      <Helmet>
        <title>Bookings | The Blend Battlegrounds</title>
        <meta name="description" content="Manage DJ booking requests and calendar events." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] text-white">
        <section className="border-b border-[#1f1f1f] px-4 py-12 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                  Account / Bookings
                </p>
                <h1 className="uppercase leading-none text-white" style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(3.5rem, 8vw, 6rem)' }}>
                  DJ Bookings
                </h1>
                <p className="mt-4 max-w-2xl text-sm leading-6 text-[#aaaaaa]">
                  Review event requests, manage booking status, and track manual external payment.
                </p>
              </div>
              <div className="flex flex-wrap gap-3">
                <Link
                  to="/account/bookings"
                  className="inline-flex h-11 items-center justify-center gap-2 border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <ArrowLeft size={15} />
                  List
                </Link>
                <Link
                  to="/account/bookings/calendar"
                  className="inline-flex h-11 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <CalendarDays size={15} />
                  Calendar
                </Link>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            {isLoading && (
              <div className="flex min-h-64 items-center justify-center border border-[#2a2a2a] bg-[#111111]">
                <Loader2 size={28} className="animate-spin text-primary" />
              </div>
            )}

            {!isLoading && error && (
              <div className="mb-6 border border-primary bg-[#180808] p-4 text-sm text-[#eeeeee]">{error}</div>
            )}

            {!isLoading && uuid && !isCalendar && booking && (
              <BookingDetailsPanel booking={booking} isSaving={isSaving} onAction={runAction} onPaymentStatus={runPayment} />
            )}

            {!isLoading && isCalendar && (
              <div className="grid gap-8">
                <BookingAnalyticsCards analytics={analytics} />
                <BookingCalendar events={calendarEvents} />
              </div>
            )}

            {!isLoading && !uuid && !isCalendar && (
              <div className="grid gap-8">
                <BookingAnalyticsCards analytics={analytics} />

                <div className="flex flex-col gap-3 border border-[#2a2a2a] bg-[#111111] p-4 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <p className="text-[11px] font-bold uppercase tracking-widest text-primary">Requests</p>
                    <h2 className="mt-1 text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      Booking Inbox
                    </h2>
                  </div>
                  <select
                    value={statusFilter}
                    onChange={(event) => setStatusFilter(event.target.value as BookingStatus | 'all')}
                    className="h-11 border border-[#444444] bg-[#050505] px-3 text-sm text-white outline-none focus:border-primary"
                  >
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="needs_discussion">Needs Discussion</option>
                    <option value="accepted">Accepted</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="declined">Declined</option>
                  </select>
                </div>

                {bookings.length === 0 ? (
                  <div className="border border-[#2a2a2a] bg-[#111111] p-10 text-center">
                    <Plus size={30} className="mx-auto text-primary" />
                    <h3 className="mt-4 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                      No Booking Requests
                    </h3>
                    <p className="mx-auto mt-3 max-w-xl text-sm leading-6 text-[#999999]">
                      Booking requests from public DJ profiles will appear here.
                    </p>
                  </div>
                ) : (
                  <div className="grid gap-4">
                    {bookings.map((item) => (
                      <BookingRequestCard key={item.uuid} booking={item} />
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>
        </section>
      </main>
    </>
  );
}
