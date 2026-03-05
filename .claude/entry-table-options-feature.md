# Plan: Entries/Index Table View Formats

## Context

The Entries/Index table currently shows 3 hardcoded columns (Date, Type, From) and has no way to display other available entry data like To, Date2, Note, Amount, or Response status. With only half the screen for the table, space is tight. This enhancement adds selectable pre-defined view formats per folder, overflow tooltips, and a format dropdown. No persistence — always defaults to "standard" on page load.

## Summary of Changes

1. **New config file** with column definitions and named formats
2. **Dynamic table rendering** driven by the selected format
3. **View format dropdown** next to the existing Show dropdown
4. **Truncate + title tooltips** on all cells for overflow

No backend changes needed — this is purely frontend.

---

## Step 1: Frontend config file

**New file:** `resources/js/Config/entryViewFormats.js`

**Column definitions** — each column has:
- `key`, `label(folder)` (uses folder prompt labels), `getValue(entry, props, helpers)`
- `width` (Tailwind class), `visibleWhen(folder)` (checks folder's hide_* flags)

Columns: `date1`, `entrytype`, `from`, `to`, `date2`, `note`, `amount`, `response`

**Named formats:**
- `standard` — Date, Type, From (always available, the default)
- `contacts` — Date, From, To (folders that have To: corr, plead, disc, memos, phone)
- `detailed` — Date, Type, From, To (same folders as contacts)
- `with_notes` — Date, Type, From, Note (all folders)
- `amounts` — Date, Type, From, Amount (medbills, costs only)
- `dates` — Date, Date2, Type, From (folders with date2)
- `response` — Date, Type, From, Response (folders with response expected)

**Helper functions:**
- `getAvailableFormats(folder)` — returns which formats apply to a folder
- `getColumns(formatKey)` — returns the column definitions for a format

**Folder availability matrix (from seeder data):**

| Folder | To | Date2 | Amount | Response | Available formats |
|--------|-----|-------|--------|----------|-------------------|
| Correspondence | yes | yes | no | yes | standard, contacts, detailed, with_notes, dates, response |
| Pleadings | yes | yes | no | yes | standard, contacts, detailed, with_notes, dates, response |
| Discovery | yes | yes | no | yes | standard, contacts, detailed, with_notes, dates, response |
| Documents | no | yes | no | no | standard, with_notes, dates |
| Memos | yes | no | no | yes | standard, contacts, detailed, with_notes, response |
| Events | no | yes | no | no | standard, with_notes, dates |
| To-Do | no | yes | no | no | standard, with_notes, dates |
| Phone | yes | no | no | yes | standard, contacts, detailed, with_notes, response |
| Med Records | no | yes | no | no | standard, with_notes, dates |
| Med Bills | no | yes | yes | no | standard, with_notes, dates, amounts |
| Costs | no | no | yes | no | standard, with_notes, amounts |

---

## Step 2: Modify Index.vue

### Script changes:
- Import from `entryViewFormats.js`
- Add reactive state: `currentFormatKey` (defaults to `'standard'`)
- Add computed: `availableFormats` (based on current folder), `activeColumns`
- Add method: `onFormatChange()` — updates `currentFormatKey`, resets to `'standard'` if current format not available for new folder
- Reset format to `'standard'` when folder changes (inside `update_listFormat()`)

### Template changes:

**Table `<table>` tag:** Add `table-fixed` class for truncation to work.

**Table header:** Replace hardcoded `<th>` elements with `v-for="col in activeColumns"` loop.

**Table body rows:** Replace hardcoded `<td>` elements with `v-for="col in activeColumns"` loop. Each cell gets:
- `truncate max-w-0` + column width class for text clipping
- `:title="col.getValue(...)"` for native hover tooltip
- Special case for `from` column (paperclip icon for linked documents)

**Empty rows:** Match dynamic column count with `v-for="col in activeColumns"`.

**Controls below table:** Keep `Show: [10▼]` as-is, add format dropdown next to it:
- Label: `View:`
- Dropdown shows available format names for current folder (e.g., Standard, Contacts, Detailed, With Notes)
- Selected value bound to `currentFormatKey`

**When folder_id is 0 (timeline) or negative:** Hide the format dropdown, always use "standard".

---

## No Persistence

Format selection resets to "standard" on every page load and every folder change. This keeps the implementation simple — purely frontend, no backend changes. Persistence can be added later if desired.

---

## Files to modify/create

| File | Action |
|------|--------|
| `resources/js/Config/entryViewFormats.js` | Create |
| `resources/js/Pages/Entries/Index.vue` | Template + script changes |

---

## Verification

1. Run `npm run dev` for frontend
2. Navigate to a file's entry list
3. Verify default "Standard" view shows Date, Type, From as before
4. Change folder dropdown — verify format dropdown updates available options
5. Select "Contacts" — verify Date, From, To columns (no Type), cells truncate with tooltips
6. Select "Detailed" — verify Date, Type, From, To columns appear
7. Select "With Notes" — verify Note column shows truncated text, hover shows full note
8. Switch to costs/medbills folder — verify "Amounts" format is available
9. Switch folders — verify format resets to Standard
10. Test on timeline view (folder_id=0) — verify format dropdown is hidden
