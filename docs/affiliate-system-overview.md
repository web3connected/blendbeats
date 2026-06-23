# BlendBeats Affiliate System Overview

## Goal

Build a BlendBeats affiliate system that lets existing users refer new users, track visits and signups, attribute credit to the correct affiliate, and award free membership credits without redesigning the core foundation.

The active BlendBeats affiliate reward is a free membership credit. Payout infrastructure exists in the codebase for a possible future program, but payouts are disabled by default and hidden from users in the current affiliate program.

## Current BlendBeats Setup

The affiliate system should fit the app that already exists:

- Backend: Laravel 13 on PHP 8.3.
- Frontend: React SPA under `resources/js/React`, served through `resources/views/welcome.blade.php` and routed by `BrowserRouter`.
- Public website routing: `routes/web.php` serves public Blade pages for news and falls through to the React app for most frontend routes.
- API routing: `routes/api.php` uses session-backed API routes with `AddQueuedCookiesToResponse`, `StartSession`, and the `public.auth` middleware for logged-in users.
- User auth: `App\Http\Controllers\Api\Auth\UserAuthController` handles register, login, logout, account updates, password reset, and avatar updates.
- Admin: AdminLTE under `routes/admin.php`, protected by the `admin.auth` guard, with Spatie permissions for admin center resources.
- Core user model: `App\Models\User` already supports notifications, billing, DJ profiles, media, mixes, playlist items, feature activations, and ad credits.
- Existing incentive foundation: `UserAdCreditService` grants registration featured-ad credits, and `GamificationService` records XP, levels, badges, and activity events.
- Existing billing foundation: Stripe Cashier is installed, PayPal subscription fields exist, and membership tiers live in `config/billing.php`.

Because of this setup, affiliate tracking should be added as a Laravel domain feature with API endpoints for the React account area and AdminLTE screens for staff.

## System Shape

```text
Affiliate System
+-- Affiliate Accounts
+-- Affiliate Campaigns
+-- Referral Links and Codes
+-- Referral Visit Tracking
+-- Signup Attribution
+-- Fraud Protection
+-- Reward System
+-- Affiliate Payouts
+-- Affiliate Notifications
+-- Affiliate Program Settings
+-- Affiliate Analytics
+-- Affiliate Dashboard
`-- Admin Management
```

## Affiliate Accounts

An affiliate account represents a BlendBeats user who can refer people.

Implemented behavior:

- A logged-in user can have one affiliate account.
- The affiliate account belongs to `users.id`.
- Affiliate status is trackable as `active`, `paused`, or `banned`.
- Public users and DJs can both become affiliates unless staff later limit eligibility.
- Affiliate profile data should be separate from the `users` table so the base account remains clean.

Implemented table:

- `affiliate_accounts`
  - `id`
  - `user_id`
  - `status`
  - `display_name`
  - `contact_email`
  - `joined_at`
  - `approved_at`
  - `paused_at`
  - `banned_at`
  - `metadata`
  - timestamps

## Affiliate Campaigns

Campaigns group referral codes and performance for promotions, seasonal pushes, influencer links, and targeted affiliate programs.

Implemented behavior:

- Staff can create and update campaigns from the Admin Center.
- Campaign status is trackable as `draft`, `active`, `paused`, `ended`, or `archived`.
- Referral codes can optionally be assigned to one campaign.
- A campaign-assigned referral code only captures visits when the campaign is `active` and inside its start/end window.
- Referral visits and signup referrals copy the campaign id at capture/attribution time so historical analytics stay stable.
- Campaign analytics are available in the AdminLTE analytics screen and admin JSON API.

Implemented table:

- `affiliate_campaigns`
  - `id`
  - `name`
  - `slug`
  - `status`
  - `description`
  - `starts_at`
  - `ends_at`
  - `created_by_admin_id`
  - `metadata`
  - timestamps

## Referral Links and Codes

Referral codes should be explicit records instead of only a field on `users`. That keeps the system flexible for future campaigns, influencer links, seasonal promotions, and staff-created codes.

Recommended MVP behavior:

- Each affiliate starts with one default code.
- Codes should be unique, URL-safe, and case-insensitive in lookup.
- A code can optionally point to a campaign.
- The public referral URL can use a short query parameter first, such as `/?ref=CODE` or `/register?ref=CODE`.

Recommended table:

- `affiliate_referral_codes`
  - `id`
  - `affiliate_account_id`
  - `affiliate_campaign_id`
  - `code`
  - `label`
  - `status`
  - `starts_at`
  - `expires_at`
  - `metadata`
  - timestamps

## Referral Visit Tracking

Referral visit tracking should capture the referral intent before signup. Since the current API is session-backed, the clean MVP is to store the referral code in the Laravel session and optionally a signed cookie.

Recommended MVP behavior:

- A referral landing request accepts `ref`.
- The backend validates the code and records a visit.
- If the code is assigned to a campaign, the campaign must be active and within schedule.
- The backend stores `affiliate_referral_code_id` in session for signup attribution.
- The referral context includes campaign id and campaign slug when present.
- Repeated visitor/device patterns are marked as suspicious on the visit record.
- Basic fraud and bot signals should be stored but not over-automated in phase 1.
- Attribution should survive normal React route changes because the first request lands through Laravel.

Recommended table:

- `affiliate_referral_visits`
  - `id`
  - `affiliate_referral_code_id`
  - `affiliate_account_id`
  - `affiliate_campaign_id`
  - `visitor_id`
  - `landing_url`
  - `referrer_url`
  - `ip_hash`
  - `user_agent_hash`
  - `utm_source`
  - `utm_medium`
  - `utm_campaign`
  - `visited_at`
  - `converted_user_id`
  - `converted_at`
  - `is_suspicious`
  - `suspicious_reason`
  - `suspicious_at`
  - `metadata`
  - timestamps

## Signup Attribution

Signup attribution should hook into `UserAuthController::register()` because registration already creates the user, grants the registration ad credit, sends notifications, and logs the new user into the `web` guard.

Recommended MVP behavior:

- When `/api/auth/register` succeeds, check the referral session/cookie.
- If a valid active referral code exists, create an attribution record.
- If the referral came from a campaign, copy that campaign id onto the signup referral.
- Prevent self-referrals by comparing the new user to the affiliate user.
- Prevent duplicate signup attribution by enforcing one signup attribution per referred user.
- Compare signup IP/user-agent hashes to the original visit hashes and flag mismatches for review.
- Mark the originating referral visit as converted when possible.

Recommended table:

- `affiliate_referrals`
  - `id`
  - `affiliate_account_id`
  - `affiliate_campaign_id`
  - `affiliate_referral_code_id`
  - `referred_user_id`
  - `affiliate_referral_visit_id`
  - `status`
  - `attribution_type`
  - `attributed_at`
  - `qualified_at`
  - `rejected_at`
  - `rejection_reason`
  - `is_suspicious`
  - `fraud_reason`
  - `fraud_flags`
  - `fraud_checked_at`
  - `metadata`
  - timestamps

Initial statuses:

- `pending`: signup was attributed but not yet qualified.
- `qualified`: the referral completed a qualifying action.
- `rejected`: self-referral, duplicate, suspicious, refunded, cancelled, or manually denied.

## Fraud Protection

Fraud protection is handled by `AffiliateFraudProtectionService` and is applied during referral visit capture, signup attribution, and subscription qualification.

Implemented automatic blocking:

- Self-referrals are rejected with reason `self_referral`.
- Duplicate attribution attempts are blocked and recorded against the existing referral with reason `duplicate_attribution`.
- Reused converted visits are not attributed again.

Implemented suspicious tracking:

- Repeated visitor sessions within 24 hours are marked on `affiliate_referral_visits`.
- Repeated IP hash plus user-agent hash patterns within 24 hours are marked on `affiliate_referral_visits`.
- Signup IP hash and user-agent hash are compared against the original referral visit.
- Signup device mismatches are marked on `affiliate_referrals` with reason `signup_device_mismatch`.
- Shared-device signup patterns are marked with reason `shared_device_signups`.

Reward protection:

- Suspicious pending referrals are blocked from automatic subscription qualification.
- Blocked qualification marks the referral rejected and records `qualification_blocked` in `fraud_flags`.
- Admin manual qualification can override a suspicious referral after review.

Admin visibility:

- `GET /admin/admincenter/affiliatereferrals` shows fraud review status, fraud reason, visit suspicious reason, IP/user-agent comparison, and fraud flags.
- Admin rejection records `rejection_reason`, `fraud_reason`, `fraud_flags`, and `fraud_checked_at`.
- Fraud reasons are searchable from the referral management screen.

## Qualifying Actions

Phase 1 can treat signup as the first tracked conversion but not necessarily the first payable reward.

Recommended qualification options for later phases:

- Referred user verifies email.
- Referred user creates a DJ profile.
- Referred user uploads a first mix or portfolio item.
- Referred user starts a paid membership.
- Referred user purchases a featured ad campaign.
- Referred user stays active for a minimum period.

This should become an event-based layer so rewards are not hard-coded into registration.

Implemented table:

- `affiliate_referral_events`
  - `id`
  - `affiliate_referral_id`
  - `event_type`
  - `event_source`
  - `target_type`
  - `target_id`
  - `transaction_type`
  - `transaction_id`
  - `event_hash`
  - `occurred_at`
  - `metadata`
  - timestamps

## Reward System

BlendBeats already has two reward-adjacent systems: ad credits and gamification. The affiliate reward layer should connect to those systems without merging into them.

Implemented approach:

- Keep affiliate rewards in their own records.
- Allow reward types such as `future_incentive`, `ad_credit`, `membership_credit`, `cash_commission`, `points`, or `manual`.
- A qualified subscription referral creates an issued `membership_credit` reward.
- Approved monetary rewards with `amount_cents` can become a payable balance only if payouts are enabled in a future program.
- Each membership credit is worth 30 days of DJ Plus and expires 12 months after issue if unused.
- Membership credits are uncapped and stack by extending the affiliate user's internal/free DJ Plus expiration.
- Expired unused membership credits move to `expired` and cannot be redeemed.
- For early non-cash rewards, use `UserAdCreditService` patterns to grant featured-ad credits.
- For engagement-style rewards, add affiliate-related `gamification_actions` later.
- Cash payouts use `affiliate_payouts` records linked back to approved monetary rewards when payout mode is enabled.

Implemented table:

- `affiliate_rewards`
  - `id`
  - `affiliate_account_id`
  - `affiliate_referral_id`
  - `affiliate_payout_id`
  - `reward_type`
  - `source`
  - `status`
  - `amount_cents`
  - `currency`
  - `points`
  - `quantity`
  - `membership_credit_days`
  - `available_at`
  - `expires_at`
  - `approved_at`
  - `issued_at`
  - `paid_at`
  - `redeemed_at`
  - `cancelled_at`
  - `voided_at`
  - `issued_reference`
  - `metadata`
  - timestamps

Implemented audit table:

- `affiliate_reward_audits`
  - `id`
  - `affiliate_reward_id`
  - `action`
  - `from_status`
  - `to_status`
  - `actor_type`
  - `actor_id`
  - `occurred_at`
  - `metadata`
  - timestamps

## Affiliate Payouts

Affiliate payouts convert approved monetary rewards into requested, approved, processing, paid, rejected, or cancelled payout records. This foundation is built but disabled in the active BlendBeats affiliate program.

Implemented behavior:

- Payouts are controlled by `AFFILIATE_PAYOUTS_ENABLED`, defaulting to disabled.
- When disabled, affiliate account APIs return `payouts_enabled: false`, zero payout balance, empty payout history, and block payout requests.
- When disabled, the React affiliate dashboard hides payout balance, request form, and payout history.
- When disabled, the AdminLTE Affiliate Payouts menu item is hidden.
- Payable balance is calculated from approved rewards with `amount_cents > 0`, `currency = USD`, and no payout assigned.
- If payouts are enabled later, affiliates can request a payout from `/api/account/affiliate/payouts`.
- A payout request links the eligible rewards to the payout and removes them from the available balance.
- Admins can approve, move to processing, mark paid, reject, or cancel payout requests.
- Marking a payout paid marks linked rewards as `paid` and writes reward audit records.
- Rejecting or cancelling a payout releases linked rewards back to the payable balance.
- Payout history is visible to affiliates through the account affiliate API and dashboard only when payouts are enabled.

Implemented table:

- `affiliate_payouts`
  - `id`
  - `affiliate_account_id`
  - `requested_by_user_id`
  - `processed_by_admin_id`
  - `status`
  - `amount_cents`
  - `currency`
  - `reward_count`
  - `payment_method`
  - `payout_reference`
  - `requested_at`
  - `approved_at`
  - `processing_at`
  - `paid_at`
  - `rejected_at`
  - `cancelled_at`
  - `rejection_reason`
  - `notes`
  - `metadata`
  - timestamps

## Affiliate Dashboard

The affiliate dashboard should live in the existing authenticated React account area.

Recommended route:

- `/account/affiliate`

Recommended API routes:

- `GET /api/account/affiliate`
- `POST /api/account/affiliate`
- `GET /api/account/affiliate/referrals`
- `GET /api/account/affiliate/rewards`
- `GET /api/account/affiliate/payouts`
- `POST /api/account/affiliate/payouts`

Dashboard MVP content:

- Affiliate status.
- Default referral code.
- Copyable referral link.
- Visit count.
- Signup count.
- Qualified referral count.
- Pending and issued rewards.
- Membership credit days, expiration date, and redemption status.
- Redeemable membership credits can be applied to the existing internal/free DJ Plus subscription feature.
- Payout features remain hidden while `AFFILIATE_PAYOUTS_ENABLED=false`.
- Recent referral activity.

This should follow the existing account patterns used by dashboard, billing, notifications, badges, featured ads, and profile pages.

## Affiliate Notifications

Affiliate notifications use the existing Laravel database notifications table and appear through the current account notification UI.

Implemented notification events:

- Affiliate account created.
- Referral signup attributed.
- Referral subscription qualified.
- Membership credit issued.
- Membership credit redeemed.
- Membership credit expiring soon.
- Membership credit expired.

Notification behavior:

- Notifications are sent to the affiliate account holder.
- Affiliate notifications use category `affiliate`.
- Each event stores duplicate-prevention markers in affiliate account, referral, or reward metadata.
- The daily `affiliate:expire-membership-credits` command sends expiring-soon notices before expiring unused credits.

## Affiliate Program Settings

Affiliate reward rules are read through `AffiliateProgramSettings`, backed by `config/affiliate.php` with environment overrides.

Implemented configurable settings:

- Reward plan: `AFFILIATE_REWARD_PLAN`.
- Qualification event: `AFFILIATE_QUALIFICATION_EVENT`.
- Membership credit tier: `AFFILIATE_MEMBERSHIP_CREDIT_TIER`.
- Membership credit duration: `AFFILIATE_MEMBERSHIP_CREDIT_DAYS`.
- Membership credit expiration: `AFFILIATE_MEMBERSHIP_CREDIT_EXPIRES_AFTER_MONTHS`.
- Expiring-soon notification window: `AFFILIATE_MEMBERSHIP_CREDIT_EXPIRING_SOON_DAYS`.
- Payout availability: `AFFILIATE_PAYOUTS_ENABLED`, disabled by default.

Admin visibility:

- `GET /admin/admincenter/affiliatesettings` shows the currently loaded affiliate program settings.
- The settings screen is read-only and uses the existing `affiliates.view` permission.

## Affiliate Analytics

Program-level analytics are provided by `AffiliateAnalyticsService` so the AdminLTE screen and JSON API share the same calculations.

Implemented analytics:

- Total affiliates.
- Active affiliates.
- Total referral visits.
- Total attributed signups.
- Total qualified referrals.
- Total membership credits issued.
- Total membership credits redeemed.
- Total membership credits expired.
- Total payable balance.
- Total payout requests.
- Total paid payout count and amount.
- Visit-to-signup conversion rate.
- Signup-to-qualified conversion rate.
- Visit-to-qualified conversion rate.
- Top affiliates leaderboard ranked by qualified referrals, signups, then visits.
- Campaign-level analytics with assigned codes, visits, signups, qualified referrals, membership credits, and conversion rates.
- Payout analytics with payable, requested, approved/processing, paid, rejected, and cancelled totals.

Implemented analytics surfaces:

- `GET /admin/admincenter/affiliateanalytics` shows the AdminLTE analytics report.
- `GET /api/admin/affiliate-analytics` returns the analytics report as JSON for administrators with `affiliates.view`.

## Admin Management

Admin tools live in the existing AdminLTE Admin Center and use the `admin` guard with Spatie permissions.

Implemented admin routes:

- `GET /admin/admincenter/affiliates`
- `PATCH /admin/admincenter/affiliates/{affiliate}/status`
- `GET /admin/admincenter/affiliatecampaigns`
- `POST /admin/admincenter/affiliatecampaigns`
- `PATCH /admin/admincenter/affiliatecampaigns/{campaign}`
- `PATCH /admin/admincenter/affiliatecodes/{code}/campaign`
- `GET /admin/admincenter/affiliatereferrals`
- `PATCH /admin/admincenter/affiliatereferrals/{referral}/status`
- `GET /admin/admincenter/affiliaterewards`
- `PATCH /admin/admincenter/affiliaterewards/{reward}/status`
- `GET /admin/admincenter/affiliatepayouts`
- `PATCH /admin/admincenter/affiliatepayouts/{payout}/status`
- `GET /admin/admincenter/affiliatesettings`
- `GET /admin/admincenter/affiliateanalytics`

Implemented admin capabilities:

- View affiliate accounts with search, status filtering, summary stats, referral counts, and reward counts.
- Activate, pause, or ban affiliate accounts.
- Create, update, search, and filter affiliate campaigns.
- Assign referral codes to campaigns.
- View campaign-level visits, signups, qualification counts, reward counts, and conversion rates.
- View signup referrals with search, status filtering, qualification stats, event counts, and reward counts.
- View referral fraud review status, fraud reasons, visit suspicious reasons, IP/user-agent comparison, and fraud flags.
- Move referrals between `pending`, `qualified`, and `rejected`.
- Reject referrals with fraud reasons and preserve review history.
- Manually qualify a referral while still creating the qualification event and reward foundation records.
- View rewards with search, status filtering, reward stats, issuance details, and audit counts.
- Move rewards through `pending`, `approved`, `issued`, `paid`, `cancelled`, and `voided`.
- Record reward status transitions in `affiliate_reward_audits` with the acting admin.
- View payout requests with search, status filtering, payable balance, requested totals, and paid totals when payout mode is enabled.
- Move payouts through `requested`, `approved`, `processing`, `paid`, `rejected`, and `cancelled`.
- Record payout references, rejection reasons, notes, and linked reward payment audits.

Implemented admin permissions:

- `affiliates.view`
- `affiliates.update`
- `affiliatereferrals.view`
- `affiliatereferrals.update`
- `affiliaterewards.view`
- `affiliaterewards.update`
- `affiliatepayouts.view`
- `affiliatepayouts.update`

## Attribution Rules

MVP attribution should be simple and predictable:

- Use last valid referral code before signup.
- Store attribution in session and optionally a signed cookie.
- Expire referral attribution after a configurable window, such as 30 days.
- Reject self-referrals.
- Reject duplicate referral records for the same referred user.
- Keep all rejected records visible to admins when useful for audits.

Future attribution options:

- First-touch attribution.
- Campaign-specific attribution windows.
- Multi-touch reporting.
- Device fingerprint or anonymous visitor ID improvements.
- Coupon or promo-code attribution from checkout.

## Integration Points

Recommended backend services:

- `AffiliateService`
  - creates accounts and codes
  - resolves referral codes
  - records visits
  - attributes signups
  - records qualifying events

- `AffiliateRewardService`
  - evaluates rewards
  - issues ad credits, points, or commissions
  - handles reward status transitions

Recommended middleware:

- `CaptureAffiliateReferral`
  - checks `ref`
  - validates active referral code
  - stores referral context in session/cookie
  - records visits

Recommended controller touchpoints:

- `UserAuthController::register()` for signup attribution.
- Billing checkout/subscription approval for paid membership qualification.
- Featured ad checkout/capture for promotion purchase qualification.
- DJ profile and media upload controllers for creator activity qualification.
- Gamification events later for XP or badge-based affiliate rewards.

## Phase Plan

## Phase 1: Tracking and Signup Attribution

- Add affiliate account, code, visit, and referral tables.
- Add models and relationships on `User`.
- Add referral capture middleware.
- Add signup attribution in registration.
- Add tests for valid referral, invalid code, self-referral, duplicate attribution, and expired attribution.

## Phase 2: Affiliate Account API and Dashboard

- Add authenticated account API endpoints.
- Add `/account/affiliate` React page.
- Show link, code, counts, referral status, and recent activity.

## Phase 3: Admin Tools

- Add AdminLTE affiliate screens.
- Add permissions and admin menu links.
- Add status management for accounts, referrals, and rewards.

## Phase 4: Reward Events

- Add qualifying event tracking.
- Add simple reward records.
- Start with non-cash rewards such as featured-ad credits or XP.
- Add notifications when referrals qualify or rewards are issued.

## Phase 5: Optional Payouts and Advanced Reporting

- If BlendBeats later activates cash rewards, enable payout mode, add date-range reports, campaign exports, payout exports, and advanced leaderboards.

## Notes

- Do not add affiliate fields directly to `users` except relationships.
- Keep referral codes as records so future campaigns do not require a redesign.
- Keep rewards separate from attribution so the program can change without losing referral history.
- Reuse Laravel sessions, notifications, ad credits, gamification, billing, and AdminLTE instead of creating a separate affiliate stack.
