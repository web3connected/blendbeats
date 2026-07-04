import apiClient from '@/lib/api-client';

export type BookingStatus = 'pending' | 'needs_discussion' | 'accepted' | 'declined' | 'cancelled' | 'completed' | 'expired';

export type BookingPaymentStatus =
  | 'unpaid'
  | 'pending_external_payment'
  | 'paid_external'
  | 'refunded_external'
  | 'not_required';

export type BookingSettings = {
  dj_profile_id: number;
  dj_name: string;
  handle: string;
  booking_enabled: boolean;
  available_for_bookings: boolean;
  rate_type: string | null;
  hourly_rate_amount: number | null;
  currency: string;
  timezone: string;
  min_notice_hours: number;
  max_advance_days: number;
  event_types: string[];
  requested_services: string[];
};

export type BookingRequestPayload = {
  event_name: string;
  event_type: string;
  event_date: string;
  start_time: string;
  end_time: string;
  timezone?: string;
  location_name?: string;
  location_address?: string;
  city?: string;
  state?: string;
  postal_code?: string;
  country?: string;
  expected_crowd_size?: number | '';
  music_style?: string;
  requested_services?: string[];
  message?: string;
  contact_name: string;
  contact_email: string;
  contact_phone?: string;
  booking_website?: string;
};

export type BookingRecord = {
  uuid: string;
  status: BookingStatus;
  payment_status: BookingPaymentStatus;
  event_name: string;
  event_type: string;
  event_date: string | null;
  start_time: string | null;
  end_time: string | null;
  timezone: string | null;
  location_name: string | null;
  location_address: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string | null;
  expected_crowd_size: number | null;
  music_style: string | null;
  requested_services: string[];
  message: string | null;
  hourly_rate_tokens: number | null;
  hourly_rate_amount: number | null;
  estimated_hours: number | null;
  estimated_total_amount: number | null;
  currency: string | null;
  contact_name: string;
  contact_email: string;
  contact_phone: string | null;
  internal_notes: string | null;
  dj: {
    id: number | null;
    name: string | null;
    handle: string | null;
    user_id: number;
  };
  requested_by: {
    id: number;
    name: string;
    email: string;
  } | null;
  calendar: BookingCalendarEvent;
  accepted_at: string | null;
  declined_at: string | null;
  cancelled_at: string | null;
  completed_at: string | null;
  paid_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type BookingCalendarEvent = {
  id: string;
  title: string;
  start: string | null;
  end: string | null;
  extendedProps: {
    status: BookingStatus;
    paymentStatus: BookingPaymentStatus;
    eventType: string;
    location: string | null;
  };
};

export type BookingAnalytics = {
  total_booking_requests: number;
  pending_requests: number;
  accepted_bookings: number;
  needs_discussion: number;
  completed_bookings: number;
  cancelled_bookings: number;
  estimated_booking_value: number;
  paid_external: number;
  unpaid_accepted_bookings: number;
  upcoming_events: number;
};

export class BookingApiError extends Error {
  errors: Record<string, string[]>;
  status: number;

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'BookingApiError';
    this.status = status;
    this.errors = errors;
  }
}

function normalizeError(error: unknown, fallback: string): never {
  if (error instanceof Error && 'response' in error) {
    const response = error.response as { status?: number; data?: { message?: string; errors?: Record<string, string[]> } };
    throw new BookingApiError(
      response.data?.message || fallback,
      response.status || 500,
      response.data?.errors || {},
    );
  }

  throw error;
}

export async function getBookingSettings(handle: string): Promise<BookingSettings> {
  try {
    const response = await apiClient.get<{ settings: BookingSettings }>(`/dj-hub/djs/${handle}/booking-settings`);
    return response.data.settings;
  } catch (error) {
    normalizeError(error, 'Unable to load booking settings.');
  }
}

export async function createBookingRequest(handle: string, payload: BookingRequestPayload): Promise<BookingRecord> {
  try {
    const response = await apiClient.post<{ booking: BookingRecord }>(`/dj-hub/djs/${handle}/booking-requests`, payload);
    return response.data.booking;
  } catch (error) {
    normalizeError(error, 'Unable to submit booking request.');
  }
}

export async function getAccountBookings(params: {
  status?: BookingStatus | 'all';
  payment_status?: BookingPaymentStatus | 'all';
  include_cancelled?: boolean;
} = {}): Promise<{
  bookings: BookingRecord[];
  calendar_events: BookingCalendarEvent[];
  analytics: BookingAnalytics;
}> {
  const response = await apiClient.get<{
    bookings: BookingRecord[];
    calendar_events: BookingCalendarEvent[];
    analytics: BookingAnalytics;
  }>('/account/bookings', {
    params: {
      status: params.status && params.status !== 'all' ? params.status : undefined,
      payment_status: params.payment_status && params.payment_status !== 'all' ? params.payment_status : undefined,
      include_cancelled: params.include_cancelled,
    },
  });

  return response.data;
}

export async function getAccountBooking(uuid: string): Promise<BookingRecord> {
  const response = await apiClient.get<{ booking: BookingRecord }>(`/account/bookings/${uuid}`);
  return response.data.booking;
}

export async function updateBookingStatus(
  uuid: string,
  action: 'accept' | 'decline' | 'needs-discussion' | 'cancel' | 'complete',
  payload: { internal_notes?: string } = {},
): Promise<BookingRecord> {
  const response = await apiClient.post<{ booking: BookingRecord }>(`/account/bookings/${uuid}/${action}`, payload);
  return response.data.booking;
}

export async function updateBookingPaymentStatus(
  uuid: string,
  payload: { payment_status?: BookingPaymentStatus; internal_notes?: string } = {},
): Promise<BookingRecord> {
  const response = await apiClient.post<{ booking: BookingRecord }>(`/account/bookings/${uuid}/mark-paid`, payload);
  return response.data.booking;
}
