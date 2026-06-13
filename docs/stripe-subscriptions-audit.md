# Stripe Subscriptions Audit and Checkout Task Plan

Date: 2026-06-13

## Goal

Wire the BlendBeats Pricing and Subscription pages to Stripe test checkout for DJ membership tiers, then use Stripe webhooks to keep the local membership tier, storage limits, and promotion access in sync.

This is a planning audit only. No checkout flow was implemented in this pass.

## Current State

### Already Present

- `laravel/cashier` is installed in `composer.json`.
- `App\Models\User` uses `Laravel\Cashier\Billable`.
- Cashier migrations exist:
  - `create_customer_columns`
  - `create_subscriptions_table`
  - `create_subscription_items_table`
  - meter-related subscription item columns
- Stripe environment placeholders exist in `.env.example`:
  - `STRIPE_KEY`
  - `STRIPE_SECRET`
  - `STRIPE_WEBHOOK_SECRET`
  - `STRIPE_MODE`
  - `CASHIER_CURRENCY`
  - `CASHIER_PATH`
  - `STRIPE_PRICE_DJ_PLUS`
  - `STRIPE_PRICE_DJ_PRO`
  - `STRIPE_PRICE_DJ_ELITE`
- Membership tiers are centralized in `config/billing.php`.
- Pricing page exists at `/pricing`.
- Subscription page exists at `/subscription`.
- Dashboard and settings already link users toward pricing/subscription areas.
- Membership tier currently drives storage limits through `MembershipTierService` and `MediaStorageQuotaService`.

### Current Frontend Behavior

- `/pricing` shows the membership model, but paid tiers display `Soon`.
- Logged-in users are sent to `/subscription?plan={tier}`.
- Guests are sent to `/register`.
- `/subscription` requires login and lets the user select a plan.
- The checkout button is disabled and says Stripe checkout is coming next.

### Current Backend Behavior

- There are no public-auth billing API routes yet.
- There is no checkout-session endpoint yet.
- There is no customer portal endpoint yet.
- There is no app-level subscription status endpoint yet.
- Cashier webhook handling is not wired into app-specific tier updates yet.

## Gaps and Risks

### Tier Default Mismatch

`config/billing.php` uses `free` as the free tier, but `database/migrations/2026_06_10_010000_add_account_fields_to_users_table.php` defaults `media_storage_tier` to `starter`.

Task:
- Change the app default to `free`.
- Add a migration or data cleanup step to convert existing `starter` users to `free`.

### Price Source Mismatch

Pricing details are duplicated in React page constants and `config/billing.php`.

Task:
- Add a backend billing plans endpoint.
- Have `/pricing` and `/subscription` render from backend plan data.
- Keep Stripe price IDs server-only.

### Checkout Security

The frontend should never send arbitrary Stripe price IDs.

Task:
- Frontend sends only a known plan key: `dj_plus`, `dj_pro`, or `dj_elite`.
- Backend validates plan key against `config/billing.php`.
- Backend resolves the matching Stripe price ID from env/config.

### Subscription Sync

Cashier will store subscription records, but BlendBeats also depends on `users.media_storage_tier`.

Task:
- On successful checkout/webhook, update `media_storage_tier`.
- On cancellation, expiration, failed payment, or downgrade to free, reset or adjust tier.
- Avoid trusting frontend state for tier changes.

## Proposed User Flow

### Guest Flow

1. User opens `/pricing`.
2. User clicks a paid plan.
3. User is sent to `/register`.
4. After login/register, send user to `/subscription?plan={selectedPlan}` if possible.
5. User clicks checkout from subscription page.

### Logged-In Flow

1. User opens `/pricing`.
2. User clicks `DJ Plus`, `DJ Pro`, or `DJ Elite`.
3. User lands on `/subscription?plan={tier}`.
4. User clicks `Continue To Checkout`.
5. Backend creates a Stripe Checkout session.
6. Browser redirects to Stripe Checkout.
7. On success, user returns to `/subscription/success?session_id=...`.
8. Webhook confirms subscription and updates local tier.

### Existing Subscriber Flow

1. User opens `/subscription`.
2. Page shows current tier and subscription status.
3. If subscribed, user can open Stripe customer portal.
4. Plan changes/cancellations happen through Stripe portal or a later custom upgrade flow.

## Backend Tasks

### 1. Billing Controller

Create `App\Http\Controllers\Api\BillingController`.

Endpoints:
- `GET /api/billing/plans`
- `GET /api/billing/subscription`
- `POST /api/billing/checkout`
- `POST /api/billing/portal`

Middleware:
- Plans endpoint can be public.
- Subscription, checkout, and portal require `public.auth` plus session middleware.

### 2. Plans Endpoint

Return sanitized plan data from `config/billing.php`:
- key
- name
- display price
- storage
- advertising groups
- features
- current user tier if authenticated
- purchasable boolean

Do not expose Stripe secret keys.
Do not expose raw env structure.

### 3. Checkout Endpoint

Request body:

```json
{
  "plan": "dj_pro"
}
```

Behavior:
- Validate plan key.
- Reject `free` checkout.
- Confirm tier has a configured Stripe price ID.
- Create or reuse Stripe customer through Cashier.
- Create subscription checkout session using Cashier.
- Set success and cancel URLs.
- Return `{ "url": "https://checkout.stripe.com/..." }`.

### 4. Customer Portal Endpoint

Behavior:
- Require authenticated user.
- Create Stripe billing portal session with Cashier.
- Return portal URL.

### 5. Webhook Handling

Wire Cashier webhook route/config and add app-specific tier sync.

Events to handle:
- `checkout.session.completed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_failed`

Sync rule:
- Map active subscription price ID to tier key.
- If no active paid subscription exists, set tier to `free`.
- Keep all tier changes server-side.

### 6. Tier Mapping Service

Create a small service such as `SubscriptionTierSyncService`.

Responsibilities:
- Map Stripe price ID to BlendBeats tier key.
- Determine active membership subscription.
- Update `users.media_storage_tier`.
- Keep logic out of controllers.

### 7. Data Cleanup

Fix existing tier mismatch:
- Convert `starter` to `free`.
- Update migration/default behavior for future users.
- Confirm seeded users use a valid tier.

## Frontend Tasks

### 1. Shared Billing API Client

Create `resources/js/React/Frontend/lib/billing.ts`.

Functions:
- `getBillingPlans()`
- `getSubscriptionStatus()`
- `startCheckout(planKey)`
- `openBillingPortal()`

### 2. Pricing Page

Update `/pricing`:
- Load plans from backend.
- Show actual configured monthly price labels.
- Keep free tier CTA as register/dashboard/subscription depending on auth state.
- Paid tier CTA sends logged-in users to `/subscription?plan={tier}`.
- Guest users go to register/login first.

### 3. Subscription Page

Update `/subscription`:
- Load selected plan from backend.
- Show current membership tier.
- Enable `Continue To Checkout` for paid tiers with configured Stripe price IDs.
- Show setup warning if a price ID is missing.
- Show `Manage Billing` when the user already has a Stripe subscription/customer.

### 4. Success and Cancel Pages

Add lightweight routes:
- `/subscription/success`
- `/subscription/cancel`

Success page:
- Tell user payment is processing.
- Refresh auth/subscription state.
- Link to dashboard.

Cancel page:
- Let user return to pricing/subscription.

## Stripe Test Setup Tasks

1. Create Stripe test products:
   - DJ Plus
   - DJ Pro
   - DJ Elite
2. Create recurring monthly test prices.
3. Set env values:
   - `STRIPE_PRICE_DJ_PLUS`
   - `STRIPE_PRICE_DJ_PRO`
   - `STRIPE_PRICE_DJ_ELITE`
4. Configure webhook endpoint:
   - local: Stripe CLI forwarding to app
   - live test server: Forge/site webhook URL
5. Set `STRIPE_WEBHOOK_SECRET`.
6. Run migrations on local and server.
7. Clear config cache after env changes.

## Test Checklist

### Local

- Guest pricing CTA routes to register.
- Logged-in pricing CTA routes to subscription selected plan.
- Free plan does not create checkout.
- Paid plan creates Stripe Checkout session.
- Checkout success redirects back to app.
- Webhook updates `media_storage_tier`.
- Dashboard shows upgraded tier.
- Storage quota changes after tier upgrade.
- Customer portal opens for subscribed users.
- Cancelled subscription returns tier to free.
- Missing Stripe price ID gives a clean UI message, not a 500.

### Server

- `php artisan migrate --force`
- `php artisan config:clear`
- `php artisan route:clear`
- `php artisan optimize:clear`
- Confirm webhook endpoint reaches the app.
- Confirm production/test mode keys match the current environment.

## Recommended Implementation Order

1. Fix tier default mismatch from `starter` to `free`.
2. Add billing plans/status API.
3. Update pricing/subscription pages to use API plan data.
4. Add checkout endpoint.
5. Add success/cancel routes.
6. Add webhook tier sync service.
7. Add customer portal endpoint.
8. Test with Stripe test cards.
9. Push and deploy.

## Open Decisions

- Monthly prices for DJ Plus, DJ Pro, and DJ Elite need final values.
- Decide whether annual plans are needed now or later.
- Decide whether upgrades/downgrades happen only through Stripe portal for MVP.
- Decide whether failed payment should immediately reduce storage access or give a grace period.
- Decide how to handle uploaded media if a user downgrades below current storage usage.
