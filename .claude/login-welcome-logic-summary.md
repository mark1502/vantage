Here's what I found after walking the registration → user-creation → middleware chain.

## How it works today

**1. Public registration** — `RegisteredUserController@store` (routes/auth.php:18)
- Validates a unique firm name + email + password
- Creates the `Firm`, copies base `Filetypes` and `Entrytypes` from firm_id=1
- Creates the `User` with `user_type = 'Admin'` and the new `firm_id`
- Logs them in and redirects to `/dashboard`
- Note: no `Contact` record is created here — the welcome flow does that
- `welcomed` is not set, so it defaults to `false`

**2. The welcomed gate** — `EnsureUserWelcomed` middleware
- Runs on every authenticated app route
- If `!welcomed`: Admins → `welcome_admin`, everyone else → `welcome_user`
- `welcome_user()` is literally `dd('welcome_user')` — that's the "die and dump" you saw

**3. Welcome flow** — `WelcomeController`
- `welcome_admin` GET renders the multi-step form
- `postWelcomeAdmin` handles four `formtype`s: `firm` (firm details), `user` (admin's own contact record), `addingAttorney` (additional users via the welcome wizard), `editUser`
- `doneWelcomeAdmin` flips `welcomed = true` and lands on the Dashboard

**4. Adding more users later** — `UserController@store` (routes/web.php:57, the `users.store` resource)
- Used by the admin from `/users` after onboarding is complete
- Creates the `User` *and* the matching `Contact` with full profile in one shot (title, names, initials, role, phones, display names, `account_status='A'`)
- Calls `syncSubscriptionQuantity()`
- **Does not set `welcomed`** → defaults to `false` → next time that new user logs in, middleware sends them to `welcome_user` → `dd()`

## Why it breaks

The welcome flow was clearly designed around the *first* admin, whose `Contact` doesn't exist yet at registration. For later users created via `UserController@store`, the Contact is already complete — there's nothing left for a welcome flow to collect, but `welcomed` is still false, so the middleware redirects them into a stub that dies.

## My recommendation (matches what you described)

Smallest, safest change — no impact on registration, no impact on the existing admin onboarding flow:

1. In `UserController@store`, set `$aUser->welcomed = true;` before saving (line ~73). Users created by an admin skip the welcome flow entirely.
2. In `WelcomeController@welcome_user`, replace the `dd()` with a redirect to `/dashboard` as a defensive fallback for any pre-existing rows with `welcomed = false` that aren't admins.

Note: I did recommendations 1 & 2.

Optional follow-up (later, when you want first-login orientation for non-admins): introduce a separate flag like `oriented` so `welcomed` keeps meaning "firm onboarding done" and `oriented` means "user has seen their personal walkthrough." That keeps the two concepts from getting tangled.
