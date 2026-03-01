# Implementing Subscriptions — Step 2: Install Cashier & Run Migrations

**Date:** 2026-02-28

---

## What Was Done

### 1. Installed Laravel Cashier
- `composer require laravel/cashier` — installed v16.3.0
- Also pulled in: `stripe/stripe-php` v17.6.0, `moneyphp/money` v4.8.0, `symfony/polyfill-intl-icu` v1.33.0

### 2. Published & Modified Cashier Migrations
- Ran `php artisan vendor:publish --tag="cashier-migrations"` to publish migrations for review
- Published `config/cashier.php` via `--tag="cashier-config"`

**Cashier's default migrations target the `users` table.** Since our billable model is `Firm`, the following changes were made:

#### `create_customer_columns` migration
- Changed `Schema::table('users', ...)` to `Schema::table('firms', ...)` in both `up()` and `down()`
- This adds `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at` to the `firms` table

#### `create_subscriptions_table` migration
- Changed `$table->foreignId('user_id')` to `$table->unsignedInteger('firm_id')`
- Changed index from `['user_id', 'stripe_status']` to `['firm_id', 'stripe_status']`

**Important — unsignedInteger vs unsignedBigInteger:**
The `firms` table uses `$table->increments('id')` which creates an `unsignedInteger` primary key. Cashier's default `foreignId()` creates `unsignedBigInteger` columns. These types are incompatible for foreign key constraints in MySQL (error 3780). All references to `firm_id` in Cashier and custom migrations were changed from `foreignId()` to `unsignedInteger()` with manual foreign key definitions where needed.

#### `create_subscription_items_table` migration
- No changes needed (references `subscription_id`, not firms)

#### Meter-related migrations
- No changes needed

### 3. Configured Cashier for Firm-Level Billing

#### `app/Providers/AppServiceProvider.php`
- Added `Cashier::useCustomerModel(Firm::class)` in `boot()` to tell Cashier the billable model is `Firm`, not `User`

#### `app/Models/Firm.php`
- Added `use Laravel\Cashier\Billable` import
- Added `Billable` trait
- Added `addons()` BelongsToMany relationship
- Added return type hints to existing `users()` and `contacts()` relationships

### 4. Created App-Specific Tables & Models

#### `plans` table & `Plan` model
- Columns: `name`, `slug` (unique), `stripe_product_id`, `description`, `base_price_monthly`, `base_price_quarterly`, `base_price_yearly`, `max_users` (nullable), `is_active`, `sort_order`
- Model has `is_active` boolean cast, `firms()` HasMany relationship

#### `addons` table & `Addon` model
- Columns: `name`, `slug` (unique), `stripe_product_id`, `description`, `price_monthly`, `price_quarterly`, `price_yearly`, `is_active`, `sort_order`
- Model has `is_active` boolean cast, `firms()` BelongsToMany relationship with pivot fields

#### `firm_addon` pivot table
- Columns: `firm_id` (unsignedInteger with FK), `addon_id` (foreignId with FK), `activated_at`, `expires_at`
- Both FKs cascade on delete

### 5. Ran Pint & Verified
- Ran `vendor/bin/pint --dirty --format agent` to fix formatting
- Ran `php artisan migrate:fresh --seed` — all migrations and seeders passed

---

## Files Changed
- `composer.json` / `composer.lock` — added `laravel/cashier`
- `config/cashier.php` — published from vendor
- `app/Providers/AppServiceProvider.php` — Cashier customer model config
- `app/Models/Firm.php` — Billable trait, addons relationship, return types
- `app/Models/Plan.php` — new model
- `app/Models/Addon.php` — new model
- `database/migrations/2026_03_01_044113_create_customer_columns.php` — Cashier, modified for firms
- `database/migrations/2026_03_01_044114_create_subscriptions_table.php` — Cashier, modified for firms
- `database/migrations/2026_03_01_044115_create_subscription_items_table.php` — Cashier, unmodified
- `database/migrations/2026_03_01_044116_add_meter_id_to_subscription_items_table.php` — Cashier, unmodified
- `database/migrations/2026_03_01_044117_add_meter_event_name_to_subscription_items_table.php` — Cashier, unmodified
- `database/migrations/2026_03_01_044325_create_plans_table.php` — new
- `database/migrations/2026_03_01_044333_create_addons_table.php` — new
- `database/migrations/2026_03_01_044347_create_firm_addon_table.php` — new
