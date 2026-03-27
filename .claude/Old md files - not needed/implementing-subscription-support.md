# Implementing Subscription Support

## Overview
Add subscription billing to Vantage using Laravel Cashier (Stripe). Firm-level billing with per-seat pricing and optional flat-rate add-ons.

## Current Status
Steps 1–6 are complete. The core subscription infrastructure is in place: Stripe integration, checkout flow, free-plan file limit enforcement, and seat syncing on member creation. See "Not Yet Built" and "Suggestions" sections below for remaining work.

---

## Billing Model
- **Firm-level billing** — the `Firm` model gets Cashier's `Billable` trait
- **Per-seat pricing** for the base plan (quantity = number of active firm members)
- **Flat pricing for add-ons** (firm-wide, not per-seat)
- Monthly, quarterly, and yearly intervals for both base plan and add-ons

## Seat Enforcement Logic
1. Firm subscribes with quantity = current active firm member count
2. `firms.max_users` set based on subscription quantity
3. Adding a user checks `current firm member count < max_users`
4. At the limit, prompt to add seats (updates Stripe subscription quantity)
5. User count syncing needed when firm members are created, deactivated, or reactivated

## App vs Marketing Site
- **Start with single Laravel app** (public routes for marketing, auth routes for app)
- Can migrate to subdomain separation (`app.vantage.com`) later if needed
- Laravel handles subdomain routing natively

---

## Implementation Steps

### Step 1: Set up Stripe account (test mode) — DONE
- Stripe account created with test/sandbox mode
- Walked through Stripe's setup guide (pricing model, payment acceptance, recurring product)
- Test API keys available (`sk_test_` / `pk_test_`)
- Add keys to `.env`:
  - `STRIPE_KEY=pk_test_...`
  - `STRIPE_SECRET=sk_test_...`
  - `STRIPE_WEBHOOK_SECRET=whsec_...` (set up later when configuring webhooks)

### Step 2: Install Cashier and run migrations — DONE
- `composer require laravel/cashier`
- Cashier publishes migrations that add columns to the billable model (`firms`) and create `subscriptions` / `subscription_items` tables
- Run `php artisan vendor:publish --tag="cashier-migrations"` to publish and review before migrating
- Add `Billable` trait to `Firm` model
- Run custom migrations for app-specific tables (see Database Changes below)

### Step 3: Create products/prices in Stripe dashboard — DONE
- Created "Vantage Standard Subscription" product in Stripe (test mode)
  - Product ID: `prod_U46ORXNxf1F9bP`
  - Monthly: `price_1T626oRa3uhe8u1pbkk4ecGe` — $15/user/month
  - Quarterly: `price_1T626KRa3uhe8u1phAARGpGF` — $36/user/quarter
  - Yearly: `price_1T5yBkRa3uhe8u1paouuucbu` — $120/user/year
  - Max users: unlimited
- Added `stripe_price_monthly`, `stripe_price_quarterly`, `stripe_price_yearly` columns to `plans` table (migration: `2026_03_01_051544_add_stripe_price_ids_to_plans_table`)
- Created `PlanSeeder` with all Stripe IDs and display prices (amounts stored in cents)
- Added `PlanSeeder` to `DatabaseSeeder`
- No add-on products created yet — base plan only for now
- Note: the `plans` table is a custom app table, not a Cashier table — Cashier doesn't manage it

### Step 4: Build checkout flow — DONE
- Created `SubscriptionController` with 4 methods:
  - `index` — shows plan info + subscription status (admin-only)
  - `checkout` — creates Stripe Checkout session with per-seat quantity, redirects via `Inertia::location()`
  - `success` — redirects back to index with flash message (webhook handles actual activation)
  - `billingPortal` — redirects to Stripe Customer Portal for self-service billing management
- Created `Subscription/Index.vue` — two-state page:
  - **Not subscribed:** billing interval selector (monthly/quarterly/yearly), per-seat price breakdown, total price, Subscribe button
  - **Subscribed:** current plan details, status, seat count, Manage Billing button
- Added 4 routes in `web.php` (inside `auth + welcomed` group):
  - `GET /subscription` — index
  - `POST /subscription/checkout` — checkout
  - `GET /subscription/success` — success redirect
  - `GET /subscription/billing-portal` — Stripe portal redirect
- Updated `HandleInertiaRequests` to share globally:
  - `subscription.is_subscribed` (boolean) — available on all pages via `$page.props`
  - `subscription.file_count`, `subscription.file_limit`, `subscription.can_create_files` — file limit info
  - `flash.message` — session flash messages
- Added Subscription button to `AdminMenu.vue`
- Webhook handling is automatic via Cashier's built-in `POST /stripe/webhook` route
- Skipped `Firm.plan()` relationship — not needed; subscription status checked via Cashier's `subscribed('default')`

### Step 5: Enforce free-plan file limit — DONE
- Free plan = no Stripe subscription = 10-file limit per firm
- Paid plan = active Stripe subscription = unlimited files
- **Firm model helpers added:**
  - `fileCount()` — returns total files for the firm
  - `fileLimit()` — returns `10` (free) or `null` (subscribed/unlimited)
  - `canCreateFiles()` — boolean check combining the above
- **Backend enforcement:**
  - `FileController@create` — redirects to files index if at the limit (blocks direct URL access)
  - `FileController@store` — rejects with validation error if at the limit
- **Frontend (Files/Index.vue):**
  - Progress bar showing "X of 10 files used" (only visible on free plan)
  - "Limit reached" label + progress bar turns red when at 10
  - Add button disabled when at the limit
  - Alt+A keyboard shortcut blocked when at the limit
  - Upgrade link for admins (goes to `/subscription`) or "Contact your firm admin" for non-admins
- Existing files are never deleted or locked — only new file creation is blocked

### Step 6: Add seat syncing — DONE
- Added `Firm::syncSubscriptionQuantity()` method:
  - Counts active firm member contacts (`Contact::firmMembers()->active()->count()`)
  - Calls `$this->subscription('default')->updateQuantity($seatCount)` on Stripe
  - No-ops silently if the firm has no active subscription
- Hooked into firm member creation points:
  - `UserController::store()` — admin adds a user from Users page
  - `WelcomeController::postWelcomeAdmin()` — admin adds a user during the welcome flow
- Deactivation/reactivation hooks deferred — no UI for toggling `contacts.current` exists yet

---

## What's Built (file inventory)

| File | What it does |
|------|-------------|
| `app/Models/Firm.php` | `Billable` trait, `syncSubscriptionQuantity()`, `fileCount()`, `fileLimit()`, `canCreateFiles()`, `addons()` relationship |
| `app/Http/Controllers/SubscriptionController.php` | `index`, `checkout`, `success`, `billingPortal` — admin-only |
| `resources/js/Pages/Subscription/Index.vue` | Plan selection + checkout (unsubscribed) / plan details + manage billing (subscribed) |
| `app/Http/Controllers/FileController.php` | File limit checks in `create()` and `store()` |
| `resources/js/Pages/Files/Index.vue` | File usage progress bar, disabled Add button at limit, upgrade prompt |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shares `subscription.*` and `flash.message` globally |
| `resources/js/Pages/AdminMenu.vue` | Subscription button for admins |
| `database/migrations/*_add_stripe_price_ids_to_plans_table.php` | Stripe price ID columns on `plans` |
| `database/seeders/PlanSeeder.php` | Seeds the standard plan with Stripe IDs and display prices |

---

## Not Yet Built

### Environment / Infrastructure
- **Stripe API keys not added to `.env`** — test keys exist in Stripe dashboard but haven't been placed in the `.env` file yet
- **Stripe webhook endpoint not configured** — Cashier auto-registers `POST /stripe/webhook`, but Stripe needs to be told to send events there. Requires: create webhook in Stripe dashboard pointing to `https://yourdomain.com/stripe/webhook`, select relevant events (e.g., `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.payment_succeeded`, `invoice.payment_failed`), then put the signing secret in `.env` as `STRIPE_WEBHOOK_SECRET`
- **Production Stripe keys** — only test keys exist so far

### User Deactivation / Reactivation
- No UI exists to toggle a firm member's `contacts.current` field ('C' → 'I' or vice versa)
- When this is built, call `$firm->syncSubscriptionQuantity()` after changing `current`
- Should also handle edge case: deactivating a user should decrease the Stripe seat count, reactivating should increase it

### Seat Limit Enforcement on User Creation
- Currently, adding a user syncs the seat count to Stripe (billing goes up) but does NOT prevent adding users beyond the subscription quantity
- Consider: should adding a user be blocked if the firm is at their seat cap? Or should it just auto-increment the Stripe quantity and charge more? Current behavior is the latter (auto-increment).
- The `firms.max_users` column mentioned in the original plan was never added — seat count is purely dynamic right now

### Subscription Lapse / Grace Period Handling
- When a subscription lapses (payment fails, cancellation), the firm reverts to free plan automatically (Cashier's `subscribed('default')` returns false)
- No grace period logic implemented — Cashier supports `onGracePeriod()` but it's not used to gate any features
- No email/notification to firm admins when payment fails or subscription is about to expire
- Consider: should there be a warning banner in the app when on a grace period?

### Add-ons
- `addons` table and `firm_addon` pivot table exist in the plan but migrations haven't been created
- `Firm::addons()` relationship is defined but has no corresponding Addon model or migration
- No add-on products created in Stripe
- No UI for purchasing or managing add-ons

### Trial Period
- No trial period implemented — firms go straight to free plan or paid
- Cashier supports trials via `->trialDays(14)` on checkout — easy to add when ready

### Notifications / Emails
- No email sent on subscription events (welcome, payment failed, cancellation, renewal)
- Stripe can send receipt emails natively (enable in Stripe dashboard > Settings > Emails)
- For custom emails (e.g., "your trial is ending"), would need a Cashier webhook listener or scheduled command

### Marketing / Public Pages
- No public-facing pricing page or marketing site
- Subscription page is admin-only behind auth

---

## Database Changes

### Modify `firms` table
- Cashier migrations added: `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`
- **Not yet added:** `max_users` (unsigned integer, nullable) — seat cap for enforcement
- **Not yet added:** `billing_contact_id` (foreign key to contacts, nullable) — billing admin
- **Candidate for removal:** `subscription_status` (unsigned integer, default 0) — Cashier manages state in its own `subscriptions` table
- **Candidate for removal:** `subscription_type` (string, nullable) — superseded by Cashier

### `plans` table (created)
- `name`, `slug` (unique), `stripe_product_id`
- `stripe_price_monthly`, `stripe_price_quarterly`, `stripe_price_yearly`
- `description`, `base_price_monthly`, `base_price_quarterly`, `base_price_yearly` (display prices in cents)
- `max_users` (nullable, null = unlimited), `is_active`, `sort_order`

### `addons` table (not yet created)
- `name`, `slug` (unique), `stripe_product_id`
- `description`, `price_monthly`, `price_quarterly`, `price_yearly`
- `is_active`, `sort_order`

### `firm_addon` pivot table (not yet created)
- `firm_id`, `addon_id`, `activated_at`, `expires_at` (nullable)

---

## Key Decisions

### Resolved
- **Plan names and pricing:** "Vantage Standard Subscription" — $15/mo, $36/qtr, $120/yr per seat
- **What happens without a subscription:** Free plan — app fully accessible but limited to 10 files per firm. No lockout, no read-only mode.
- **Subscription lapse:** Reverts to free plan (10-file limit). Existing files remain accessible; only new file creation is blocked at the limit.
- **Seat syncing strategy:** Auto-sync to Stripe on member creation. No hard seat cap — adding users auto-increments billing.

### Still Needed
- Which features qualify as add-ons
- Trial period length (if any)
- Grace period after failed payment — how long before reverting to free plan?
- Whether to enforce a hard seat cap or just auto-bill for additional seats
- Email/notification strategy for billing events
- Public pricing page design and content
- Production Stripe keys and webhook configuration

---

## Suggestions for Next Steps

1. **Add Stripe keys to `.env` and test the checkout flow end-to-end** — this is the most immediate blocker. Use Stripe's test card numbers (e.g., `4242 4242 4242 4242`) to verify the full subscribe → webhook → status update cycle.

2. **Configure the Stripe webhook** — without this, subscription status won't update after checkout. Set up the endpoint in Stripe dashboard and add `STRIPE_WEBHOOK_SECRET` to `.env`. For local development, use `stripe listen --forward-to localhost:8000/stripe/webhook` (Stripe CLI).

3. **Build user deactivation/reactivation UI** — this is a natural next feature since seat syncing is already wired up. Just needs a toggle on the Users page and a controller method to flip `contacts.current`.

4. **Add a grace period banner** — when `$firm->subscription('default')->onGracePeriod()` is true, show a warning banner across the app. Low effort, high value for reducing involuntary churn.

5. **Enable Stripe receipt emails** — free and automatic. Go to Stripe dashboard > Settings > Customer emails > Successful payments.

6. **Consider a scheduled command for seat count reconciliation** — `$firm->syncSubscriptionQuantity()` is called on member creation, but a daily artisan command could catch any drift (e.g., direct database changes, failed syncs). Something like `php artisan subscriptions:sync-seats`.
