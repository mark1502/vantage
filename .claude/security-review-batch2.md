# Security Review — Batch 2 (Sibling Controllers)

**Date:** 2026-06-03
**Scope:** The C3 "unaudited sibling controllers" from `security-review-remaining.md`:
`Folder`, `View`, `User`, `Preference`, `RecentFile`, `Dashboard`, `Admin`, `Entrytype`, `Profile`, `Subscription`, `Welcome`.
**Status:** Audit only — **no code changed yet.** Findings below, severity-ranked, with the established fix pattern noted for each.
**Related:** [`security-review-remaining.md`](security-review-remaining.md) · [`security-review-batch1.md`](security-review-batch1.md)

---

## TL;DR

The same broken-multi-tenant-authorization (IDOR) class found in Batch 1 is present here, plus a **second class new to this batch: missing Admin authorization** on user-management and shared-resource endpoints. Several routes that create/modify **users** and **firm-wide entry types** are reachable by any authenticated user and resolve records by raw route-model binding with **no firm check**.

Two findings rise to **Critical** (cross-firm account takeover / user edit). The good news: `SubscriptionController`, `DashboardController`, `ProfileController`, and `RecentFileController` are clean.

Worst offenders, in order: **UserController**, **WelcomeController**, **ViewController**, **EntrytypeController**, **FolderController**, **PreferenceController**.

---

## Findings

### 🔴 CRITICAL

#### B2-1 — `UserController@edit` / `@update`: cross-firm user read + write, no Admin gate
- **Route:** `Route::resource('users', …)` → `/users/{user}` (`auth`+`welcomed` only; **no Admin middleware**).
- **`edit(User $user)`** (line 106): route-model binding with **no firm check**, then `Contact::where('user_id', $user->id)->first()`. A user from firm A can load the edit screen for **any user in any firm** → reads name, email, phones, initials, role (cross-firm PII).
- **`update(Request $request, User $user)`** (line 132): the contact lookup uses `->where('firm_id', $user->firm_id)` — that's the **target** user's firm, not the caller's, so it always resolves. There is **no check that `$user` belongs to the caller's firm**. A firm-A user can rewrite any firm-B user's name/email/phones, **reset their password** (`change_password`), and **promote them to `Admin`** (`user_type`). Full cross-firm account takeover + privilege escalation.
- **No Admin gate** either: even within a firm, a `Standard` user can edit other users.
- **Fix:** new `UserPolicy` (`view`/`update` → `$caller->firm_id === $user->firm_id` **and** `$caller->user_type === 'Admin'`); `$this->authorize()` in `edit`/`update`; consider an `admin` route middleware or `can:` gate on the `users` resource. Same for `store` (see B2-5).

#### B2-2 — `WelcomeController@postWelcomeAdmin`: cross-firm account takeover via `editUser`, no Admin gate
- **Route:** `POST /welcome_admin` under **`auth` only** (welcome routes intentionally skip `welcomed`) — and there is **no Admin gate**.
- **`formtype === 'editUser'`** (line 152): `$foundUser = User::where('email', $verified3['email'])->firstOrFail()` resolves a user **by email globally, with no firm scoping**. The attacker supplies a victim's email and can change their `display_name`, `user_type`, and **password** (`change_password`) → account takeover across firms. The uniqueness/conflict checks scope to `$foundUser->firm_id`, reinforcing that the caller's firm is never compared.
- **`formtype === 'addingAttorney'`** (line 98) creates a new `User` + `Contact`; firm_id is taken from the caller (`$user->firm_id`) so it can't cross firms, but it has **no Admin gate** → any authenticated user can create users (incl. `Admin`) in their own firm (privilege escalation, see B2-5).
- **Fix:** gate `postWelcomeAdmin` to Admins; for `editUser`, resolve the target within the caller's firm (`->where('firm_id', $caller->firm_id)`) and authorize, instead of a global email lookup. Reconsider whether `editUser`/`addingAttorney` belong in the welcome flow at all (they duplicate `UserController`).

---

### 🟠 HIGH

#### B2-3 — `ViewController@store` / `@update` + `StoreViewRequest`: unscoped FKs, cross-firm writes
- **`StoreViewRequest::authorize()` returns `true`** (line 12, comment "changed to true to disable for now"), and **none of its FKs are firm-scoped** — `file_id`, `folder_id`, `entrytype_id`, `from_contact_id`, `to_contact_id`, `is_response_to`, `was_response_to` are plain `integer`/`numeric` with no `Rule::exists(...)->where('firm_id', …)`. This is the same gap that was closed in `StoreEntryRequest` and `CalendarController`, but never applied here.
- **`store()`** forces `entry.firm_id` to the caller's firm (good for the entry row itself), **but**:
  - `savePendingContactRoles($request, $entry->file_id)` writes `ContactRole` rows keyed on a **request-supplied `file_id`** with an arbitrary `contact_id` — no check that the file or contact belong to the caller's firm → cross-firm contact-role write (mirrors the Batch 1 reserved-file hardening).
  - `handleThisResponse(...)` (line 483) does `Entry::where('id', $response_to)` and **sets `expecting_response`** / creates a `Response` row pointing at a **request-supplied entry id** with no firm check → a user can flip response state on, and attach Response rows to, **any entry in any firm**. `handleIsNoResponse` has the same shape.
- **`update(StoreViewRequest, Entry $view)`** does check `$entry->firm_id === caller firm_id` inline (line 171) — but on mismatch it **silently no-ops** (no 403; minor info issue) and still inherits the unscoped FK problem for `entrytype_id`/`from_contact_id`/`to_contact_id` and the `handleThisResponse` cross-firm write.
- **Fix:** firm-scope every FK in `StoreViewRequest` via `Rule::exists(...)->where('firm_id', $firmId)` (folder stays plain `exists`; allow the reserved `file_id` per the Batch 1 carve-out); make `authorize()` delegate to `EntryPolicy`/`FilePolicy`; firm-scope the `is_response_to`/`was_response_to` lookups inside `handleThisResponse`/`handleIsNoResponse` (or pass `$firmId` and add `->where('firm_id', …)`); convert the inline `update` check to `$this->authorize('update', $view)` for a real 403.

#### B2-4 — `EntrytypeController@update` / `@destroy` / `@restore`: cross-firm IDOR write
- **Routes:** `PUT/DELETE/PATCH /entrytypes/{entrytype}` — route-model binding, **no firm check** in any of the three.
- A user from firm A can **rename** (`update`), **soft-delete** (`destroy`, sets `faux_deleted`), or **restore** (`restore`) **any firm's entry types** by ID. `index`/`store` are correctly firm-scoped, so this is purely the by-ID actions.
- The SOL-protection guard (`name === SOL_ENTRYTYPE_NAME && folder_id === 6`) is not a firm check.
- **Fix:** new `EntrytypePolicy` (`update`/`delete` → firm match) + `$this->authorize()` in `update`/`destroy`/`restore`. Established pattern.

#### B2-5 — Missing Admin authorization on user/shared-resource mutation (privilege escalation)
- There is **no `admin` route middleware anywhere** (`bootstrap/app.php` registers none). Admin-only intent is enforced only ad-hoc (`SubscriptionController` does it well with `abort_unless($user->user_type === 'Admin', 403)`).
- Consequently a **`Standard` user can**: create users incl. `Admin` accounts (`UserController@store`, `WelcomeController` `addingAttorney`), and reach `/adminmenu` (B2-8). Combined with B2-1/B2-2 this is a clear privilege-escalation surface.
- **Fix:** introduce a reusable `admin` gate/middleware and apply it to `users.*`, the `postWelcomeAdmin` admin formtypes, `folders.*`, `entrytypes.*` (store/update/destroy/restore), `filetypes.*`, and `/adminmenu`. (Confirm with the product owner which of these are meant to be Admin-only vs. all-users.)

---

### 🟡 MEDIUM

#### B2-6 — `FolderController@store` / `@update` / `@edit`: any user can modify a global shared table
- Per the schema notes, **`folders` is a global/shared table** (same IDs used by every firm; no `firm_id`). So there's no IDOR — but that's exactly the problem: **any welcomed user from any firm** can create new folders or rename/reconfigure existing folder definitions (prompts, `input_time`, etc.) that are **used by every firm in the system**. Cross-tenant shared-resource tampering / integrity risk.
- `edit` uses route-model binding (fine, global table); `destroy`/`show` are empty no-ops.
- **Fix:** these should almost certainly be **super-admin / system-admin only**, not per-firm-admin and definitely not all-users. Gate the write actions (`store`/`update`). Confirm intended ownership model with the owner — if folders are supposed to be per-firm, that's a larger schema change.

#### B2-7 — `PreferenceController@index`: cross-user/cross-firm preference read + auto-create
- **Route:** `GET /users/{user}/preferences` — `index(Request, User $user)` has **no ownership or firm check**. Any user can view **any other user's** preferences page, and the method **auto-creates** missing `Preference` rows for that user as a side effect.
- **Fix:** `abort_unless($request->user()->id === $user->id || ($request->user()->user_type === 'Admin' && $request->user()->firm_id === $user->firm_id), 403)` (or a `PreferencePolicy`).

#### B2-8 — Preference update methods: Admin branch not firm-scoped
- `eventcolor_update`, `hover_placement_update`, `file_open_update`, `theme_update` all gate on `$request->user()->id == $request->user_id || $request->user()->user_type === 'Admin'`. The code itself flags the gap (`// NOTE: admin needs further test for correct firm`): an **Admin in firm A can update preferences for a user in firm B** (request-supplied `user_id`, no firm check). Lower severity (requires Admin) but cross-tenant.
- **Fix:** when taking the Admin branch, also require the target `user_id`'s `firm_id` to equal the admin's firm_id.

---

### ⚪ LOW / INFO

- **B2-9 — `/adminmenu` not Admin-gated** (`AdminController@menu`): any welcomed user can load the admin menu UI. Low on its own (it's a link list), but the linked actions must be individually protected (see B2-5). `test_form()` has no registered route.
- **B2-10 — Stray debug endpoint:** `GET /preferences/updateEntrytypes` → `PreferenceController@update_entrytypes` ends in **`dd('Done!')`** and is reachable by any welcomed user. It only writes to the caller's own firm (copies firm-1 default entry types), so not cross-tenant, but it's an un-gated maintenance/debug route that halts execution. Recommend removing or gating to Admin.
- **B2-11 — Silent no-op instead of 403:** `ViewController@update` (firm mismatch) and `EntryController@toggle_read` return empty/no-op rather than `abort(403)` on cross-firm attempts. Not exploitable but inconsistent with the established pattern and hides the condition.
- **B2-12 — `StoreViewRequest::authorize()` hard-coded `true`** with a "disable for now" comment — should delegate to a policy (covered by B2-3).
- **B2-13 — Minor robustness:** `RecentFileController@index` maps `$recentFile->file->id` without a null guard (deleted file → null deref). Non-security.

---

## Clean (no action)

- **`SubscriptionController`** — every method `abort_unless(Admin)`; firm derived from `$request->user()->firm`; Stripe interval validated. ✅ Good reference implementation.
- **`DashboardController`** — all queries firm-scoped via `$user->firm_id`; SOL buckets scoped. ✅
- **`ProfileController`** — operates only on `$request->user()`. ✅
- **`RecentFileController`** — scoped to `auth()->user()->id` (aside from B2-13). ✅
- **`AdminController`** — no data access (aside from the un-gated menu route, B2-9). ✅

---

## Recommended remediation order

1. **B2-1, B2-2** (Critical: cross-firm user takeover) — `UserController@edit/@update`, `WelcomeController@postWelcomeAdmin` `editUser`. Add firm check + Admin gate.
2. **B2-5** (the structural Admin-gate gap that B2-1/B2-2/B2-6 all share) — introduce an `admin` middleware/gate and apply to user/shared-resource routes. Decide with the owner which routes are Admin-only.
3. **B2-3** (`ViewController` + `StoreViewRequest`) — firm-scope FKs and the response-handling lookups; this is the last unfixed instance of the Batch-1 calendar/entry bug.
4. **B2-4** (`EntrytypeController` update/destroy/restore) — policy + authorize.
5. **B2-6, B2-7, B2-8** (Medium) — folder write gating, preference index ownership, preference-update admin firm check.
6. **B2-9 – B2-13** (Low/info) — gate/remove debug routes, convert silent no-ops to 403, null guard.

**Cross-cutting:** every Critical/High here is a downstream symptom of (a) no global `firm_id` scope and (b) no `admin` gate. Step #3 of the parent doc (global scope) plus a new `admin` middleware would neutralize most of this batch structurally.

---

## Verification still owed (per project preference: no automated tests)
Once fixes land, click-test in-app: cross-firm `/users/{id}` edit → 403; `editUser` with a foreign email → 403; view/calendar add with a foreign `file_id`/`contact_id`/`is_response_to` → rejected; entrytype update/delete/restore on a foreign id → 403; `/users/{id}/preferences` for another user → 403; non-admin hitting user/folder/entrytype writes → 403.
