# Plan: Entries/Index Table View Formats

## Context

The Entries/Index table currently shows 3 hardcoded columns (Date, Type, From) and has no way to display other available entry data like To, Date2, Note, Amount, or Response status. With only half the screen for the table, space is tight. This enhancement adds selectable pre-defined view formats per folder, a "Custom..." column picker, overflow tooltips, and optional persistence of the user's choices.

## Summary of Changes

1. **New config file** with column definitions and named formats
2. **Dynamic table rendering** driven by the selected format
3. **View format dropdown** next to the existing Show dropdown
4. **Column picker modal** for custom column selection (max 5)
5. **Truncate + title tooltips** on all cells for overflow
6. **Backend: preferences persistence** (migration, route, controller method)
7. **Backend: pass display prefs** to the frontend via EntryController

---

## Step 1: Migration — Add pref_defaults rows

**New file:** `database/migrations/2026_02_28_000001_add_display_format_pref_defaults.php`

Insert 2 rows into `pref_defaults`:
- `remember_folder_display` (setting: `'off'`) — toggle to persist format choices
- `folder_display_formats` (setting: `'{}'`) — JSON map of folder_id to format key

No schema changes needed — the existing `preferences.setting` column stores the JSON string.

---

## Step 2: Backend route + controller

**Modify:** `routes/web.php`
- Add `Route::post('/preferences/display-format', ...)` named `preferences.display_format`

**Modify:** `app/Http/Controllers/PreferenceController.php`
- Add `update_display_format(Request $request)` method
- Reads/creates the `folder_display_formats` preference row
- Merges the new folder_id => format into the JSON, saves

---

## Step 3: Pass display prefs from EntryController

**Modify:** `app/Http/Controllers/EntryController.php` (index method)
- Query the user's `remember_folder_display` and `folder_display_formats` preferences
- Add `'display_prefs' => [...]` to the Inertia render data

---

## Step 4: Frontend config file

**New file:** `resources/js/Config/entryViewFormats.js`

**Column definitions** — each column has:
- `key`, `label(folder)` (uses folder prompt labels), `getValue(entry, props, helpers)`
- `width` (Tailwind class), `visibleWhen(folder)` (checks folder's hide_* flags)

Columns: `date1`, `entrytype`, `from`, `to`, `date2`, `note`, `amount`, `response`

**Named formats:**
- `standard` — Date, Type, From (always available)
- `detailed` — Date, Type, From, To (folders: corr, plead, disc, memos, phone)
- `with_notes` — Date, Type, From, Note (all folders)
- `amounts` — Date, Type, From, Amount (medbills, costs only)
- `dates` — Date, Date2, Type, From (folders with date2)
- `response` — Date, Type, From, Response (folders with response expected)

**Helper functions:**
- `getAvailableFormats(folder)` — returns which formats apply to a folder
- `getAvailableColumns(folder)` — returns which columns are available for custom picker

**Folder availability matrix (from seeder data):**

| Folder | To | Date2 | Amount | Response | Available formats |
|--------|-----|-------|--------|----------|-------------------|
| Correspondence | yes | yes | no | yes | standard, detailed, with_notes, dates, response |
| Pleadings | yes | yes | no | yes | standard, detailed, with_notes, dates, response |
| Discovery | yes | yes | no | yes | standard, detailed, with_notes, dates, response |
| Documents | no | yes | no | no | standard, with_notes, dates |
| Memos | yes | no | no | yes | standard, detailed, with_notes, response |
| Events | no | yes | no | no | standard, with_notes, dates |
| To-Do | no | yes | no | no | standard, with_notes, dates |
| Phone | yes | no | no | yes | standard, detailed, with_notes, response |
| Med Records | no | yes | no | no | standard, with_notes, dates |
| Med Bills | no | yes | yes | no | standard, with_notes, dates, amounts |
| Costs | no | no | yes | no | standard, with_notes, amounts |

---

## Step 5: Modify Index.vue

### Script changes:
- Import from `entryViewFormats.js`
- Add `display_prefs` prop
- Add reactive state: `currentFormatKey`, `customColumns`, `showColumnPicker`
- Add computed: `currentFolder`, `availableFormats`, `activeColumns`, `availableColumnsForPicker`
- Add methods: `onFormatChange()`, `saveFormatPreference()`, `applyCustomColumns()`, `toggleCustomColumn()`, `restoreFormatForFolder()`
- Call `restoreFormatForFolder()` at end of `update_listFormat()` and on init

### Template changes:

**Table `<table>` tag:** Add `table-fixed` class for truncation to work.

**Table header:** Replace hardcoded `<th>` elements with `v-for="col in activeColumns"` loop.

**Table body rows:** Replace hardcoded `<td>` elements with `v-for="col in activeColumns"` loop. Each cell gets:
- `truncate max-w-0` + column width class for text clipping
- `:title="col.getValue(...)"` for native hover tooltip
- Special case for `from` column (paperclip icon for linked documents)

**Empty rows:** Match dynamic column count with `v-for="col in activeColumns"`.

**Controls below table:** Keep `Show: [10▼]` as-is, add format dropdown `[Standard ▼]` right next to it under the same "Show:" label. The format dropdown shows the available formats for the current folder plus "Custom..." at the bottom.

**When folder_id is 0 (timeline) or negative:** Hide the format dropdown, always use "standard".

**Column picker modal:** DaisyUI `<dialog>` with checkboxes for available columns, max 5 enforced, Apply/Cancel buttons.

---

## Step 6: Persistence flow

1. User selects a format → `onFormatChange()` fires
2. If `display_prefs.remember` is true → `router.post` to `preferences.display_format` with `{ folder_id, format }` (preserveState, preserveScroll)
3. On folder switch → `restoreFormatForFolder()` checks `display_prefs.formats[folderId]` and applies saved format, falling back to "standard"
4. If remember is off → always starts with "standard", no save calls

---

## Files to modify/create

| File | Action |
|------|--------|
| `database/migrations/2026_02_28_*_add_display_format_pref_defaults.php` | Create |
| `app/Http/Controllers/PreferenceController.php` | Add method |
| `app/Http/Controllers/EntryController.php` | Add display_prefs to index |
| `routes/web.php` | Add POST route |
| `resources/js/Config/entryViewFormats.js` | Create |
| `resources/js/Pages/Entries/Index.vue` | Major template + script changes |

---

## Verification

1. Run migration: `php artisan migrate`
2. Run `npm run dev` for frontend
3. Navigate to a file's entry list
4. Verify default "Standard" view shows Date, Type, From as before
5. Change folder dropdown — verify format dropdown updates available options
6. Select "Detailed" — verify To column appears, all cells truncate with tooltips
7. Select "With Notes" — verify Note column shows truncated text, hover shows full note
8. Select "Custom..." — verify modal opens, can pick up to 5 columns, Apply works
9. Switch to costs/medbills folder — verify "Amounts" format is available
10. Test with pagination — verify format persists across page changes
11. Test on timeline view (folder_id=0) — verify format dropdown is hidden
