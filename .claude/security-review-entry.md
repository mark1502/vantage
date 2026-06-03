# Security Review — Entries (Pages, Controller, Model)

**Date:** 2026-06-02
**Scope:**
- `resources/js/Pages/Entries/Index.vue`, `resources/js/Pages/Entries/EntryForm.vue`
- `app/Http/Controllers/EntryController.php`
- `app/Models/Entry.php`
- `app/Http/Requests/StoreEntryRequest.php` (used by `store` and `update`)
- Relevant routes in `routes/web.php`

> **Key framing (same as the file review):** all entry routes sit inside the `auth` + `welcomed` middleware group, so a request must be authenticated. But authentication ≠ authorization. This is a multi-tenant app where every record carries a `firm_id`, and the security question is whether each endpoint confirms the record being read/written/deleted belongs to **the caller's firm**. `EntryController` does this **inconsistently** — `serve_document`, `toggle_read`, and `lookup_contact` check it; the core CRUD methods (`index`, `store`, `destroy`) do not. There is no `EntryPolicy` and no global firm scope on the `Entry` or `File` models.

---

## Fixes Applied (2026-06-02)

All Critical/High/Medium findings below were remediated, reusing the authorization plumbing added in the file review (`AuthorizesRequests` on the base controller, plus the policy pattern). Summary of changes and how each maps to a finding:

### Fix A — `EntryPolicy` (supports Findings #2, #3, #5)
- **`app/Policies/EntryPolicy.php` (new):** `view`/`update`/`delete` abilities, each `true` only when `$user->firm_id === $entry->firm_id`. Auto-discovered by Laravel 12 (`App\Models\Entry` → `App\Policies\EntryPolicy`).

### Fix B — `index` firm-scoped + pagination cap (resolves Finding #1 and #7)
- **`EntryController@index`:** the file lookup is now `File::where('id', $file_id)->where('firm_id', $request->user()->firm_id)->with('Filetype')->firstOrFail()`. Cross-tenant or missing IDs return **404** instead of disclosing another firm's entries, members, attorneys, contacts, and folder structure. `firstOrFail()` also removes the previous null-dereference 500.
- Pagination is clamped: `$show = min((int) $request->query('show', 15) ?: 15, 50);` and `->paginate($show)`.

### Fix C — `store` authorization + firm-scoped foreign keys (resolves Finding #3 and #4)
- **`StoreEntryRequest::authorize()`** no longer returns a blanket `true`. It now resolves the bound record from the route and delegates to the policies:
  - if an `{entry}` is bound (the `update` route) → `$user->can('update', $entry)`;
  - else if a `{file}` is bound (the `store` route) → `$user->can('update', $file)`;
  - else deny.
  Because `store` is `store(StoreEntryRequest $request, File $file)` (the `{file}` segment is model-bound), this blocks creating entries on another firm's file before the controller runs.
- **`StoreEntryRequest::rules()`** now firm-scopes every foreign key with `Rule::exists(...)->where('firm_id', $firmId)`: `entrytype_id`, `from_contact_id`, `to_contact_id`, `was_response_to`, `is_response_to`, and `pending_contact_roles.*.contact_id`. `folder_id` uses `Rule::exists('folders','id')` (folders are a global/shared table, not firm-owned). This is shared by both `store` and `update`.

### Fix D — `update` real authorization + file/entry consistency (resolves Finding #5)
- Authorization is now enforced by `StoreEntryRequest::authorize()` (entry branch → `EntryPolicy::update`), which returns a real **403** instead of the previous silent no-op.
- Added a consistency check so the URL's file must match the entry's file: `abort_unless((int) $request->route('file') === (int) $entry->file_id, 404);`. (See concern #C1 below for why this reads the route segment directly rather than the `$file_id` argument.)
- **Removed the now-redundant inline `if ($entry->firm_id === $request->user()->firm_id)` wrapper.** Because `StoreEntryRequest::authorize()` already enforces the firm match before the controller runs, the inline check could never be false; the guarded body (`save`, `savePendingContactRoles`, `checkInFile` calls, response handling, `comeback` redirect) now runs unconditionally and was de-indented one level. A comment was added pointing to the upstream authorization. No behavior change for legitimate requests.

### Fix E — `destroy` authorization (resolves Finding #2)
- **`EntryController@destroy`:** added `$this->authorize('delete', $entry);` as the first statement, so a cross-firm entry ID now returns 403 before any deletion/cascade runs. Removed the stray `// dd($request)` debug comment.

### Fix F — remove reachable `dd()` + firm-scope `edit` (resolves Finding #8)
- **`EntryController@edit`:** removed `dd('THIS IS CALLED');`, added `$this->authorize('view', $file);`, scoped the entry query to the caller's firm, and changed the trailing `->first()` to `->firstOrFail()` so the (still route-registered) edit endpoint can no longer dump state or 500 on a null entry.

### Fix G — validate `folder_id` in `add_new_entrytype` (resolves Finding #6)
- `folder_id` rule is now `['required','numeric','integer', Rule::exists('folders','id')]`, preventing creation of entrytypes attached to non-existent folders.

### Verification
- `vendor/bin/pint` passes; `php -l` clean on the controller, request, and policy; `php artisan route:list --name=entries` resolves all routes. Per project preference, no tests were written or run.

### Not changed (intentionally)
- **Finding #9 (duplicate contact-add handlers), #10 (reflected-but-escaped message), #11 (`Entry::firm()` return type), #12 (no `$fillable`):** left as-is — all are low/informational hardening items, none currently exploitable. Noted for a future cleanup pass.
- **Positive findings (`serve_document`, `linked_document_path`, `toggle_read`, `lookup_contact`, no `v-html`):** unchanged.

### Concerns / Follow-ups
- **C1 — `update`'s `$file_id` argument may not be bound.** The route segment is `{file}` but the method parameter is named `$file_id`; Laravel matches route params to method args **by name**, so `$file_id` likely never received the value (the original code never used it). To avoid a false 404 on every legitimate update, Fix D reads the segment via `$request->route('file')` instead of trusting `$file_id`. **Recommend** renaming the parameter to `$file` (or removing it) for clarity, and confirming the update flow end-to-end in the running app — I did not execute it here.
- **C2 — shared `StoreEntryRequest` now firm-validates response/contact FKs.** If any *pre-existing* data legitimately references entries/contacts across firms (it shouldn't, given the model), those edits would now fail validation. Worth a quick data sanity check before/after deploy.
- **C3 — the same IDOR class very likely exists in sibling controllers** (`CalendarController`, `ContactController`, `ContactRoleController`, `FiletypeController`, etc.) since there is still no global firm scope. A `firm_id` global scope on the tenant-owned models would close the read-side IDORs app-wide in one place and is the recommended structural follow-up. The per-controller policy approach used here is correct but must be repeated everywhere.

---

## Critical

### 1. `index($file_id)` — cross-tenant read of an entire file's entries and firm data (IDOR)
**Where:** `EntryController@index`, route `GET /files/{file}/entries`.

```php
$file = File::where('id', $file_id)->with('Filetype')->first();   // no firm_id check
```

The file is loaded purely by the ID in the URL with **no `firm_id` filter and no authorization**. Any authenticated user can read **any other firm's** file by changing the ID, and the page returns far more than the entries:

- All `entries` for that file (notes, dates, amounts, correspondence — the most sensitive data in the app).
- `firm_members`, `attorneys`, `filetypes`, `folders`, `file_contacts`, `file_contact_roles` — and critically these helpers are called with **`$file->firm_id`** (the *target* file's firm), so the response leaks the other firm's member roster, attorneys, contacts, and folder/entrytype structure too.

**Secondary issues in the same method:**
- If `$file_id` doesn't exist, `$file` is `null` and `$file->filetype->$folderFlag` throws → unhandled 500.
- `RecentFile::track(auth()->id(), $file->id, ...)` will write "recent file" rows pointing at another firm's file.

**Impact:** Full cross-tenant disclosure of case data and firm metadata. This is the most serious finding.

### 2. `destroy()` — cross-tenant deletion of any entry (IDOR)
**Where:** `EntryController@destroy`, route `DELETE /files/{file}/entries/{entry}`.

```php
public function destroy(Request $request, $file_id, Entry $entry)
{
    $request->validate([... 'entry_id' => 'required', ...]);
    if ($request->entry_id == $entry->id) {   // attacker controls both sides
        $this->handleIsNoResponse($entry->id);
        $entry->delete();
        ...
    }
}
```

There is **no `firm_id` check** anywhere in `destroy`. The only guard is `$request->entry_id == $entry->id`, but the attacker supplies both the route `{entry}` and the body `entry_id`, so it's trivially satisfied. Any authenticated user can delete **any entry in the system** by ID — along with cascading effects (`handleIsNoResponse` deletes related `Response` records and flips `expecting_response` on other entries; `checkInFile` may delete `ContactRole` rows). Cross-tenant destructive action with side effects.

### 3. `store(File $file)` — cross-tenant entry creation / data corruption (IDOR)
**Where:** `EntryController@store`, route `POST /files/{file}/entries`.

The `File` is route-model-bound with **no firm check**. The new entry is given `firm_id = $request->user()->firm_id` (the caller's firm) but `file_id = $file->id` (potentially another firm's file). An attacker can:
- Inject entries into another firm's file (the entries even carry the attacker's `firm_id`, producing inconsistent/orphaned cross-tenant rows).
- Trigger `savePendingContactRoles` and response-handling against another firm's file.

**Impact:** Cross-tenant write and data integrity corruption.

---

## High

### 4. `StoreEntryRequest` disables authorization and does not firm-scope foreign keys
**Where:** `app/Http/Requests/StoreEntryRequest.php` (used by both `store` and `update`).

- `authorize()` returns `true` ("set to true to disable authorization") — so the Form Request provides no access control; it relies entirely on the controller, which (per #1–#3) doesn't enforce firm ownership for create.
- The FK rules are type-only: `folder_id`, `entrytype_id`, `from_contact_id`, `to_contact_id` are validated as `integer`/`numeric` with **no `Rule::exists(...)->where('firm_id', ...)`**. A crafted request can therefore reference **another firm's contact** as `from_contact_id`/`to_contact_id`, or set an arbitrary `entrytype_id`/`folder_id`. (Compare to the `StoreFileRequest`/`UpdateFileRequest` added in the file review, which do scope these.)

**Impact:** Even once #1–#3 are fixed, entries could still be linked to cross-tenant contacts/entrytypes. Should be fixed together.

### 5. `update()` — firm check is a silent no-op and route/entry file mismatch is unchecked
**Where:** `EntryController@update`, route `PUT /files/{file}/entries/{entry}`.

```php
if ($entry->firm_id === $request->user()->firm_id) {
    $entry->save();
    ...
}
// else: nothing — no abort(403), returns empty response
```

A firm check *exists* here (good), but:
- On mismatch it **silently does nothing** and returns an empty response instead of `abort(403)`, masking the rejection and making misuse hard to detect.
- The `$file_id` route segment is never validated against `$entry->file_id`, so the URL's file and the entry's actual file need not agree.
- Same un-scoped FK problem as #4 (shares `StoreEntryRequest`).

**Recommendation:** Replace the inline `if` with a policy/`abort(403)` and assert `$entry->file_id == $file_id`.

---

## Medium

### 6. `add_new_entrytype` — unvalidated `folder_id`
**Where:** `EntryController@add_new_entrytype`. `folder_id` is validated only as `required|numeric|integer`; no check that it is a real folder. The new `Entrytype` is created with the caller's `firm_id` (safe on tenancy) but with an arbitrary `folder_id`, allowing creation of dangling/invalid entrytypes. Data-integrity issue.

### 7. Unbounded pagination size in `index`
**Where:** `EntryController@index` — `->paginate($show ? $show : 15)` with `$show = $request->query('show')` and no upper bound. A user can request `?show=1000000`, forcing a huge result load (resource-exhaustion / DoS vector). Same issue and fix as the file review (`min(..., 50)`).

### 8. Reachable debug `dd()` in `edit()`
**Where:** `EntryController@edit` begins with `dd('THIS IS CALLED');`. Although commented as "might not get called anymore," the `Route::resource('files.entries', ...)` registration **still maps** `GET /files/{file}/entries/{entry}/edit` to this method. Any authenticated user hitting that URL triggers `dd()`, which halts execution and (in non-production debug mode) can dump request/state. There is no firm check before the `dd()` either. Remove the dead method or the route; never leave reachable `dd()` in a controller. (Also several `// dd($request)` comments scattered through the file — cleanup.)

---

## Low / Informational

### 9. Three near-duplicate contact-creation handlers
`contact_add_modal`, `contact_add_modal2`, and `new_contact_modal` are ~identical (validation + create + differing return shapes). All correctly set `firm_id` from the user and scope the `display_name` uniqueness rule to the firm, so there's no tenancy bug — but the duplication is a security-maintenance risk: a future hardening fix applied to one can easily be missed in the others. Consolidate into one method/Form Request.

### 10. User input reflected in validation message
The contact handlers build the uniqueness error with `'...the name '.$request->display_name.' is already...'`. This is returned as a validation error and rendered by Vue's text interpolation (auto-escaped), so it is **not** an XSS vector — noted only for awareness that raw input is echoed back.

### 11. `Entry::firm()` return type can be violated
**Where:** `app/Models/Entry.php` — `public function firm(): Firm { return Firm::find($this->firm_id); }`. `Firm::find()` returns `?Firm`, but the signature is non-nullable `Firm`, so a missing/invalid `firm_id` throws a `TypeError`. Robustness, not directly exploitable. (Note: this is a plain lookup, not an Eloquent relationship method — eager-loading it isn't possible, which is a minor design smell.)

### 12. `Entry` model declares no `$fillable`/`$guarded`
The model doesn't define mass-assignment protection. It's not currently exploitable because `store`/`update` assign each attribute explicitly (no `Entry::create($request->all())` / `->fill()`), but any future switch to mass assignment would immediately allow setting `firm_id`, `file_id`, etc. from the request. Add an explicit `$fillable` (or keep all writes explicit and document it).

---

## Positive Findings (done well — keep)
- **`serve_document`** is solid: firm-ownership check (`abort(403)`), verifies the firm base path with `realpath`, builds the full path via the model helper, and enforces **path-traversal containment** (`str_starts_with($resolvedFullPath, $baseWithSep)`) before serving. Good defensive coding.
- **`linked_document_path`** is validated in `StoreEntryRequest` with `not_regex:/\.\./` to block `..` sequences, complementing the realpath check above.
- **`toggle_read`** and **`lookup_contact`** both correctly scope by `$request->user()->firm_id`.
- **No XSS surface in the Vue pages:** no `v-html`, `innerHTML`, or `eval`; all dynamic entry/contact data is rendered through escaped `{{ }}` interpolation.

---

## Summary / Priority Order
1. **#1 `index` cross-tenant read** — highest impact; leaks entries + firm roster/contacts for any file ID.
2. **#2 `destroy` cross-tenant delete** — destructive, with cascading side effects.
3. **#3 `store` cross-tenant write** — data corruption across firms.
4. **#4 / #5** — add firm-scoped FK validation and turn `update`'s silent skip into real authorization; enable `authorize()` in the Form Request.
5. **#6 / #7 / #8** — validate `folder_id`, cap pagination, remove the reachable `dd()`.
6. **#9–#12** — cleanup/hardening.

**Recommended structural fix (mirrors the file review):** add an `EntryPolicy` (`view`/`update`/`delete` checking `entry.firm_id === user.firm_id`), resolve `File`/`Entry` through firm-scoped binding, and move the foreign-key `exists` rules into the Form Request. A `firm_id` global scope on `Entry` and `File` would close the read-side IDORs (#1) across the whole app in one place. Note that the file review already added a `FilePolicy` and `AuthorizesRequests` on the base controller, so the authorization plumbing is in place to reuse here.

*No code changes were made as part of this review — findings only.*
