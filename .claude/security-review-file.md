# Security Review — `resources/js/Pages/Files`

**Date:** 2026-06-02
**Scope:** All Vue files in `resources/js/Pages/Files/` (`Index.vue`, `Create.vue`, `Edit.vue`, `FileForm.vue`, `FileLookup.vue`, `FileLookup_form.vue`), plus the backend endpoints they call (`FileController`, routes, `File` model), since client-side code cannot enforce security on its own.

> **Key framing:** These are client-side SPA components. Anything enforced only in Vue (button disabling, role checks, limits) can be bypassed by a user crafting requests directly. The findings that matter for security are therefore mostly about whether the **server** enforces what the UI assumes. The most serious issues below are server-side gaps that these pages depend on.

---

## Fixes Applied (2026-06-02)

The findings below were remediated. Summary of the changes and how each maps to a finding:

### Fix A — Firm-ownership authorization on edit/update/destroy (resolves Finding #1, Critical)
- **`app/Policies/FilePolicy.php` (new):** Added a policy with `view`, `update`, and `delete` abilities, each returning `true` only when `$user->firm_id === $file->firm_id`. Laravel 12 auto-discovers this policy (`App\Models\File` → `App\Policies\FilePolicy`), so no manual registration is needed.
- **`app/Http/Controllers/Controller.php`:** Added the `AuthorizesRequests` trait to the base controller so `$this->authorize(...)` is available (the default Laravel 12 base controller ships empty).
- **`FileController@edit`:** Now calls `$this->authorize('view', $file)` before loading any data, so requesting another firm's file ID returns 403 instead of disclosing it.
- **`FileController@update`:** Authorization is enforced in `UpdateFileRequest::authorize()` via `$this->user()->can('update', $this->route('file'))`, which blocks cross-firm updates before validation runs.
- **`FileController@destroy`:** Switched from `destroy(Request $request, $id)` with an unscoped `File::find($id)` to route-model binding `destroy(File $file)` guarded by `$this->authorize('delete', $file)` (via `FilePolicy::delete`). A cross-firm ID now returns **403** and a non-existent ID returns **404**, instead of soft-closing another firm's file. This matches the policy-based pattern used in `EntryController@destroy`, so both `destroy` methods are now consistent. *(Note: the previous friendly "Unable to delete this file…" flash message is removed — no legitimate user reaches that path, since the UI only ever surfaces the caller's own file IDs.)* The soft-delete logic (`date_closed`, "File Closed as DELETED" summary append) is unchanged.

**Net effect:** every by-ID action (`edit`, `update`, `destroy`) is now scoped to the caller's firm. The previously-safe `index`/`create`/`store`/`lookup_file` were already firm-scoped and are unchanged in that respect.

### Fix B — Validate foreign keys belong to the firm (resolves Finding #2, High)
- **`app/Http/Requests/StoreFileRequest.php` (new)** and **`app/Http/Requests/UpdateFileRequest.php` (new):** `attorney_id`, `client_contact_id` (store only), and `filetype_id` now use `Rule::exists(...)->where('firm_id', $firmId)` so a request can no longer attach a contact or filetype belonging to another firm. Custom messages explain the rejection. `FileController@store` and `@update` were switched to type-hint these Form Requests, and the old inline `$request->validate([...])` blocks were removed.

### Fix C — Cap pagination size (resolves Finding #3, Medium)
- **`FileController@index`:** Replaced `->paginate($show ? $show : 10)` with a clamped value: `$show = min((int) $request->query('show', 10) ?: 10, 50);`. A user can no longer request an arbitrarily large page size; the per-page count is capped at 50 (default 10).

### Also addressed
- **Finding #6 (Form Request convention):** `store`/`update` now use dedicated Form Request classes instead of inline validation, matching the project convention and giving the firm-scoped rules and `authorize()` a proper home.

### Not changed (intentionally)
- **Finding #4 (client-only UI gating):** Left as-is — the server already enforces the file-creation limit in `create`/`store` via `canCreateFiles()`; the Vue checks are cosmetic and acceptable as long as that holds.
- **Finding #5 (XSS):** No change needed — positive finding.
- **Finding #7 (`$guarded = []`):** Left as-is for now; not currently exploitable because all writes are explicit. Noted as future hardening.
- **Finding #8 (`destroy` is a soft-close):** Behavior preserved; it is now firm-scoped (see Fix A).

---

## Critical

### 1. Broken access control / IDOR — cross-tenant file edit, update, and delete
**Where:** `FileController@edit`, `@update`, `@destroy` (`app/Http/Controllers/FileController.php`), reached from `Edit.vue` / `FileForm.vue` (`files.update`) and `Index.vue` `confirmDelete()` (`router.delete('/files/' + id)`).

`edit(File $file)` and `update(Request, File $file)` use route-model binding, and `destroy($id)` uses `File::find($id)` — **none of them verify that the file belongs to the authenticated user's firm.** There is no policy (`app/Policies` is empty) and no global firm scope on the `File` model.

**Impact:** Any authenticated user (in any firm) can read, modify, or "delete" (soft-close) **any file in the entire system** just by changing the numeric ID in the URL, e.g. `GET /files/123/edit`, `PUT /files/123`, `DELETE /files/123`. This exposes other firms' confidential matter data (summaries, fees, clients, court/docket info) and lets them tamper with it. This is the headline finding.

Note that `index`, `create`, `store`, and `lookup_file` **do** correctly scope by `->where('firm_id', $request->user()->firm_id)`, which makes the omission in `edit`/`update`/`destroy` stand out as the gap.

**Fix:** Add a `FilePolicy` (`Gate`/`authorize`) checking `$file->firm_id === $user->firm_id`, or apply a global firm scope, or explicitly `->where('firm_id', ...)` in every lookup. Resolve `destroy` via the same firm-scoped binding rather than raw `File::find($id)`.

---

## High

### 2. Cross-tenant foreign keys not validated on store/update
**Where:** `FileController@store` and `@update`.

`attorney_id`, `client_contact_id`, and `filetype_id` are validated only as `integer`/`nullable` — there is no check that those IDs belong to the current firm. A crafted request can attach a `ContactRole` pointing at a contact or filetype from **another firm**, corrupting tenant isolation and leaking the referenced names back into the UI (`client_name`, attorney display name).

**Fix:** Use `Rule::exists('contacts','id')->where('firm_id', $firmId)` (and similarly for `filetype_id`), or look the records up scoped to the firm before saving.

---

## Medium

### 3. Unbounded pagination size (`show` / per-page) — resource exhaustion
**Where:** `Index.vue` reads `show` from `state.show` and the URL (`urlParams.get('show')`) and passes it to `router.get`; `FileController@index` does `->paginate($show ? $show : 10)` with no upper bound.

A user can request `?show=1000000`, forcing the server to load and serialize an enormous result set (a cheap DoS / memory-pressure vector). The dropdown only offers 6–25, but the value is fully user-controlled.

**Fix:** Cap server-side, e.g. `$perPage = min((int) $request->query('show', 10), 50);`.

### 4. Authorization/limit checks duplicated only in the client
**Where:** `Index.vue` — `isAdmin`, `atFileLimit`, `addDisabled`, and the "Upgrade" gating are computed purely in Vue from `page.props`.

The create/file-limit case is *also* enforced server-side in `create`/`store` via `canCreateFiles()` (good). Flagging it so it's understood these UI checks are cosmetic only — never add a new gated action without a matching server check. No action needed as long as that pattern holds.

---

## Low / Informational

### 5. No XSS exposure observed (positive finding)
All dynamic file data (`file.name`, `summary`, `court_filed`, `docket_number`, client/attorney names, etc.) is rendered with Vue's `{{ }}` interpolation, which auto-escapes. No `v-html`, no `innerHTML`, no dynamic `:href`/`:src` built from user input. Summary is shown in a `readonly`/escaped `<textarea>`. Good — keep avoiding `v-html` for these fields.

### 6. Convention: inline validation instead of Form Requests
**Where:** `store`/`update` validate inline. `CLAUDE.md` / Laravel guidelines call for dedicated Form Request classes (and they'd be the natural place to add the firm-scoped `exists` rules and an `authorize()` method that fixes findings #1 and #2). Not a vulnerability by itself, but the right home for the fixes above.

### 7. `$guarded = []` on the `File` model
**Where:** `app/Models/File.php`. Mass assignment is fully open. The current controllers assign each field explicitly, so it's not currently exploitable here — but any future `File::create($request->all())` or `->update($request->all())` would immediately allow setting `firm_id` (which is `$hidden` but not guarded) and any other column. Consider `$fillable` or keeping all writes explicit.

### 8. `destroy` is a soft-close, not a delete (behavioral note)
`destroy` sets `date_closed` and appends "File Closed as DELETED" to the summary rather than deleting. Not a security flaw, but worth confirming this matches intent. The cross-tenant exposure here (a caller silently "closing" another firm's active files) is now closed by the `FilePolicy::delete` authorization added in Fix A; the soft-close behavior itself is intentionally preserved.

---

## Summary / Priority Order
1. **Fix #1 (cross-tenant IDOR on edit/update/destroy)** — most urgent; full cross-firm read+write of confidential data.
2. **Fix #2 (validate FK ownership on store/update).**
3. **Fix #3 (cap pagination size).**
4. Address #6/#7 as hardening; #4/#5/#8 are informational.

A `FilePolicy` plus firm-scoped Form Requests would resolve #1, #2, and #6 together.
