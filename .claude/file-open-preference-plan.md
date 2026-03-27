# File Open Preference Implementation Plan

## Overview

Implement two user preferences that control which folder/tab a file opens to:

- **`file_open_to`** (default: `'correspondence'`) - The folder/tab every file opens to by default.
- **`file_recent_spot`** (default: `false`) - When true, files opened from the recent-files HoverDropdown use their stored `filepart` (last-viewed folder) instead of `file_open_to`.

### Logic Summary

| Opened From | `file_recent_spot` | Result |
|---|---|---|
| Files/Index.vue "Open" button | any | Use `file_open_to` preference |
| Recent-files HoverDropdown | `false` | Use `file_open_to` preference |
| Recent-files HoverDropdown | `true` | Use stored `filepart` from RecentFile record |

**Fallback:** If `file_open_to` is set to a folder not available in the file's filetype (e.g., `pleadings` but filetype has `has_pleadings = 0`), fall back to `correspondence`.

### `file_open_to` Valid Options

- `correspondence`, `pleadings`, `discovery`, `documents`, `memos`, `events`, `todo`, `phone`, `medrecs`, `medbills`, `costs`
- `all` (File Timeline)
- `info` (File Details)

---

## Implementation Steps

### Step 1: FileController — Pass `file_open_to` to Files/Index.vue

**File:** `app/Http/Controllers/FileController.php` — `index()` method

Query the user's `file_open_to` preference and pass it as a prop:

```php
$fileOpenTo = $request->user()->preferences()
    ->where('name', 'file_open_to')
    ->value('setting') ?? 'correspondence';

return Inertia::render('Files/Index', compact('files', 'fileOpenTo'));
```

No global sharing needed — this is the only page that needs this value.

### Step 2: Files/Index.vue — Use the prop

**File:** `resources/js/Pages/Files/Index.vue`

Add `fileOpenTo` to the props, then replace the hardcoded `'correspondence'`:

```js
const props = defineProps({
    files: Object,
    fileOpenTo: { type: String, default: 'correspondence' },
});
```

Where `disp.openurl` is built (currently line 61):
```js
// Before:
disp.openurl = route('entries.index', { file: file1.value, filepart: 'correspondence', page: 1, show: state.show });

// After:
disp.openurl = route('entries.index', { file: file1.value, filepart: getValidFilepart(file1), page: 1, show: state.show });
```

**Filetype validation helper** (client-side, since the file's filetype is already loaded):
```js
function getValidFilepart(file) {
    const pref = props.fileOpenTo;
    if (pref === 'all' || pref === 'info') return pref;
    if (file.filetype[`has_${pref}`] === 1) return pref;
    return 'correspondence';
}
```

This runs each time a file is selected, so if the user's preferred folder isn't available for that file's type, it falls back to correspondence.

### Step 3: RecentFileController — Apply preferences server-side

**File:** `app/Http/Controllers/RecentFileController.php` — `index()` method

This is the key change from the original plan. Instead of passing preferences to the frontend and having AuthenticatedLayout.vue do the logic, the controller resolves the correct `filepart` before returning JSON:

```php
public function index(): JsonResponse
{
    $user = auth()->user();

    // Load user's file-open preferences
    $prefs = $user->preferences()
        ->whereIn('name', ['file_open_to', 'file_recent_spot'])
        ->pluck('setting', 'name');

    $fileOpenTo = $prefs['file_open_to'] ?? 'correspondence';
    $fileRecentSpot = ($prefs['file_recent_spot'] ?? 'false') === 'true';

    $recentFiles = RecentFile::where('user_id', $user->id)
        ->orderByDesc('last_opened_at')
        ->with('file:id,name')
        ->get()
        ->map(fn ($recentFile) => [
            'id' => $recentFile->file->id,
            'name' => $recentFile->file->name,
            'filepart' => $fileRecentSpot ? $recentFile->filepart : $fileOpenTo,
            'page' => $fileRecentSpot ? $recentFile->page : 1,
            'show' => $recentFile->show,
        ]);

    return response()->json($recentFiles);
}
```

**Result:** AuthenticatedLayout.vue needs **no changes** — it already uses `file.filepart` and `file.page` from the API response. The controller now returns the preference-adjusted values.

### Step 4: EntryController — Server-side filetype validation (safety net)

**File:** `app/Http/Controllers/EntryController.php` — `index()` method

After loading the file and reading `filepart` from the query string (around line 33), add validation before it's used:

```php
$filepart = $request->query('filepart', 'correspondence');

// Validate that the requested folder exists for this file's type
if (!in_array($filepart, ['all', 'info', 'file_contacts'])) {
    $folderFlag = 'has_' . $filepart;
    if (!$file->filetype->$folderFlag) {
        $filepart = 'correspondence';
    }
}

$viewfolder_id = $this->get_folder_info($filepart);
```

This catches edge cases regardless of where the request originated (Open button, recent files, direct URL, etc.).

### Step 5: Preferences UI

**File:** `resources/js/Pages/Preferences/Index.vue`

Add a new section below the existing calendar sections for "File Open" preferences:

```
┌─────────────────────────────────────────────────┐
│ File - Default Open Location                    │
│                                                 │
│ Open Files at: [dropdown v]          [Save]     │
│                                                 │
│ ☐ Open recent files where I left off   [Save]   │
│   (overrides above setting when opening         │
│    from the recent files menu)                   │
└─────────────────────────────────────────────────┘
```

**Dropdown options:**
| Value | Label |
|---|---|
| `correspondence` | Correspondence |
| `pleadings` | Pleadings and Motions |
| `discovery` | Discovery |
| `documents` | Documents |
| `memos` | Memos |
| `events` | Events |
| `todo` | To-Do |
| `phone` | Phone Calls |
| `medrecs` | Medical Records |
| `medbills` | Medical Bills |
| `costs` | Case Costs |
| `all` | File Timeline |
| `info` | File Details |

Implementation follows the existing pattern in the file:
- Load `file_open_to` and `file_recent_spot` from the `preferences` prop in the `switch` block.
- Add reactive state: `user_prefs.file_open_to`, `user_prefs.file_open_to_saved`, `user_prefs.file_recent_spot`, `user_prefs.file_recent_spot_saved`.
- Save via POST to new route (Step 6), with save/revert pattern matching existing sections.

### Step 6: PreferenceController — Save endpoint + route

**File:** `app/Http/Controllers/PreferenceController.php`

Add a new method:

```php
public function file_open_update(Request $request): RedirectResponse
{
    Preference::where('user_id', $request->user_id)
        ->where('name', 'file_open_to')
        ->update(['setting' => $request->file_open_to]);

    Preference::where('user_id', $request->user_id)
        ->where('name', 'file_recent_spot')
        ->update(['setting' => $request->file_recent_spot]);

    return redirect()->back();
}
```

**File:** `routes/web.php`

Add route (within the `auth + welcomed` middleware group):
```php
Route::post('/preferences/file_open', [PreferenceController::class, 'file_open_update']);
```

---

## Files Modified

| File | Change |
|---|---|
| `app/Http/Controllers/FileController.php` | Pass `file_open_to` pref as prop in `index()` |
| `app/Http/Controllers/RecentFileController.php` | Apply `file_open_to` / `file_recent_spot` logic server-side |
| `app/Http/Controllers/EntryController.php` | Add filetype validation for `filepart` |
| `app/Http/Controllers/PreferenceController.php` | Add `file_open_update()` method |
| `resources/js/Pages/Files/Index.vue` | Use `fileOpenTo` prop + filetype validation |
| `resources/js/Pages/Preferences/Index.vue` | Add file-open preference UI section |
| `routes/web.php` | Add POST route for file_open preference |

**Not modified:** `HandleInertiaRequests.php`, `AuthenticatedLayout.vue`

## Assumptions

- User will add `file_recent_spot` to the `pref_defaults` table and seeder separately.
- `file_recent_spot` stores `'true'`/`'false'` as strings (consistent with other preference settings being strings).
- `correspondence` is always available in every filetype (safe fallback).
