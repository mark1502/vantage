# Security Review — `FirmController`

**Date:** 2026-06-02
**Scope:**
- `app/Http/Controllers/FirmController.php` — `edit`, `update`, `protocolSetup`, `browseDirectory`
- `app/Http/Requests/UpdateFirmRequest.php`
- Routes in `routes/web.php` (lines 113–116)
- `resources/js/Components/DocumentPicker.vue` (the `browseDirectory` caller)
**Related docs:** [`.claude/security-review-remaining.md`](security-review-remaining.md) · [`.claude/security-review-file.md`](security-review-file.md) · [`.claude/security-review-entry.md`](security-review-entry.md)

> **Key framing (same as prior reviews):** all four routes are inside the `Route::middleware('auth', 'welcomed')` group, so requests are authenticated. The questions are (a) per-action *authorization* (is the Admin gate applied consistently?), and (b) **path traversal / arbitrary file disclosure**, since `browseDirectory` walks the server filesystem. There is no route-supplied record ID on any of these actions — every method resolves the firm via `$request->user()->firm_id` — so the cross-firm IDOR class that dominated the file/entry reviews is **not present here**.

*No code changes were made as part of this review — findings only.*

---

## Headline — path-traversal protection in `browseDirectory` is correct (positive finding)

`browseDirectory` is the highest-risk surface (it enumerates directories on the server), and its containment is implemented properly — it mirrors the `serve_document` pattern the entry review flagged as the one done *right*:

- `realpath()` canonicalizes the firm base path, and **the target is also `realpath()`-resolved (line 85) before the containment check.** Any `..` segment, symlink, or mixed `/`/`\` separator collapses to its true canonical path first, so the check can't be fooled by un-normalized input.
- Containment uses a **separator-terminated base prefix** (`$baseWithSep`, line 93) with `str_starts_with`, plus an explicit `$resolvedTarget === $resolvedBase` allowance for the root. This blocks both:
  - `path=../../../etc` → resolves outside the base → `str_starts_with` fails → **403**; and
  - the sibling-prefix bypass (base `/firmdocs`, target `/firmdocsEVIL`) → fails because of the trailing separator.
- Error responses are generic ("Directory not found", "Access denied", "Unable to read directory contents") and the returned `current_path` is **relative to the base**, so no server absolute path leaks to the client.
- Symlinks are resolved by `realpath` (a symlink inside the base pointing outside resolves outside → 403). Hidden/dot-prefixed entries are skipped.

**No cross-firm exposure:** the base always comes from the caller's own firm (`Firm::findOrFail($request->user()->firm_id)`), never a route ID. A user cannot browse another firm's configured path.

---

## Fixes Applied (2026-06-02)

### Fix A — constrain `document_base_path` to safe locations (resolves Finding #1)
Firms store documents on their own network shares, never on the app server, so the guard is an **allow-list** (cross-platform; no OS-specific assumptions — production target is Linux):

- **`config/documents.php` (new):** `allowed_roots`, read from `DOCUMENT_ALLOWED_ROOTS` (comma-separated). Empty in local dev; **set in production** to the firm-share mount root(s), e.g. `/mnt/firm-shares`.
- **`app/Models/Firm.php`:** added `safeDocumentBasePath(?string): ?string` (single source of truth). A path is accepted only when it (a) resolves to an existing directory, (b) is **neither inside nor an ancestor of `base_path()`** — this is the always-on protection against reading the app's own `.env`/source/files even when no allow-list is set, and (c) lives under one of the allowed roots when an allow-list is configured. Helpers `allowedDocumentRoots()` and `pathContains()` (separator-terminated prefix using `DIRECTORY_SEPARATOR`, so sibling-name prefixes don't false-match, and both `/` and `\` work).
- **`app/Http/Requests/UpdateFirmRequest.php` (write-time guard):** the `document_base_path` rule now rejects any value failing `Firm::safeDocumentBasePath()`, so an admin can no longer save a path that points inside the app server (or outside the allow-list).
- **`FirmController@browseDirectory` + `EntryController@serve_document` (read-time defense in depth):** both now resolve the base via `Firm::safeDocumentBasePath()` instead of a raw `realpath()`, so even a base saved before this change (or by some other path) cannot be used to enumerate/stream app-server files.

**Net effect:** a firm admin can no longer turn `document_base_path` into an app-server file-read primitive. The always-on `base_path()` exclusion protects the application directory unconditionally; the optional allow-list (recommended in production) restricts the base to the firms' document mount(s).

**Verified mechanism this closes:** `Entry::fullDocumentPath()` = `document_base_path` + `linked_document_path`. Previously an admin could set the base to e.g. the app root and read `.env` (no `..`, so it passed the `not_regex` rule) via `serve_document`, or enumerate the app filesystem via `browseDirectory`. Both readers now reject an unsafe base.

> **Deployment note (out of app scope):** this constrains what *the app* will read. Continue to run PHP as a least-privilege user, keep `.env` out of any served tree, and apply network ACLs to the shares.

### Fix B — validate the `path` query param (resolves Finding #3)
- **`FirmController@browseDirectory`:** added `$request->validate(['path' => 'nullable|string|max:500'])` at the top of the method.

### Not changed (intentionally)
- **Finding #2 (no Admin gate on `browseDirectory`):** left as-is — intentional; regular users need it for the document picker. Adding a gate would break attaching documents.
- **Finding #4 (unbounded directory listing):** left as-is — minor resource concern only; realistic firm folders make it a non-issue.
- **Finding #5 (`protocolSetup`):** no change needed — confirmed clean.

---

## Medium

### 1. `document_base_path` is an unconstrained absolute server path (threat-model dependent) — ✅ RESOLVED (see Fix A)

> **Update:** the user confirmed firm documents live on each firm's own network share, **not** the app server, so cross-firm-on-disk access is not a concern — but reading **app-server** files is. Fix A above closes that: the always-on `base_path()` exclusion plus the optional production allow-list prevent the base from pointing at the application or other server locations. Original analysis retained below for context.
**Where:** `FirmController@update` + `UpdateFirmRequest` (the source of truth), consumed by `browseDirectory` **and** `EntryController@serve_document`.

`UpdateFirmRequest` validates `document_base_path` only with `is_dir($value)` (and `max:500`). Nothing constrains *where* it points. The traversal check in `browseDirectory` contains browsing *within* whatever base the admin chose — but it does **not** constrain the base itself.

**Consequence:** a firm **Admin** can set `document_base_path` to **any directory the PHP/web-server process can read** — another tenant's document root, or a system path. After that:
- every member of that firm can enumerate the chosen directory tree via `browseDirectory`, and
- `serve_document` will stream files out of it (its own containment only guarantees the file stays *under* this same admin-chosen base).

Together these become an **arbitrary-file-read primitive scoped only by "wherever an admin pointed the base."** The containment logic is correct; the trust boundary is the problem.

Whether this is a vulnerability or accepted behavior depends on the deployment:
- **On-prem / single firm per server, where the admin already controls the box** → acceptable; the admin can read those files anyway.
- **Shared SaaS host with multiple firms' files on one filesystem** → this is a real tenant-isolation hole. A malicious or careless firm admin can reach other tenants' documents and server files.

**Options if the SaaS threat model applies:**
- Constrain `document_base_path` to an allow-listed root — e.g. derive it from config (`config('documents.root')`) and only let the admin pick/create a **subdirectory under a per-firm root** (`<root>/{firm_id}/…`), validating with `realpath` + the same `str_starts_with` containment used in `browseDirectory`.
- Or, if firms legitimately store documents on their own network shares, document this as an explicit, accepted trust decision so it isn't mistaken for an oversight later.

---

## Low / Informational

### 2. `browseDirectory` is not Admin-gated (intentional, but state it)
`edit`, `update`, and `protocolSetup` all begin with `if ($request->user()->user_type !== 'Admin') { abort(403); }`. `browseDirectory` has **no** such gate. This is almost certainly intentional: regular (non-admin) users open `DocumentPicker.vue` to attach documents to entries, so they need to browse. The effect is that **every firm member can enumerate the firm's entire document tree** (subject to Finding #1's base). Acceptable within-firm — noted only so the asymmetry is understood and not "fixed" by mistakenly adding an Admin gate that would break the document picker.

### 3. `path` query param has no length/type validation in `browseDirectory` — ✅ RESOLVED (see Fix B)
`$request->query('path', '')` was consumed directly (then normalized). Now validated with `nullable|string|max:500` at the top of the method.

### 4. Unbounded directory listing
`browseDirectory` returns every (non-hidden) entry in the target directory as JSON with no cap. A directory with a very large number of entries produces a large response. Minor resource concern; realistic firm folders make this a non-issue. A cap or pagination would be defensive hardening only.

### 5. `protocolSetup` is a static render (no issue)
`protocolSetup` only renders `Firm/ProtocolSetup` after the Admin check; it takes no input and exposes no data. No findings. (Context: relates to the `vantage://` protocol-handler work tracked separately.)

---

## Authorization summary (the four actions)

| Method | Auth gate | Record resolution | Verdict |
|---|---|---|---|
| `edit` | `Admin` check + `auth/welcomed` | `firm_id` from session | OK — no ID input, admin-gated |
| `update` | `Admin` check + `auth/welcomed`; `UpdateFirmRequest` | `firm_id` from session | OK for tenancy; see Finding #1 re: `document_base_path` constraint |
| `protocolSetup` | `Admin` check + `auth/welcomed` | none | OK |
| `browseDirectory` | `auth/welcomed` only (no Admin gate — intentional) | `firm_id` from session | Traversal containment correct; see Finding #1 |

**Note on `UpdateFirmRequest::authorize()`:** it returns a blanket `true`, but `update()` performs the `Admin` check inline and resolves the firm from the session (not from request input), so there is no authorization gap — the same justified pattern as `StoreFileRequest` (no existing foreign record to authorize against). Optionally, the inline `Admin` check could be moved into `authorize()` for consistency with the policy-based pattern used in the file/entry domains, but that is a refactor, not a fix.

---

## Summary / Priority Order
1. **Finding #1 (`document_base_path` could read app-server files)** — ✅ **RESOLVED** (Fix A): write-time + read-time allow-list with an always-on app-directory exclusion. **Action remaining for you:** set `DOCUMENT_ALLOWED_ROOTS` in production to the firm-share mount root(s) to fully lock the base down (dev works without it via the `base_path()` exclusion).
2. **Finding #3 (`path` validation)** — ✅ **RESOLVED** (Fix B).
3. **#2, #4, #5** — informational/hardening; intentionally not changed (see "Not changed").

**Bottom line:** `browseDirectory` was already well-built — path-traversal defense correct, no cross-firm IDOR. The real risk was that the firm `document_base_path` was an unconstrained server path that a firm admin could aim at the app server (`.env`, source) and read via `serve_document`/`browseDirectory`. That is now closed by the shared `Firm::safeDocumentBasePath()` guard enforced at both write and read time. Remember to set `DOCUMENT_ALLOWED_ROOTS` in production.
