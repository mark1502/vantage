# Audio/Video Memo Recording Feature

## Context

The Memos folder (folder_id 5) currently hides the entrytype dropdown and auto-selects "Memo". We're expanding this so users can choose between Memo (standard), Audio Memo, and Video Memo. Audio/Video memos record directly in the browser (max 3 minutes), are stored on the server in private storage, and served through authenticated routes. Recordings are immutable — they can only be deleted along with the entry. All firm members with file access can view/play memos. This will eventually be offered as a firm add-on, but is included for all firms initially.

**Clean slate assumption**: No existing data or users. Folder/entrytype changes go in seeders, not data migrations. Only one migration needed (media columns).

---

## Security Model

| Concern | Implementation |
|---------|---------------|
| **Authenticated access** | Media serve route inside `auth + welcomed` middleware group |
| **Firm-scoped authorization** | `serve_media` checks `$entry->firm_id === $request->user()->firm_id` |
| **MIME type validation** | Form request `mimetypes:` rule — only allows audio/video webm, ogg, mp4 |
| **File size limit** | Form request `max:25600` (25MB); frontend enforces 3-min limit |
| **Path traversal prevention** | Storage paths built server-side from `firm_id` + `entry_id` — no user input in path |
| **No path exposure** | Client only sees `/entries/{entry}/media` route; never sees disk path |
| **CSRF** | Standard Laravel CSRF middleware (Inertia sends token automatically) |
| **Duration limit** | Frontend auto-stops at 180s; backend validates `max:180` |
| **No editing** | Controller rejects upload if entry already has media |

---

## Implementation Steps

### 1. Migration: Add media columns to entries table
**Create** `database/migrations/..._add_media_columns_to_entries_table.php`

Add 4 nullable columns after `linked_document_path`:
- `media_disk_path` string(500) nullable
- `media_mime_type` string(50) nullable
- `media_duration_seconds` unsignedSmallInteger nullable
- `media_size_bytes` unsignedInteger nullable

### 2. Update FolderSeeder — unhide entrytype for Memos
**Modify** `database/seeders/FolderSeeder.php`

Change the Memos row (line 37-40): set `'hide_entrytype_prompt' => false`

### 3. Update EntrytypeSeeder — add Audio/Video Memo
**Modify** `database/seeders/EntrytypeSeeder.php`

Add two rows after the existing Memo row (line 34):
- `[ 'firm_id' => 1, 'folder_id' => 5, 'name' => 'Audio Memo', ... ]`
- `[ 'firm_id' => 1, 'folder_id' => 5, 'name' => 'Video Memo', ... ]`

### 4. Update Entry model
**Modify** `app/Models/Entry.php`

- Add `hasMedia` computed Attribute (like existing `hasLinkedDocument`)
- Add `isAudioMemo()` / `isVideoMemo()` helpers based on `media_mime_type`

### 5. Update StoreEntryRequest validation
**Modify** `app/Http/Requests/StoreEntryRequest.php`

Add rules for:
- `media` — nullable, file, max:25600, mimetypes:audio/webm,video/webm,audio/ogg,video/mp4,audio/mp4
- `media_duration_seconds` — nullable, integer, min:1, max:180

### 6. Update EntryController store method
**Modify** `app/Http/Controllers/EntryController.php` — `store()` (after line 169 `$entry->save()`)

After saving entry, check if `$request->hasFile('media')`:
- Determine extension from MIME type
- Store to `memos/{firm_id}/{entry_id}.{ext}` on local disk
- Update entry with `media_disk_path`, `media_mime_type`, `media_duration_seconds`, `media_size_bytes`

### 7. Update EntryController destroy method
**Modify** `app/Http/Controllers/EntryController.php` — `destroy()` (before line 335 `$entry->delete()`)

If entry has `media_disk_path`, delete the file from storage before deleting the entry.

### 8. Add serve_media method to EntryController
**Modify** `app/Http/Controllers/EntryController.php`

New `serve_media(Request $request, Entry $entry)` method:
- Verify `$entry->firm_id === $request->user()->firm_id` (403 if not)
- Verify `media_disk_path` exists (404 if not)
- Check for path traversal (`..` in path → 403)
- Verify file exists on disk (404 if not)
- Return `response()->file()` with correct Content-Type header

### 9. Add route
**Modify** `routes/web.php`

Inside the `auth + welcomed` group, near existing document route (~line 104):
```php
Route::get('/entries/{entry}/media', [EntryController::class, 'serve_media'])->name('entries.media');
```

### 10. Create useMediaRecorder composable
**Create** `resources/js/Composables/useMediaRecorder.js`

Encapsulates browser MediaRecorder API:
- `startRecording(type)` — requests getUserMedia, starts recording ('audio' or 'video')
- `stopRecording()` — stops recorder, assembles blob
- `discardRecording()` — clears recorded data, revokes object URL
- Exposes: `isRecording`, `recordedBlob`, `recordedUrl`, `duration`, `formattedDuration`, `remainingSeconds`, `error`, `canRecord`, `stream`
- Auto-stops at `maxDuration` (180s default)
- Cleans up stream/URLs on unmount
- Video constraints capped at 640x480

### 11. Create MemoRecorder component
**Create** `resources/js/Components/MemoRecorder.vue`

Props: `recordingType` ('audio'|'video')
Emits: `update:blob`, `update:duration`

Three states:
1. **Pre-recording** — "Start Recording" button + max duration note
2. **Recording** — live video preview (if video) + pulsing red dot + timer + remaining time + Stop button
3. **Recorded** — audio/video player for preview + "Discard & Re-record" button

### 12. Create MemoPlayer component
**Create** `resources/js/Components/MemoPlayer.vue`

Props: `entry` (Object)

Displays `<audio>` or `<video>` element with controls, sourced from `route('entries.media', entry.id)`. Shows formatted duration.

### 13. Integrate into EntryForm.vue
**Modify** `resources/js/Pages/Entries/EntryForm.vue`

Key changes:
- Import MemoRecorder, MemoPlayer
- Add `mediaBlob` ref and `mediaDuration` ref for recording state
- Add computed `currentEntrytypeName` — looks up selected entrytype name from folder data
- Add computed `isMediaMemo` — true if name is "Audio Memo" or "Video Memo"
- Add computed `recordingType` — 'audio' or 'video' based on entrytype name
- **Entrytype default**: When folder_id is 5 (now unhidden), default selection to "Memo" (standard) — update the logic at line 812 since `hide_entrytype_prompt` is now false for memos
- **Template**: After the Note row (~line 1050), add `<MemoRecorder>` (shown during entry_add when `isMediaMemo`)
- **Template**: In browse mode, add `<MemoPlayer>` when entry has `media_disk_path`
- **Submit flow**: Add `media` and `media_duration_seconds` fields to `entry_form`. Before posting, if `mediaBlob.value` exists, set these fields. Inertia auto-converts to multipart/form-data when a File/Blob is present.
- **Note field**: When `isMediaMemo`, the note field label changes to "Description (optional)"

### 14. Update entry list display
**Modify** `resources/js/Pages/Entries/Index.vue`

Add a small speaker/camera icon next to entries that have `media_disk_path` set (similar to how `linked_document_path` shows an icon).

### 15. Pass media fields to frontend
**Modify** `app/Http/Controllers/EntryController.php` — `index()` method

Ensure entry queries include the media columns so the frontend can detect `media_disk_path`, `media_mime_type`, and `media_duration_seconds` for display.

---

## Storage Layout

```
storage/app/private/memos/{firm_id}/{entry_id}.webm
```

Paths are constructed entirely server-side. No user-supplied path components.

---

## File Summary

| File | Action |
|------|--------|
| `database/migrations/..._add_media_columns_to_entries_table.php` | Create |
| `database/seeders/FolderSeeder.php` | Modify (unhide entrytype for Memos) |
| `database/seeders/EntrytypeSeeder.php` | Modify (add Audio Memo + Video Memo) |
| `app/Models/Entry.php` | Modify |
| `app/Http/Requests/StoreEntryRequest.php` | Modify |
| `app/Http/Controllers/EntryController.php` | Modify (store, destroy, new serve_media) |
| `routes/web.php` | Modify |
| `resources/js/Composables/useMediaRecorder.js` | Create |
| `resources/js/Components/MemoRecorder.vue` | Create |
| `resources/js/Components/MemoPlayer.vue` | Create |
| `resources/js/Pages/Entries/EntryForm.vue` | Modify |
| `resources/js/Pages/Entries/Index.vue` | Modify |

---

## Verification

1. Run `php artisan migrate:fresh --seed` — clean database with updated seeders
2. Check Memos folder now shows entrytype dropdown with 3 options: Memo, Audio Memo, Video Memo
3. Select "Memo" — form behaves exactly as before
4. Select "Audio Memo" — recording controls appear; record, preview, submit
5. Select "Video Memo" — recording controls with video preview appear; record, preview, submit
6. Browse an entry with media — playback widget appears with controls
7. Delete an entry with media — confirm media file is removed from disk
8. Try accessing `/entries/{other_firm_entry}/media` — should get 403
9. Try accessing media URL when logged out — should redirect to login
10. Check file sizes on disk are reasonable (~3MB audio, ~15MB video for 3 min)
