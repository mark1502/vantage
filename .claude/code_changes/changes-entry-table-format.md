# Entry Table View Formats

## New file: `resources/js/Config/entryViewFormats.js`
- Column definitions for: date1, entrytype, from, to, date2, note, amount, response
- 7 named formats: Standard, Contacts, Detailed, With Notes, Amounts, Dates, Response
- Each format has an `availableWhen()` function based on folder hide flags
- Formats with entrytype-optional folders (like Memos, Med Bills) dynamically adjust their column list
- Helper functions `getAvailableFormats(folder)` and `getColumns(formatKey, folder)` exported

## Modified file: `resources/js/Pages/Entries/Index.vue`
- Imported the config helpers
- Added `currentFormatKey` ref (defaults to `'standard'`), `currentFolder` / `availableFormats` / `activeColumns` computed properties
- Table header and body now render dynamically via `v-for="col in activeColumns"` loops
- Added `table-fixed` + `truncate max-w-0` for overflow clipping with native `title` tooltips on all cells
- Paperclip icon preserved on the `from` column via `col.hasLinkedDoc` flag
- Empty rows match dynamic column count
- **View dropdown** added next to Show dropdown — only visible when `view_folder_id > 0` and more than one format is available
- Format resets to "standard" on folder change if the current format isn't available for the new folder
