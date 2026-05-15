# Building a Custom Laravel Starter Kit (Fortify + Tailwind 4 + daisyUI 5)

## Context

Older starter kits like Breeze are no longer well-supported by the Laravel installer, and Breeze defaults to Tailwind 3. This discussion covers what's involved in building a custom starter kit using Fortify, Tailwind 4, and daisyUI 5 — and whether forking the official `laravel/vue-starter-kit` is a viable shortcut.

---

## Part 1: What's involved in building a starter kit from scratch

### Two viable structures

- **Full app skeleton** (like `laravel/laravel`): a complete repo the installer clones. Simplest to build, easiest for users. The new official starter kits (`laravel/vue-starter-kit`, etc.) are skeleton repos.
- **Installable package** (like Breeze originally was): a Composer package with an `install` Artisan command that scaffolds files into an existing Laravel app. More work, more flexible.

For a personal/team starter, the **full skeleton** route is dramatically less work.

### What goes in the skeleton

Starting from a fresh `laravel new` app:

- **Add Fortify**: `composer require laravel/fortify`, publish its config/migrations, register `FortifyServiceProvider`, wire up the views/Inertia pages for login, register, password reset, 2FA, email verification. Fortify is headless — you own every auth view. **This is the bulk of the work.**
- **Add Inertia + Vue 3**: `composer require inertiajs/inertia-laravel`, `npm i @inertiajs/vue3 vue`, set up `app.js`, `app.blade.php`, the `HandleInertiaRequests` middleware.
- **Tailwind v4**: `npm i tailwindcss @tailwindcss/vite`. v4 is config-less by default — just `@import "tailwindcss";` in `app.css` and the Vite plugin in `vite.config.js`. No `tailwind.config.js` needed unless you want theme tokens (and in v4 those go in CSS via `@theme`).
- **DaisyUI v5**: `npm i -D daisyui@latest`, then in `app.css`:
  ```css
  @import "tailwindcss";
  @plugin "daisyui";
  @plugin "daisyui/theme" { /* optional theme overrides */ }
  ```
  DaisyUI v5 is wired as a Tailwind v4 plugin via `@plugin`, no JS config.
- **Build the auth pages** as Inertia/Vue components using daisyUI components (`btn`, `input`, `card`, etc.).
- **Ziggy** if you want `route()` in JS (optional but common).

### Distribution

- Push to GitHub.
- Users install with `laravel new my-app --using=your-vendor/your-kit` — the installer just runs `composer create-project` under the hood, so any public Composer package with `"type": "project"` in `composer.json` works.
- Optionally publish to Packagist so the short name resolves.

### Realistic effort

The mechanical parts (Tailwind 4 + daisyUI 5 + Inertia) are an afternoon. **Fortify's auth flows are the real work** — probably 2–3 days to build clean Inertia pages for login, register, forgot/reset password, email verification, 2FA challenge, recovery codes, and profile (password change, 2FA enable/disable, delete account). That's what Breeze/Jetstream's value really was.

**Shortcut**: Laravel's new official `laravel/vue-starter-kit` already uses Fortify + Inertia + Vue + Tailwind 4. Forking it and swapping in daisyUI is probably the fastest path by a wide margin.

---

## Part 2: Forking `laravel/vue-starter-kit` and removing shadcn

### shadcn-vue in the starter kit

shadcn-vue isn't a dependency you `npm uninstall`. It's a CLI that **copies component source files into your repo** (typically `resources/js/components/ui/`). So "removing shadcn" means:

1. Delete `resources/js/components/ui/` (the copied components: Button, Input, Dialog, etc.).
2. Remove the underlying primitives it depends on: `reka-vue` (the headless primitives, formerly radix-vue), `class-variance-authority`, `clsx`, `tailwind-merge`, `lucide-vue-next` (icons — maybe keep), `tw-animate-css`.
3. Delete the `cn()` utility in `resources/js/lib/utils.ts`.
4. Rewrite every auth/settings page that imports from `@/components/ui/*` to use daisyUI classes instead (`<button class="btn btn-primary">` etc.).
5. Strip shadcn-specific CSS variables from `app.css` — the kit defines a big block of `--background`, `--foreground`, `--primary` HSL tokens for shadcn's theming. daisyUI has its own theme system via `@plugin "daisyui/theme"`, so those go.

Step 4 is the bulk of the work. The kit ships pages for login, register, forgot/reset password, email verification, 2FA confirm, plus a settings area (profile, password, appearance, delete account) and an AppLayout with sidebar/nav. Each is composed of shadcn primitives. Realistically a day or two of mechanical rewrites.

### Size and runtime tradeoff

daisyUI is lighter at runtime — pure CSS, no JS primitives — vs. shadcn-vue pulling reka-vue's headless behavior JS for every interactive component.

Tradeoff: shadcn gives you accessible dialog/popover/combobox behavior for free; with daisyUI you write that JS yourself when you need it. For auth flows you barely need any of it, so the swap is a clear win there.

---

## Part 3: Wayfinder vs Ziggy

Different shapes of the same idea.

- **Ziggy** exports your route list to JS as a runtime object. You call `route('users.show', { user: 1 })` and it builds the URL string from a template. Untyped (or loosely typed via a generated `.d.ts`), works everywhere, dead simple.
- **Wayfinder** is a build-time code generator. It runs `php artisan wayfinder:generate` and produces **typed TS functions per route** — `import { show } from '@/actions/UserController'; show({ user: 1 })` returns `{ url, method }`. Full TS autocomplete, full type safety on params, and it also generates form-action helpers that plug into Inertia's `<Form>` component. Downside: extra build step, regenerate after route changes (there's a Vite plugin that watches).

For a kit you control, Wayfinder is genuinely nicer if you're on TS. If you prefer plain JS or want minimum tooling, swap back to Ziggy — it's a 15-minute change: `composer require tightenco/ziggy`, add `@routes` to `app.blade.php`, replace Wayfinder imports with `route()` calls in the handful of pages that use them.

---

## Recommended path

1. Fork `laravel/vue-starter-kit`.
2. Decide on TS vs JS — if you're keeping TS, **keep Wayfinder**, it's the better tool. If you want to match your current Breeze-style setup, swap to Ziggy.
3. Rip out shadcn-vue components and the reka-vue/cva/tw-merge deps.
4. Add daisyUI v5 as a `@plugin` in `app.css`, remove shadcn's CSS variable block.
5. Rewrite the auth + settings pages with daisyUI markup.
6. Keep lucide-vue-next for icons (it's independent of shadcn and pairs fine with daisyUI).

---

## Part 4: Fork vs. build from scratch (JS + Ziggy scenario)

Assuming the decision is **JS (not TS) + Ziggy (not Wayfinder) + daisyUI (not shadcn)**, is it still preferable to fork the official kit, or build a new one directly on Fortify?

### Upfront effort

Forking still saves time upfront, but less than you'd think once you're also ripping out TS and Wayfinder on top of shadcn. Tally of what changes from the kit's defaults:

- Remove shadcn-vue components, reka-vue, cva, clsx, tw-merge → add daisyUI
- Remove Wayfinder, regenerate scripts, Vite plugin → add Ziggy, `@routes` directive
- Convert every `.ts` / `.vue<script setup lang="ts">` to plain JS — every page, every composable, every type import deleted
- Rewrite auth + settings pages with daisyUI markup (you'd do this either way)
- Delete `tsconfig.json`, TS-related ESLint config, types packages

At that point you've touched **nearly every file in `resources/js/`**. The Fortify backend wiring (controllers, FortifyServiceProvider, routes, middleware) is the part you genuinely inherit cleanly, and that's maybe a day of work to replicate from scratch.

So the calculus: forking saves you the Fortify wiring (~1 day) but costs you a TS→JS conversion (~1 day) plus shadcn→daisyUI (~1-2 days either way). **Roughly a wash on upfront effort.**

### Maintainability over time — this is where it diverges sharply

**Forking has a long-term tax.** Every Laravel/kit release, you face a choice:

- **Merge upstream**: painful, because your fork diverges in deep ways (TS→JS especially — every upstream file conflicts). You'll spend hours resolving conflicts on every minor release, and the resolution is mostly "keep mine" anyway.
- **Don't merge upstream**: your kit drifts. You miss Fortify config updates, Inertia v2.x improvements, security patches to auth flows, new Laravel conventions baked into the kit.

In practice most fork-maintainers stop merging after the second or third painful sync and the kit slowly becomes stale.

**Building from scratch has a different shape of tax.** You own a smaller surface area:

- Your Fortify integration: a `FortifyServiceProvider`, a handful of view-binding closures, ~10 Inertia auth pages. That's it. Fortify itself is a Composer dep that updates on its own.
- Your `composer.json` and `package.json` are clean — `composer update` and `npm update` Just Work because you have no weird transitive deps from shadcn/reka/Wayfinder.
- When Laravel 13 lands, you bump `laravel/framework`, run migrations, fix anything that breaks. Same as any normal app.
- You never have to "sync with upstream" because there is no upstream.

The cost is you don't get free improvements to the official kit's UX (new settings pages, accessibility fixes, etc.). But since you're using daisyUI anyway, those upstream improvements would barely apply to your kit.

### Recommendation

**Given JS + Ziggy + daisyUI, build from scratch.** Three reasons:

1. The TS→JS conversion alone wipes out most of the forking savings.
2. Your stack diverges enough from the official kit that upstream merges will be painful forever.
3. A small, clean kit you fully understand is much easier to keep current than a fork with mystery shadcn/Wayfinder remnants you didn't quite finish removing.

**Exception**: if you think you might switch to TS later, fork. The fork preserves the option to re-adopt official-kit conventions cheaply. If you're confident JS is the long-term choice, scratch wins on maintainability by a wide margin.
