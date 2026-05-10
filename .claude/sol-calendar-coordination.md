# Plan: Statute of Limitations Calendar Coordination

## Overview

When a File's `date_sol` is set on create or changed on edit, automatically create/update an all-day calendar event entry for the file's assigned attorney. The event should be immovable on the calendar — clicking it explains it must be changed via file details and offers navigation to the file edit page.

---

## Key Decisions

- **Entrytype identification:** Each firm already has a "Deadline - Statute of Limitations" entrytype (created during firm setup). We look it up by `name + firm_id`. Users will be prevented from renaming or deleting this entrytype.
- **No `is_locked` column:** Instead of adding a database flag, we identify SOL events by their entrytype. The CalendarController marks them as `editable: false` in the FullCalendar event object, and the frontend uses the entrytype to determine behavior on click.
- **Attorney source:** Use `ContactRole` where `is_file_attorney = true` for the file. Every file has an assigned attorney (required field).
- **No backfill needed:** No legacy data exists, but on file edit, if `date_sol` is filled and no corresponding calendar entry exists, one will be created.

---

## Implementation Phases

### Phase 1: Backend Sync Logic

The foundation — create, update, and delete SOL calendar entries when `date_sol` changes on a file. All other phases depend on this.

#### 1.1 Protect the SOL Entrytype

- Prevent users from renaming or deleting the "Deadline - Statute of Limitations" entrytype
- Backend: Add validation in any entrytype update/delete endpoint to reject changes to this name
- Frontend: Disable edit/delete controls for this entrytype in any entrytype management UI

#### 1.2 Create `syncSolCalendarEntry()` on File Model

Extract shared logic into `$file->syncSolCalendarEntry()` to keep controllers thin and ensure consistency between store/update.

#### 1.3 Create/Update SOL Entry on File Save

**In `FileController@store`** (after file + ContactRole creation):
```
if file.date_sol is not null AND filetype.enable_file_SOL:
    find entrytype = Entrytype where firm_id + name = "Deadline - Statute of Limitations"
    find attorney_contact_id = ContactRole where file_id + is_file_attorney = true

    create Entry:
        firm_id = user's firm
        file_id = new file id
        folder_id = 6
        entrytype_id = entrytype.id
        date1 = date_sol (as datetime, start of day)
        all_day = true
        on_calendar = true
        from_contact_id = attorney_contact_id
        note = "Statute of Limitations - [file name]"
```

**In `FileController@update`:**
```
find sol_entrytype = Entrytype where firm_id + name = "Deadline - Statute of Limitations"
find existing_sol_entry = Entry where file_id + entrytype_id = sol_entrytype.id

if date_sol changed or date_sol is filled and no existing entry:
    if new date_sol is null:
        delete existing_sol_entry
    else if existing_sol_entry exists:
        update entry.date1 = new date_sol
        update from_contact_id if attorney changed
    else:
        create new SOL entry (same as store logic)

if attorney changed and existing_sol_entry exists:
    update from_contact_id to new attorney's contact_id
```

#### 1.4 Handle Edge Cases

- **File attorney changes:** Update the SOL entry's `from_contact_id` when attorney ContactRole changes
- **Filetype changes:** If filetype changes to one where `enable_file_SOL = false`, delete the SOL entry and clear `date_sol`
- **File deletion:** SOL entries cascade-delete with the file (existing FK behavior)
- **SOL entry missing on edit:** If `date_sol` is filled but no SOL entry exists, create one during update

---

### Phase 2: Frontend FileForm Confirmation Modals

Add confirmation modals when `date_sol` is set, changed, or cleared on the file edit form. Can be tested independently against the Phase 1 backend.

#### 2.1 SOL Date Change Confirmation Modal

**In `Files/FileForm.vue`:**

On mount, capture the original `date_sol` value. When the user clicks Save, before submitting, compare the current value to the original. If changed, show a confirmation modal before proceeding:

- **Date set** (was null, now has a value): "The SOL Date has been set to [new date]. Do you want [new date] to be the SOL Date for this file?"
- **Date changed** (was X, now Y): "The SOL Date has been changed from [old date] to [new date]. Do you want [new date] to be the SOL Date for this file?"
- **Date cleared** (was X, now null/empty): "The SOL Date [old date] has been removed. Do you want to remove the SOL Date for this file?"

Each modal has:
- **Yes** → proceed with save
- **No** → dismiss modal, revert `date_sol` to original value, remain on form

This fits into the existing `fileform_click('ok')` flow, gating the save alongside the existing SOL-empty validation modal.

---

### Phase 3: Calendar Integration

Mark SOL events as non-editable on the calendar and handle click behavior. Depends on Phase 1 entries existing in the database.

#### 3.1 Mark SOL Events as Non-Editable in Calendar Response

**In `CalendarController@get_events`:**

When building the event array for FullCalendar, check if the entry's entrytype name is "Deadline - Statute of Limitations":

```php
$is_sol = $event->entrytype->name === 'Deadline - Statute of Limitations';

return [
    // ... existing fields ...
    'editable' => !$is_sol,  // FullCalendar respects this per-event
    'extendedProps' => [
        // ... existing props ...
        'is_sol' => $is_sol,
        'file_id' => $event->file->id,
    ],
];
```

Setting `editable: false` on the event object prevents FullCalendar from allowing drag or resize. No `eventAllow` callback needed — it's handled per-event.

#### 3.2 Handle SOL Event Click

**In `Calendar/Index.vue`:**

When `eventClick` fires (existing `click_event` handler):
- Check `event.extendedProps.is_sol`
- If true, instead of opening the normal edit form, show a modal:
  - Message: "The Statute of Limitations date cannot be moved on the calendar. It must be changed in the file details."
  - Button: "Edit File Details" → `router.visit(route('files.edit', event.extendedProps.file_id))`
  - Button: "Close" → dismisses modal
- SOL events are **not deletable** from the calendar. They can only be removed by clearing `date_sol` on the file.

#### 3.3 Visual Distinction + Cursor

- Add CSS for SOL events: `cursor: not-allowed` on hover (via a class based on `is_sol` extendedProp)
- SOL events follow the **same color scheme** as other events for that attorney (no distinct/fixed color)
- Title format from backend: `(XX) SOL - [File Name]` where XX = attorney initials

---

## Files to Modify

| Phase | File | Changes |
|-------|------|---------|
| 1 | `app/Models/File.php` | `syncSolCalendarEntry()` helper method |
| 1 | `app/Http/Controllers/FileController.php` | Call `syncSolCalendarEntry()` in store() and update() |
| 1 | Entrytype management (if exists) | Prevent rename/delete of SOL entrytype |
| 2 | `resources/js/Pages/Files/FileForm.vue` | SOL date change confirmation modal, capture original date_sol on mount |
| 3 | `app/Http/Controllers/CalendarController.php` | Add `editable` and `is_sol` to event response in get_events() |
| 3 | `resources/js/Pages/Calendar/Index.vue` | SOL click modal, cursor styling, prevent normal edit form for SOL events |

---

## Resolved Decisions

1. **Not deletable from calendar** — SOL entries can only be removed by clearing `date_sol` on the file edit form.
2. **Same color as attorney's events** — SOL events follow the same color scheme as other events for that attorney, no distinct/fixed color.
