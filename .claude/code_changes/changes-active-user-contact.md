# Rename `contacts.current` to `account_status` + Wire Up User Deactivation

## Migration changes
- `0001_01_01_000000_create_users_table.php` — Removed the unused `is_active` column
- `2026_01_07_201513_create_contacts_table.php` — Renamed `current` to `account_status` with default `'N'`

## Model changes
- `Contact.php` — Renamed `isCurrent()` → `isActive()`, `scopeCurrent()` → `scopeActive()`, updated to use `account_status` column with `'A'` value
- `User.php` — Updated `isActive()` and `scopeActive()` to use `account_status === 'A'`, removed `scopeCurrent()` alias
- `Firm.php` — Changed `->current()` → `->active()` in `syncSubscriptionQuantity()`

## Controller changes
- `SubscriptionController.php` — Changed `->current()` → `->active()` (2 occurrences)
- `UserController.php` — Added `account_status` to edit data, validation (`Rule::in(['A', 'I'])`), saving, and `syncSubscriptionQuantity()` call on update; set `account_status = 'A'` for new users in store
- `EntryController.php` — Updated `getFirmMembers()` and `getAttorneys()` to use `account_status`/`'A'`
- `ViewController.php` — Updated `getFirmMembers()` and `getAttorneys()` to use `account_status`/`'A'`
- `CalendarController.php` — Updated firm members query to use `account_status`/`'A'`

## Frontend
- `Users/Edit.vue` — Changed `form3.current` → `form3.account_status`, updated dropdown to show "Active"/"Inactive" with values `'A'`/`'I'`

## Seeder/Docs
- `SampleDataSeeder.php` — Firm members use `'account_status' => 'A'`, external contacts use `'account_status' => 'N'`
- `CLAUDE.md` — Updated documentation to reflect new column name and values

## Column value meanings
- `'A'` = Active (firm members who are currently active)
- `'I'` = Inactive (deactivated firm members)
- `'N'` = Normal (default for external contacts)

## Post-change requirement
Run `php artisan migrate:fresh --seed` to rebuild the database with the new schema.
