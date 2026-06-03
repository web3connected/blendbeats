# Next Task: Database Connection and Feature Check

Date: 2026-06-02
App: BlendBeats / The Blend Battlegrounds
Current dev URL: `http://localhost:3150/`

## Goal

Connect the app to a real database and start turning the visible BlendBeats feature set from static homepage content into working product flows.

## Current App State

The app now boots on Vite at port `3150`.

Verified:

```text
GET /           -> 200 OK
GET /api/health -> 200 OK, {"ok":true}
```

The frontend is currently mostly static. The homepage displays data for DJ battles, top mixes, top DJs, merch, and gear, but those records are hardcoded inside `src/pages/index.tsx`.

The server currently has only one API endpoint:

```text
GET /api/health
```

## Features Currently Visible in the App

Visible or advertised features:

- DJ battles with vote counts and live status.
- Mix cards with DJ name, title, genre, rating, and play count.
- DJ leaderboard teaser with rank, wins, genre, and rating.
- Merch and DJ gear shopping sections.
- Cookie consent and analytics consent behavior.
- Header and footer navigation for Battles, Mixes, Merch, Gear, DJs, and Leaderboard.
- Health API endpoint.
- SEO helpers for `robots.txt` and `sitemap.xml`.

## Gaps Found During Feature Check

Only these routes are registered:

```text
/
*
```

The UI links to routes that do not exist yet:

```text
/battles
/mixes
/merch
/gear
/djs
/leaderboard
/gear/turntables
/gear/mixers
```

Expected result today: those links fall through to the 404 page.

No database client exists yet. The production server shutdown path already looks for a future `./db/client.js`, but there is no `src/server/db` implementation yet.

## Recommended Database Choice

Use PostgreSQL unless there is a specific reason to choose another database.

Why PostgreSQL fits this app:

- Battles, votes, DJs, mixes, products, orders, and ratings are relational.
- It can handle leaderboard queries cleanly.
- It is easy to add migrations later.
- It works well with common Node ORMs and query builders.

Suggested stack:

```text
Database: PostgreSQL
ORM: Prisma or Drizzle
Env var: DATABASE_URL
```

Prisma is the friendlier first pass. Drizzle is lighter and more SQL-forward. Either works.

## First Database Schema Draft

Start with these tables/models:

```text
users
djs
genres
mixes
mix_ratings
battles
battle_votes
products
orders
order_items
```

Minimum fields:

```text
users:
- id
- email
- display_name
- role
- created_at
- updated_at

djs:
- id
- user_id
- stage_name
- bio
- home_city
- primary_genre_id
- rating_average
- wins
- losses
- created_at
- updated_at

genres:
- id
- name
- slug

mixes:
- id
- dj_id
- title
- genre_id
- audio_url
- cover_image_url
- play_count
- rating_average
- published_at
- created_at
- updated_at

mix_ratings:
- id
- mix_id
- user_id
- rating
- created_at

battles:
- id
- dj1_id
- dj2_id
- genre_id
- status
- starts_at
- ends_at
- created_at
- updated_at

battle_votes:
- id
- battle_id
- user_id
- voted_for_dj_id
- created_at

products:
- id
- name
- slug
- category
- description
- price_cents
- image_url
- inventory_count
- active
- created_at
- updated_at

orders:
- id
- user_id
- status
- total_cents
- created_at
- updated_at

order_items:
- id
- order_id
- product_id
- quantity
- unit_price_cents
```

## API Routes To Add First

Start read-only, then add writes.

Read endpoints:

```text
GET /api/battles
GET /api/mixes
GET /api/djs
GET /api/leaderboard
GET /api/products
GET /api/products/:slug
```

Write endpoints:

```text
POST /api/battles/:id/votes
POST /api/mixes/:id/ratings
POST /api/orders
```

Admin or future endpoints:

```text
POST /api/mixes
POST /api/battles
POST /api/products
PATCH /api/products/:id
```

## Frontend Pages To Build Next

Create routes and pages for:

```text
/battles
/mixes
/merch
/gear
/djs
/leaderboard
```

Suggested first pass:

- `/battles`: list active battles and allow a vote action.
- `/mixes`: list top mixes and allow rating.
- `/djs`: list DJ profiles.
- `/leaderboard`: rank DJs by wins, battle votes, or rating.
- `/merch`: product list filtered to merch.
- `/gear`: product list filtered to gear.

## Implementation Checklist

1. Pick ORM: Prisma or Drizzle.
2. Add `DATABASE_URL` to `.env` and `env.example`.
3. Install database package and ORM tooling.
4. Add `src/server/db/client.ts`.
5. Add schema and migrations.
6. Seed the database with the current homepage data.
7. Replace hardcoded homepage arrays with API-backed data.
8. Add the missing routes and pages.
9. Add API error handling and validation with `zod`.
10. Add tests for health, database connection, and first feature APIs.

## Feature Test Checklist

Manual checks after database connection:

- Homepage still loads.
- `/api/health` still returns `{"ok":true}`.
- New database health check confirms connection without exposing secrets.
- Battles list loads from DB.
- Mixes list loads from DB.
- DJs list loads from DB.
- Leaderboard loads from DB.
- Merch and gear products load from DB.
- Missing route still shows 404.
- Build and type-check pass.

Commands:

```powershell
npm.cmd run type-check
npm.cmd run build
npm.cmd run dev -- --host 0.0.0.0 --port 3150 --strictPort
```

## Open Decision

Choose the database/ORM combination before coding:

```text
Recommended: PostgreSQL + Prisma
Alternative: PostgreSQL + Drizzle
```

The next coding task should be: add the database client, schema, seed data, and the first read-only endpoints for battles, mixes, DJs, leaderboard, and products.
