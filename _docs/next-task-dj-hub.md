# DJ Hub Build Task

## Stage Goal

Turn `/djs` into the BlendBeats discovery engine where visitors can find public DJs by name, genre, location, DJ type, booking availability, and platform momentum.

## Phase 1 Scope

- Replace the placeholder DJs page with a real DJ Hub directory.
- Add public API endpoints for listing DJs and viewing one DJ by handle.
- Surface public DJ profile data from `dj_profiles`, `users`, `dj_genres`, `dj_booking_settings`, and public media uploads.
- Add database foundations for featured placement and follows.
- Keep follow actions and public profile detail as the next implementation step.

## Backend

- Create `DjHubController`.
- Add `GET /api/dj-hub/djs`.
- Add `GET /api/dj-hub/djs/{handle}`.
- Add migration for `dj_featured_status`.
- Add migration for `followers`.
- Add model relations for followers and featured status.

## Frontend

- Create `src/lib/dj-hub.ts`.
- Replace `src/pages/djs.tsx` with:
  - Hero/search band
  - Filter rail
  - DJ card grid
  - Empty/loading/error states
  - Featured badge readiness

## Next Step After Phase 1

- Add public DJ profile page at `/djs/:handle`.
- Add authenticated follow/unfollow actions.
- Add activity feed events for profile creation, uploads, and follows.
