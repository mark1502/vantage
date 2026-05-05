# Plan: Store filepart, page, and show in recent_files

## Context
The recent-files dropdown currently links every file to `filepart=correspondence&page=1`. This means users always land at the start of correspondence, losing their place. By storing `filepart`, `page`, and `show` when a file is accessed, the dropdown can return users to exactly where they left off.

## Changes

### 1. Migration — add columns to `recent_files`
Create a new migration to add three columns:
- `filepart` (string, default `'correspondence'`)
- `page` (unsignedInteger, default `1`)
- `show` (unsignedInteger, default `15`)

**File:** new migration via `php artisan make:migration`

### 2. RecentFile model — update `track()` signature
**File:** `app/Models/RecentFile.php`

Update `track()` to accept and store the new fields:

```php
public static function track(int $userId, int $fileId, string $filepart = 'correspondence', int $page = 1, int $show = 15): void
{
    self::updateOrCreate(
        ['user_id' => $userId, 'file_id' => $fileId],
        [
            'last_opened_at' => now(),
            'filepart' => $filepart,
            'page' => $page,
            'show' => $show,
        ]
    );
    // ... pruning stays the same
}
```

### 3. EntryController — move `track()` call and pass new params
**File:** `app/Http/Controllers/EntryController.php`

Move the `RecentFile::track()` call from line 32-34 (before `$show`/`$filepart` are assigned) to after `$viewfolder_id` is assigned (after line 38) and before the `$entries = Entry::query()` line (line 40). Pass the new parameters:

```php
if ($refresh === 'full') {
    RecentFile::track(
        auth()->id(),
        $file->id,
        $filepart ?: 'correspondence',
        (int) ($request->query('page') ?: 1),
        (int) ($show ?: 15)
    );
}
```

### 4. RecentFileController — include new fields in JSON response
**File:** `app/Http/Controllers/RecentFileController.php`

Update the `->map()` to include `filepart`, `page`, and `show`:

```php
->map(fn ($recentFile) => [
    'id' => $recentFile->file->id,
    'name' => $recentFile->file->name,
    'filepart' => $recentFile->filepart,
    'page' => $recentFile->page,
    'show' => $recentFile->show,
]);
```

Note: the query needs adjusting slightly — currently it plucks `file` and then maps; we need to map on the RecentFile records instead so we can access the new columns.

### 5. AuthenticatedLayout.vue — use stored values in dropdown links
**File:** `resources/js/Layouts/AuthenticatedLayout.vue`

Update the `<Link>` for each recent file to use the stored values:

```vue
<Link v-for="file in recentFiles" :key="file.id"
    :href="route('entries.index', { file: file.id, filepart: file.filepart, page: file.page, show: file.show })"
    ...>
```

No changes needed to HoverDropdown.vue — it's a generic container component.

## File Summary
| File | Action |
|------|--------|
| `database/migrations/xxxx_add_context_to_recent_files_table.php` | Create |
| `app/Models/RecentFile.php` | Edit `track()` |
| `app/Http/Controllers/EntryController.php` | Move & update `track()` call |
| `app/Http/Controllers/RecentFileController.php` | Include new fields in response |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Use stored values in link |

## Verification
1. Run the migration: `php artisan migrate`
2. Navigate to a file's entries with a specific filepart (e.g., pleadings, page 2)
3. Hover over the File dropdown — the recent file link should point to that filepart/page
4. Click the link — should land on the correct filepart and page
