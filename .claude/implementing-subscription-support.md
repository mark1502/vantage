# Implementing Subscription Support

## Overview
Add subscription billing to Vantage using Laravel Cashier (Stripe). Firm-level billing with per-seat pricing and optional flat-rate add-ons.

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

### Step 4: Build checkout flow — PICK UP HERE
- Firm admin selects a plan
- Cashier generates a Stripe Checkout session referencing the `price_id`
- Set subscription quantity = active firm member count
- Stripe handles payment collection
- Webhook confirms subscription activation
- Redirect user back to app on success/cancel

### Step 5: Add subscription middleware
- Gate the app behind an active subscription check
- Similar pattern to existing `EnsureUserWelcomed` middleware
- Check `$user->firm->subscribed('default')` (Cashier method)
- Consider building this early and keeping it permissive during dev
- Decide: what happens when subscription lapses? (read-only vs full lockout)

### Step 6: Add seat syncing
- When firm members are created/deactivated/reactivated, update Stripe subscription quantity
- Hooks into existing contact creation and status change logic
- `$firm->subscription('default')->updateQuantity($newCount)`
- Source of truth for seat count: active firm member contacts (`Contact::firmMembers()->current()->count()`)

### Step 7: Add billing management page
- Use Cashier's Stripe Customer Portal redirect (one-liner)
- Users can manage payment methods, view invoices, cancel/resume subscription
- Minimal custom UI needed — Stripe hosts the portal

---

## Database Changes

### Modify `firms` table
- Add `max_users` (unsigned integer, nullable) — seat cap for enforcement
- Add `billing_contact_id` (foreign key to contacts, nullable) — billing admin
- Consider dropping `subscription_status` once Cashier is installed (Cashier manages state in its own `subscriptions` table)
- Cashier migrations will add: `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`

### Existing `firms` columns (subscription-related)
- `subscription_type` (string, nullable) — stores plan name; may be superseded by Cashier's `subscriptions` table
- `subscription_status` (unsigned integer, default 0) — candidate for removal

### New `plans` table
Local reference for Stripe products (for display and logic):
- `name`, `slug` (unique), `stripe_product_id`
- `description`, `base_price_monthly`, `base_price_quarterly`, `base_price_yearly`
- `max_users` (nullable, null = unlimited), `is_active`, `sort_order`

### New `addons` table
- `name`, `slug` (unique), `stripe_product_id`
- `description`, `price_monthly`, `price_quarterly`, `price_yearly`
- `is_active`, `sort_order`

### New `firm_addon` pivot table
- `firm_id`, `addon_id`, `activated_at`, `expires_at` (nullable)

### Not Needed
- Invoice/payment tables — Stripe + Cashier handle this
- Subscription history table — Cashier's `subscriptions` table + Stripe webhooks cover this
- Separate seat tracking table — count of active firm member contacts is the source of truth

---

## Key Decisions Still Needed
- Exact plan names and pricing tiers
- Which features qualify as add-ons
- Trial period length
- Grace period after failed payment
- What happens when subscription lapses (read-only? full lockout?)
- Exact `.env` key values (once finalized in Stripe dashboard)
