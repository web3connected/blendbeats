# Universal Video Player Replacement Plan

## Goal

Replace the current custom bottom audio player with the new Ultimate Video Player package while preserving the existing BlendBeats playback architecture.

The key rule:

```text
Pages should keep using usePlayer().
The player engine/rendering changes under the hood.
```

This avoids rewriting Mixes, DJ Hub, DJ Portfolio, DJ Lounge, public DJ profile pages, and featured components.

## Package Reviewed

Archive:

```text
resources/assets/video-player.zip
```

Temporary extraction used for review:

```text
_player_review
```

Main package identity:

```text
Ultimate Video Player / FWDUVPlayer
```

Important files:

```text
start/java/FWDUVPlayer.js
start/java/FWDUVPlayer-unminified.js
start/content/global.css
start/content/minimal_skin_dark/
start/content/modern_skin_dark/
start/content/classic_skin_dark/
start/content/metal_skin_dark/
documentation/index.html
start/API-example.html
start/STICKY-example.html
start/responsive-minimal-skin-dark.html
```

## Relevant Features Found

The player supports:

- MP3 audio playback
- MP4 video playback
- YouTube
- Vimeo
- HLS / `.m3u8`
- DASH / `.mpd`
- playlists
- playlist thumbnails
- poster artwork
- audio visualizer
- playback speed
- fullscreen
- sticky display
- share/embed/download controls
- subtitles
- cuepoints
- annotations
- ad support
- VAST / VMAP / Google IMA ad support
- API events
- API control methods

Useful API methods found in `API-example.html`:

```text
player.play()
player.pause()
player.stop()
player.playNext()
player.playPrev()
player.playShuffle()
player.playVideo(videoId)
player.scrub(percent)
player.setVolume(percent)
player.share()
player.downloadVideo()
player.goFullScreen()
player.loadPlaylist(playlistId)
```

Useful API events found:

```text
READY
ERROR
PLAY
PAUSE
STOP
UPDATE
UPDATE_TIME
UPDATE_VIDEO_SOURCE
UPDATE_POSTER_SOURCE
START_TO_LOAD_PLAYLIST
LOAD_PLAYLIST_COMPLETE
PLAY_COMPLETE
```

## Current BlendBeats Player Architecture

Current central file:

```text
resources/js/React/Frontend/components/player/PlayerProvider.tsx
```

Current public hook:

```ts
usePlayer()
```

Current API exposed to pages:

```ts
currentTrack
isPlaying
error
mode
playbackBlocked
playTrack(track)
updateCurrentTrack(patch)
loadQueue(options)
togglePlay()
stop()
```

Current consumers:

```text
Mixes page
DJ Portfolio page
DJ Hub page
Public DJ Profile page
DJ Lounge page
Featured DJ sidebar spotlight
Featured mix/action cards
```

This is good. We should keep this hook stable.

## Recommended Integration Strategy

### Do Not Replace Every Page

Do not update each page to know about `FWDUVPlayer`.

Instead:

```text
PlayerProvider
  stays as the app-facing playback API

FWDUVPPlayerHost
  becomes the engine/view adapter

Pages
  continue using usePlayer()
```

## Proposed Files

Original target:

```text
public/vendor/fwduvp/FWDUVPlayer.js
public/vendor/fwduvp/content/global.css
public/vendor/fwduvp/content/minimal_skin_dark/
public/vendor/fwduvp/content/modern_skin_dark/
```

Current loader target:

```text
public/media/fwduvp/java/FWDUVPlayer.js
public/media/fwduvp/content/global.css
public/media/fwduvp/content/minimal_skin_dark/
public/media/fwduvp/content/modern_skin_dark/
public/media/fwduvp/content/fonts/
```

Reason:

```text
The local Windows environment denied new folder creation under public/vendor and public/media/vendor.
The app already serves media assets from public/media, so /media/fwduvp is the app-owned player runtime path.
```

Add React adapter:

```text
resources/js/React/Frontend/components/player/FWDUVPPlayerHost.tsx
resources/js/React/Frontend/components/player/fwduvp-loader.ts
resources/js/React/Frontend/components/player/fwduvp-playlist.ts
resources/js/React/Frontend/components/player/fwduvp-types.ts
resources/js/React/Frontend/components/player/useFWDUVPlayer.ts
```

Later, if we want to keep both engines temporarily:

```text
resources/js/React/Frontend/components/player/LegacyAudioPlayerHost.tsx
resources/js/React/Frontend/components/player/PlayerProvider.tsx
```

## Asset Copy Rules

Copy only runtime assets needed by the app:

```text
start/java/FWDUVPlayer.js
start/content/global.css
start/content/minimal_skin_dark/
start/content/modern_skin_dark/
start/content/fonts/
```

Do not copy demo media:

```text
start/content/videos/
start/content/videos2/
start/content/mp3/
start/content/posters/
start/content/thumbnails/
```

Do not copy helper PHP files unless we explicitly review and secure them:

```text
downloader.php
mp3.php
proxy.php
proxyFolder.php
sendMail.php
sendMailToAFriend.php
```

Reason: those scripts may expose download/proxy/mail behaviors we do not want public.

Do not copy SWF unless a later browser-support requirement proves it is needed:

```text
cb.swf
```

## Playlist Mapping

The package expects hidden HTML playlist markup:

```html
<ul id="playlists" style="display:none;">
  <li data-source="playlist1" data-playlist-name="BlendBeats Queue"></li>
</ul>

<ul id="playlist1" style="display:none;">
  <li
    data-thumb-source="/media/..."
    data-video-source="/api/media/files/1/stream"
    data-poster-source="/media/..."
    data-downloadable="no"
  >
    <div data-video-short-description="">
      ...
    </div>
  </li>
</ul>
```

We should generate this markup from `PlayerTrack[]`.

Current `PlayerTrack` should expand slightly:

```ts
type PlayerTrack = {
  id: string | number;
  title: string;
  artist?: string | null;
  src: string;
  artwork?: string | null;
  poster?: string | null;
  mediaType?: 'audio' | 'video' | 'youtube' | 'vimeo' | 'hls' | 'dash';
  downloadable?: boolean;
  duration?: number | null;
  countLabel?: string | null;
  countValue?: number | null;
};
```

For existing MP3 uploads:

```text
data-video-source = track.src
data-poster-source = track.artwork or default BlendBeats artwork
data-thumb-source = track.artwork or default thumbnail
```

## Player Modes

Keep existing modes:

```ts
type PlayerMode = 'standard' | 'lounge_live';
```

Map them to FWDUVPlayer behavior:

```text
standard
  single track or normal queue

lounge_live
  shared playlist, low volume, starts at server-calculated position
```

DJ Lounge needs the most careful migration because it uses shared live-state sync.

## DJ Lounge Live Sync

Current lounge behavior:

```text
GET /api/lounge/live-state
-> current_track
-> playlist
-> current_position_seconds
-> playlist_version
-> mode: lounge_live
```

Risk:

FWDUVPlayer API exposes `scrub(percent)`, not clearly `setCurrentTime(seconds)`.

Plan:

1. Test whether FWDUVPlayer has an undocumented seek-by-time method in `FWDUVPlayer-unminified.js`.
2. If only `scrub(percent)` exists, convert server seconds to percent after duration is known.
3. If duration is unknown, start track, wait for `UPDATE_TIME`/metadata, then scrub.
4. Keep drift correction every 30-60 seconds.

This needs a small proof of concept before replacing lounge playback.

## Global Bottom Player Design

The package has a built-in sticky display mode:

```text
displayType: "sticky"
showPlayerByDefault: "yes"
verticalPosition: "bottom"
horizontalPosition: "center"
```

But our current player is a custom bottom bar.

Recommended first implementation:

```text
Use FWDUVPlayer sticky/bottom mode.
Wrap it in a fixed bottom container controlled by PlayerProvider.
Style with minimal_skin_dark first.
```

After it works:

```text
Tune skin assets / colors to match BlendBeats red, yellow, black.
```

## Implementation Phases

### Phase 1: Static Asset Install

Copy only runtime player files to:

```text
public/vendor/fwduvp/
```

Add a loader:

```ts
loadFWDUVP(): Promise<void>
```

This loader should:

- inject `global.css`
- inject `FWDUVPlayer.js`
- resolve when `window.FWDUVPlayer` and `window.FWDUVPUtils` are available
- only load once

### Phase 2: Isolated React Test Harness

Create a private/dev route or hidden component:

```text
/player-test
```

Use one known public MP3 from the portfolio/mixes API.

Confirm:

- script loads
- skin loads
- MP3 plays
- visualizer appears
- play/pause works
- volume works
- player can be destroyed/remounted safely
- no duplicate global instances after React navigation

### Phase 3: Adapter Component

Create:

```text
FWDUVPPlayerHost.tsx
```

Responsibilities:

- render player mount div
- render hidden playlist markup
- instantiate `new FWDUVPlayer(...)`
- register API events
- relay state back to `PlayerProvider`
- expose imperative actions internally:
  - play
  - pause
  - stop
  - setVolume
  - loadPlaylist
  - playVideo
  - scrub

### Phase 4: PlayerProvider Engine Swap

Refactor `PlayerProvider.tsx`:

```text
State/context API stays the same.
HTMLAudioElement is replaced by FWDUVPlayer adapter.
```

Keep the old HTML audio engine behind a feature flag during testing:

```text
VITE_PLAYER_ENGINE=legacy|fwduvp
```

If no Vite env is wanted, use a constant:

```ts
const PLAYER_ENGINE = 'legacy' | 'fwduvp';
```

### Phase 5: Migrate Pages Without Page Rewrites

Because pages already use `usePlayer()`, most pages should keep working:

```text
mixes.tsx
DjPortfolioPage.tsx
djs.tsx
PublicDjProfilePage.tsx
DjLoungePage.tsx
FeaturedDjSidebarSpotlight.tsx
```

Only update track payloads where needed:

- add `mediaType`
- add `poster`
- normalize artwork fallback
- ensure stream URL is absolute or `/api/media/files/{id}/stream`

### Phase 6: Lounge Live Validation

Verify:

- entering lounge loads queue
- player mode shows DJ Lounge Live
- starts at correct live timestamp
- volume defaults to 30%
- playback does not reset during navigation
- drift sync still works
- autoplay blocked state still gives a start button/state

### Phase 7: Remove Legacy Player UI

After the new player is stable:

- remove old bottom bar markup
- remove custom `PlayerVisualizer`
- remove direct `<audio>` engine code
- keep `usePlayer()` and player types

## Risks

### React + Non-React Player Lifecycle

FWDUVPlayer is a global DOM-driven script. React can remount components frequently.

Mitigation:

- one singleton player instance
- stable `instanceName`
- explicit cleanup if supported
- avoid multiple hidden playlist IDs

### Bundle / Asset Size

`FWDUVPlayer.js` is about 765 KB minified.

Mitigation:

- load it only when needed
- serve from `public/vendor/fwduvp`
- do not bundle with Vite

### Security

The package includes PHP helper scripts for proxy/download/mail behavior.

Mitigation:

- do not deploy those helper scripts in phase 1
- keep all downloads/streaming through existing Laravel media routes

### Lounge Sync

The API clearly supports `scrub(percent)`, but live sync needs seconds.

Mitigation:

- test seek behavior first
- use percent conversion after duration is known
- keep old engine available until lounge sync passes

### Skin Customization

The skin is image-based.

Mitigation:

- start with `minimal_skin_dark`
- later customize PNGs or HEX settings where supported

## First Build Step

Do not replace the production player immediately.

Start with:

```text
1. Copy runtime assets to public/media/fwduvp.
2. Create FWDUVP loader.
3. Create isolated player test component/route.
4. Test one MP3 and one queue.
5. Then wire the adapter into PlayerProvider behind a feature flag.
```

## Installation And Modularity Review

### React vs Laravel Decision

Use React for the player class/hook.

Reason:

```text
FWDUVPlayer is a browser DOM player with global JavaScript, lifecycle events, playlists, and playback methods.
React owns the mounted player UI, persistent bottom player, route transitions, and the existing usePlayer() app contract.
Laravel should continue to own media routes, file authorization, stream URLs, and track metadata.
```

Laravel responsibilities:

- return playable `/media/...`, `/storage/...`, or `/api/media/files/{id}/stream` URLs
- keep private media protected
- track plays, views, permissions, lounge playlist state, and metadata
- expose payloads that React can convert into player sources

React responsibilities:

- load FWDUVPlayer CSS and script once
- create and destroy the player instance
- keep playback persistent across page navigation
- map BlendBeats tracks to FWDUVPlayer playlist items
- keep `usePlayer()` stable for pages

### Modular Files Added

```text
resources/js/React/Frontend/components/player/fwduvp-types.ts
resources/js/React/Frontend/components/player/fwduvp-loader.ts
resources/js/React/Frontend/components/player/useFWDUVPlayer.ts
resources/js/React/Frontend/components/player/fwduvp-playlist.ts
```

These files are intentionally small:

- `fwduvp-types.ts` declares the global player constructor and instance methods.
- `fwduvp-loader.ts` lazy-loads `/media/fwduvp/content/global.css` and `/media/fwduvp/java/FWDUVPlayer.js`.
- `useFWDUVPlayer.ts` exposes browser load status for future hosts/components.
- `fwduvp-playlist.ts` converts the current `PlayerTrack` shape into normalized FWDUVPlayer source data.

### Asset Install Status

The zip was reviewed and the safe runtime asset list is known.

Runtime assets are installed at:

```text
public/media/fwduvp
```

Installed from:

```text
resources/assets/video-player.zip:start/java/FWDUVPlayer.js
resources/assets/video-player.zip:start/content/global.css
resources/assets/video-player.zip:start/content/minimal_skin_dark/
resources/assets/video-player.zip:start/content/modern_skin_dark/
resources/assets/video-player.zip:start/content/fonts/
```

Installed file count:

```text
470
```

Safety check:

```text
No package PHP helpers or SWF files were copied.
Demo media folders were not copied.
```

## Final Recommendation

Adopt the new player as the engine and UI, but keep the BlendBeats `usePlayer()` hook as the platform-level playback contract.

This gives us the new player features without breaking the app pages that already call the global player.
