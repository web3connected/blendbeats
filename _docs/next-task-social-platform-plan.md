# Next Task: Social Platform Foundation

Date: 2026-06-06
App: BlendBeats / The Blend Battlegrounds

## Goal

Start the social side of BlendBeats with a YouTube-style experience for DJs, mixes, battles, and community interaction.

The first pass should establish the database tables, Laravel models, and relationships needed for:

- User profiles and DJ channels.
- Following/subscribing to creators.
- Uploading and browsing media posts.
- Likes, comments, replies, and saves.
- Playlists/collections.
- Notifications.
- Basic moderation and reporting.

## Product Direction

Use YouTube as the mental model, adapted for DJs:

- A user has an account and can have a public channel/profile.
- A channel publishes mixes, videos, clips, battle entries, and posts.
- Other users can subscribe/follow.
- Content has views, likes, comments, saves, tags, and status.
- Users can build playlists or saved collections.
- The home/feed experience can rank by subscriptions, freshness, popularity, genre, and battle activity.

## Stage 1 Task: DJ Career And DJ Dashboard

Before building the public social feed, build the DJ Dashboard. This is the logged-in user's creator/profile control center.

Right now, every public account is just a `User`. The first social step is to let a user start a DJ career. Starting a DJ career creates their DJ channel/profile and unlocks the DJ Dashboard.

### User Journey

```text
User registers/logs in
-> Account menu shows "Start DJ Career" if no channel exists
-> User completes DJ profile setup
-> System creates one channel for the user
-> User lands on DJ Dashboard
-> User can manage profile, public channel info, media, stats, and settings
```

### Routes To Build

Frontend routes:

```text
/dj/start
/dj/dashboard
/dj/profile
/dj/content
/dj/analytics
/dj/settings
```

Backend API routes:

```text
GET /api/dj/me
POST /api/dj/start
PATCH /api/dj/profile
GET /api/dj/dashboard
GET /api/dj/content
GET /api/dj/analytics
PATCH /api/dj/settings
```

### DJ Dashboard Screens

#### 1. Start DJ Career

Purpose:

- Convert a normal user account into a creator/DJ account.
- Create the user's first channel.

Fields:

```text
stage_name
handle
primary_genre_id
home_city
bio
avatar
banner_image
website_url
social_links
```

Validation:

- `handle` must be unique.
- `stage_name` is required.
- `primary_genre_id` is optional at first but recommended.
- User can only create one channel in MVP.

#### 2. DJ Dashboard Home

Purpose:

- Give the DJ a YouTube Studio-style overview.

Cards/widgets:

```text
profile_completion_percent
subscriber_count
total_view_count
total_like_count
published_media_count
draft_media_count
latest_comments
recent_activity
next_suggested_action
```

Suggested actions:

```text
complete profile
upload first mix
add banner image
connect socials
join first battle
```

#### 3. DJ Profile Manager

Purpose:

- Manage the public channel/profile.

Editable fields:

```text
stage_name
handle
description
bio
home_city
primary_genre_id
avatar
banner_image
website_url
social_links
profile_visibility
```

#### 4. DJ Content Manager

Purpose:

- Manage uploaded mixes/videos/posts.

MVP content actions:

```text
view content list
filter by status
edit title/description/genre/tags
publish/unpublish
archive
```

Upload can be a later step. The dashboard should be designed with upload actions visible but not required for the first database pass.

#### 5. DJ Analytics

Purpose:

- Give creators simple feedback before building full analytics.

MVP metrics:

```text
views
likes
comments
saves
subscribers
top media
recent media activity
```

#### 6. DJ Settings

Purpose:

- Manage creator preferences.

Settings:

```text
notifications_enabled
comment_policy enum: all, subscribers, off
profile_visibility enum: public, private
allow_battle_invites boolean
allow_collab_requests boolean
```

### Tables Needed For Stage 1

Minimum tables for DJ Dashboard MVP:

```text
channels
genres
media_posts
subscriptions
comments
reactions
media_views
```

Optional but useful in Stage 1:

```text
channel_settings
channel_stats_snapshots
```

#### channel_settings

Store dashboard/settings preferences separately from the public channel fields.

```text
channel_settings:
- id
- channel_id foreign channels.id unique
- notifications_enabled boolean default true
- comment_policy enum: all, subscribers, off default all
- allow_battle_invites boolean default true
- allow_collab_requests boolean default true
- created_at
- updated_at
```

Laravel model:

```text
App\Models\ChannelSetting
```

Relationship:

- belongsTo `Channel`

#### channel_stats_snapshots

Optional daily stats snapshots for future charts.

```text
channel_stats_snapshots:
- id
- channel_id foreign channels.id
- date date
- subscriber_count unsigned integer default 0
- view_count unsigned big integer default 0
- like_count unsigned big integer default 0
- comment_count unsigned integer default 0
- media_count unsigned integer default 0
- created_at
- updated_at
```

Unique index:

```text
unique(channel_id, date)
```

Laravel model:

```text
App\Models\ChannelStatsSnapshot
```

### Stage 1 Todo List

1. Add channel/profile database foundation.
2. Add `Channel` model and relationship from `User`.
3. Add `ChannelSetting` model and relationship from `Channel`.
4. Add starter `Genre` records.
5. Add `GET /api/dj/me` to return whether the current user has started a DJ career.
6. Add `POST /api/dj/start` to create the user's channel and default channel settings.
7. Add `PATCH /api/dj/profile` for profile edits.
8. Add placeholder dashboard API response with counts.
9. Add frontend `/dj/start` page.
10. Add frontend `/dj/dashboard` page.
11. Update account/header menu:
    - show `Start DJ Career` when user has no channel
    - show `DJ Dashboard` when user has a channel
12. Add tests for:
    - user can start DJ career once
    - duplicate handles are rejected
    - unauthenticated users cannot create a channel
    - dashboard endpoint returns the user's channel summary

### Stage 1 Acceptance Criteria

- A logged-in user without a channel sees a `Start DJ Career` action.
- Starting a DJ career creates exactly one channel for that user.
- The user can view `/dj/dashboard` after channel creation.
- The user can edit public DJ profile fields.
- Header/account dropdown links to DJ Dashboard after setup.
- Normal non-DJ users can still use the site as fans/listeners.
- No public feed or upload workflow is required yet.

## MVP Social Models And Tables

### 1. User Profile Layer

Existing table:

```text
users
```

Add or extend with:

```text
users:
- id
- name
- email
- password
- avatar
- use_gravatar
- username
- bio
- location
- website_url
- social_links json nullable
- profile_visibility enum: public, private
- email_verified_at
- created_at
- updated_at
```

Laravel model:

```text
App\Models\User
```

Relationships:

- hasOne `Channel`
- hasMany `MediaPost`
- hasMany `Comment`
- hasMany `Reaction`
- hasMany `Playlist`
- hasMany `Subscription` as subscriber

### 2. Channels

YouTube-style public creator page.

```text
channels:
- id
- user_id foreign users.id unique
- name
- slug unique
- handle unique
- description text nullable
- banner_image nullable
- avatar nullable
- primary_genre_id nullable
- subscriber_count unsigned big integer default 0
- total_view_count unsigned big integer default 0
- is_verified boolean default false
- is_active boolean default true
- created_at
- updated_at
```

Laravel model:

```text
App\Models\Channel
```

Relationships:

- belongsTo `User`
- belongsTo `Genre`
- hasMany `MediaPost`
- hasMany `Subscription`
- hasMany `Playlist`

### 3. Genres And Tags

```text
genres:
- id
- name
- slug unique
- created_at
- updated_at

tags:
- id
- name
- slug unique
- created_at
- updated_at
```

Pivot:

```text
media_post_tag:
- id
- media_post_id foreign media_posts.id
- tag_id foreign tags.id
- created_at
```

Laravel models:

```text
App\Models\Genre
App\Models\Tag
```

### 4. Media Posts

Core content table. This replaces hardcoded mixes later and can support video/audio/image/text posts.

```text
media_posts:
- id
- user_id foreign users.id
- channel_id foreign channels.id
- genre_id nullable foreign genres.id
- title
- slug
- description text nullable
- type enum: mix, video, short, battle_entry, post
- status enum: draft, published, unlisted, private, archived
- media_url nullable
- thumbnail_url nullable
- duration_seconds nullable
- visibility enum: public, subscribers, private, unlisted
- view_count unsigned big integer default 0
- like_count unsigned big integer default 0
- comment_count unsigned big integer default 0
- save_count unsigned big integer default 0
- published_at nullable
- created_at
- updated_at
- deleted_at nullable
```

Laravel model:

```text
App\Models\MediaPost
```

Relationships:

- belongsTo `User`
- belongsTo `Channel`
- belongsTo `Genre`
- belongsToMany `Tag`
- hasMany `Comment`
- hasMany `Reaction`
- hasMany `View`
- hasMany `PlaylistItem`

### 5. Subscriptions / Follows

User subscribes to a channel.

```text
subscriptions:
- id
- subscriber_user_id foreign users.id
- channel_id foreign channels.id
- notifications_enabled boolean default true
- created_at
- updated_at
```

Unique index:

```text
unique(subscriber_user_id, channel_id)
```

Laravel model:

```text
App\Models\Subscription
```

### 6. Reactions

Generic likes/dislikes for media and comments.

```text
reactions:
- id
- user_id foreign users.id
- reactable_type
- reactable_id
- type enum: like, dislike
- created_at
- updated_at
```

Unique index:

```text
unique(user_id, reactable_type, reactable_id)
```

Laravel model:

```text
App\Models\Reaction
```

Relationships:

- morphTo `reactable`
- belongsTo `User`

### 7. Comments And Replies

Threaded comments on media posts.

```text
comments:
- id
- user_id foreign users.id
- media_post_id foreign media_posts.id
- parent_id nullable foreign comments.id
- body text
- status enum: visible, hidden, flagged, deleted
- like_count unsigned integer default 0
- reply_count unsigned integer default 0
- created_at
- updated_at
- deleted_at nullable
```

Laravel model:

```text
App\Models\Comment
```

Relationships:

- belongsTo `User`
- belongsTo `MediaPost`
- belongsTo `Comment` as parent
- hasMany `Comment` as replies
- morphMany `Reaction`

### 8. Views / Watch History

Useful for ranking, counts, and user history.

```text
media_views:
- id
- media_post_id foreign media_posts.id
- user_id nullable foreign users.id
- session_id nullable
- ip_hash nullable
- watched_seconds unsigned integer default 0
- completed boolean default false
- created_at
```

Laravel model:

```text
App\Models\MediaView
```

### 9. Playlists / Collections

YouTube-style playlists and saved sets.

```text
playlists:
- id
- user_id foreign users.id
- channel_id nullable foreign channels.id
- title
- slug
- description text nullable
- visibility enum: public, private, unlisted
- item_count unsigned integer default 0
- created_at
- updated_at
```

```text
playlist_items:
- id
- playlist_id foreign playlists.id
- media_post_id foreign media_posts.id
- position unsigned integer default 0
- created_at
```

Unique index:

```text
unique(playlist_id, media_post_id)
```

Laravel models:

```text
App\Models\Playlist
App\Models\PlaylistItem
```

### 10. Saves / Watch Later

Fast personal saves without requiring a playlist.

```text
saves:
- id
- user_id foreign users.id
- media_post_id foreign media_posts.id
- created_at
```

Unique index:

```text
unique(user_id, media_post_id)
```

Laravel model:

```text
App\Models\Save
```

### 11. Notifications

For subscriptions, comments, replies, likes, battle updates, and admin notices.

Laravel has built-in notifications, but a dedicated table is still useful for custom feed rendering.

```text
notifications:
- id uuid
- type
- notifiable_type
- notifiable_id
- data json
- read_at nullable
- created_at
- updated_at
```

Laravel model:

```text
Use Laravel DatabaseNotification unless custom behavior is required.
```

### 12. Reports / Moderation

Needed early so public social features are not launched without safety hooks.

```text
reports:
- id
- reporter_user_id foreign users.id
- reportable_type
- reportable_id
- reason enum: spam, harassment, copyright, explicit, impersonation, other
- details text nullable
- status enum: open, reviewing, resolved, dismissed
- reviewed_by_admin_id nullable foreign admins.id
- reviewed_at nullable
- created_at
- updated_at
```

Laravel model:

```text
App\Models\Report
```

## Later Tables

Add these after the MVP works:

```text
blocks
mentions
hashtags
community_posts
livestreams
live_chat_messages
content_moderation_actions
creator_badges
channel_links
```

## First API Endpoints

Read endpoints:

```text
GET /api/feed
GET /api/channels/{handle}
GET /api/channels/{handle}/media
GET /api/media
GET /api/media/{slug}
GET /api/media/{media}/comments
GET /api/genres
GET /api/tags
```

Authenticated endpoints:

```text
POST /api/channels
PATCH /api/channels/{channel}
POST /api/media
PATCH /api/media/{media}
DELETE /api/media/{media}
POST /api/channels/{channel}/subscribe
DELETE /api/channels/{channel}/subscribe
POST /api/media/{media}/reactions
DELETE /api/media/{media}/reactions
POST /api/media/{media}/comments
POST /api/comments/{comment}/replies
POST /api/media/{media}/saves
DELETE /api/media/{media}/saves
POST /api/playlists
POST /api/playlists/{playlist}/items
POST /api/reports
```

## Suggested First Implementation Task

Create the social foundation migrations and models only.

Scope:

- Add migrations for:
  - `genres`
  - `tags`
  - `channels`
  - `media_posts`
  - `media_post_tag`
  - `subscriptions`
  - `comments`
  - `reactions`
  - `media_views`
  - `playlists`
  - `playlist_items`
  - `saves`
  - `reports`
- Add Laravel models with relationships.
- Add factories for seed/test data.
- Seed starter genres and a few sample channels/media posts.
- Do not build the full frontend feed yet.

## Acceptance Criteria

- `php artisan migrate:fresh --seed` succeeds locally.
- Existing auth and admin tests still pass.
- Users can own one channel.
- Channels can publish many media posts.
- Users can subscribe to channels.
- Media posts can be liked, commented on, saved, tagged, and added to playlists.
- Reports can target media posts, comments, or channels.
- No social API routes are exposed until auth/authorization policies are ready.

## Open Decisions

- Content upload storage: local disk first, S3-compatible later.
- Media type naming: keep `mix`, `video`, `short`, `battle_entry`, `post`.
- Follow vs subscribe language: use `subscribe` in database/API to match YouTube, display can say follow or subscribe.
- One channel per user for MVP. Multiple channels per user can be added later if needed.
