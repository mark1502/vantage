# Security Review — Status, Concerns & Next Steps (Handoff)

**Date:** 2026-06-02 (updated 2026-06-03, session 2)
**Purpose:** Running summary of the multi-tenant security review so work can resume cleanly later.
**Related docs:** [`.claude/security-review-file.md`](security-review-file.md) · [`.claude/security-review-entry.md`](security-review-entry.md) · [`.claude/security-review-firm.md`](security-review-firm.md) · [`.claude/security-review-batch1.md`](security-review-batch1.md) · [`.claude/security-review-batch2.md`](security-review-batch2.md)

> **⏩ RESUME HERE (session 3, 2026-06-04):** The `firm_id` global scope rollout is **COMPLETE** — all 6 tenant models (`Entrytype`, `Filetype`, `Preference`, `Contact`, `Entry`, `File`) are scoped and **click-tested OK by the user** (including the reserved-file carve-out on `File`). The next phase is the **`admin` gate layer** + the explicit fixes the scope can't reach (B2-1, B2-2, B2-3, B2-5/B2-6). See "Recommended next steps" §0. A separate **"Duplicative code to clean up later"** section now records the manual `firm_id` filters the scope made redundant (deferred — do not remove yet).

---

## TL;DR

The core problem across the app is **broken multi-tenant authorization (IDOR)**: every record carries a `firm_id`, but many controller actions resolve records by raw ID (route-model binding or `find($id)`) **without confirming the record belongs to the caller's firm**. Authentication is enforced everywhere (all routes are behind `auth` + `welcomed`); per-record *authorization* is not.

So far the **Files**, **Entries**, **Firm**, **Contact**, **Filetype**, and **Calendar** domains have been remediated (plus **ContactRole**, which was already secure). **All remaining sibling controllers have now been audited (Batch 2 — see `security-review-batch2.md`).** The recommended structural fix — a `firm_id` global scope on tenant-owned models — is now **COMPLETE** (all 6 models live and verified; see "Global scope rollout" below).

---

## What's been done

### Files domain (complete — see `security-review-file.md`)
- **`app/Policies/FilePolicy.php`** (new): `view`/`update`/`delete` gated on `firm_id` match (auto-discovered).
- **`app/Http/Controllers/Controller.php`**: added `AuthorizesRequests` trait so `$this->authorize()` works (Laravel 12 base controller ships empty).
- **`app/Http/Requests/StoreFileRequest.php` / `UpdateFileRequest.php`** (new): firm-scoped `Rule::exists` on `attorney_id`, `client_contact_id`, `filetype_id`; `UpdateFileRequest::authorize()` enforces the policy.
- **`FileController`**: `edit` authorizes; `store`/`update` use the Form Requests; `destroy` uses route-model binding + `$this->authorize('delete', $file)` (returns 403 cross-firm / 404 missing) — matches the `EntryController@destroy` pattern; `index` caps pagination at 50.

### Entries domain (complete — see `security-review-entry.md`)
- **`app/Policies/EntryPolicy.php`** (new): `view`/`update`/`delete` on `firm_id` match.
- **`app/Http/Requests/StoreEntryRequest.php`**: `authorize()` now resolves the bound route record and delegates to `EntryPolicy`/`FilePolicy` (was a blanket `true`); all FKs firm-scoped via `Rule::exists(...)->where('firm_id', …)` (`entrytype_id`, `from/to_contact_id`, `was/is_response_to`, `pending_contact_roles.*.contact_id`); `folder_id` validated with `Rule::exists('folders','id')` (no firm filter — see "folders" note below).
- **`EntryController`**: `index` firm-scoped `firstOrFail()` + pagination cap; `store` authorized via Form Request; `update` real 403 + file/entry consistency check (and the now-redundant inline firm-check `if` was removed, since `StoreEntryRequest::authorize()` already enforces it upstream); `destroy` authorizes `delete`; `edit` — removed reachable `dd('THIS IS CALLED')`, added authorize + firm scope + `firstOrFail()`; `add_new_entrytype` validates `folder_id`.

### Firm domain (complete — see `security-review-firm.md`)
- **Path-traversal in `browseDirectory` was already correct** (realpath-resolved target + separator-terminated base prefix, mirroring `serve_document`); no cross-firm IDOR (base always derived from the caller's own firm).
- **Real risk found & fixed (Finding #1):** `document_base_path` was an unconstrained absolute server path set by a firm admin, usable to read **app-server** files (`.env`, source) via `serve_document`/`browseDirectory`. Added `Firm::safeDocumentBasePath()` (single source of truth) enforcing an always-on `base_path()` exclusion + an optional allow-list (`config/documents.php` ← `DOCUMENT_ALLOWED_ROOTS`); enforced at write-time (`UpdateFirmRequest`) and read-time (`browseDirectory`, `serve_document`). Cross-platform (Linux is the prod target).
- **`browseDirectory`** `path` query param now validated (`nullable|string|max:500`).
- **Deferred (intentional):** no Admin gate on `browseDirectory` (regular users need the document picker); unbounded directory listing; `protocolSetup` confirmed clean.
- **⚠️ Production action (not code):** set `DOCUMENT_ALLOWED_ROOTS` to the firm-share mount root(s) so the allow-list fully engages.

### Batch 1 — Contact / ContactRole / Filetype / Calendar (complete — see `security-review-batch1.md`)
- **`CalendarController@store`** (was 🔴 Critical): `edit`/`delete` actions did a bare `Entry::where('id',…)->first()` with **no firm check** — any user could edit/delete *any entry in any firm* by ID. Fixed: FKs firm-scoped via `Rule::exists(...)->where('firm_id', …)` (`file_id`/`entrytype_id`/`from_contact_id`/`entry_id`; `folder_id` plain exists); `edit`/`delete` now `Entry::findOrFail()` + `$this->authorize('update'|'delete', …)` (EntryPolicy) → 403/404, also fixing a null-deref.
- **`ContactController`** (was 🔴 High): `edit`/`update`/`destroy`/`restore` had no firm check (cross-firm PII read + write/soft-delete/restore). Fixed: new **`ContactPolicy`** + `authorize()` on all four; `index` pagination capped at 50.
- **`FiletypeController`** (was 🟠 High): `edit`/`update` had no firm check; `update` even **reassigned `firm_id` to the caller's firm** (tenant theft). Fixed: new **`FiletypePolicy`** + `authorize()` on `edit`/`update`; removed the `firm_id` reassignment line; `index` pagination capped.
- **`ContactRoleController`**: audited — **already secure** (every method enforces `$file->firm_id !== user firm_id → abort(403)`). No change. (Minor non-security: `store` uses `Contact::find` without a null guard.)
- **Not yet exercised in the running app** — the new `authorize()`/`findOrFail` guards surface as 403/404; happy paths (calendar add/edit/delete, contact edit/delete/restore, filetype edit/update) should be click-tested.
- Non-security `FiletypeController` typo (`has_enable_file_SOL`, line ~244) left as-is (out of scope).

### Reserved (non-file-specific) file — follow-up fix (2026-06-03, see `security-review-batch1.md`)
- The Batch 1 CAL-2 fix firm-scoped `file_id`, which **broke the shared reserved file** (`file_id = 1`, `"Reserved File 1 - Not File Specific"`, owned by firm 1) that every firm uses for non-file-specific entries (memos / phone / to-do / calendar). Surfaced via the C2 data check (8 firm-7 entries on firm-1's file 1).
- **Fixed (Option A — keep the single shared file):** added `config('documents.reserved_file_id')`; allowed the reserved id in `CalendarController@store`, `StoreEntryRequest::authorize()`, and `EntryController@index`/`edit`; and **added `->where('firm_id', $firmId)` to the `index` entries query** to prevent a cross-firm leak (it previously filtered by `file_id` only). Isolation rides on `entry.firm_id`; the reserved file record itself remains editable only by firm 1.
- **Follow-up hardening:** `index`'s side-channel loads were also `file_id`-scoped only. Firm-scoped four of them (each gained a `$firmId` param; no-op for normal files): `getContactRoleIds`/`getFileContactRoles` via `whereHas('contact', …firm_id…)` (contact_roles has no `firm_id` column → scoped through the contact relation; needed because `savePendingContactRoles()` can write a non-firm-1 contact_role onto file 1); `getFileContacts` via `->where('firm_id', …)` on its two entry subqueries (a real leak — it pulled contact ids+names from every firm's file-1 entries); and `getExpectingResponse` via `->where('firm_id', …)`. Left `$file->client`/`$file->assignedAttorney` unscoped by decision (require `is_file_*` flags that can only ever be firm 1's data — not a current leak). See `security-review-batch1.md` for the full rationale.
- **⚠️ Global-scope caveat:** the reserved file is now a deliberate exception to "every row owned by one firm" — the future `firm_id` global scope (step #3 below) will need a carve-out so file 1 stays loadable by all firms while its entries remain firm-isolated. Option B (per-firm reserved files) was the cleaner alternative and is still worth revisiting if the special-case becomes painful.

### Batch 2 — sibling-controller audit (complete — see `security-review-batch2.md`)
- Audited `Folder`, `View`, `User`, `Preference`, `RecentFile`, `Dashboard`, `Admin`, `Entrytype`, `Profile`, `Subscription`, `Welcome`.
- **New finding class beyond IDOR: missing Admin authorization** (no `admin` middleware exists anywhere). Two **Critical** cross-firm account-takeover findings (`UserController@edit/@update` = B2-1; `WelcomeController@postWelcomeAdmin` `editUser` = B2-2), plus High `ViewController`/`StoreViewRequest` (B2-3), `EntrytypeController` by-id writes (B2-4), and the admin-gate gap (B2-5). Clean: `Subscription`, `Dashboard`, `Profile`, `RecentFile`.

### Global scope rollout (COMPLETE — session 2 2026-06-03, finished session 3 2026-06-04)
Implemented the `BelongsToFirm` trait and began applying it model-by-model with click-testing between each.
- **New file `app/Models/Concerns/BelongsToFirm.php`**: registers a global scope under the string key `'firm'` that adds `where('<table>.firm_id', auth()->user()->firm_id)` **only when `auth()->check()`** (so seeders/jobs/console/pre-login no-op); plus a `creating` hook that auto-stamps `firm_id` when empty + authenticated. Exposes an overridable `applyFirmScope()` so `File` can later add the reserved-file OR-clause. Escape hatch for legitimate cross-firm reads: `Model::withoutGlobalScope('firm')`.
- **Models scoped & each click-tested OK by the user:**
  - **`Entrytype`** ✅ verified. Also **removed dead code**: `PreferenceController@update_entrytypes` + its `/preferences/updateEntrytypes` route (was B2-10) — confirmed unused. Closes **B2-4** (cross-firm entrytype update/destroy/restore now 404 via route-model binding).
  - **`Filetype`** ✅ verified. Carve-out: `FiletypeController:24` base-filetype copy now uses `->withoutGlobalScope('firm')` (authenticated firm-1 read).
  - **`Preference`** ✅ verified. No carve-out needed.
  - **`Contact`** ✅ verified. No carve-out needed. **Side-effect mitigations:** B2-1 `UserController@update` cross-firm → 404 at contact lookup; B2-2 `WelcomeController editUser` cross-firm → fails at contact `firstOrFail`.
  - **`Entry`** ✅ verified (session 3). Side-effects: mitigates **B2-3** (response handlers no-op on foreign entry ids) and closes a **latent unscoped cross-firm leak** in `ViewController::getExpectingResponse` (line ~313, file filter was commented out). **Unrelated bug found & fixed during this verification:** `ViewController::update` (line 216) passed `$create = true` to `handleThisResponse` on the *update* path, forcing an INSERT and a unique-constraint violation on `responses.entry_id` when editing a response-entry from `Views/Index` (the `EntryController` update path correctly passes `$create = false`). Changed to `$create = false`.
  - **`File`** ✅ verified (session 3). Added `use BelongsToFirm` + an overridden `applyFirmScope()` with the reserved-file OR-clause `where(files.firm_id = auth OR files.id = config('documents.reserved_file_id'))`. The carve-out lives **only on `File`** (entries on the reserved file stay isolated by their own `firm_id`). `FilePolicy` still gates update/delete on `$user->firm_id === $file->firm_id`, so although file 1 is now *readable* by every firm, only firm 1 can edit/delete it. No authenticated cross-firm `File` read needed an escape hatch (no firm-1 base-file copy on registration, unlike `Filetype`).
- **DB:: blind spot (by design):** raw `DB::table('contacts'|'users')` queries (e.g. `UserController@index`, `ViewController::getFileContacts_fake`, `EntryController:864`) are **not** covered by the Eloquent scope and keep their own manual `firm_id` filters.

### Duplicative code to clean up later (DEFERRED — do NOT remove yet)
Now that the global scope is live, several **manual `->where('firm_id', …)` filters on `File` queries are redundant** (the scope already constrains them to the caller's firm). They are harmless (the extra predicate is a no-op for same-firm rows) and are being **left in place for now as belt-and-suspenders**; clean up gradually once the scope has been exercised in production. Recorded here so we know where they are:

- **`EntryController` lines ~28–35 (entries index)** — the **manual reserved-file OR-clause** (`where(firm_id = $firmId OR id = $reservedFileId)`) is now **fully duplicated** by `File::applyFirmScope()`. This is the most notable duplication: the scope's carve-out and this inline clause do exactly the same thing. (Note: this loads via `firstOrFail()`, so once trusted, the whole closure can drop to `File::with('Filetype')->findOrFail($file_id)`.)
- **`FileController:31` (index)** — `->where('firm_id', $request->user()->firm_id)` redundant.
- **`FileController:283` (`lookup_file`)** — `->where('firm_id', $request->user()->firm_id)` redundant.
- **`CalendarController:331` (`lookup_file`)** — `->where('firm_id', $request->user()->firm_id)` redundant.
- **`DashboardController:107` (SOL-bucket `$base` builder)** — `->where('firm_id', $firmId)` redundant.
- **`FiletypeController:270` (`entriesFound`)** — `->where('firm_id', $request->user()->firm_id)` redundant.
- **`Firm.php:142` (`firmFileCount`)** — `File::where('firm_id', $this->id)->count()` is **NOT purely redundant**: it filters by `$this->id`, not the auth firm. Only ever called for the caller's own firm today, so the scope is a no-op there — but if it were ever reused for a *different* firm, the scope would (correctly, for safety) zero the count. Leave as-is; flagged so a future refactor doesn't assume it's cross-firm capable.

Not duplicative (keep): `FilePolicy` (controls *what you may do* vs. the scope's *what you can see* — complementary defense-in-depth) and the route-model-binding `findOrFail` 404s on `edit`/`show`/`destroy` (the scope now produces the 404 before `authorize()` runs, but both layers are intended).

### ⚠️ Interim behavior changes introduced by the scope (not bugs — track until admin-gate layer lands)
- The two still-ungated cross-firm **abuse** endpoints now **500 (null-deref on `$user->contact`)** instead of silently leaking, when hit with a *foreign-firm* user id: `UserController@edit` (B2-1 read side) and `PreferenceController@index` = `/users/{user}/preferences` (B2-7). Strictly safer, but ugly; the clean 403 comes with the admin-gate work. **Normal same-firm flows are unaffected.**
  - **UPDATE (session 4) — both now RESOLVED:** `UserController@edit` (B2-1) returns a clean **403** (the new `UserPolicy` authorize runs before the contact lookup); `/users/{user}/preferences` (B2-7) returns a clean **403** (the new `canManagePreferencesFor` guard runs before `$user->contact`). The interim-500 condition no longer exists.

### Admin gate layer (COMPLETE — session 4, 2026-06-04) — closes B2-1, B2-2, B2-4, B2-6, B2-5
**Product rules confirmed by the user this session:**
- `Admin` is a **per-firm** role. Only Admins may manage users / designate other users (from their own firm only) as Admin.
- **Entry types:** *adding* is open to **all users**; *editing/deleting/restoring* is **Admin-only** (within firm).
- **Folders are a global/shared table → fully read-only for everyone, including all Admins.** No firm may add/edit/delete a folder.
- **WelcomeController** is meant to run only on a firm's first login; the full workflow needs its **own separate plan** (deferred). Only a security stop-gap was applied here.

**Infrastructure (new):**
- **`User::isAdmin(): bool`** — `user_type === 'Admin'` (single source of truth; mirrors `isActive()`).
- **`app/Http/Middleware/EnsureUserIsAdmin.php`** (new) — `abort_unless($request->user()?->isAdmin(), 403)`. Registered as alias **`'admin'`** in `bootstrap/app.php`.
- **`app/Policies/UserPolicy.php`** (new, auto-discovered) — `view`/`update`/`delete` → `$user->isAdmin() && $user->firm_id === $target->firm_id`. (Note: `User` is **not** covered by the `BelongsToFirm` global scope, so this explicit firm check is essential.)
- **`app/Policies/EntrytypePolicy.php`** (new, auto-discovered) — `update`/`delete` → `$user->isAdmin() && same firm`.

**Fixes applied:**
- **B2-1 (`users.*`):** added `->middleware('admin')` to the `users` resource (blocks Standard users from all user management incl. `create`/`store`); added `$this->authorize('update', $user)` to `UserController@edit` and `@update` (firm-match → cross-firm now clean 403, and clears the interim edit-side 500). Closes the cross-firm account-takeover + privilege-escalation hole.
- **B2-4 (`entrytypes` writes):** `$this->authorize()` on `update`/`destroy`/`restore` via `EntrytypePolicy`. `store` left **ungated** (all users may add, per product rule). Global scope already 404s cross-firm; the policy adds the same-firm-Standard-user → 403.
- **B2-6 (`folders`):** route changed to `Route::resource('folders', …)->only(['index'])` → all write routes (`create`/`store`/`edit`/`update`/`destroy`) removed → 404 for everyone, no exceptions. The `FolderController` write methods are now **dead code** (left in place by decision; clean up in the folder-UI follow-up). **UI note:** `Folders/Index.vue` still renders Edit/Create/Delete buttons that now dead-end (they use hardcoded URL strings, not `route()`, so rendering doesn't break) — remove them in a follow-up UI pass.
- **B2-2 (`WelcomeController` — stop-gap only):** `abort_unless($user->isAdmin(), 403)` at the top of `postWelcomeAdmin`; firm-scoped the `editUser` target lookup (`->where('firm_id', $user->firm_id)`). Full workflow redesign deferred to its own plan.
- **B2-5 (structural admin-gate gap):** the reusable `admin` middleware + the two new policies are the structural fix; applied to the above. `filetypes.*` was **already** firm-checked via `FiletypePolicy` (Batch 1) but is **not** Admin-gated — revisit if filetype management should be Admin-only (open question, not done).

**⚠️ Still open after this batch:** `filetypes.*` not Admin-gated (by design pending decision). *(B2-7's interim cross-firm 500 and B2-9's `/adminmenu` gate are now resolved — see sections below.)*

### B2-7 / B2-8 — Preferences domain (COMPLETE — session 4, 2026-06-04)
Both findings shared the same root cause: preference access keyed on a request-supplied/route-bound user with no firm check (and an Admin branch that ignored firm).
- **New private helper `PreferenceController::canManagePreferencesFor(User $actor, int $targetUserId, ?int $targetFirmId = null): bool`** — returns true only if the actor *is* the target user, or is an **Admin in the same firm**. When the target's firm isn't already known (the AJAX update methods only get a raw `user_id`), it looks it up via `User::where('id', …)->value('firm_id')` (`User` is not covered by the global scope, so the explicit firm comparison is required).
- **B2-7 (`index`, `GET /users/{user}/preferences`):** added `abort_unless($this->canManagePreferencesFor($request->user(), $user->id, $user->firm_id), 403)` at the top. Any user could previously view (and auto-create `Preference` rows for) any other user. **This also resolves the interim cross-firm 500** (the guard now 403s before reaching `$user->contact`, which the `Contact` global scope would otherwise null-deref for a foreign-firm user).
- **B2-8 (`eventcolor_update`, `hover_placement_update`, `file_open_update`, `theme_update`):** replaced the firm-blind `... || user_type == 'Admin'` condition in all four with `if ($this->canManagePreferencesFor($request->user(), (int) $request->user_id))`. An Admin in firm A can no longer update a firm-B user's preferences. (Kept the existing silent-no-op structure on the unauthorized branch rather than 403 — these are AJAX writes and the original code never 403'd; converting to abort is left to the B2-11 pass.) Removed the now-resolved `// NOTE: admin needs further test for correct firm` comment.
- **Verification:** `vendor/bin/pint --dirty` pass; `php -l` clean. **NOT yet click-tested.** Owed: own preferences page + each color/hover/file-open/theme update → works; a same-firm Admin editing another user's prefs → works; a non-admin requesting another user's `/preferences` → 403; cross-firm (admin or not) → 403 (index) / no-op (updates).

### B2-9 / B2-13 — admin-menu gate + recent-files null guard (COMPLETE — session 4, 2026-06-04)
- **B2-9 (`/adminmenu`):** added `->middleware('admin')` to the `adminmenu` route (`route:list` confirms `EnsureUserIsAdmin` is attached). Any welcomed user could previously load the admin menu UI; now Admin-only. (`AdminController@test_form` still has no registered route — left as-is.) **UI follow-up (not security):** the AdminMenu link in `AuthenticatedLayout.vue` may still render for non-admins and now dead-ends at 403 — hide it behind an `isAdmin` check in a later UI pass.
- **B2-13 (`RecentFileController@index`):** `$recentFile->file` can be `null` (file deleted, or — now that the `File` global scope is active — a file not visible to the caller's firm), which would null-deref in the `map`. Added `->filter(fn ($recentFile) => $recentFile->file !== null)` before the `map`, plus `->values()` after to keep the JSON response a clean array.
- **Verification:** `vendor/bin/pint --dirty` pass; `php -l` clean; `route:list` confirms the gate. **NOT yet click-tested.** Owed: non-admin hitting `/adminmenu` → 403; admin → loads; recent-files endpoint returns cleanly when a recent file's target was deleted/not visible.

### B2-11 — silent no-ops → real 403 (COMPLETE — session 4, 2026-06-04)
The two named silent-no-op-on-cross-firm branches are both resolved:
- **`ViewController@update`** — already handled when B2-3 unwrapped its `if ($entry->firm_id === caller firm_id)` wrapper (authorization now enforced by `StoreViewRequest::authorize()` + the `Entry` global scope → real 403/404).
- **`EntryController@toggle_read` (`PUT /toggle_read/{entry}`)** — previously returned an empty string on a cross-firm hit (and was even wrapped in a redundant `if ($entry)`). Replaced the nested `if ($entry) { if ($entry->firm_id === …) { … } }` with a single `$this->authorize('update', $entry)` (EntryPolicy), matching `EntryController@destroy`. The `Entry` global scope already 404s a cross-firm/nonexistent id at route binding; the `authorize` adds the documented belt-and-suspenders 403. Happy-path return value is unchanged (`$entry->date2`, the toggled value).
- **Verification:** `vendor/bin/pint --dirty` pass; `php -l` clean. **NOT yet click-tested.** Owed: toggle read/unread on an own-firm entry → works and returns the new `date2`; cross-firm id → 404/403 (not a silent empty response).

### B2-3 — Views domain (COMPLETE — session 4, 2026-06-04)
Closed the last unfixed instance of the Batch-1 calendar/entry IDOR pattern. `StoreViewRequest` is used by both `ViewController@store` (file_id in body, no route binding) and `@update` (`{view}` → `Entry` route binding).
- **`StoreViewRequest::authorize()`** — was hard-coded `return true` ("disabled for now"). Now: *update path* → `$user->can('update', $route('view'))` (EntryPolicy); *store path* → allow the reserved file id, else `File::find($file_id)` (firm-scoped global scope → foreign id resolves null → denied) + `FilePolicy`. Added `use App\Models\File;` + `use Illuminate\Validation\Rule;`.
- **`StoreViewRequest::rules()`** — firm-scoped every FK via `Rule::exists(...)->where('firm_id', $firmId)`: `entrytype_id` (nullable), `from_contact_id` (required), `to_contact_id` (nullable), `was_response_to` (nullable), `is_response_to` (nullable), `pending_contact_roles.*.contact_id` (required). `folder_id` now `Rule::exists('folders','id')` (plain — shared table). `file_id` ownership is enforced in `authorize()` (not via `Rule::exists`, because the reserved-file carve-out needs the OR-clause that a raw `exists` can't express).
- **`ViewController@update`** — removed the silent-no-op `if ($entry->firm_id === caller firm_id)` wrapper (B2-11). Authorization is now enforced upstream by the FormRequest `authorize()` + the `Entry` firm global scope on route binding (foreign id → 404 before the controller runs), so the body executes unconditionally and cross-firm attempts get a real 403/404 instead of a silent no-op.
- **No change needed** for `getExpectingResponse` (Entry global scope already firm-isolates it — its `file_id` filter was commented out) or `handleThisResponse`/`handleIsNoResponse` (their `Entry::where('id',…)` lookups are globally firm-scoped, and `is_response_to`/`was_response_to` are now firm-validated, so `savePendingContactRoles`'s file/contact are caller-owned).
- **Verification:** `vendor/bin/pint --dirty` pass; `php -l` clean on both files. **NOT yet click-tested.** Owed: add memo/phone/todo/event from Views (own file + reserved file) → works; edit a response-entry from Views/Index → works (the session-3 `$create=false` fix); cross-firm `file_id`/`contact_id`/`is_response_to` in a crafted POST → rejected/403.

**Verification (session 4):**
- `vendor/bin/pint --dirty` clean; `php -l` clean on the 3 new files; `php artisan route:list` confirms `folders` = index-only and `EnsureUserIsAdmin` is attached to `users.*`.
- **NOT yet click-tested in-app.** Owed checklist: (1) Standard user → `/users`, `/users/{id}/edit`, entrytype edit/delete/restore, any `/folders/*` write URL → all **403/404**; (2) Admin editing a same-firm user / adding entry type / editing entry type → **works**; (3) cross-firm Admin hitting another firm's `/users/{id}/edit` → **403**; (4) `postWelcomeAdmin` `editUser` with a foreign-firm email → **404** (firmOrFail); non-admin POST to `/welcome_admin` → **403**.

### Verification performed
- `vendor/bin/pint` clean on all changed PHP; `php -l` clean on the trait and each scoped model.
- **No automated tests written or run** (per project preference).
- **In-app click-testing:** all 6 models — `Entrytype`, `Filetype`, `Preference`, `Contact`, `Entry`, `File` — confirmed working by the user (the `File` reserved-file carve-out and the `Views/Index` response-edit fix included).

---

## Schema / performance notes (confirmed)
- `files`: `firm_id` is the **leading column** of `unique(firm_id, name)` and several `(firm_id, date_*)` indexes. ✅
- `entries`: `firm_id`-leading composite indexes exist (`(firm_id, date1)`, `(firm_id, folder_id, date1)`, etc.). ✅
- `contacts`, `filetypes`: indexed on `firm_id`. ✅ (the new `exists` validation lookups are cheap)
- **`folders`: NOT indexed on `firm_id` — by design.** Folders are a **shared/global table**: the same folder IDs are used by every firm. The `folder_id` validation therefore correctly uses `Rule::exists('folders','id')` with **no** `firm_id` predicate, and resolves on the primary key — no `firm_id` index is needed or wanted here.
- **Net performance impact of changes so far:** negligible. Policies do zero queries; the added `Rule::exists` checks are indexed point-lookups on low-frequency write paths; the pagination cap is a net win (removes a `?show=huge` DoS vector).

---

## Open concerns / risks to confirm

1. **C1 — `update`'s `$file_id` argument binding.** In `EntryController@update($request, $file_id, Entry $entry)` the route segment is `{file}` but the arg is `$file_id`; Laravel binds route params to args **by name**, so `$file_id` likely never received the value (original code never used it). The new file/entry consistency check reads `$request->route('file')` directly to avoid a false 404 — but **the update flow has not been exercised end-to-end**. Recommend renaming the param to `$file` and clicking through an entry update in the app.
2. **C2 — stricter FK validation vs. existing data. ✅ CHECKED (2026-06-03, clean).** Ran cross-firm joins on every firm-validated FK (`entrytype_id`, `from/to_contact_id`, `filetype_id`, attorney/client `contact_roles`) → **0 bad rows**; the stricter validation rejects no existing record. The only mismatch found was 8 entries on the shared reserved file (`file_id = 1`), which is by-design and handled by the reserved-file fix above (not a validation failure — `file_id` is deliberately not firm-scoped in `StoreEntryRequest`).
3. **C3 — sibling controllers ✅ ALL AUDITED.** Batch 2 (`Folder`, `View`, `User`, `Preference`, `RecentFile`, `Dashboard`, `Admin`, `Entrytype`, `Profile`, `Subscription`, `Welcome`) is complete — see `security-review-batch2.md`. The global-scope rollout (above) plus the explicit fixes (B2-1 … B2-13, session 4) cover the findings — **Batch 2 is now fully closed.** Batch 1 (`Calendar`/`Contact`/`ContactRole`/`Filetype`) and `Firm` were done earlier.
4. **C4 — `EntryController` cleanup (low/info, deferred):** three near-duplicate contact-add handlers (`contact_add_modal`, `contact_add_modal2`, `new_contact_modal`); `Entry::firm()` has a non-nullable `: Firm` return type but calls `Firm::find()` (can return null → TypeError); neither `Entry` nor `File` declares `$fillable`/`$guarded` (safe today only because all writes are explicit).
5. **C5 — no policy/scope coverage is enforced globally.** Today protection is opt-in per method. Until a global scope (below) lands, every new controller action is a fresh opportunity to reintroduce this bug. There is no test guarding against regressions.

---

## Recommended next steps (in priority order)

0. **🔜 IMMEDIATE (resume point):**
   - ✅ **DONE** — `Entry` scope click-tested (and the `Views/Index` response-edit `$create` bug fixed along the way).
   - ✅ **DONE** — `File` scope implemented with the reserved-file carve-out and click-tested; `FilePolicy` confirmed to still restrict update/delete of file 1 to firm 1.
   - ✅ **DONE (session 4, 2026-06-04) — admin gate layer + B2-1/B2-2/B2-4/B2-6.** See "Admin gate layer" section below. **Not yet click-tested in-app** — see verification checklist there.
   - ✅ **DONE (session 4, 2026-06-04) — B2-3** (`ViewController`/`StoreViewRequest`). See "B2-3 — Views domain" section below. **Not yet click-tested in-app.**
   - ✅ **DONE (session 4, 2026-06-04) — B2-7 + B2-8** (`PreferenceController`). See "B2-7 / B2-8 — Preferences domain" section below. **Not yet click-tested in-app.**
   - ✅ **DONE (session 4, 2026-06-04) — B2-9 + B2-13** (`/adminmenu` gate; `RecentFileController` null guard). See "B2-9 / B2-13" section below. **Not yet click-tested in-app.**
   - ✅ **DONE (session 4, 2026-06-04) — B2-11** (`EntryController@toggle_read` silent no-op → 403). See "B2-11" section below. **Not yet click-tested in-app.**
   - **🟢 BATCH 2 COMPLETE.** All B2 findings (B2-1 … B2-13) are now closed. Remaining open items are **not** Batch-2 findings: the deferred **WelcomeController workflow redesign** (its own plan), the optional **`filetypes.*` Admin-gating decision**, and the **C-series open concerns** (C1 entry-update binding click-test, C4 cleanup) + the **deferred duplicative-`firm_id`-filter cleanup**. **The whole review still needs the in-app click-testing pass** (every fix this session is verified by `pint`/`php -l`/`route:list` only).
   - ~~Remaining lower-priority Batch-2 items: B2-7, B2-8, B2-9, B2-11, B2-13~~ ✅ **ALL DONE this session** (see their COMPLETE sections above). B2-10 dead route was already removed earlier.
   - **Deferred to its own plan (per user):** the **WelcomeController workflow redesign** — the welcome flow is only meant to run on a firm's very first login and should not remain reachable afterward; `editUser`/`addingAttorney` duplicate `UserController`. B2-2 here is only a **security stop-gap** (admin gate + firm-scoped `editUser` lookup), not the redesign.
1. **Verify the earlier-changed domains work in-app** (esp. C1 entry update, file edit/update/destroy, cross-firm returns 403/404).
2. ~~**Audit the remaining C3 sibling controllers**~~ ✅ **DONE** — Batch 2 complete (`security-review-batch2.md`).
3. ~~**Finish the `firm_id` global scope**~~ ✅ **DONE** — all 6 models scoped and verified (see "Global scope rollout" above). General notes on the design (retained for reference + future maintenance):
   - Add a `BelongsToFirm` trait that registers a global scope using `auth()->user()->firm_id` when a user is present.
   - **Handle the no-user paths**: queue jobs, seeders, artisan commands, and any legitimate cross-firm/admin access must `withoutGlobalScope(...)` (or the scope must no-op when unauthenticated). Load-test these.
   - **Reserved-file carve-out**: the shared reserved file (`config('documents.reserved_file_id')`, currently `file_id = 1`, owned by firm 1) must stay loadable by all firms — the `File` scope needs an exception for that id (or it would 404 for everyone but firm 1). Its *entries* stay firm-isolated normally. (Revisiting Option B — a per-firm reserved file — would remove this special case entirely.)
   - Keep the policies and `Rule::exists` validation — the scope controls *which rows are visible*; policies control *what you may do*; validation controls *input integrity*. They are complementary layers (defense in depth), not replacements.
   - After it lands, the manual `->where('firm_id', …)` filters become redundant and can be removed gradually.
   - **Indexing discipline going forward:** with the scope, `firm_id` is always the leading predicate, so any new filter/sort column should be added to a composite index *after* `firm_id` (e.g. `(firm_id, <col>)`). Leave shared tables like `folders` alone.
4. **Add regression tests** (when the team is ready to allow tests): for each tenant-owned resource, assert that a user from firm A receives 403/404 for firm B's records on view/update/delete, and that cross-firm FK references are rejected on store/update.
5. **Address C4 cleanup** opportunistically (consolidate contact-add handlers; fix `Entry::firm()` return type; add explicit `$fillable`).

---

## Quick reference — the established fix pattern
For any tenant-owned resource `X`:
1. `php artisan make:policy XPolicy --model=X` → `view`/`update`/`delete` return `$user->firm_id === $x->firm_id` (auto-discovered; base controller already has `AuthorizesRequests`).
2. Controller: `$this->authorize('<ability>', $x)` on `edit`/`show`/`destroy`; scope index queries with `->where('firm_id', $request->user()->firm_id)` and use `firstOrFail()`; cap pagination (`min((int)$show, 50)`).
3. Form Requests (`StoreXRequest`/`UpdateXRequest`): `authorize()` delegates to the policy via route binding; firm-scope every foreign key with `Rule::exists('<table>','id')->where('firm_id', $firmId)` — **except** references to shared/global tables like `folders`, which use a plain `Rule::exists('folders','id')`.
