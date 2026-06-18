# Vantage Application Review

**Date:** 2026-06-10
**Scope:** Frontend code in `resources/js` and HTTP controllers in `app/Http/Controllers`, plus the routing, Form Request, policy, and model code needed to evaluate them.
**Reviewer:** Claude Code (Fable 5)

---

## Summary

Vantage is in good overall shape for an application at this stage. The multi-tenancy story is notably solid: the `BelongsToFirm` global scope on `Entry` (and related models), the per-model policies, the firm-scoped `Rule::exists` checks in the Form Requests, and the layered path-traversal defenses in `Firm::safeDocumentBasePath()` / `EntryController::serve_document()` are all well done. I did not find any cross-firm data leak.

The most important findings are: **deactivated users can still log in** (security), two **undefined-variable 500s** in the "add new entrytype" flows (bug), a **broken comparison in `EntryController::checkInFile()`** that can delete the file attorney's/client's contact role (data-integrity bug), and a handful of unvalidated/uncapped request inputs that produce 500s or oversized queries. On the frontend, the main themes are very large components (`EntryForm.vue` is 1,543 lines), heavy direct DOM manipulation, duplicated logic between the file and view flows, and magic numbers (folder ids, reserved file id = 1) hardcoded on both client and server.

---

## 1. Security Concerns

### 1.1 HIGH — Deactivated users can still log in

`contacts.account_status` is the documented single source of truth for active/inactive status, and `User::isActive()` exists, but nothing enforces it:

- `Auth/AuthenticatedSessionController::store()` and Breeze's `LoginRequest` only check credentials — a user whose status was set to `'I'` can still authenticate and use the entire app.
- `UserController::update()` (app/Http/Controllers/UserController.php:218) sets `account_status = 'I'` but does not invalidate the target user's existing sessions, so a just-deactivated user keeps working until their session expires.

**Recommendation:** Reject inactive users in `LoginRequest::authenticate()` (or a middleware that runs on every request, which also covers already-issued sessions), and consider `Auth::logoutOtherDevices()`-style session invalidation when an admin deactivates an account. For a legal case-management product, "terminated employee retains access" is the kind of finding a customer security review will flag immediately.

### 1.2 MEDIUM — `CalendarController::get_events()` trusts unvalidated input

app/Http/Controllers/CalendarController.php:150 — `$request->start`, `$request->end`, `user1`, `include_due`, `due_to`, `due_from` are used with no validation at all. Values are parameter-bound so there is no SQL injection, but:

- Missing/malformed `start`/`end` produce SQL errors (500s) from `whereBetween`.
- `user1` is compared with `!= '1'` and used directly as a contact id filter. The base `firm_id` filter keeps results firm-scoped, but a `user1` belonging to another firm silently returns an empty/odd result set instead of a 422.
- Booleans arrive as the strings `'true'`/`'false'` and are compared with `== 'true'` — easy to break and easy to validate properly with `$request->boolean()`.

Also a robustness issue in the same method: `$event->contact_to->member_initials`, `$event->entrytype->name`, and `$event->file->id` (lines 213–277) will throw if the related contact, entrytype, or file is missing — one orphaned row breaks the entire calendar for the firm.

### 1.3 MEDIUM — Unbounded `show`/pagination parameters

`FileController` and `ContactController` correctly cap `show` at 50, but these don't:

- `UserController::index` (UserController.php:30) — `paginate($show ? $show : 10)`
- `ViewController::index` (ViewController.php:34) — `paginate($show)`
- `EntrytypeController::index`, `FiletypeController::index`, `FolderController::index`

`?show=100000` forces huge queries/payloads. Apply the same `min((int)..., 50)` guard everywhere (ideally a shared helper or Form Request rule).

### 1.4 MEDIUM — `pending_contact_roles.*.role` accepts arbitrary strings

`StoreEntryRequest` (app/Http/Requests/StoreEntryRequest.php:70) validates the role as only `required|string` (no max length, no allow-list), while `ContactRoleController::store()` correctly restricts to `Rule::in(array_keys(ContactRole::ROLE_LABELS))`. The same data lands in the same table via `savePendingContactRoles()` in both `EntryController` and `ViewController`. Arbitrary, unbounded role strings can be persisted through the entry form path. Align the validation with `ContactRoleController`.

### 1.5 LOW — Whole `User` model shared as an Inertia prop

`HandleInertiaRequests::share()` passes `$request->user()` directly. Today `$hidden` covers `password`, `remember_token`, `firm_id`, but any future column added to `users` (e.g., a 2FA secret, a Stripe id) is exposed to every page by default. Prefer an explicit array (`id`, `name`, `email`, `user_type`) so the safe set is opt-in rather than opt-out.

Also note the `subscription` closure in the same method runs `subscribed()` + `fileCount()` queries on **every** request; consider caching per-request or only sharing on the pages that need it.

### 1.6 LOW — Authorization style is inconsistent (works today, fragile tomorrow)

Three different patterns are in use:

- Policies (`$this->authorize(...)`) — Files, Contacts, Entries, Entrytypes, Filetypes, Users. Good.
- Inline `user_type !== 'Admin'` checks — `FirmController`, `SubscriptionController`, `WelcomeController`.
- Route middleware `admin` — `users` resource, `adminmenu`.

`FirmController::browseDirectory` notably has **no** admin check while its sibling methods do (it appears intentional, since `DocumentPicker.vue` is used by regular users in the entry form, and results are firm-scoped — but the asymmetry deserves a comment so it isn't "fixed" or loosened by accident). Pick one mechanism (policies + the `admin` middleware) and apply it uniformly.

### 1.7 LOW — Silent authorization failures in `PreferenceController`

`eventcolor_update`, `hover_placement_update`, `theme_update` (PreferenceController.php:73, 104, 144) wrap their work in `if ($this->canManagePreferencesFor(...))` and return nothing on failure — an unauthorized request gets an empty 200. Use `abort_unless(..., 403)` as `index()` already does, so failures are visible to both clients and logs.

### 1.8 Informational

- `config/documents.php` defaults `reserved_file_id` to 1 via env — fine, but see §2.5 about the frontend hardcoding `1`.
- `.env.example` ships `APP_DEBUG=true` — expected for an example file; just make sure production deploys set `APP_DEBUG=false` (debug pages leak config and code paths).
- CSRF is handled by the standard Inertia/axios setup; the GET aliases for modal "error passback" routes (`/contact_add_modal`, etc.) only re-render and don't mutate. OK.
- `serve_document()` and `browseDirectory()` path handling (realpath + base-prefix check + allow-list + app-dir exclusion) is genuinely good defense in depth. The `not_regex:/\.\./` rule on `linked_document_path` is belt-and-suspenders on top of it.

---

## 2. Bugs

### 2.1 HIGH — Undefined `$new_entrytype` causes a 500

`EntryController::add_new_entrytype()` (EntryController.php:1064–1075): when `$request->name == $request->chosen_name`, the `if` block is skipped and `$new_entrytype` is never created — yet it's used in the return (`$new_entrytype->firm_id` and `'new_entrytype' => $new_entrytype`). PHP fatals with "undefined variable."

The identical bug exists in `CalendarController::add_new_event_type()` (CalendarController.php:352–369): `$new_entrytype` is only set inside the `if`, but `'new_event_type' => $new_entrytype` is always returned.

The client tries to avoid sending duplicates (`clicked_entrytypeModal_button` checks for an existing match first), which is why this hasn't bitten in practice — but a stale client list or a double submit will 500.

### 2.2 HIGH — `checkInFile()` compares an id against a model

`EntryController::checkInFile()` (EntryController.php:743–761):

```php
$fileAtty = ContactRole::where(...)->where('is_file_attorney', true)->first();  // a ContactRole model (or null)
...
if ($contact_id !== $fileAtty && $contact_id !== $fileClient) {
```

An integer is never `===` a model, so this guard is **always true**. The intended protection — never delete the contact role of the file's designated attorney or client — does not work. If the attorney/client stops appearing in any entry (e.g., the last entry naming them is edited or deleted), their `attorney_for_client`/`client` role row is deleted, breaking `assignedAttorney`/client display on the file. Should compare against `$fileAtty?->contact_id` / `$fileClient?->contact_id`.

### 2.3 MEDIUM — `ViewController::getContactFor()` null dereference

ViewController.php:454–462 returns `Contact::...->first()`, which is `null` when the user-supplied `view_for` query string doesn't match any member initials in the firm. Every caller immediately does `$for->id` → 500. `?view_for=ZZ` on `/views` crashes the page. Validate `view_for` (and `view`, `from_to`, `read` — none are validated) or fall back to the authenticated user's initials when no match is found.

### 2.4 MEDIUM — Missing parentheses around `orWhere` in `EntryController::edit()`

EntryController.php:248–255:

```php
->where('file_id', $file->id)
->where('expecting_response', true)
->orWhere('id', $entry->response->response_to)
```

The `orWhere` is not grouped, so the expression is `(file_id = ? AND expecting_response) OR id = ?` — the firm global scope still protects tenancy, but the "expecting entries" list logic is wrong relative to the comment's intent. Should be `->where(fn ($q) => $q->where(...)->orWhere(...))` or keep the file filter outside a grouped closure. (Worth confirming whether `edit()` is even reachable anymore — the comment says editing now happens in the index; if dead, remove it.)

### 2.5 MEDIUM — Reserved file id hardcoded as `1` in the frontend

The backend consistently uses `config('documents.reserved_file_id')`, but the frontend hardcodes `1` in at least seven places (`EntryForm.vue:283,662,713,881,1107`, `Calendar/Index.vue:322,517`), and `ViewController.php:134,211` also hardcodes `!== 1`. If `RESERVED_FILE_ID` is ever changed, "Not File Related" handling silently breaks. Share the value as an Inertia prop (e.g., via `HandleInertiaRequests::share()`) and use it everywhere; fix the two ViewController spots to read config.

### 2.6 MEDIUM — Hardcoded `entrytype_id: 33` in the calendar form

`Calendar/Index.vue:71` — `entrytype_id: 33, // meeting, for now`. Entrytypes are per-firm rows; id 33 belongs to one firm. For any other firm this default either fails validation (`Rule::exists ... where firm_id`) or points at the wrong type. Default it from the `event_types` prop instead.

### 2.7 LOW — `ContactRoleController::store()` null dereference ordering

ContactRoleController.php:31–48: `File::findOrFail($request->file_id)` runs **before** validation (so a missing `file_id` is a 404/500 instead of a 422), and `Contact::find($request->contact_id)` can return `null` (the rule is only `required|integer`, not `exists`), after which `$contact->firm_id` throws. Validate first with firm-scoped `exists` rules, as the entry Form Requests already do.

### 2.8 LOW — Controller actions that return nothing

`FileController::update()` (returns void), `EntryController::update()` when `comeback` is false, `CalendarController::store()`/`event_placement()`, and the three `PreferenceController` update methods all end without a response. The axios-called ones get an empty 200 (fine, if intentional), but `FileController::update()` is hit by an Inertia `form.put()` — Inertia expects a redirect; returning nothing relies on awkward client-side handling and skips flash/error semantics. Return `back()` or a redirect explicitly.

### 2.9 LOW — `event_placement()` fails silently

CalendarController.php:307–324: if the entry isn't found or belongs to another firm, the method does nothing and returns 200. The drag/drop appears to succeed in FullCalendar but nothing was saved. Return 403/404 so the client can refetch and surface an error.

### 2.10 LOW — `UserController::store()` / `WelcomeController` multi-model writes lack transactions

User + Contact are created in sequence (UserController.php:68–99); if the contact save fails (e.g., a race on the unique `display_name`), an orphan user row remains — and the app's "every user has a contact" invariant breaks (e.g., `PreferenceController::index` does `$user->contact->member_initials` with no null check). Wrap in `DB::transaction()`.

### 2.11 LOW — `Entry::firm()` is not a relationship

Entry.php:56–59 defines `firm()` returning `Firm::find(...)`. It works for the two call sites but breaks Eloquent conventions: no eager loading, `$entry->firm` (property access) doesn't resolve through it, and it runs a query on every call. Make it a real `belongsTo(Firm::class)`.

---

## 3. Frontend (resources/js) Review

### 3.1 Component size and duplication (biggest maintainability issue)

- `EntryForm.vue` (1,543 lines), `Calendar/Index.vue` (1,112), `Views/Index.vue` (1,033), `Entries/Index.vue` (996). These are god components mixing data loading, modal management, keyboard handling, formatting, and submission.
- `submit_file_add`/`submit_view_add` and `submit_file_edit`/`submit_view_edit` in EntryForm are near-duplicates; the `folder_singular` array is copy-pasted in both EntryForm and Entries/Index; `reformat_date`/time-formatting logic is re-implemented in EntryForm and Calendar (`toolTimeCalc`) with manual string slicing.
- The same backend duplication mirrors this: `handleThisResponse`, `handleIsNoResponse`, `savePendingContactRoles`, `getFirmMembers`, `getAttorneys`, `getFirmFolders` exist in both `EntryController` and `ViewController`, and the ~70-line contact-creation block is repeated **three times** (`contact_add_modal`, `contact_add_modal2`, `new_contact_modal`) plus near-copies in `ContactController::store/update`. Extract an `EntryResponseService` / `ContactService` (or shared Form Request + private builder) — fixing a bug in one copy currently requires remembering five places.

**Suggestion:** extract composables (`useEntryForm`, `useModal`, `useDateFormat`) and child components (response picker, role-assignment modal, file-contacts modal) from EntryForm; share the singular-folder names and date formatting from one module (a `Config/` module already exists — `entryViewFormats.js` is a good precedent).

### 3.2 Direct DOM manipulation instead of Vue state

`document.getElementById('...').showModal()/close()/focus()` appears throughout EntryForm, Entries/Index, Calendar/Index, ContactLookup (`display_modal()` re-implemented in three files). This works with DaisyUI `<dialog>` but is fragile: it breaks when elements are conditionally unmounted (`display_modal` in EntryForm already wraps everything in `nextTick` to dodge this), is untestable, and `display_modal(..., 'status')` returns a value from inside `nextTick` so it actually returns `undefined`. Prefer template refs or a small `useModal()` composable; the codebase already has a `Modal.vue` component (used by DocumentPicker) that could be the standard.

### 3.3 Array-index-by-id coupling for folders

`props.p1.folders[entry_form.folder_id - 1]` (EntryForm.vue:329,722–723 and elsewhere) and `get_folder_info()`'s position-in-array mapping (EntryController.php:408–429) both assume folder ids are exactly 1–11, contiguous, and ordered. One inserted/deleted folder row breaks prompts, time-picker behavior, and filepart routing everywhere. Use `folders.find(f => f.id === ...)` on the client and an id-keyed map (or the `short_name` column that already exists on folders) on the server. Similarly, folder ids 5/6/7/8 are hardcoded as memos/events/todo/phone in DashboardController, ViewController, CalendarController, EntryController, EntryForm, and ContactLookup — centralize as named constants/enum shared to the client via props.

### 3.4 Leftover debug and dead code

- `alert('right!')` — Entries/Index.vue:135 (live debug artifact).
- 16 `console.log` calls across `resources/js`.
- Dead files: `Welcome_original.vue`, `AuthenticatedLayout_original.vue`, `Register_original.vue`, `ApplicationLogo_original.vue`, `FileLookup.vue` (its own comment says it's unused and "will be removed later").
- Dead backend code: `EntryController::create()` (commented out), `testparts()`/`testtwo()`, `AdminController::test_form()` (renders a page that doesn't exist), `FolderController` store/create/edit/update/destroy (no routes expose them — good that routes are locked down, but the code invites accidental re-exposure; `edit()` and `update()` have no authorization at all if ever routed).
- Large commented-out blocks in EntryController/ViewController/Entries pages.

Deleting these costs nothing and meaningfully reduces review surface.

### 3.5 Inconsistent data-fetch patterns

Three styles coexist: Inertia `useForm`/`router.reload` with partial reloads + custom `X-Custom-Refresh` headers (clever, works), raw `axios` posts that return JSON (`/calendar`, `/lookup_contact`, `/new_contact_modal`, `/toggle_read`), and `fetch()` (AuthenticatedLayout recent files). Two components (`DocumentPicker.vue:36`, `ContactLookup.vue:75`) use the global `window.axios` without importing it — it works because of `bootstrap.js`, but it breaks linting/bundling assumptions; import it explicitly. Longer term, Inertia v2's deferred props and `WhenVisible` could replace several of the bespoke partial-reload mechanisms.

### 3.6 Lookup endpoints fire on every keystroke

`ContactLookup.lookup_contact()` posts to `/lookup_contact` on each `@input` when local filtering misses; `lookup_related_file()` in Calendar has a *commented-out* debounce. Add a ~250ms debounce (lodash `debounce` is already a dependency, judging by the commented import) and consider a `throttle:60,1` rate limit on the lookup routes server-side.

### 3.7 Fragile change detection

`objectChanged()` compares `JSON.stringify(entry_form)` against a spread-clone of a `useForm` object. The long comment at `onBeforeUnmount` (EntryForm.vue:1036+) documents the resulting pain (false "unsaved changes" prompts because Inertia's internal form props leak into the comparison, worked around with a hand-maintained field whitelist). Inertia forms already provide `form.isDirty` / `form.data()`; comparing `JSON.stringify(form.data())` against a snapshot of `form.data()` would remove the whole class of problem. Also, native `confirm()` inside `onBeforeUnmount` fires during normal SPA navigation — consider Inertia's `router.on('before')` for a cancellable prompt instead.

### 3.8 Smaller frontend notes

- `AuthenticatedLayout.vue:46–48`: `let isAdmin = ref(false); isAdmin = user.user_type == 'Admin' ...` — the ref is thrown away and replaced with a plain boolean. Harmless today (it's unused in the template, which checks `$page.props` directly) but it's a trap; delete it or make it a `computed`.
- Props typed as `Object` when they're arrays (`firm_members`, `entries.data` consumers, etc.) and `reactive({...list: Object})` placeholders — use proper defaults (`[]`) and array types; `ContactLookup` already shows the corrected pattern (`{ data: [] }`).
- `v-html` usage is confined to paginator labels (`Pagination.vue:43`, `Folders/Index.vue:244`) — these are Laravel-generated (`&laquo;` etc.), so XSS-safe, but keep it that way; never feed user data into them.
- Keyboard/hotkey handling: the Y/N hotkeys in `handle_hotkey_press` act on whatever modal logic matches the current mode, with global listeners — works, but a focused-modal `@keydown` handler is more robust. Lookup suggestion lists (`ContactLookup`, file lookups) are mouse-only (`@mousedown`), with no arrow-key/Enter selection and no ARIA roles — an accessibility gap worth scheduling.
- `entryList_click('doc_button')` builds `vantage://open?path=...` URLs without encoding (`Entries/Index.vue:129`) — spaces/`&`/`#` in document paths will mangle the URL; use `encodeURIComponent` on the path. (Related to the known in-progress protocol-handler work.)
- `useTheme` persists theme to `localStorage` and to server preferences separately; on a fresh browser the server-saved theme only arrives via session flash on login (`theme_preference`). A user's saved theme silently doesn't follow them to a second machine after the first page — consider sharing the theme preference as a standard Inertia prop.
- Hand-rolled date strings (`dt.slice(5, 7) + '/' ...`) appear in at least four places. A tiny shared formatter (or `date-fns`) would remove subtle inconsistencies (e.g., `reformat_date` shows 12:xx PM as "12:xxam" because only `> 12` flips to pm — actually `> 11` is handled in `toolTimeCalc` but not in `reformat_date`'s am/pm logic; the two implementations disagree).

---

## 4. Suggestions Going Forward

1. **Enforce account status at the auth boundary** (§1.1) — highest-value security fix.
2. **Fix the three crash/data bugs**: undefined `$new_entrytype` (×2), `checkInFile()` model-vs-id comparison, `getContactFor()` null deref. All are small, contained changes.
3. **Adopt Form Requests everywhere.** ContactController, UserController, CalendarController, EntrytypeController, FolderController, and the three modal contact endpoints all use long inline `validate()` blocks, several of them duplicated. A `StoreContactRequest`/`UpdateContactRequest` pair would delete ~250 duplicated lines and make the title/company conditional rule live in one place.
4. **Create shared services for the duplicated domain logic** (response handling, contact-role cleanup, firm-scoped lookups) used by both `EntryController` and `ViewController`.
5. **Centralize magic numbers**: folder ids/names, reserved file id, SOL entrytype name (currently a string literal matched in PHP *and* in two Vue files — a rename breaks SOL protection silently). One PHP enum/config + one shared Inertia prop.
6. **Standardize JSON endpoints**: axios-called actions should consistently return JSON with proper status codes (no void returns, no silent authorization failures), and Inertia-called actions should always redirect.
7. **Cap and validate all list/query parameters** (`show`, `view`, `view_for`, `from_to`, `read`, `status`, `filter`, calendar params) — a tiny shared rule set handles all of them.
8. **Plan a decomposition pass on the four largest Vue pages** before adding more features to them; extract the response picker, role modal, contact pickers, and modal management first since they're already duplicated.
9. **Remove dead files and debug artifacts** (`*_original.vue`, `alert('right!')`, console.logs, commented-out blocks, unrouted FolderController writes).
10. **Add automated tests around tenancy and the entry/response state machine.** `handleThisResponse()` encodes intricate business rules (full→partial transitions, re-expecting responses) duplicated in two controllers with no test coverage; it's the part of the codebase most likely to regress silently.
11. **Operational hardening for launch**: rate-limit login and the lookup endpoints, confirm `APP_DEBUG=false`/proper `APP_URL` in production, and consider security headers (CSP is hard with Inertia but X-Frame-Options/Referrer-Policy are cheap).

---

## 5. Things Done Well (keep doing these)

- **Tenancy by default**: the `BelongsToFirm` global scope plus firm-stamping on create is the right architecture, and the manual `firm_id` filters layered on top ("reserved-file safety") show good defense-in-depth instincts.
- **Document path security**: `safeDocumentBasePath()` (realpath, app-dir exclusion, allow-list, separator-terminated prefix checks) and `serve_document()`'s ordered checks are careful and well-commented.
- **Form Requests for the core write paths** (files, entries, firm) with firm-scoped `exists` rules and custom messages.
- **Policies + route-model binding** for Files/Entries/Contacts/Entrytypes/Filetypes/Users.
- **Thoughtful UX details** in the frontend: SOL entry protection mirrored client- and server-side, unsaved-change prompts, keyboard-first entry workflow, partial reloads to keep the entry list fast.
