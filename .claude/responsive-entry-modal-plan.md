# Responsive-Entry Selection Modal — Implementation Plan

## Goal

Replace the `is_response_to` `<select>` in `resources/js/Pages/Entries/EntryForm.vue` with a modal-based picker that:

1. Opens automatically when the user changes `is_a_response` from `"N"` to `"P"` (Partial) or `"F"` (Full).
2. Shows the currently selected responsive entry as a compact read-only display, alongside an "Edit" (change) button that re-opens the modal.
3. Preserves **every existing behavior** around `was_response_to`, `is_response_to`, `was_a_response`, `is_a_response`, and the `related_entry` display object — no backend changes, no request shape changes.

## Non-Goals / Guardrails

- **Do not touch the controllers.** `EntryController::handleThisResponse()`, `ViewController::handleThisResponse()`, and the `expecting_response` flipping logic remain unchanged. The modal only changes how the user *picks* a value for `entry_form.is_response_to`; the submitted payload is identical.
- **Do not change `StoreEntryRequest` or `StoreViewRequest`.** Validation rules for `is_a_response`, `is_response_to`, and `was_response_to` stay as-is.
- **Do not change how `was_response_to` is populated in `update_disp()`** (lines ~660–679). That block still runs when an existing entry loads and must continue to seed `was_response_to`, `was_a_response`, `related_entry.date/from/type/expecting_response` exactly as today.
- **Do not change the edit-mode dirty-check** — `is_response_to` must remain in the `watchedFields` list (line ~911) so `checkEditMode()` still detects a change.

## Background: How `was_response_to` Is Used Today (Must Preserve)

From reading the code:

- `was_response_to` is a **snapshot of the original responsive target** at the moment the entry was loaded into the form. It is used for two things:
  1. **Re-submitted to the controller** as part of the request (validated in `StoreEntryRequest` / `StoreViewRequest` line 38). Controllers use it (via `handleThisResponse` logic chain) to know what the previous response target was so they can flip `expecting_response` back on the prior target when the user changes the relationship.
  2. **Ensures the previously-targeted entry remains selectable** in the current dropdown even if that entry is no longer in `props.p1.expecting_response` (because it's no longer expecting a response). This is the `v-if="entry_form.was_response_to && related_entry.expecting_response == false"` fallback option at line 1181.
- `was_a_response` is the parallel snapshot of the original `response_type` (`N`/`P`/`F`).
- `related_entry` (reactive object) holds the **display fields** (date / from / type / expecting_response) for whichever entry is currently referenced by `was_response_to` — populated from `theEntry.response.response_to` on load.

**Critical invariant:** `was_response_to` / `was_a_response` / `related_entry` must **only** be written in `update_disp()` on entry load. The modal must **never** mutate them. The modal only writes `entry_form.is_response_to` (and, implicitly via the is-a-response select, `entry_form.is_a_response`).

## Behavior Spec

### Trigger: opening the modal

- Add a `@change` handler on the `is_a_response` `<select>` (in addition to the existing `checkEditMode()` call) that checks:
  - If the new value is `'P'` or `'F'` **and** (`entry_form.is_response_to` is null **or** was_a_response was 'N'), open the modal.
  - If the new value is `'N'`, do not open the modal. (Existing logic already hides the response-to row in that case. We should also clear `entry_form.is_response_to = null` when user switches to `'N'` to avoid stale ids being submitted — verify current behavior first; if the current select already effectively leaves the stale value, keep matching behavior to avoid changing submitted payloads.)
- The trigger must NOT fire on initial form population from `update_disp()`. Because `update_disp()` sets `entry_form.is_a_response` programmatically (not via user `@change`), the native change event won't fire — so this is safe. But confirm during implementation by testing an edit of an existing responsive entry.

### The modal itself (inline in EntryForm.vue using DaisyUI)

No separate Vue component. The modal is defined directly in `EntryForm.vue` using a DaisyUI `<dialog class="modal">` block. Rationale: the modal consumes data already in EntryForm's scope (`responseCandidates`, `fallbackResponseEntry`, `entry_form`, `related_entry`, `findEntryType()`, `reformat_date()`) — extracting a component would require prop-drilling all of these for a single use site. Native `<dialog>` also handles Esc/backdrop dismissal for free, so no focus-trap plumbing.

Structure:
- Template ref: `const responseModalRef = ref(null)` bound to the `<dialog>` element.
- Open: `responseModalRef.value.showModal()`.
- Close: `responseModalRef.value.close()` (or user presses Esc / clicks backdrop).
- Block lives near the bottom of the `<template>`, outside the form element, mirroring standard DaisyUI modal placement.

Layout:
- Title: "Select the entry this is a response to"
- A scrollable `<table>` inside `modal-box` with columns: **Date**, **From**, **Type**. Each row is a clickable `<tr>` that, on click, sets `entry_form.is_response_to`, calls `checkEditMode()`, and closes the dialog.
- Row source: iterate `responseCandidates`, plus prepend `fallbackResponseEntry` as a row if it's non-null (same condition that currently drives the fallback `<option>` at line 1181).
- Highlight (e.g. `bg-base-200`) the row whose id matches `entry_form.is_response_to`.
- Empty state: if both `responseCandidates` is empty AND `fallbackResponseEntry` is null, show "No entries in this file are expecting a response."
- Footer: a single Cancel button inside a `<form method="dialog">` so it closes the dialog natively. No Clear button (switching "Is responsive?" to No covers clearing).
- No search/filter in v1 — deferred.

### EntryForm.vue changes

1. **Add a template ref** `const responseModalRef = ref(null)` and small helpers `openResponseModal()` / `closeResponseModal()` that call `showModal()` / `close()` on it.
2. **Add a computed** `responseCandidates` that returns the same filtered list the current `<template v-for>` produces — entries from `props.p1.expecting_response` where `expecting_entry.file_id === entry_form.file_id`. This keeps the filter logic in one place.
3. **Add a computed** `fallbackResponseEntry` that returns `{ id: entry_form.was_response_to, date: related_entry.date, from: related_entry.from, type: related_entry.type }` when `entry_form.was_response_to && related_entry.expecting_response === false`, else `null`. This replicates the current fallback-option condition on line 1181.
4. **Add a computed** `currentResponseDisplay` that returns a display string for the currently selected `entry_form.is_response_to`:
   - If it matches `was_response_to` and the fallback applies, use `related_entry` fields.
   - Otherwise, look it up in `responseCandidates` by id.
   - Returns `null` if nothing selected.
5. **Add a row-select handler** `pickResponseEntry(id)` that sets `entry_form.is_response_to = id`, calls `checkEditMode()`, then `closeResponseModal()`.
6. **Replace the "Is Response To Droplist Row" (lines 1170–1200)** with a new block shown under the same `v-show` condition:
   - Label: "Response to:"
   - If `currentResponseDisplay` is set: show it as read-only text (e.g. in a bordered span styled like a disabled input) + an **"Edit"** button that calls `openResponseModal()`.
   - If not set: show a **"Select responsive entry…"** button that calls `openResponseModal()`. (This covers the case where the user switches `is_a_response` to P/F but cancels the modal — they need a way back in.)
7. **Add the inline `<dialog class="modal">` block** near the bottom of the template, bound to `responseModalRef`. Iterates `fallbackResponseEntry` (if present) + `responseCandidates` as table rows, each calling `pickResponseEntry(row.id)` on click. Highlights the row matching `entry_form.is_response_to`. Footer has a `<form method="dialog">` Cancel button.
8. **Add the `is_a_response` change handler** (e.g. `onIsAResponseChange`) wired to `@change` on the existing select (replacing the current inline `checkEditMode()` call):
   ```js
   function onIsAResponseChange() {
       checkEditMode();
       if ((entry_form.is_a_response === 'P' || entry_form.is_a_response === 'F')
           && !entry_form.is_response_to) {
           openResponseModal();
       }
   }
   ```
   Decision point to confirm with user before implementing: should the modal also open when the user switches **between** P and F (i.e. changes type while already having a selection)? My recommendation is **no** — they already picked an entry; changing P↔F doesn't invalidate the target. The "Edit" button is available if they want to change it. Current behavior of the plain `<select>` also doesn't force re-picking, so this keeps parity.
9. **Keep `is_response_to` in `watchedFields`** (line 911) unchanged.

### What happens on cancel

- If the user opens the modal via auto-trigger (just switched from N→P/F) and then cancels without picking:
  - `entry_form.is_a_response` is still `'P'` or `'F'` but `entry_form.is_response_to` is still whatever it was (likely `null` for a new entry, or the prior value for an edit).
  - The read-only display block will show either the current selection (with "Edit" button) or the "Select responsive entry…" button.
  - This matches the current `<select>` behavior where the user can leave it unselected — submission-time validation is unchanged, so the controllers' existing `! empty($request->is_response_to)` guard still protects the backend.

## Files to Touch

- **Edit only:** `resources/js/Pages/Entries/EntryForm.vue`
  - Template: replace the response-to dropdown block; add inline `<dialog class="modal">` block.
  - Script: add `responseModalRef`, three computeds, `pickResponseEntry`, `openResponseModal`/`closeResponseModal`, `onIsAResponseChange` handler, and wire `@change` on the is_a_response select.
  - **Do not** edit: `update_disp()` lines 660–679, `watchedFields`, `entry_form` initial shape, or any submit logic.

No new files.

## Files Explicitly NOT to Touch

- `app/Http/Controllers/EntryController.php`
- `app/Http/Controllers/ViewController.php`
- `app/Http/Requests/StoreEntryRequest.php`
- `app/Http/Requests/StoreViewRequest.php`
- `app/Models/Response.php`
- Any migration or DB schema

## Verification Checklist (manual, post-implementation)

- [ ] New entry, switch "Is responsive?" from No → Partial: modal opens automatically.
- [ ] New entry, cancel modal: row shows "Select responsive entry…" button; submitting still works (validates unchanged).
- [ ] New entry, pick an entry in modal: selection displays; submit creates entry with correct `is_response_to`; prior entry's `expecting_response` flips to false (controller behavior, unchanged).
- [ ] Edit existing responsive entry whose target is still expecting_response: form loads with correct display; modal does NOT auto-open; Edit button re-opens modal; can pick a different target.
- [ ] Edit existing responsive entry whose target is NO longer expecting_response: the fallback entry shows as the current selection AND appears as a selectable row in the modal (via `fallbackResponseEntry`).
- [ ] Switch `is_a_response` from P → N: response-to row hides (existing `v-show`), modal does not open, `was_response_to` still submitted as before.
- [ ] Switch N → F → N → F: modal opens only on the first N→F transition when nothing is selected.
- [ ] `checkEditMode()` still correctly detects a change to `is_response_to` and enables save.
