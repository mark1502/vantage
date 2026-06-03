# Security Review — Batch 1 (Contact / ContactRole / Filetype / Calendar)

**Date:** 2026-06-02
**Scope:** First batch of the C3 sibling-controller audit from [`security-review-remaining.md`](security-review-remaining.md).
**Status:** AUDIT ONLY — no code changes made yet.

Same root cause as the rest of the app: records carry `firm_id`, but several actions resolve by raw ID (route-model binding / `where('id', …)->first()`) **without confirming firm ownership** → multi-tenant IDOR. Auth is enforced; per-record authorization is not.

---

## CalendarController — 🔴 Critical — ✅ FIXED (2026-06-02)

**Fix applied to `store()`:**
- FKs firm-scoped via `Rule::exists(...)->where('firm_id', $firmId)` — `file_id`, `entrytype_id`, `from_contact_id`; `folder_id` uses plain `Rule::exists('folders','id')` (shared/global table); `entry_id` firm-scoped too.
- `edit`/`delete` branches now `Entry::findOrFail(...)` + `$this->authorize('update'|'delete', $event)` via `EntryPolicy` → 403 cross-firm, 404 missing (also fixes the prior null-deref).
- `php -l` clean, `pint` clean. Not yet exercised in the running app.

### Finding CAL-1 — `store()` edit/delete actions have no firm check (Critical)
Route: `POST /calendar` (`calendar.store`).
- `action === 'edit'`: `Entry::where('id', $request->entry_id)->first()` then `save()` — **no `firm_id` check**. Any authenticated user can edit *any entry in any firm* by ID, and repoint `file_id` / `entrytype_id` / `from_contact_id` to arbitrary values. Also null-derefs (500) when the id doesn't exist.
- `action === 'delete'`: `Entry::where('id', $request->entry_id)->first()->delete()` — **no `firm_id` check**. Delete any entry in the system by ID. Null-derefs when missing.

### Finding CAL-2 — `store()` add action: unvalidated cross-firm FKs (Medium)
`action === 'add'` sets `firm_id` to the caller's firm (good) but `file_id`, `folder_id`, `entrytype_id`, `from_contact_id` are validated only as integers, not firm-scoped. Lets a user attach a calendar entry to **another firm's `file_id`**, leaking that file's name/closed-status back through `get_events`' `file:id,name,date_closed` eager load.

### Clean in this controller
`index`, `get_events` (firm-scoped), `event_placement` (has `$event->firm_id == $request->user()->firm_id` guard), `lookup_file`, `add_new_event_type` (sets firm_id) — all OK.
- Minor (non-security): `add_new_event_type` references `$new_entrytype` even when the `if` that defines it is false → undefined-variable/null when `name == chosen_name`.
- Minor: `get_events` uses `$request->user1/start/end` unvalidated, but stays firm-scoped (no IDOR).

---

## Follow-up: Reserved (non-file-specific) file — ✅ FIXED (2026-06-03)

**How it surfaced:** the C2 data sanity check (see `security-review-remaining.md`) found 8 entries whose `firm_id` (firm 7) differed from their parent file's `firm_id`. That parent is `file_id = 1`, **`"Reserved File 1 - Not File Specific"`, owned by firm 1** — a single shared file every firm uses as the bucket for entries not tied to a real case (memos, phone messages, to-dos, calendar events).

**The regression introduced by Batch 1:** the CAL-2 fix firm-scoped `file_id` (`Rule::exists('files','id')->where('firm_id', $firmId)`). For every firm except firm 1 that **rejects the reserved file**, breaking calendar add/edit/delete on it. The same wall existed on the non-calendar paths that authorize on the *file* (`EntryController@index` → 404, `store` via `StoreEntryRequest`/`FilePolicy` → 403, legacy `edit` → 403).

**The invariant that makes the fix safe:** *the shared file is only a label; isolation rides entirely on `entry.firm_id`.* Every entry read filters by `entry.firm_id = caller's firm`, every write authorizes on it (`EntryPolicy`), and creation stamps it. So multiple firms can attach entries to file 1, yet each firm sees/edits only its own. The reserved **file record itself** stays untouchable by other firms (file edit/update/destroy still go through `FilePolicy`) — only firm 1 can rename/delete it.

**Decision (Option A):** keep the single shared reserved file (matches existing app design) rather than per-firm reserved files (Option B, cleaner but needs a seeder + per-firm lookup + data repoint). Accepted trade-off: the reserved file is a permanent exception to "every row owned by one firm," so every query touching it must remember to firm-scope its entries (will also need a carve-out in the future `firm_id` global scope).

**Changes applied:**
1. **`config/documents.php`** — added `'reserved_file_id' => (int) env('RESERVED_FILE_ID', 1)` (removes the magic number; env-overridable).
2. **`EntryController@index`** — loads the file when it's the caller's own firm **or** the reserved id; **added `->where('firm_id', $firmId)` to the entries query** (the line that prevents the cross-firm leak — `index` previously filtered by `file_id` only, which on the shared file would have exposed *every* firm's entries; no-op for normal files); dropdown helpers (`getFirmMembers/Attorneys/FileTypes/Folders`) now take the **caller's** firm, not `$file->firm_id` (which is firm 1 for the reserved file).
3. **`StoreEntryRequest::authorize()`** — returns `true` when the bound route file is the reserved id (creation stamps `entry.firm_id`, so isolation holds); otherwise unchanged `FilePolicy` check.
4. **`CalendarController@store`** — `file_id` validation now accepts the caller's own file **or** the reserved file (`Rule::exists` with an `orWhere('id', $reservedFileId)` closure).
5. **`EntryController@edit`** (legacy, possibly unused) — skips the `FilePolicy::view` gate for the reserved file; its entry query was already firm-scoped.

**Follow-up hardening (same day) — `index` auxiliary queries:** the fix above scoped the *main* entries query, but `index` also fires side-channel loads that were scoped by `file_id` only — safe for normal firm-owned files, but cross-firm-leaky on the shared reserved file. Firm-scoped four of them (each gained a `$firmId` param; call sites in `index()` updated to pass `$firmId`; all no-ops for normal files):
- **`getContactRoleIds($file_id, $firmId)`** and **`getFileContactRoles($file_id, $firmId, $refresh)`** — added `->whereHas('contact', fn ($q) => $q->where('firm_id', $firmId))` (a PK-keyed `EXISTS` on `contacts`). `contact_roles` has **no `firm_id` column** (only `file_id` + `contact_id`), so scoping must go through the `contact` relation. *Why it matters:* `contact_roles` rows *can* land on the reserved file from a non-firm-1 user via `savePendingContactRoles()` (called unconditionally by `EntryController@store`/`@update` when the request carries `pending_contact_roles[]`, writing `file_id = 1` + a caller-firm `contact_id`). `ContactRoleController` itself is *not* a vector — it `abort(403)`s cross-firm.
- **`getFileContacts($file_id, $firmId, $refresh, $new_contact_added)`** — added `->where('firm_id', $firmId)` to both `entries` subqueries (the from/to contact-id gatherers). This was a genuine leak, not just an edge case: the subqueries pulled contact ids from *every* firm's entries on `file_id = 1`, and the outer `contacts` query had no firm filter, so a firm-7 user would get other firms' contact ids + display names. Scoping the entry subqueries is sufficient (entry FKs are already firm-validated, so the resulting `IN (…)` list contains only this firm's contacts) and trims the list earlier. File_id-leading indexes serve the query; `firm_id` is a cheap residual.
- **`getExpectingResponse($file_id, $firmId)`** — added `->where('firm_id', $firmId)` after `expecting_response`. Backed by the `(firm_id, expecting_response, date_response_expected)` composite index.
- **Deliberately left unscoped (your call):** `$file->client` / `$file->assignedAttorney` — require `is_file_client`/`is_file_attorney = true`, which `savePendingContactRoles` never sets and which `ContactRoleController`/file-create can't set cross-firm → can only ever be firm 1's data. Candidates for defense-in-depth scoping later, not a current leak.

- `php -l` clean on all changed files; `pint --dirty` clean. **Not yet exercised in the running app.**
- **To verify in-app:** a firm-7 user on `/files/1/entries` sees **only firm-7's** non-file-specific entries; add/edit/delete of memo/phone/to-do/calendar entries on file 1 works; a second firm cannot see the first firm's reserved-file entries.

---

## ContactController — 🔴 High — ✅ FIXED (2026-06-02)

**Fix applied:**
- New `app/Policies/ContactPolicy.php` (`view`/`update`/`delete` = `firm_id` match; auto-discovered).
- `$this->authorize('view', $contact)` in `edit`; `'update'` in `update` and `restore`; `'delete'` in `destroy` → 403 cross-firm.
- `index` pagination capped: `min((int) $request->query('show', 10) ?: 10, 50)` (matches `FileController`).
- `php -l` clean, `pint` clean. Not yet exercised in the running app.

### Finding CON-1 — `edit` / `update` / `destroy` / `restore` have no firm check (High)
All resolve `Contact $contact` by route-model binding with **no firm-ownership verification**:
- `edit(Contact $contact)` — view any contact's full PII cross-firm (data disclosure).
- `update(Request, Contact $contact)` — modify any contact cross-firm. The `display_name` uniqueness rule validates against the **caller's** firm, not the contact's, so the guard is doubly wrong.
- `destroy(Request, Contact $contact)` — soft-delete (`faux_deleted = true`) any contact cross-firm.
- `restore(Request, Contact $contact)` — un-delete any contact cross-firm.

### Clean
`index` and `store` are firm-scoped.

### Minor
`index` does not cap `$show` (user-controlled `?show=`) → `?show=huge` DoS vector, same as the capped Files/Entries indexes.

---

## FiletypeController — 🟠 Medium / High — ✅ FIXED (2026-06-02)

**Fix applied:**
- New `app/Policies/FiletypePolicy.php` (`view`/`update`/`delete` = `firm_id` match; auto-discovered).
- `$this->authorize('view', $filetype)` in `edit`; `'update'` in `update` → 403 cross-firm.
- **Removed the `$filetype->firm_id = $request->user()->firm_id` line in `update`** — that was the tenant-theft vector; the existing `firm_id` is now preserved and the policy guarantees same-firm ownership.
- `index` pagination capped: `min((int) $request->query('show', 10) ?: 10, 50)`.
- `php -l` clean, `pint` clean (reformatted whitespace/style). Not yet exercised in the running app.
- Non-security `has_enable_file_SOL` typo (FT minor) left as-is — out of security scope; flag separately if you want it fixed.

### Finding FT-1 — `edit` / `update` have no firm check (High)
- `edit(Filetype $filetype)` — view any filetype cross-firm.
- `update(Request, Filetype $filetype)` — modify any filetype cross-firm. It executes `$filetype->firm_id = $request->user()->firm_id`, so an attacker can **reassign another firm's filetype into their own firm** (tenant record theft), not merely edit it. The `check4RemovedFolder`/`entriesFound` guards run against the *caller's* firm while the filetype belongs to another — mismatched.

### Clean
`index`, `store`, `set_default_type` (toggles only within the caller's own firm) are firm-scoped. `destroy(string $id)` is an empty no-op.

### Minor
- `index` does not cap `$show` → same DoS vector as above.
- Non-security bug: `check4RemovedFolder` line ~244 reads `$filetype->has_enable_file_SOL` (no such column; should be `enable_file_SOL`), so that SOL-removal branch never fires.

---

## ContactRoleController — 🟢 Already secure

All four actions resolve the parent `File` and enforce `$file->firm_id !== $request->user()->firm_id → abort(403)`:
- `getContactRoleIds(File $file)` — firm-checked.
- `store` — firm-checked on file; also verifies the contact belongs to the firm before attaching.
- `update(ContactRole $contactRole)` — loads file via `file_id`, firm-checked.
- `destroy(ContactRole $contactRole)` — firm-checked.

### Minor (non-security)
`store` does `Contact::find($request->contact_id)` with no null guard → 500 on a bad id (use `findOrFail`). Functionally secure either way.

---

## Recommended fixes (when approved)

Apply the established pattern from the Files/Entries/Firm work:

1. **Contact** — `php artisan make:policy ContactPolicy --model=Contact` (`view`/`update`/`delete` = `firm_id` match); `authorize()` in `edit`/`update`/`destroy`/`restore` (or move to `StoreContactRequest`/`UpdateContactRequest`); cap `index` pagination.
2. **Filetype** — `FiletypePolicy` + `authorize()` in `edit`/`update`; **stop setting `firm_id` from the request in `update`** (keep the existing value); cap `index` pagination.
3. **Calendar `store`** — for `edit`/`delete`, load the entry **and** verify `firm_id` (mirror `event_placement`'s guard or use `EntryPolicy`); for `add`/`edit`, firm-scope `file_id` / `entrytype_id` / `from_contact_id` via `Rule::exists(...)->where('firm_id', $firmId)` (folder is the shared/global table → plain exists); add null guards. Consider extracting a `StoreCalendarEventRequest`.
4. **ContactRole** — optional: `findOrFail` for the contact lookup; otherwise leave as-is.

Severity order to fix: **CAL-1 (Critical) → CON-1 (High) → FT-1 (High) → CAL-2 (Medium) → DoS caps / minors.**
