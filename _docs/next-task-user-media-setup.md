# Next Task: User Media Setup & Feature Activation

Date: 2026-06-09
App: BlendBeats / The Blend Battlegrounds

## Goal

Create a setup flow that activates the media system for a user before they upload content.

This setup should:

- Create the user's media workspace.
- Create the public storage folder structure.
- Register that the user is using the media system.
- Provide one place to list active account features.
- Prepare the account for tier-based storage limits.

## Product Idea

A normal account should not automatically become a media account until the user starts using media features.

When a user opens DJ Portfolio or clicks a setup action, the app can activate media for that user:

```text
User account
-> Activate Media Library
-> Create media workspace row
-> Create public storage folders
-> Activate media_library feature
-> User can upload files
```

## Public Storage Path

Use Laravel's `public` disk.

Actual disk root:

```text
backend/storage/app/public
```

Public URL root after `php artisan storage:link`:

```text
/storage
```

User media files should live under:

```text
storage/app/public/media/accounts/{account_slug}/
```

Example:

```text
storage/app/public/media/accounts/silconone/
```

Public URL example:

```text
/storage/media/accounts/silconone/audio/silconone-mix.mp3
```

## Default Folder Structure

When the media setup is activated, create:

```text
media/accounts/{account_slug}/audio
media/accounts/{account_slug}/video
media/accounts/{account_slug}/images
media/accounts/{account_slug}/documents
media/accounts/{account_slug}/temp
```

Future optional folders:

```text
media/accounts/{account_slug}/press-kit
media/accounts/{account_slug}/portfolio
media/accounts/{account_slug}/battle-entries
media/accounts/{account_slug}/avatars
media/accounts/{account_slug}/banners
```

## Account Slug Rules

Preferred slug source:

```text
dj_profiles.handle
```

Fallback order:

```text
1. dj_profiles.handle
2. users.name
3. account-{user_id}
```

Slug must be normalized:

```text
lowercase
spaces -> hyphen
remove unsafe characters
unique if needed
```

Example:

```text
SilconOne -> silconone
DJ Silicon One -> dj-silicon-one
```

## Database Design

## Newer Media Library Schema Review

The newer pasted schema includes two separate ideas:

1. A Spatie Media Library style table named `media`.
2. A custom media manager layer named `media_manager_files`, `media_manager_permissions`, and `media_manager_audit_logs`.

Current repo status:

```text
spatie/laravel-permission is installed
spatie/laravel-medialibrary is not installed
```

Decision:

- Do not add the polymorphic `media` table unless the project intentionally installs and adopts `spatie/laravel-medialibrary`.
- Keep our current custom media manager layer for now.
- Use `media_accounts` and `user_feature_activations` as the activation/setup layer above the file records.
- Keep permission checks in service/config for MVP instead of adding `media_manager_permissions` immediately.

Why:

- The Spatie `media` table expects package models, traits, conversions, responsive images, and package conventions.
- Adding it without the package would create an unused table.
- Our current product need is user-scoped public storage, storage quotas, feature activation, and DJ Portfolio uploads.

Current custom table:

```text
media_files
```

Newer pasted custom table name:

```text
media_manager_files
```

Recommended path:

- Keep `media_files` unless we decide to rename before deployment.
- If we rename, do it once with a migration before production data matters.
- Add `media_accounts` before expanding the file schema further.
- Add `media_account_id` to `media_files` after `media_accounts` exists.

Future optional custom permission table:

```text
media_manager_permissions:
- id
- role
- disk
- permissions json
- created_at
- updated_at
```

For MVP, permissions remain code/config based:

```text
admin: public/local/s3 view, upload, delete, move, archive
staff: public/local view, upload, delete
user: public view, upload, delete own files
```

### 1. media_accounts

One row per user using the media system.

```text
media_accounts:
- id
- user_id foreign users.id unique
- account_slug unique
- disk default public
- root_path
- storage_tier
- storage_limit_bytes
- storage_used_bytes default 0
- status enum: active, suspended, disabled
- activated_at timestamp nullable
- last_scanned_at timestamp nullable
- created_at
- updated_at
```

Why this table exists:

- Keeps folder naming stable even if the DJ handle changes later.
- Gives the app one place to find the user's media root.
- Allows storage usage caching.
- Allows feature suspension without deleting files.

Laravel model:

```text
App\Models\MediaAccount
```

Relationships:

```text
User hasOne MediaAccount
MediaAccount belongsTo User
MediaAccount hasMany MediaFile through user_id or via media_account_id later
```

### 2. user_feature_activations

Track which product features a user is actively using.

```text
user_feature_activations:
- id
- user_id foreign users.id
- feature_key string
- status enum: active, paused, disabled
- source nullable string
- metadata json nullable
- activated_at timestamp nullable
- paused_at timestamp nullable
- disabled_at timestamp nullable
- created_at
- updated_at
```

Unique index:

```text
unique(user_id, feature_key)
```

Initial feature keys:

```text
media_library
dj_profile
dj_portfolio
dj_lounge
booking_profile
```

Future feature keys:

```text
press_kit
analytics
ai_career_summary
battle_entries
marketplace_booking
```

Laravel model:

```text
App\Models\UserFeatureActivation
```

Relationships:

```text
User hasMany UserFeatureActivation
UserFeatureActivation belongsTo User
```

## Existing Tables That Stay

### media_files

Physical file records remain here.

Add later if needed:

```text
media_account_id nullable foreign media_accounts.id
```

For now, `user_id` is enough to scope files.

### users

Keep:

```text
media_storage_tier
```

This can later move into `media_accounts` or subscription billing if needed. For now, user-level is simple and useful.

## Media Setup API

Authenticated endpoints:

```text
GET /api/media/setup
POST /api/media/setup
GET /api/account/features
```

Optional admin endpoints later:

```text
PATCH /api/admin/users/{user}/features/{feature}
PATCH /api/admin/media-accounts/{mediaAccount}
```

## Setup Endpoint Behavior

`POST /api/media/setup` should:

1. Require authenticated user.
2. Build or reuse account slug.
3. Create `media_accounts` row if missing.
4. Create public disk folders.
5. Activate `media_library` in `user_feature_activations`.
6. Return media account, quota, and active features.

Response shape:

```json
{
  "media_account": {
    "id": 1,
    "account_slug": "silconone",
    "disk": "public",
    "root_path": "media/accounts/silconone",
    "status": "active"
  },
  "quota": {
    "tier": "starter",
    "limit_formatted": "500 MB",
    "used_formatted": "0 B",
    "remaining_formatted": "500 MB"
  },
  "features": [
    {
      "feature_key": "media_library",
      "status": "active"
    }
  ]
}
```

## Frontend Setup Flow

On `/dj/portfolio`:

- If user has no DJ profile: redirect to `/dj/start`.
- If user has DJ profile but no media account: show `Activate Media Library`.
- After activation: show portfolio media dashboard.

UI states:

```text
Loading setup
Not activated
Activating
Active
Activation failed
Suspended/disabled
```

Setup card copy:

```text
Activate Media Library
Create your secure public media workspace for uploads, portfolio assets, and future press kit files.
```

## Upload Behavior After Setup

Uploads should store under the user's `media_accounts.root_path`.

Examples:

Audio:

```text
media/accounts/silconone/audio/{filename}
```

Images:

```text
media/accounts/silconone/images/{filename}
```

Video:

```text
media/accounts/silconone/video/{filename}
```

Generic portfolio media:

```text
media/accounts/silconone/portfolio/{filename}
```

## Feature Activation Rules

Feature activation should be idempotent.

Calling setup multiple times should:

- Not create duplicate `media_accounts`.
- Not create duplicate `user_feature_activations`.
- Recreate missing folders if needed.
- Return the existing active media setup.

## Acceptance Criteria

- [x] User can activate media library once.
- [x] Activation creates a stable `media_accounts` row.
- [x] Activation creates user media folders under `storage/app/public/media/accounts/{account_slug}`.
- [x] Activation creates or updates `user_feature_activations.media_library` as active.
- [x] Media uploads require an active media account.
- [x] Uploads use the media account root path.
- [x] Existing quota limits still apply.
- [x] Account features endpoint returns active features for the user.
- [x] Re-running setup is safe and idempotent.

## Suggested Implementation Order

1. [x] Add this doc.
2. [x] Add `media_accounts` migration and model.
3. [x] Add `user_feature_activations` migration and model.
4. [x] Add relationships to `User`.
5. [x] Add `MediaSetupService`.
6. [x] Add `MediaSetupController`.
7. [x] Add setup routes.
8. [x] Update `MediaManagerService` to require and use active media account root path.
9. [x] Update DJ Portfolio page to activate/load setup before listing uploads.
10. [x] Add tests for setup idempotency, folder creation, active feature tracking, and upload path.

## Open Decisions

- Should account slug update when DJ handle changes, or remain stable forever?
- Should old files be moved if a slug changes?
- Should media activation happen automatically when a DJ profile is created?
- Should non-DJ users be able to activate media for avatars and general files?
- Should `media_files` get a direct `media_account_id` now or after first CRUD pass?
