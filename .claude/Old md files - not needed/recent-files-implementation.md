# Recent Files Feature — Implementation Plan

## Overview

Allow users to quickly reopen recently accessed files from a hover dropdown on the "File" nav link, without navigating to the Files index first. The system tracks the last 10 files opened per user.

## Components

### 1. Migration — `create_recent_files_table`

Create `recent_files` table:

| Column         | Type              | Notes                                |
|----------------|-------------------|--------------------------------------|
| `id`           | integer (unsigned)| auto-increment primary key           |
| `user_id`      | foreignId         | references `users.id`, cascade delete|
| `file_id`      | unsignedInteger   | references `files.id` (integer PK), cascade delete |
| `last_opened_at`| datetime          | when the file was last opened        |
| `created_at`   | timestamp         | standard Laravel timestamp           |
| `updated_at`   | timestamp         | standard Laravel timestamp           |

- Unique index on `(user_id, file_id)` — each file appears at most once per user.
- `file_id` uses `unsignedInteger` + manual `foreign()` since `files.id` is an integer, not bigint.

### 2. Model — `RecentFile`

**File:** `app/Models/RecentFile.php`

- Relationships: `belongsTo` User, `belongsTo` File
- Static helper method:

```php
public static function track(int $userId, int $fileId): void
{
    // Upsert: insert or update last_opened_at
    self::updateOrCreate(
        ['user_id' => $userId, 'file_id' => $fileId],
        ['last_opened_at' => now()]
    );

    // Prune: keep only the 10 most recent for this user
    $keep = self::where('user_id', $userId)
        ->orderByDesc('last_opened_at')
        ->take(10)
        ->pluck('id');

    self::where('user_id', $userId)
        ->whereNotIn('id', $keep)
        ->delete();
}
```

### 3. Backend — Track file opens in EntryController@index

**File:** `app/Http/Controllers/EntryController.php`

In the `index` method, after fetching `$file`, add the tracking call **only on full page loads** (not partial Inertia refreshes):

```php
if ($refresh === 'full') {
    RecentFile::track(auth()->id(), $file->id);
}
```

The `$refresh` variable is already set from the `X-Custom-Refresh` header. This ensures tracking only fires on initial file opens, not on pagination or partial data refreshes within an already-open file.

### 4. Backend — Dedicated API endpoint

**Route:** `GET /recent-files` (named `recent-files.index`)
**File:** `app/Http/Controllers/RecentFileController.php`

Returns JSON (not an Inertia response) with the user's recent files:

```php
public function index(): JsonResponse
{
    $recentFiles = RecentFile::where('user_id', auth()->id())
        ->orderByDesc('last_opened_at')
        ->with('file:id,name')
        ->get()
        ->pluck('file')
        ->map(fn ($file) => ['id' => $file->id, 'name' => $file->name]);

    return response()->json($recentFiles);
}
```

**Route registration** in `routes/web.php` (inside the `auth + welcomed` middleware group):

```php
Route::get('recent-files', [RecentFileController::class, 'index'])->name('recent-files.index');
```

### 5. Frontend — AuthenticatedLayout.vue changes

**File:** `resources/js/Layouts/AuthenticatedLayout.vue`

Replace the current File `<NavLink>` with a hover-dropdown component. The dropdown:

- **Trigger:** The existing File icon + "File" text, preserving NavLink active styling.
- **On layout mount:** Fetch `GET /recent-files` via `fetch()` or `axios` and store in a reactive ref.
- **On hover:** Show the dropdown with the cached list (no additional request).

#### Dropdown content (top to bottom):

1. **"Show File List"** — link to `route('files.index')`
2. **Divider** with label **"Recently used files"**
3. **List of up to 10 recent file names** — each linking to `route('entries.index', file.id)`

```
┌─────────────────────────┐
│  Show File List          │
├─── Recently used files ──┤
│  Smith v. Jones          │
│  Johnson Estate          │
│  Acme Corp Litigation    │
│  ...                     │
└─────────────────────────┘
```

- **Refresh strategy:** Re-fetch the list after Inertia navigations to `entries.index` (using Inertia's `router.on('navigate')` event), so the list stays current when files are opened.
- **Desktop only** — the mobile responsive nav keeps the simple link to `files.index`.

#### Hover dropdown — `HoverDropdown.vue`

**File:** `resources/js/Components/HoverDropdown.vue`

A new component (separate from the existing click-based `Dropdown.vue`) that:

- Opens on `mouseenter` of the wrapper element.
- Closes on `mouseleave` with a small delay (~150ms) to prevent flicker when the cursor briefly leaves the trigger area while moving to the dropdown content.
- Closes on `Escape` key.
- Accepts `align` and `width` props matching `Dropdown.vue` conventions.
- Uses the same transition classes as `Dropdown.vue` for visual consistency.

### 6. Files to create/modify

| File | Action |
|------|--------|
| `database/migrations/xxxx_create_recent_files_table.php` | Create |
| `app/Models/RecentFile.php` | Create |
| `app/Http/Controllers/RecentFileController.php` | Create |
| `app/Http/Controllers/EntryController.php` | Modify — add `RecentFile::track()` call |
| `routes/web.php` | Modify — add `recent-files` route |
| `resources/js/Components/HoverDropdown.vue` | Create |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Modify — replace File NavLink with hover dropdown |

## Not in scope (future enhancements)

- Configurable list size via user preferences (currently hardcoded to 10)
- Mobile nav recent files
- Pinned/favorite files
