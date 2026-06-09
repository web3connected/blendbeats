# Next Task: DJ Portfolio & Media Management

Date: 2026-06-09
App: BlendBeats / The Blend Battlegrounds

## Objective

Allow DJs to build a professional portfolio showcasing their music, mixes, performances, media, and career achievements.

This module transforms a DJ profile from a simple account into a complete career portfolio.

Priority goal:

```text
Allow a new DJ to create an account, upload content, showcase their best work, and share a professional portfolio link within minutes.
```

## Current Build State

Completed foundation:

- User dashboard action changed from `Listen To Mixes` to `My DJ Portfolio`.
- Private `/dj/portfolio` route exists.
- DJs without a profile are redirected to `/dj/start`.
- Media manager foundation exists:
  - `media_files`
  - `media_manager_audit_logs`
  - `MediaFile`
  - `MediaManagerService`
  - `MediaStorageQuotaService`
  - `/api/media/files`
- Uploads support audio, video, and image files.
- Uploaded files are scoped to the logged-in user.
- Storage tiers exist:
  - Starter/free: 500 MB
  - Growth: 3 GB
  - Pro: 5 GB
- DJ Portfolio page shows storage usage and upload/list UI.

## Phase 1: Media Library

Create a centralized media management area where DJs can upload and organize their content.

### Supported Media Types

Audio:

- MP3 tracks
- WAV files
- DJ mixes
- Radio shows
- Podcast episodes
- Sample packs

Images:

- Profile photos
- Promotional photos
- Event flyers
- Album covers
- Press images

Video:

- Live performances
- Event recaps
- Promotional videos
- Tutorials
- Competition entries

External media:

- YouTube links
- SoundCloud links
- Mixcloud links
- Spotify tracks
- Apple Music tracks

### Media Item Fields

Common fields:

- Title
- Description
- Category
- Genre
- Upload date
- Visibility: public, private, subscribers only
- Featured status
- Tags

Audio-specific fields:

- Duration
- BPM
- Track type
- Explicit content flag
- Download enabled

Video-specific fields:

- Video length
- Platform
- Embed URL

## Phase 1 Database Plan

Keep `media_files` as the physical file record. Add `dj_portfolio_items` as the creator-facing media record.

Recommended table:

```text
dj_portfolio_items:
- id
- dj_profile_id foreign dj_profiles.id
- user_id foreign users.id
- media_file_id nullable foreign media_files.id
- title
- slug
- description text nullable
- category enum: audio, image, video, external
- media_type enum: track, mix, radio_show, podcast_episode, sample_pack, photo, flyer, album_cover, press_image, live_performance, event_recap, promo_video, tutorial, competition_entry, external
- status enum: draft, published, archived
- visibility enum: public, private, subscribers
- genre nullable
- tags json nullable
- is_featured boolean default false
- featured_slot nullable enum: mix, track, video, gallery_image
- external_platform nullable enum: youtube, soundcloud, mixcloud, spotify, apple_music, other
- external_url nullable
- embed_url nullable
- thumbnail_media_file_id nullable foreign media_files.id
- thumbnail_url nullable
- duration_seconds nullable
- bpm nullable unsigned small integer
- track_type nullable string
- explicit boolean default false
- download_enabled boolean default false
- play_count unsigned big integer default 0
- view_count unsigned big integer default 0
- like_count unsigned big integer default 0
- published_at nullable timestamp
- created_at
- updated_at
- deleted_at nullable
```

Indexes:

```text
unique(dj_profile_id, slug)
index(dj_profile_id, status)
index(dj_profile_id, category)
index(dj_profile_id, visibility)
index(featured_slot, is_featured)
index(published_at)
```

Laravel model:

```text
App\Models\DjPortfolioItem
```

Relationships:

```text
DjProfile hasMany DjPortfolioItem
DjPortfolioItem belongsTo DjProfile
DjPortfolioItem belongsTo User
DjPortfolioItem belongsTo MediaFile
DjPortfolioItem belongsTo thumbnail MediaFile
```

## Phase 1 API Plan

Authenticated DJ endpoints:

```text
GET /api/dj/portfolio
POST /api/dj/portfolio
GET /api/dj/portfolio/{item}
PATCH /api/dj/portfolio/{item}
DELETE /api/dj/portfolio/{item}
POST /api/dj/portfolio/{item}/publish
POST /api/dj/portfolio/{item}/archive
POST /api/dj/portfolio/{item}/feature
DELETE /api/dj/portfolio/{item}/feature
```

Existing media manager endpoints stay responsible for physical file uploads:

```text
GET /api/media/files
POST /api/media/files
DELETE /api/media/files/{file}
```

## Phase 1 Frontend Plan

Private route:

```text
/dj/portfolio
```

Optional edit/create routes:

```text
/dj/portfolio/new
/dj/portfolio/:portfolioItemId/edit
```

Media dashboard sections:

- Header with upload/create actions.
- Storage tier usage panel.
- Filters for category, type, status, visibility, and featured status.
- Search input.
- Media list/table.
- Empty state.
- Create/edit form.
- External media link form.

Primary actions:

- Upload file.
- Create portfolio item from uploaded file.
- Add external media link.
- Edit metadata.
- Publish.
- Archive.
- Delete.
- Mark as featured.

## Phase 2: Featured Content

Allow DJs to highlight their best work.

Featured sections:

- Featured mix.
- Featured track.
- Featured video.
- Featured gallery image.

Business rules:

- A DJ should have only one active item per featured slot.
- Setting a new featured mix should unset the previous featured mix.
- Featured content must be published and visible enough for the target audience.

## Phase 3: Public DJ Portfolio Page

Public route options:

```text
/dj/{handle}
/djs/{handle}
```

Recommended public profile layout:

Hero section:

- DJ name.
- Cover image.
- Profile image.
- Headline.
- Location.
- Genres.

Quick stats:

- Followers.
- Mixes uploaded.
- Tracks uploaded.
- Competitions entered.
- Competition wins.
- Years active.

Featured content:

- Featured mix.
- Featured track.
- Featured video.

About section:

- Biography.
- Signature sound.
- Influences.
- Equipment setup.

Media gallery:

- Tracks.
- Mixes.
- Videos.
- Photos.

## Phase 4: Professional Career Assets

Resume-style fields:

- Years active.
- Career highlights.
- Awards.
- Certifications.
- Competition results.
- Major events played.

Recommended tables:

```text
dj_career_highlights
dj_awards
dj_certifications
dj_competition_results
```

## Phase 4 Event History

Store DJ performance history.

Recommended table:

```text
dj_event_history:
- id
- dj_profile_id
- event_name
- venue
- city
- state nullable
- country nullable
- event_date date
- attendance unsigned integer nullable
- event_type string nullable
- description text nullable
- created_at
- updated_at
```

## Phase 5: Booking Portfolio

Booking information:

- Booking email.
- Booking phone.
- Manager contact.
- Travel availability.
- Travel radius.
- Minimum rate.
- Rate type.

Service types:

- Club DJ.
- Mobile DJ.
- Radio DJ.
- Battle DJ.
- Producer.
- Open format DJ.
- Wedding DJ.

This should extend the existing `dj_booking_settings` table instead of creating an unrelated booking table.

## Phase 6: Future Premium Features

Advanced analytics:

- Profile views.
- Track plays.
- Mix plays.
- Video views.
- Audience growth.

Press kit generator:

- Downloadable DJ press kits.
- EPK export.
- One-click professional portfolio packet.

AI career summary:

- Generate professional DJ bios.
- Generate achievement summaries.
- Generate short promoter-ready booking copy.

## MVP Build Order

1. Media library.
2. Audio uploads.
3. Portfolio item CRUD metadata.
4. Featured content.
5. Public portfolio page.
6. Media gallery.
7. Booking information.
8. Career history.
9. Analytics.
10. Press kit generator.

## Immediate Next Implementation Task

Build user media setup first, then `dj_portfolio_items`.

Media setup reference:

```text
_docs/next-task-user-media-setup.md
```

The media setup task creates the user's media workspace, public storage folders, and feature activation records. Portfolio CRUD should use that active media workspace instead of inventing its own folder rules.

Scope:

- Add media setup migration/service/controller from the media setup doc.
- Add migration.
- Add model and relationships.
- Add CRUD controller.
- Add ownership checks.
- Connect the existing `/dj/portfolio` page to list portfolio items, not just raw `media_files`.
- Add create/edit form for metadata.
- Allow a selected uploaded media file to become a portfolio item.
- Allow external media items without uploading a file.

Acceptance criteria:

- DJ can upload a file and create a portfolio item from it.
- DJ can create an external YouTube/SoundCloud/Mixcloud/Spotify/Apple Music item.
- DJ can edit title, description, category, genre, tags, visibility, and media-specific fields.
- DJ can publish/archive/delete their own portfolio items.
- DJ cannot access another DJ's portfolio items.
- Featured item selection is represented in the database, even if the public profile page is built later.
