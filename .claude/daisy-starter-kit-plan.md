# Plan: Build a Laravel Starter Kit from Scratch

**Stack:** Laravel 13 · Fortify · Inertia.js v3 (SSR off by default) · Vue 3 · Tailwind 4 · daisyUI 5 · Ziggy · lucide-vue-next (icons)
**Style:** Breeze-inspired (simple, minimal abstraction, plain JS) with daisyUI components instead of hand-rolled Tailwind markup
**Distribution:** Full app skeleton, installable via `laravel new my-app --using=<vendor>/<kit>`
**Working name (placeholder):** `vendor/daisy-starter-kit` — rename before publishing

> **Version-target caveat:** This plan targets Laravel 13 and Inertia v3. Both are ahead of the maintainer's day-to-day reference points (Laravel 12 / Inertia v2 in the Vantage app). Before starting Phase 1, **verify against the current Laravel 13 upgrade guide and Inertia v3 release notes** — minor API or installation changes between versions can invalidate specific commands below. Where this plan reflects v2/12 patterns that may shift, it is flagged inline.

---

## Guiding principles

- **Follow Breeze's posture, not Jetstream's.** Plain Inertia + Vue. No Livewire, no Teams, no API tokens by default. Pages are simple `.vue` files you can read top-to-bottom.
- **Plain JavaScript, not TypeScript.** No `tsconfig.json`, no `lang="ts"`, no type imports.
- **daisyUI components everywhere.** No shadcn, no reka-vue, no `cn()` utility, no CVA. Markup uses daisyUI classes (`btn`, `input`, `card`, `form-control`, `label`, `alert`, etc.).
- **Fortify is the auth backend.** Headless — we own every Inertia view. View-binding closures live in `FortifyServiceProvider`.
- **Ziggy for route generation.** `route('login')` in Vue, `@routes` directive in the root Blade.
- **Inertia v3 without SSR.** Inertia v3 supports SSR but the kit ships with SSR **off** — no `@inertiajs/server` install, no `inertia:start-ssr` process, no SSR Vite entry. Teams that want SSR can add it; it's a clean opt-in, not the default.
- **lucide-vue-next for icons.** Tree-shakable, framework-agnostic, no shadcn dependency. Imported per-component (`import { LogOut } from 'lucide-vue-next'`).
- **No premature abstractions.** A starter kit should be a clear example, not a framework. Resist building reusable component libraries — write pages directly.

---

## Phase 0 — Decisions and scaffold setup

Before writing code, lock in the answers below so the kit has a consistent identity.

- Pick a vendor/package name (e.g. `kowit/daisy-breeze`). Reserve on Packagist if publishing publicly.
- Pick a default daisyUI theme pair (`light`/`dark` recommended) and decide whether the toggle persists to the DB (user `preferences.theme`) or localStorage. **Default in this plan: localStorage** — simpler, no auth dependency, no migration needed.
- Confirm Node + PHP version targets. Laravel 13 likely raises the floor — **verify the actual minimum PHP version from the L13 upgrade guide** (probably PHP 8.3+ or 8.4+). Node 20 LTS or newer.
- Open the Inertia v3 release notes and skim for breaking changes vs v2: middleware signature, root view, `@inertia` directive name, `createInertiaApp` options, deferred-prop syntax. Fix in your head before Phase 1 — don't paste v2 patterns blindly.

**Deliverable:** a written one-paragraph "what this kit is" blurb that will go in the README later (don't write the README itself yet).

---

## Phase 1 — Base Laravel 13 + Inertia v3 + Vue + Vite + Lucide

Goal: a working "hello Inertia" app with no auth yet. **SSR explicitly off.**

1. `laravel new daisy-starter-kit` (no `--using` flag — start from `laravel/laravel`, which should now produce a Laravel 13 skeleton once L13 is released).
2. `composer require inertiajs/inertia-laravel:^3.0` — **verify the actual L-side package version that ships with Inertia v3**. The PHP-side package may keep a different major (it tracked v1→v2 without bumping; v3 may or may not bump it).
3. `php artisan inertia:middleware` → register `HandleInertiaRequests` in `bootstrap/app.php`. Confirm the v3 middleware signature in case it changed.
4. `npm install @inertiajs/vue3@^3 vue @vitejs/plugin-vue`. **Do NOT install `@inertiajs/server`** — that's the SSR adapter, and the kit is shipping SSR-off.
5. Update `vite.config.js`: add the `@vitejs/plugin-vue` plugin, set `resolve.alias['@'] = '/resources/js'`. **Do not add a second `ssr` input** to `laravel-vite-plugin` config — SSR is off.
6. Replace `resources/views/welcome.blade.php` with the standard `app.blade.php` Inertia root template. Use `@inertia` (v3 may rename — verify) and `@inertiaHead` if v3 still uses that for head-management.
7. Rewrite `resources/js/app.js` to bootstrap Inertia + Vue via `createInertiaApp` with `resolvePageComponent`. No `setup` SSR branch needed.
8. `npm install lucide-vue-next` — icon library used throughout the kit (navbar, settings menu items, alerts, buttons that want an icon prefix). No global registration; import per-component.
9. Create `resources/js/Pages/Welcome.vue` as a minimal landing page with one heading and one Lucide icon to confirm the icon import works. Wire `/` route to `Inertia::render('Welcome')`.
10. Confirm in `config/inertia.php` that any SSR section is left at defaults (disabled). If L13/Inertia-v3 ships a different config layout, leave `ssr.enabled = false` (or whatever the equivalent key becomes).

**Exit criteria:** `composer dev` runs cleanly; visiting `/` shows the Welcome page rendered through Inertia, with a Lucide icon visible. No SSR process is running and none is expected.

---

## Phase 2 — Tailwind 4 + daisyUI 5 with full theme support

Goal: styling works, the kit ships a curated theme registry (mix of custom-defined and built-in daisyUI themes), a theme picker is available in the navbar, and the user's choice persists. This mirrors the Vantage app's approach so the kit feels consistent with how the maintainer already builds Laravel + daisyUI apps.

### 2a. Install and wire up Tailwind 4 + daisyUI 5

1. `npm install -D tailwindcss @tailwindcss/vite daisyui@latest`.
2. Add `@tailwindcss/vite` to `vite.config.js` plugins.
3. Replace `resources/css/app.css` content with the structure below. The `@plugin "daisyui"` declaration lists the **built-in themes** to include. Below it, **custom themes** are defined inline via `@plugin "daisyui/theme"` blocks with explicit OKLCH color tokens (so they survive daisyUI version bumps that might change built-in defaults):
   ```css
   @import 'tailwindcss';

   @plugin "daisyui" {
       themes: dark --prefersdark, corporate, nord, dim, winter, fantasy, bumblebee;
   }

   @plugin "daisyui/theme" {  /* light (default) — full OKLCH token block */
       name: "light";
       default: true;
       prefersdark: false;
       color-scheme: "light";
       /* ...color and radius tokens... */
   }

   /* Additional custom themes: retro, aqua, dracula, caramellatte, coffee, night, autumn, business */
   /* Each as its own @plugin "daisyui/theme" block with full token definitions */

   @source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
   @source '../../storage/framework/views/*.php';
   @source '../../resources/views/**/*.blade.php';
   @source '../../resources/js/**/*.vue';

   @theme {
       --font-sans: Figtree, ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji',
                    'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
   }

   @layer base {
       *, ::after, ::before, ::backdrop, ::file-selector-button {
           border-color: var(--color-base-300, currentcolor);
       }
       html { font-size: 16px; overflow-y: auto; }
       body { font-size: 1rem; line-height: 1.5; overflow-y: scroll; }
   }
   ```
4. **Reference**: copy the custom theme blocks (light, retro, aqua, dracula, caramellatte, coffee, night, autumn, business) verbatim from the Vantage app's `resources/css/app.css`. Same with the built-in theme list. This gives the kit ~16 ready-to-use themes out of the box.

### 2b. Add the `useTheme` composable

Create `resources/js/Composables/useTheme.js` mirroring Vantage's implementation:

```js
import { ref } from 'vue'

export const AVAILABLE_THEMES = [
    'light', 'corporate', 'autumn', 'fantasy', 'bumblebee', 'winter', 'nord',
    'caramellatte', 'retro', 'coffee', 'aqua', 'night', 'dark', 'dracula',
    'business', 'dim',
]

const theme = ref(localStorage.getItem('theme') || 'light')

export const useTheme = () => {
    const setTheme = (newTheme) => {
        theme.value = newTheme
        document.documentElement.setAttribute('data-theme', newTheme)
        localStorage.setItem('theme', newTheme)
    }

    const initTheme = (savedTheme = null) => {
        const themeToApply = savedTheme ?? localStorage.getItem('theme') ?? 'light'
        const validated = AVAILABLE_THEMES.includes(themeToApply) ? themeToApply : 'light'
        setTheme(validated)
    }

    const previewTheme = (previewThemeName) => {
        document.documentElement.setAttribute('data-theme', previewThemeName)
    }

    const revertPreview = () => {
        document.documentElement.setAttribute('data-theme', theme.value)
    }

    return { theme, themes: AVAILABLE_THEMES, setTheme, initTheme, previewTheme, revertPreview }
}
```

### 2c. Apply theme on initial paint (avoid FOUC)

In `app.blade.php` `<head>`, before Vite assets load, inline a tiny script that reads `localStorage.theme` and sets `data-theme` on `<html>` synchronously. This prevents a flash of the default theme before Vue hydrates.

```html
<script>
    (function () {
        try {
            var t = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    })();
</script>
```

In `app.js`, call `initTheme()` on app boot so the Vue-side `theme` ref stays in sync.

### 2d. Build a theme picker component

Create `resources/js/Components/ThemePicker.vue` — a daisyUI `dropdown` containing a `menu` of all themes from `AVAILABLE_THEMES`. Each item:
- Calls `previewTheme(name)` on `mouseenter` so users can see the theme live before committing
- Calls `setTheme(name)` on click to commit
- Calls `revertPreview()` on dropdown close if no selection was made

Wire the picker into Phase 2's `Welcome.vue` to demonstrate, and (later, in Phase 6) into the authenticated navbar.

**Exit criteria:**
- Welcome page renders daisyUI components correctly under every theme.
- Theme picker lists all 16 themes; hovering previews; clicking commits; choice persists across reloads.
- No FOUC on initial paint.

---

## Phase 3 — Ziggy

Goal: `route()` available everywhere in Vue.

1. `composer require tightenco/ziggy`.
2. Add `@routes` directive to `app.blade.php` `<head>`.
3. Import `route` globally in `app.js` so it's available in templates without per-file imports (this is the Breeze pattern — `globalProperties.route = window.route` or rely on the global Ziggy injects).
4. Optionally publish Ziggy's TS definitions for editor autocomplete — but since we're plain JS, skip.

**Exit criteria:** `<Link :href="route('home')">` works from any page component.

---

## Phase 4 — Fortify backend wiring

Goal: Fortify is installed and configured; auth routes work via curl/Postman before any UI exists.

1. `composer require laravel/fortify`.
2. `php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"` — publishes config + migrations.
3. Run migrations (`fortify` adds the two_factor columns; we'll use them in Phase 7).
4. Register `App\Providers\FortifyServiceProvider` in `bootstrap/providers.php`.
5. Configure `config/fortify.php`:
   - `home` → `/dashboard`
   - `views` → `true`
   - `features` → enable `registration`, `resetPasswords`, `emailVerification`, `updateProfileInformation`, `updatePasswords`. **Leave `twoFactorAuthentication` commented for now.**
6. In `FortifyServiceProvider::boot()`, bind every view to an Inertia render call:
   ```php
   Fortify::loginView(fn () => Inertia::render('Auth/Login'));
   Fortify::registerView(fn () => Inertia::render('Auth/Register'));
   // etc.
   ```
7. Configure rate limiting for `login` and `two-factor` in the provider (copy Fortify docs' standard recipe).
8. Make sure `User` model uses `Laravel\Fortify\Concerns\TwoFactorAuthenticatable` trait now (so adding 2FA later is just a config flip, no model migration drama).

**Exit criteria:** `POST /login` with valid credentials returns a redirect to `/dashboard`; `POST /register` creates a user; `POST /forgot-password` sends a reset email (use `MAIL_MAILER=log` in dev). No Vue pages yet — just verify the backend.

---

## Phase 5 — Auth Inertia pages (the daisyUI rewrite of Breeze)

Goal: a user can register, log in, log out, reset their password, and verify their email — all through daisyUI-styled Inertia pages.  We can use Breeze as a starting reference point for the layouts and auth pages.

For each page, structure mirrors Breeze: a `GuestLayout.vue` wrapper with a centered `card`, a logo, the form. Use Inertia's `useForm()` for client state and submission. Error display via daisyUI `label-text-alt text-error` under each input.

### Auth-area theming constraint

The auth pages are deliberately **light/dark only** — the full 16-theme registry from Phase 2 is reserved for the authenticated experience. Rationale: simpler visual validation of auth flows (only two themes to test), cleaner brand impression for first-time visitors, and isolation from any "weird" theme the user might have selected post-login.

Implementation:

- `GuestLayout.vue` ignores the main `useTheme` composable's persisted value when rendering. Instead it uses a tiny dedicated state — read `localStorage.authTheme` (defaulting to `'light'`, or to `'dark'` if `prefers-color-scheme: dark`) and apply it via `document.documentElement.setAttribute('data-theme', 'light' | 'dark')` on mount.
- On unmount (i.e. when the user logs in and moves to `AuthenticatedLayout`), restore the main app theme by calling `useTheme().initTheme()`.
- Add a small `AuthThemeToggle.vue` component — a simple sun/moon `swap` button, **not** the full dropdown picker. Lucide `Sun` and `Moon` icons. Two states only. Writes to `localStorage.authTheme`.
- Keep the auth-theme key (`authTheme`) separate from the main theme key (`theme`) in localStorage so the two contexts don't bleed into each other.

Pages to build in `resources/js/Pages/Auth/`:

1. **Login.vue** — email, password, remember-me checkbox; submits to `route('login')`.
2. **Register.vue** — name, email, password, password confirmation; submits to `route('register')`.
3. **ForgotPassword.vue** — email field; submits to `route('password.email')`; shows status alert on success.
4. **ResetPassword.vue** — receives `token` and `email` props; new password fields; submits to `route('password.update')`.
5. **VerifyEmail.vue** — displays "check your email" message; button to resend verification.
6. **ConfirmPassword.vue** — used by Fortify's password-confirmation middleware for sensitive operations.

Shared building blocks in `resources/js/Components/`:

- `GuestLayout.vue` — centered card layout for auth pages; enforces the auth-only light/dark theming described above
- `AuthThemeToggle.vue` — sun/moon swap toggle used only inside `GuestLayout`
- `InputError.vue` — small error text under an input
- `PrimaryButton.vue` — `<button class="btn btn-primary">` with loading state when `processing`
- `Checkbox.vue` — daisyUI `checkbox` wrapper

**Exit criteria:** All flows work end-to-end through the UI. Validation errors render under the correct fields. Status messages (e.g. "reset link sent") render in daisyUI `alert` components.

---

## Phase 6 — Authenticated layout, dashboard, and settings pages

Goal: a logged-in experience with navigation and a profile/settings area.

1. **`AuthenticatedLayout.vue`** — daisyUI `navbar` at top with app name, nav links, user dropdown (avatar + dropdown with "Profile", "Log Out"). Responsive: drawer on mobile via daisyUI `drawer` component. The `ThemePicker` component from Phase 2 lives in the navbar so users can switch themes from anywhere.
2. **`Dashboard.vue`** — protected by `auth` + `verified` middleware. Minimal placeholder: "You're logged in" card. Demonstrates the layout.
3. **Settings area** at `/settings/*` with a sub-nav:
   - `Settings/Profile.vue` — update name + email (Fortify `update-profile-information` endpoint). Email change triggers re-verification.
   - `Settings/Password.vue` — current password + new password + confirmation (Fortify `update-password` endpoint).
   - `Settings/DeleteAccount.vue` — danger zone, password confirmation, deletes the account.
4. Settings layout: daisyUI `tabs` or a left sidebar `menu`, with the active route highlighted.

**Exit criteria:** A user can sign up, verify email, log in, see the dashboard, update their profile, change password, and delete their account.

---

## Phase 7 — Two-factor authentication (optional, additive)

Goal: enable Fortify's 2FA support with full UI.

This phase is **additive** — nothing in phases 1–6 changes. The `User` model already has the trait (from Phase 4 step 8), and the migration columns already exist.

1. In `config/fortify.php`, uncomment `Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])`.
2. In `FortifyServiceProvider::boot()`, bind the 2FA challenge view:
   ```php
   Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactorChallenge'));
   ```
3. Build **`Auth/TwoFactorChallenge.vue`** — shown after password login when 2FA is enabled. Two modes: TOTP code input OR recovery code input, toggled via a "Use a recovery code instead" link.
4. Build a **2FA settings section** in `Settings/Profile.vue` (or a new `Settings/TwoFactor.vue` page):
   - If 2FA disabled: "Enable 2FA" button → POSTs to `user/two-factor-authentication` → shows QR code (`/user/two-factor-qr-code`) → confirm-with-code input → POSTs to `/user/confirmed-two-factor-authentication`.
   - If 2FA enabled: show "Recovery codes" toggle, "Regenerate recovery codes" button, "Disable 2FA" button.
   - All sensitive actions are gated by Fortify's password-confirmation middleware → triggers `ConfirmPassword.vue` flow built in Phase 5.

**Exit criteria:** A user can enable 2FA, confirm with a TOTP app (Google Authenticator, 1Password, etc.), log out, log in again with password, complete the 2FA challenge with either a TOTP code or a recovery code, and disable 2FA after re-confirming their password.

---

## Phase 8 — Polish for distribution

Goal: the kit is ready for `laravel new --using=...`.

1. **`composer.json`**:
   - Set `"type": "project"`.
   - Set `"name": "<vendor>/<kit>"`.
   - Set sensible `"description"` and `"keywords"`.
   - Configure `post-create-project-cmd` scripts: copy `.env.example` to `.env`, run `php artisan key:generate`, `php artisan migrate --seed --no-interaction`, `npm install`.
2. **`.env.example`** — clean defaults, `APP_NAME=Laravel`, `MAIL_MAILER=log`, etc.
3. **README.md** — short. What's in the kit, how to install, how to run dev, where the auth pages live. Don't write a tutorial.
4. **Smoke test** — in a scratch folder run `composer create-project <vendor>/<kit> test-app` against your local copy, verify it boots and all auth flows work.
5. **Publish**:
   - Push to GitHub (public repo).
   - Submit to Packagist (or rely on `laravel new` resolving GitHub URLs directly — Laravel installer accepts `<vendor>/<repo>` and `https://github.com/...`).
6. **CHANGELOG.md** — start one. Even a personal kit benefits from a record of what changed between Laravel versions.

**Exit criteria:** `laravel new <test> --using=<vendor>/<kit>` from a clean machine produces a working app whose auth flows function out of the box.

---

## Phase 9 — Ongoing maintenance posture

Not a build phase — a checklist for keeping the kit current.

- **Track upstream Laravel releases.** When a new Laravel minor or major lands (this kit is being built against L13 / Inertia v3 — future versions will follow), bump `laravel/framework` in `composer.json`, run `composer update`, run all auth flows manually, commit. Fortify, Inertia, Ziggy, and lucide-vue-next are independent — bump on their own cadences.
- **Re-check SSR-off assumption with each Inertia major.** If Inertia ever flips SSR to default-on, you'll need to add explicit opt-out steps to the kit's `composer.json post-create-project-cmd` (e.g. remove SSR config keys, delete `ssr.js`).
- **Track Tailwind / daisyUI releases.** Tailwind 4.x minor bumps are usually drop-in. daisyUI changes class names occasionally — review their changelog and grep the kit for renamed classes.
- **Don't accumulate components.** Resist adding "useful" abstractions over time. The kit's value is its small surface area. If you need a real component library for a project built on the kit, build it in that project.
- **Re-run the smoke test before tagging a release.** Tag with the Laravel version it targets (e.g. `v12.x.0`).

---

## Effort estimate (rough)

| Phase | Effort |
|---|---|
| 0 — Decisions | 30 min |
| 1 — Inertia + Vue scaffold | 2 hours |
| 2 — Tailwind + daisyUI | 1 hour |
| 3 — Ziggy | 30 min |
| 4 — Fortify backend wiring | 2-3 hours |
| 5 — Auth Inertia pages | 1 day |
| 6 — Authenticated layout + settings | 1 day |
| 7 — 2FA (additive) | 0.5-1 day |
| 8 — Distribution polish | 2-3 hours |
| **Total to first usable release (phases 0-6, 8)** | **~3 days** |
| **Total with 2FA (phases 0-8)** | **~4 days** |

---

## Open questions to resolve during the build

- **Theme persistence**: localStorage by default (mirrors Vantage). If cross-device persistence becomes desirable later, add a `theme` column to `users` or a `preferences` table and have the `HandleInertiaRequests` middleware share the saved value to the client, where `initTheme(savedTheme)` will apply it. Easy to add later without changing the composable's API.
- **Email verification mode**: required on signup, or soft (banner prompt)? Fortify defaults to required via `verified` middleware. Recommend keeping that default.
- **`remember_token` handling**: Fortify handles it; nothing to decide, just confirm checkbox works.
- **Logout route**: Fortify uses `POST /logout`. Make sure the user-dropdown menu submits via Inertia's `router.post()`, not a link.
