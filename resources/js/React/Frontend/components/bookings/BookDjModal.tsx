import { CalendarCheck, Loader2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { BookingRequestForm } from '@/components/bookings/BookingRequestForm';
import {
  BookingApiError,
  createBookingRequest,
  getBookingSettings,
  type BookingRecord,
  type BookingRequestPayload,
  type BookingSettings,
} from '@/lib/bookings';

export function BookDjModal({
  handle,
  djName,
  isOpen,
  onClose,
}: {
  handle: string;
  djName: string;
  isOpen: boolean;
  onClose: () => void;
}) {
  const [settings, setSettings] = useState<BookingSettings | null>(null);
  const [booking, setBooking] = useState<BookingRecord | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  useEffect(() => {
    if (!isOpen) return;

    setIsLoading(true);
    setError('');
    setFieldErrors({});
    setBooking(null);

    getBookingSettings(handle)
      .then(setSettings)
      .catch((loadError) => {
        setError(loadError instanceof Error ? loadError.message : 'Booking settings could not be loaded.');
      })
      .finally(() => setIsLoading(false));
  }, [handle, isOpen]);

  if (!isOpen) return null;

  async function submit(payload: BookingRequestPayload) {
    setIsSubmitting(true);
    setError('');
    setFieldErrors({});

    try {
      const createdBooking = await createBookingRequest(handle, payload);
      setBooking(createdBooking);
    } catch (submitError) {
      if (submitError instanceof BookingApiError) {
        setError(submitError.message);
        setFieldErrors(submitError.errors);
      } else {
        setError('Booking request could not be submitted.');
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[80] overflow-y-auto bg-black/80 px-4 py-8">
      <div className="mx-auto max-w-4xl border border-[#333333] bg-[#111111] text-white shadow-2xl shadow-black">
        <div className="flex items-start justify-between gap-4 border-b border-[#2a2a2a] p-5">
          <div>
            <p className="text-[11px] font-bold uppercase tracking-widest text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
              Booking Request
            </p>
            <h2 className="mt-2 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
              Book {djName}
            </h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="flex h-11 w-11 items-center justify-center border border-[#444444] text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
            aria-label="Close booking modal"
          >
            <X size={18} />
          </button>
        </div>

        <div className="p-5">
          {isLoading && (
            <div className="flex min-h-64 items-center justify-center text-[#aaaaaa]">
              <Loader2 size={26} className="animate-spin text-primary" />
            </div>
          )}

          {!isLoading && error && !settings && (
            <div className="border border-primary bg-[#180808] p-5 text-sm text-[#eeeeee]">{error}</div>
          )}

          {!isLoading && settings && booking && (
            <div className="border border-[#2a2a2a] bg-[#080808] p-8 text-center">
              <CalendarCheck size={34} className="mx-auto text-[#22c55e]" />
              <h3 className="mt-4 text-4xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                Request Sent
              </h3>
              <p className="mx-auto mt-3 max-w-xl text-sm leading-6 text-[#aaaaaa]">
                Your booking request for {booking.event_name} has been sent to {djName}.
              </p>
              <button
                type="button"
                onClick={onClose}
                className="mt-6 inline-flex h-11 items-center justify-center border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                Close
              </button>
            </div>
          )}

          {!isLoading && settings && !booking && (
            <>
              {error && <div className="mb-5 border border-primary bg-[#180808] p-4 text-sm text-[#eeeeee]">{error}</div>}
              <BookingRequestForm settings={settings} onSubmit={submit} isSubmitting={isSubmitting} errors={fieldErrors} />
            </>
          )}
        </div>
      </div>
    </div>
  );
}
