# Linked Documents Feature Plan

## Overview
Allow entries to link to external documents stored on the firm's file system. Users can open linked documents from the entries list in a new browser tab. The web server serves the files through Laravel (browsers block direct `file://` URLs).

## Architecture Decisions

- **Single document per entry** (MVP - can expand to multi-document later)
- **Firm-level base path** stored in `firms` table
- **Relative path** stored on `entries` table (relative to firm's base path)
- **Laravel serves files** via `response()->file()` with path traversal protection
- **Text input + Browse button** for path entry — Browse opens a custom file picker modal
- **Paths displayed in native OS format** (backslashes on Windows, forward slashes on Mac/Linux) — normalized internally when building full paths
- **Admin-only** firm settings management

## File Model Naming Conflict

The app uses `App\Models\File` for case/matter files. This conflicts with Laravel's `Illuminate\Support\Facades\File` facade. **Workaround**: We avoid needing the `File` facade entirely by using `response()->file()` to serve documents. If the facade is ever needed, alias it:
```php
use App\Models\File;
use Illuminate\Support\Facades\File as FileFacade;
```
This is not a blocker — just something to be aware of in the document-serving controller.

---

## Phase 1: Database Changes

### Migration 1: Add `document_base_path` to `firms`
- Add nullable `varchar(500)` column `document_base_path`
- This is the root directory where the firm stores their documents
- Example: `S:\Cases\` or `\\fileserver\shared\cases\` or `/Volumes/shared/cases/`

### Migration 2: Add `linked_document_path` to `entries`
- Add nullable `varchar(500)` column `linked_document_path`
- Stores the **relative** path from the firm's base path to the specific document
- Example: `2024\Smith\contract.pdf`

### Model Updates
- **Firm model**: Add `document_base_path` — no special casts needed
- **Entry model**: Add `linked_document_path`
- **Entry model**: Add accessor `has_linked_document` (boolean convenience)
- **Entry model**: Add method `full_document_path()` that combines firm base path + relative path, normalizing directory separators for the server OS

---

## Phase 2: Firm Edit Page (New)

### Context
The app currently has **no Firm edit page**. This phase creates one, accessible only to Admin users from the Admin menu.

### New Route
```
GET  /firm/edit       → FirmController@edit
PUT  /firm/update     → FirmController@update
```
- Middleware: `auth`, `welcomed`
- Controller authorization: verify `user_type === 'Admin'`

### Firm Edit Form (Pages/Firm/Edit.vue)
Editable fields:
- **Name** (required, string, max 255)
- **Address** (nullable, string, max 255)
- **Phone** (nullable, string, max 50)
- **Email** (required, email, unique per firm)
- **Document Base Path** (nullable, string, max 500)
  - Label: "Document Storage Path"
  - Help text: "The root folder where your firm's documents are stored (e.g., S:\Cases or /Volumes/shared/cases)"
  - On save: validate the path exists and is readable by the web server; show error if not

### Form Request: UpdateFirmRequest
- Validation rules for all fields
- Custom rule for `document_base_path`: if provided, must be a valid readable directory on the server

### Admin Menu Integration
- Add "Firm Settings" link to the Admin menu section
- Only visible to Admin users

---

## Phase 3: Backend - File Serving & Security

### New Route
```
GET /entries/{entry}/document
```
- Named route: `entries.document`
- Middleware: `auth`, `welcomed`
- Controller: `EntryController@serve_document`

### Controller Method: `serve_document(Entry $entry)`
1. **Authorization**: Verify user belongs to the same firm as the entry
2. **Validate base path exists**: Check firm has `document_base_path` set
3. **Build full path**: Combine firm base path + entry relative path, normalize separators
4. **Path traversal protection**:
   - Resolve the full path with `realpath()`
   - Verify resolved path starts with the firm's base path (also resolved)
   - Reject if path escapes the base directory
5. **File existence check**: If file doesn't exist, redirect back with flash error message ("Document not found at the expected path — the file may have been moved or renamed")
6. **Serve file**: Use `response()->file()` with appropriate content type
   - Set `Content-Disposition: inline` so PDFs/images open in browser
   - Browser handles download for unsupported types (Word, Excel)

### Security Checklist
- [ ] Path traversal prevention (`realpath()` + starts-with check)
- [ ] Firm isolation (user can only access their own firm's files)
- [ ] File existence validation with user-friendly error
- [ ] No directory listing exposed
- [ ] Sanitize stored paths on save (reject `..` sequences)

---

## Phase 4: Directory Browser API & Component

### Backend: Browse Directory Endpoint
```
GET /firm/browse-directory?path=optional/subfolder
```
- Admin not required — any authenticated user can browse (they need to pick files)
- Returns JSON: `{ current_path: "subfolder", parent: "..", items: [{ name, type: "file"|"directory", size, modified }] }`
- Same security as file serving: `realpath()` validation, must stay within firm's base path
- Only returns files and directories (no hidden/system files)
- Sorted: directories first, then files, alphabetically

### Frontend: DocumentPicker.vue Component
A modal component with:
- **Breadcrumb navigation** showing current path from base
- **Directory listing** styled with DaisyUI — folder icons and file icons
- **Click folder** → drills into it (fetches new listing)
- **Click file** → selects it, populates the path input, closes modal
- **"Up" button** → navigates to parent directory (cannot go above base path)
- **Cancel button** → closes without selection

~100-150 lines of Vue. No external dependencies needed — uses DaisyUI classes for styling and Axios/Inertia router for API calls.

### Integration with Entry Form
- Text input for `linked_document_path` with a "Browse..." button next to it
- Browse button opens the DocumentPicker modal
- Selecting a file in the picker populates the text input
- User can also type/paste a path directly
- Only shown when the firm has a `document_base_path` configured

---

## Phase 5: Entry Form Changes (EntryForm.vue)

### New Field: Linked Document
- **Position**: After the Note textarea, before Response Expected Date
- **Label**: "Linked Document"
- **Layout**: Text input + "Browse..." button (inline)
- **Placeholder**: `e.g., 2024\Smith\contract.pdf`
- **Condition**: Only show if the firm has a `document_base_path` set (pass `firm_document_base_path` as prop — truthy check controls visibility, also needed by the picker)
- **Behavior on edit**: Pre-populate with existing path; clearing the field removes the link

### Props Changes
- Pass `firm_document_base_path` (string|null) from EntryController to EntryForm
- Pass `linked_document_path` as part of entry data when editing

### Form Data
- Add `linked_document_path` to the useForm data object
- Include in store/update submissions

### Validation (StoreEntryRequest)
- `linked_document_path`: nullable, string, max 500
- Custom rule: reject paths containing `..` sequences

---

## Phase 6: Entries Index Changes (Index.vue)

### Button in Action Bar (near Edit/Delete for selected entry)
- **No document linked**: Button says "Add Doc" → switches to edit mode for that entry (existing edit flow)
- **Document linked**: Button says "Open Doc" → opens document in new tab via `window.open(route('entries.document', entry.id))`
- **Firm has no base path**: Button hidden entirely

### Visual Indicator on Entry Rows
- Small document icon (or paperclip icon) next to entries that have a linked document
- Subtle, doesn't clutter the table — just a visual hint

### Props Changes
- Include `linked_document_path` (or boolean `has_linked_document`) in the entries pagination data
- Include `firm_document_base_path` (truthy/falsy) to control button visibility

### Error Handling
- If "Open Doc" results in a file-not-found, the user sees a flash message (redirect back from the serve_document controller)
- Since we open in a new tab, the flash would show in that tab — consider instead returning an error page with a message and a "close tab" suggestion, or using a fetch + error modal approach on the index page

---

## Phase 7: Testing

### Feature Tests
1. **Firm edit - happy path**: Admin can update firm settings including document base path
2. **Firm edit - non-admin blocked**: Non-admin users cannot access firm edit
3. **Firm edit - base path validation**: Invalid/non-existent paths are rejected
4. **Serve document - happy path**: User with valid firm path and linked entry can access file
5. **Serve document - path traversal blocked**: Paths containing `..` are rejected
6. **Serve document - wrong firm**: User from firm A cannot access firm B's entry document
7. **Serve document - no base path**: Returns error when firm has no base path configured
8. **Serve document - file not found**: Returns flash error, not a raw 404
9. **Browse directory - happy path**: Returns correct directory listing
10. **Browse directory - path traversal blocked**: Cannot browse outside base path
11. **Browse directory - no base path**: Returns error
12. **Store entry with document path**: Path saved correctly
13. **Update entry document path**: Path updated/cleared correctly
14. **Validation**: Rejects paths with `..` sequences

---

## Implementation Order

1. **Migrations** — Add columns to firms and entries tables
2. **Model updates** — Accessors, methods on Entry and Firm
3. **Firm edit page** — New FirmController methods, UpdateFirmRequest, Firm/Edit.vue, Admin menu link
4. **File serving route & controller** — serve_document with security
5. **Directory browser** — API endpoint + DocumentPicker.vue component
6. **Entry form** — Add linked document field with Browse button
7. **Entries index** — Add Open Doc/Add Doc button and document icon indicator
8. **Tests** — Cover all scenarios
9. **Run Pint** — Format PHP files

---

## Cross-Platform Path Handling

### Strategy
- **Display**: Show paths using the separator the user typed (preserve their input)
- **Storage**: Store the path exactly as entered by the user
- **Server-side resolution**: When building the full path to serve a file, normalize separators to match the server's OS (`DIRECTORY_SEPARATOR`)
- **File picker**: The browse-directory endpoint returns paths using the server's native separator, so the picker naturally uses the right format

### Example
- User enters: `2024\Smith\contract.pdf`
- Stored in DB: `2024\Smith\contract.pdf`
- On Windows server: resolves to `S:\Cases\2024\Smith\contract.pdf` ✓
- On Linux server: normalized to `S:/Cases/2024/Smith/contract.pdf` → would fail because the base path itself is Windows-format

**Note**: The base path and server OS must match. A Windows base path only works on a Windows server. This is expected — the firm's file storage is on the same network as the server.

---

## Future Enhancements (Not in MVP but planned)
- **Multiple documents per entry**: Separate `entry_documents` table
- **File preview**: Thumbnail/preview of linked documents inline
- **Drag-and-drop**: Drop a file from explorer onto an entry to link it
- **Broken link detection**: Periodic validation or visual indicator when files are missing
- **Cloud storage support**: S3/Azure Blob instead of local filesystem
- **File-level subfolder**: Optional path prefix on the File model to reduce repetitive typing
