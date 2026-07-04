# BlendBeat DJ Booking System

## Goal

The DJ Booking System lets visitors request DJ services from a public DJ profile and lets DJs manage those requests inside their account. This phase is request, availability, calendar, status, and manual payment tracking only. It does not process payments, escrow, deposits, Stripe, PayPal, or wallet tokens.

## Existing Foundation

The platform already has public DJ profiles, DJ Hub discovery, and basic booking availability:

- `dj_profiles.booking_enabled`
- `dj_booking_settings.available_for_bookings`
- `dj_booking_settings.booking_email`
- public DJ profile pages at `/djs/{handle}`
- internal app endpoints under `/api` loaded from `routes/web.php`

This booking system extends that foundation with first-class booking requests and account/admin management.

## Route Boundary

Booking routes are internal application routes. They should be defined in the internal web-loaded route files, not in `routes/api.php`.

`routes/api.php` remains for outside services and integrations only, such as PayPal webhooks and automation APIs.

## MVP User Flow

1. A visitor opens a public DJ profile.
2. If the profile is public, active, and booking enabled, the profile shows `Book Me`.
3. The visitor opens a booking modal or page and submits event details and contact information.
4. The DJ receives the booking request.
5. The DJ manages the request from `/account/bookings`.
6. The DJ can accept, decline, mark needs discussion, cancel, mark paid externally, or mark completed.
7. Accepted, pending, needs-discussion, and completed bookings appear in the booking calendar view. Cancelled and declined bookings are hidden by default.

## Payment Scope

No platform payment processing is included in this phase.

Customers and DJs arrange payment outside BlendBeat through their preferred method. The system only stores payment status:

- `unpaid`
- `pending_external_payment`
- `paid_external`
- `refunded_external`
- `not_required`

Future payment fields can be added later for deposits, wallet locks, booking escrow, platform fees, payouts, and refunds.

## Data Model

Primary table:

`dj_booking_requests`

Core fields:

- `uuid`
- `dj_profile_id`
- `dj_user_id`
- `requested_by_user_id`
- `event_name`
- `event_type`
- `event_date`
- `start_time`
- `end_time`
- `timezone`
- `location_name`
- `location_address`
- `city`
- `state`
- `postal_code`
- `country`
- `expected_crowd_size`
- `music_style`
- `requested_services`
- `message`
- `hourly_rate_tokens`
- `hourly_rate_amount`
- `estimated_hours`
- `estimated_total_amount`
- `currency`
- `contact_name`
- `contact_email`
- `contact_phone`
- `status`
- `payment_status`
- status timestamps
- `internal_notes`
- `metadata`

Recommended indexes:

- unique `uuid`
- `dj_profile_id, status`
- `dj_user_id, status`
- `event_date`
- `payment_status`
- `created_at`

## Booking Statuses

- `pending`: request submitted and awaiting DJ response.
- `needs_discussion`: DJ wants to discuss details before accepting or declining.
- `accepted`: DJ accepted the booking request.
- `declined`: DJ declined the request.
- `cancelled`: booking was cancelled.
- `completed`: event is complete.
- `expired`: request expired or became stale.

## Booking Settings

The existing booking settings table should be extended so DJs can control booking behavior:

- hourly rate
- currency
- default timezone
- minimum notice hours
- maximum advance days
- auto-accept toggle

Public `Book Me` visibility requires:

- `dj_profiles.visibility = public`
- `dj_profiles.profile_status = active`
- `dj_profiles.booking_enabled = true`

## Internal API

Public DJ profile booking:

- `GET /api/dj-hub/djs/{handle}/booking-settings`
- `POST /api/dj-hub/djs/{handle}/booking-requests`

DJ account booking management:

- `GET /api/account/bookings`
- `GET /api/account/bookings/{booking:uuid}`
- `POST /api/account/bookings/{booking:uuid}/accept`
- `POST /api/account/bookings/{booking:uuid}/decline`
- `POST /api/account/bookings/{booking:uuid}/needs-discussion`
- `POST /api/account/bookings/{booking:uuid}/cancel`
- `POST /api/account/bookings/{booking:uuid}/complete`
- `POST /api/account/bookings/{booking:uuid}/mark-paid`

Admin booking review:

- `GET /api/admin/bookings`
- `GET /api/admin/bookings/{booking:uuid}`

## Frontend Routes

Public:

- `/djs/{handle}` shows the `Book Me` button and modal.
- `/djs/{handle}/book` can deep-link to the profile with booking modal open.

DJ account:

- `/account/bookings`
- `/account/bookings/{uuid}`
- `/account/bookings/calendar`

## Validation Rules

Booking creation requires:

- event name, type, date, start time, end time
- event date today or later
- end time after start time
- contact name and contact email
- optional location, crowd size, music style, services, message, and phone

Requested services are stored as an array.

## Anti-Spam Rules

The public booking form should include:

- honeypot field
- IP throttling
- duplicate request detection

Duplicate detection checks for the same DJ, same contact email, same event date, and same start time within 10 minutes.

## Notifications

Use the existing notification system for authenticated users:

- new booking request submitted
- DJ accepts
- DJ declines
- DJ marks needs discussion
- booking cancelled
- booking completed

Guest customer email can be added later when outbound email templates are ready.

## Done Criteria

The MVP is complete when:

- customers can submit booking requests from public DJ profiles
- DJs can view and manage booking requests in account pages
- accepted bookings appear on a booking calendar
- DJs can track manual external payment status
- DJs can mark bookings completed
- admins can view all booking requests
- no payment processing is included
