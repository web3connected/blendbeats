# BlendBeats Documentation Center

## Overview

The Documentation Center is the signed-in user help area for BlendBeats. It lives inside the account area and centralizes feature explanations, onboarding notes, workflow references, FAQs, and future tutorial foundations.

## User Routes

- `/account/docs` - Documentation Center index with category navigation and search
- `/account/docs/{slug}` - Documentation article page
- `/account/support/docs/{topic}` - Legacy support-doc route that redirects into the Documentation Center

## Categories

- Getting Started
- Account
- Memberships
- Affiliate Program
- DJ Features
- Marketplace
- Community
- FAQs

## Admin Route

- `/admin/admincenter/documentation` - Read-only documentation inventory and management foundation

The first implementation uses static article data in `resources/js/React/Frontend/lib/documentation.ts`. Admin inventory metadata is mirrored in `config/documentation.php` until article editing, markdown, screenshots, videos, and database-backed publishing are added.
