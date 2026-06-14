# BlendBeats Notification System Plan

## Goal

Build a standard Laravel notification system for the user account area, starting with database notifications and a simple React frontend inbox. The first version should be useful without realtime broadcasting, then leave a clean path for live updates later.

## Current Audit

- `App\Models\User` already uses Laravel `Notifiable`.
- `App\Models\Admin` already uses Laravel `Notifiable`.
- No `notifications` database table was found.
- No notification API routes/controllers were found.
- No frontend notification library, page, bell dropdown, or settings page exists yet.
- The Settings page already has a Notifications card that currently points back to `/account/settings`.
- The frontend is a Laravel + React SPA, not a Next.js app.

## Recommended MVP Approach

Use Laravel's common notification setup, package-first:

- Laravel's built-in `illuminate/notifications` package through `laravel/framework`.
- Laravel database notification channel for persistent in-app notifications.
- Optional mail channel later when email delivery is configured.
- React account Notifications page for viewing, filtering, and marking notifications read.
- Header/account dropdown badge for unread count.
- Polling first, realtime broadcasting later.

## Package Decision

The preferred package-style setup is Laravel's native Notifications package because it is already installed through `laravel/framework`, is the most common Laravel notification foundation, and supports database, mail, broadcast, Slack, and custom channels.

Spatie review:

- `spatie/laravel-notification-log` exists, but it logs notifications that were sent; it does not replace Laravel's in-app notification inbox.
- The latest Spatie package versions require PHP `^8.4`.
- This project is currently running PHP `8.3.23`.
- Older Spatie versions do not support Laravel 13.

Decision:

- Use Laravel Notifications for the MVP.
- Do not install `spatie/laravel-notification-log` yet.
- Revisit Spatie Notification Log after either the server moves to PHP 8.4 or the package adds PHP 8.3-compatible Laravel 13 support.

## Phase 1: Database Foundation

1. Create Laravel notifications table:
   - Use Laravel's standard `notifications` table shape.
   - Fields:
     - `id`
     - `type`
     - `notifiable_type`
     - `notifiable_id`
     - `data`
     - `read_at`
     - `created_at`
     - `updated_at`

2. Add optional notification preferences table:
   - `user_notification_preferences`
   - Fields:
     - `id`
     - `user_id`
     - `category`
     - `database_enabled`
     - `email_enabled`
     - `created_at`
     - `updated_at`

3. Suggested initial categories:
   - `account`
   - `dj_profile`
   - `uploads`
   - `mixes`
   - `dj_lounge`
   - `featured_ads`
   - `billing`
   - `support`
   - `system`

## Phase 2: Notification Classes

Create notification classes for common platform events:

1. `AccountUpdatedNotification`
   - Trigger when important account details change.

2. `DjProfilePublishedNotification`
   - Trigger when a DJ profile becomes public.

3. `MediaUploadProcessedNotification`
   - Trigger after a portfolio upload is accepted.

4. `MixPublishedNotification`
   - Trigger when a public mix becomes visible.

5. `FeaturedAdCampaignStatusNotification`
   - Trigger when a featured ad is pending payment, active, expired, rejected, or approved.

6. `SupportTicketReceivedNotification`
   - Trigger later when support ticket submission is implemented.

Each notification should store structured `data`:

```json
{
  "title": "Mix published",
  "message": "Your mix is now public on the Mixes page.",
  "category": "mixes",
  "action_label": "View Mix",
  "action_url": "/mixes/example-slug",
  "icon": "music"
}
```

## Phase 3: Backend API

Add `NotificationController` under `App\Http\Controllers\Api`.

Routes under authenticated API middleware:

```text
GET    /api/notifications
GET    /api/notifications/unread-count
PATCH  /api/notifications/{notification}/read
PATCH  /api/notifications/read-all
DELETE /api/notifications/{notification}
```

List endpoint should support:

- `status=all|read|unread`
- `category=...`
- pagination or limit/offset

Response shape:

```json
{
  "notifications": [],
  "unread_count": 0,
  "filters": {
    "categories": []
  }
}
```

Security rules:

- A user can only read/update/delete their own notifications.
- Admin notifications should stay separate unless we intentionally add admin notification UI later.

## Phase 4: Frontend Library

Create `resources/js/React/Frontend/lib/notifications.ts`.

Functions:

- `getNotifications(query)`
- `getUnreadNotificationCount()`
- `markNotificationRead(id)`
- `markAllNotificationsRead()`
- `deleteNotification(id)`

Types:

- `NotificationRecord`
- `NotificationsResponse`
- `NotificationCategory`

## Phase 5: Notifications Page

Create `/account/notifications`.

Page sections:

1. Header
   - Title: `Notifications`
   - Summary: unread count and total visible notifications.

2. Filters
   - All
   - Unread
   - Read
   - Category dropdown/tabs.

3. Notification List
   - Icon/category marker.
   - Title.
   - Message.
   - Date.
   - Read/unread visual state.
   - Action button when `action_url` exists.
   - Mark read.
   - Delete.

4. Empty State
   - Message: `No notifications yet.`

5. Bulk Action
   - `Mark all as read`

## Phase 6: Settings Integration

Update the existing Settings Notifications card:

- Current: `/account/settings`
- New: `/account/notifications`

Later, if notification preferences get their own screen, add:

- `/account/notifications/preferences`

## Phase 7: Header Badge

Add notification badge to the frontend header/account area.

MVP behavior:

- Fetch unread count when authenticated user loads.
- Poll every 60 seconds.
- Show a small badge when unread count is greater than zero.
- Link to `/account/notifications`.

Avoid realtime setup in MVP so the system stays stable.

## Phase 8: Event Triggers

Start with low-risk triggers:

1. Account profile updated.
2. DJ profile created/published.
3. Portfolio upload created.
4. Featured ad campaign status changed.
5. Support ticket received after support tickets exist.

Do not over-notify. Each event should answer: "Does the user need to know this later?"

## Phase 9: Future Realtime Upgrade

When ready, add Laravel broadcasting:

- Laravel Reverb or Pusher-compatible broadcasting.
- Private user channel:
  - `private-users.{userId}`
- Broadcast notification events to authenticated users.
- Frontend Echo listener updates unread count and inserts new notification into the list.

Realtime is not required for the first version.

## Implementation Order

1. Add notification database migration.
2. Add notification API controller and routes.
3. Add frontend notification API library.
4. Add `/account/notifications` page.
5. Update Settings Notifications card.
6. Add header unread count badge.
7. Add first test notification trigger or seed/dev helper.
8. Add real platform triggers one at a time.
9. Add preferences page.
10. Add realtime broadcasting later.

## Testing Checklist

- User sees only their own notifications.
- Unread count matches unread database rows.
- Mark one notification read updates count.
- Mark all read updates count and list state.
- Delete removes only the user's notification.
- Empty state displays cleanly.
- Settings card routes to `/account/notifications`.
- Header badge hides at zero and shows above zero.
- Mobile layout stays usable.

## Notes

- Keep MVP database-first.
- Do not add realtime dependencies yet.
- Keep notification `data` structured so future notification types render without frontend rewrites.
- Prefer small reusable frontend components once the first page is working.
