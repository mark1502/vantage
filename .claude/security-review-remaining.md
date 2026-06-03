# Security Review — Status, Concerns & Next Steps (Handoff)

**Date:** 2026-06-02 (updated 2026-06-03, session 2)
**Purpose:** Running summary of the multi-tenant security review so work can resume cleanly later.
**Related docs:** [`.claude/security-review-file.md`](security-review-file.md) · [`.claude/security-review-entry.md`](security-review-entry.md) · [`.claude/security-review-firm.md`](security-review-firm.md) · [`.claude/security-review-batch1.md`](security-review-batch1.md) · [`.claude/security-review-batch2.md`](security-review-batch2.md)

> **⏩ RESUME HERE (session 2, 2026-06-03):** Batch 2 audit is complete (see `security-review-batch2.md`). The `firm_id` global scope is **mid-rollout** — 5 of 6 tenant models done (`Entrytype`, `Filetype`, `Preference`, `Contact`, `Entry`); **`File` is the only one left** (needs the reserved-file OR-clause carve-out). See the new **"Global scope rollout"** section below for exact state, what's verified vs. untested, and the next action.

---

## TL;DR

The core problem across the app is **broken multi-tenant authorization (IDOR)**: every record carries a `firm_id`, but many controller actions resolve records by raw ID (route-model binding or `find($id)`) **without confirming the record belongs to the caller's firm**. Authentication is enforced everywhere (all routes are behind `auth` + `welcomed`); per-record *authorization* is not.

So far the **Files**, **Entries**, **Firm**, **Contact**, **Filetype**, and **Calendar** domains have been remediated (plus **ContactRole**, which was already secure). **All remaining sibling controllers have now been audited (Batch 2 — see `security-review-batch2.md`).** The recommended structural fix — a `firm_id` global scope on tenant-owned models — is now **mid-rollout** (5 of 6 models live; see "Global scope rollout" below).

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

### Global scope rollout (IN PROGRESS — session 2, 2026-06-03)
Implemented the `BelongsToFirm` trait and began applying it model-by-model with click-testing between each.
- **New file `app/Models/Concerns/BelongsToFirm.php`**: registers a global scope under the string key `'firm'` that adds `where('<table>.firm_id', auth()->user()->firm_id)` **only when `auth()->check()`** (so seeders/jobs/console/pre-login no-op); plus a `creating` hook that auto-stamps `firm_id` when empty + authenticated. Exposes an overridable `applyFirmScope()` so `File` can later add the reserved-file OR-clause. Escape hatch for legitimate cross-firm reads: `Model::withoutGlobalScope('firm')`.
- **Models scoped & each click-tested OK by the user:**
  - **`Entrytype`** ✅ verified. Also **removed dead code**: `PreferenceController@update_entrytypes` + its `/preferences/updateEntrytypes` route (was B2-10) — confirmed unused. Closes **B2-4** (cross-firm entrytype update/destroy/restore now 404 via route-model binding).
  - **`Filetype`** ✅ verified. Carve-out: `FiletypeController:24` base-filetype copy now uses `->withoutGlobalScope('firm')` (authenticated firm-1 read).
  - **`Preference`** ✅ verified. No carve-out needed.
  - **`Contact`** ✅ verified. No carve-out needed. **Side-effect mitigations:** B2-1 `UserController@update` cross-firm → 404 at contact lookup; B2-2 `WelcomeController editUser` cross-firm → fails at contact `firstOrFail`.
  - **`Entry`** ⚠️ **applied + Pint/`php -l` clean, but NOT yet click-tested by the user** (they ran out of time). **This is the immediate thing to verify on resume.** Side-effects: mitigates **B2-3** (response handlers no-op on foreign entry ids) and closes a **latent unscoped cross-firm leak** in `ViewController::getExpectingResponse` (line ~313, file filter was commented out).
- **Remaining model: `File`** — NOT started. Needs the **overridden `applyFirmScope()`** with the reserved-file OR-clause: `where(firm_id = auth OR id = config('documents.reserved_file_id'))`. The carve-out goes **only on `File`** (entries on the reserved file stay isolated by their own `firm_id`). Also re-confirm `FilePolicy` keeps update/delete of file 1 restricted to firm 1.
- **DB:: blind spot (by design):** raw `DB::table('contacts'|'users')` queries (e.g. `UserController@index`, `ViewController::getFileContacts_fake`, `EntryController:864`) are **not** covered by the Eloquent scope and keep their own manual `firm_id` filters.

### ⚠️ Interim behavior changes introduced by the scope (not bugs — track until admin-gate layer lands)
- The two still-ungated cross-firm **abuse** endpoints now **500 (null-deref on `$user->contact`)** instead of silently leaking, when hit with a *foreign-firm* user id: `UserController@edit` (B2-1 read side) and `PreferenceController@index` = `/users/{user}/preferences` (B2-7). Strictly safer, but ugly; the clean 403 comes with the admin-gate work. **Normal same-firm flows are unaffected.**

### Verification performed
- `vendor/bin/pint` clean on all changed PHP; `php -l` clean on the trait and each scoped model.
- **No automated tests written or run** (per project preference).
- **In-app click-testing:** `Entrytype`, `Filetype`, `Preference`, `Contact` confirmed working by the user. **`Entry` not yet exercised.** `File` not yet implemented.

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
3. **C3 — sibling controllers ✅ ALL AUDITED.** Batch 2 (`Folder`, `View`, `User`, `Preference`, `RecentFile`, `Dashboard`, `Admin`, `Entrytype`, `Profile`, `Subscription`, `Welcome`) is complete — see `security-review-batch2.md`. The global-scope rollout (above) plus the still-needed explicit fixes (B2-1/B2-2/B2-3/B2-5) cover the findings. Batch 1 (`Calendar`/`Contact`/`ContactRole`/`Filetype`) and `Firm` were done earlier.
4. **C4 — `EntryController` cleanup (low/info, deferred):** three near-duplicate contact-add handlers (`contact_add_modal`, `contact_add_modal2`, `new_contact_modal`); `Entry::firm()` has a non-nullable `: Firm` return type but calls `Firm::find()` (can return null → TypeError); neither `Entry` nor `File` declares `$fillable`/`$guarded` (safe today only because all writes are explicit).
5. **C5 — no policy/scope coverage is enforced globally.** Today protection is opt-in per method. Until a global scope (below) lands, every new controller action is a fresh opportunity to reintroduce this bug. There is no test guarding against regressions.

---

## Recommended next steps (in priority order)

0. **🔜 IMMEDIATE (resume point):**
   - **Click-test `Entry`** in-app (it's live but unverified): file entry tabs/list/pagination, create/edit/delete entry, toggle read, response full/partial + clear, Views pages (esp. "expecting response" list shows only this firm), calendar render + drag/resize + add/edit/delete, dashboard counts, and reserved-file (non-file-specific) entries.
   - **Finish the scope rollout with `File`** (the last model) — override `applyFirmScope()` with the reserved-file OR-clause `where(firm_id = auth OR id = config('documents.reserved_file_id'))`; verify `FilePolicy` still restricts update/delete of file 1 to firm 1; click-test file list/open/create/edit + reserved-file entries.
   - **Then build the `admin` gate layer** and land the explicit fixes the scope can't reach: **B2-1** (`UserController@edit/@update` — policy + admin gate; also clears the interim 500s), **B2-2** (`WelcomeController` `editUser` — resolve target within caller's firm + admin gate), **B2-3** (`ViewController`/`StoreViewRequest` — firm-scope FKs + delegate `authorize()`; `StoreViewRequest::authorize()` currently hard-coded `true`), **B2-5/B2-6** (apply the admin gate to `users.*`, `folders.*` writes, `entrytypes.*` writes, `filetypes.*`, `/adminmenu`; decide per-route whether Admin-only).
   - Remaining lower-priority Batch-2 items: B2-7 (`/users/{user}/preferences` ownership/firm check), B2-8 (preference-update Admin branch firm check), B2-9 (`/adminmenu` gate), B2-11 (silent no-ops → 403), B2-13 (RecentFile null guard). (B2-10 dead route already removed.)
1. **Verify the earlier-changed domains work in-app** (esp. C1 entry update, file edit/update/destroy, cross-firm returns 403/404).
2. ~~**Audit the remaining C3 sibling controllers**~~ ✅ **DONE** — Batch 2 complete (`security-review-batch2.md`).
3. **Finish the `firm_id` global scope** (IN PROGRESS — `File` is the only model left; see "Global scope rollout" above). General notes on the design:
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
