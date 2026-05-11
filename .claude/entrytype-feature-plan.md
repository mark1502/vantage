# Entrytype Management Feature Plan

## Overview

Build `Entrytypes/Index.vue` — an admin page for managing entry types per folder. Uses DaisyUI modals for add/edit/delete (no separate Create/Edit components needed since only the `name` field is editable).

## What Already Exists

- **Model:** `Entrytype` with `faux_deleted` boolean cast, `folder()` and `entries()` relationships
- **Controller:** `EntrytypeController` with `index`, `store`, `update`, `destroy`, `restore` — all fully implemented
- **Routes:** All five routes registered in `web.php` (`entrytypes.index`, `.store`, `.update`, `.destroy`, `.restore`)
- **AdminMenu.vue:** Button already links to `route('entrytypes.index')`

**No backend changes needed.** This is purely a frontend task.

## What Needs to Be Built

### Single file: `resources/js/Pages/Entrytypes/Index.vue`

### Props (from controller)
- `entrytypes` — paginated collection
- `folders` — all folders (id, name) for the dropdown filter
- `selectedFolderId` — currently selected folder
- `filter` — 'current' | 'deleted' | 'all'

### Layout & Structure (borrowing from Contacts/Index)

1. **Header:** "Entry Types" title
2. **Controls bar (above table):**
   - Folder dropdown (select) — filters entrytypes by folder via `router.get` to reload with `folder_id` param
   - Filter dropdown (Current / Deleted / All) — same pattern as Contacts/Index `filterChanged()`
3. **Table:**
   - Single column: entry type name
   - Click to select (highlight row), same `setEntryClass()` pattern
   - Deleted items shown with strikethrough + "deleted" badge (same as Contacts)
   - Empty rows to fill table height (same `emptyRows` computed)
4. **Pagination + Show dropdown** below table (reuse `Pagination` component)
5. **Action buttons:**
   - **+ Add** — opens Add modal (hidden when filter is 'deleted')
   - **△ Change** — opens Edit modal with current name pre-filled (disabled when selected item is deleted)
   - **- Delete** — opens Delete confirmation modal (shown when selected item is NOT deleted)
   - **↩ Restore** — calls `router.patch` to restore (shown when selected item IS deleted)

### Modals (3 DaisyUI `<dialog>` modals)

1. **Add Modal (`add_modal`)**
   - Text input for name (auto-focused)
   - Submits via `router.post(route('entrytypes.store'), { name, folder_id, show, filter })`
   - Close on cancel or backdrop click

2. **Edit Modal (`edit_modal`)**
   - Text input pre-filled with selected entrytype's name
   - Submits via `router.put(route('entrytypes.update', entrytype.id), { name, folder_id, page, show, filter })`
   - Close on cancel or backdrop click

3. **Delete Modal (`delete_modal`)**
   - Confirmation text showing the entrytype name
   - Submits via `router.delete(route('entrytypes.destroy', entrytype.id), { data: { folder_id, page, show, filter } })`
   - Cancel button closes modal

### Keyboard Support (matching Contacts pattern)
- Arrow Up/Down to navigate rows
- Enter to open Edit modal (if not deleted)
- Escape to blur/deselect
- PageUp/PageDown for pagination

### Key Differences from Contacts/Index
- **No right-side detail panel** — entrytypes only have a name, so a two-column layout is unnecessary. Use a centered single-column layout.
- **Folder dropdown** replaces the search field as the primary filter above the table.
- **Modals instead of route navigation** for add/edit — no `state.editurl` or `state.createurl` needed for navigation, just modal show/hide.
- **No search field** — the folder dropdown + filter is sufficient for the small number of entrytypes per folder.

## Styling
- Match Contacts/Index: `bg-base-300`, `sm:rounded-lg`, `min-h-dvh`, DaisyUI buttons and selects
- Table: `border border-base-content`, same header styling
- Buttons: same `btn btn-primary` / `btn-outline btn-error` / `btn-outline btn-success` pattern

## Execution Steps

1. Create `resources/js/Pages/Entrytypes/Index.vue` with the above structure
2. Verify the page loads from AdminMenu → Entry Types button
