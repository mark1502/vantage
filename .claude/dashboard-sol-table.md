# Plan: Dashboard Summary — Statute of Limitations Table

## Goal

Add a "Summary: Statute of Limitations" table to `resources/js/Pages/Dashboard.vue`, below the existing sections card, showing firm-wide and (for attorneys) user-specific SOL counts across three buckets.

## Phases

- **Phase 1** — Build the table and counts (display-only). Single, cohesive change; no need to subdivide further.
- **Phase 2** — Make non-zero cells clickable, drilling into a filtered list of the underlying files.

Phase 1 must be merged/working before starting Phase 2. Phase 2 adds navigation only — it must not alter the counts or table shape produced in Phase 1.

---

# Phase 1 — Basic SOL Summary Table

## Table Layout

| | Within Next 90 Days | Expired (unfiled or late) | Unspecified S.O.L. Date |
|---|---|---|---|
| **Your files** *(attorneys only)* | `N (M unfiled)` | `N` | `N` |
| **All Files** | `N (M unfiled)` | `N` | `N` |

- Column 1 cells display in the format **`5 (3 unfiled)`** — total count of files with `date_sol` between today and today+90, followed by the subset of those that have `date_filed IS NULL`.
- "Your files" row renders only when `user->contact->firm_role === 'Attorney'`.
- Display-only for now. (Future enhancement: make non-zero cells clickable to drill into filtered file lists — out of scope for this plan.)

## Column Definitions (firm-scoped on `files.firm_id = $user->firm_id`)

1. **Within Next 90 Days**
   - Total: `date_sol >= today() AND date_sol <= today()->addDays(90)`
   - Unfiled subcount: same condition AND `date_filed IS NULL`
2. **Expired (unfiled or late)**
   - `date_sol < today() AND (date_filed IS NULL OR date_filed > date_sol)`
3. **Unspecified S.O.L. Date**
   - `date_sol IS NULL` AND the file's `filetype.enable_file_SOL = true`
   - Implemented via `whereHas('filetype', fn ($q) => $q->where('enable_file_SOL', true))`

For the **"Your files"** row, each of the above is additionally constrained to files where the assigned attorney's `ContactRole` matches the current user's contact: `whereHas('assignedAttorney', fn ($q) => $q->where('contact_id', $user_contact_id))`.

Note: `enable_file_SOL` is stored with that exact casing in the DB (verified). MySQL is case-insensitive on column names, but use the exact name in code for clarity.

## Backend Changes

### `app/Http/Controllers/DashboardController.php`

After existing message computations, add SOL summary logic and pass it as a single `sol_summary` prop. Sketch:

```php
use App\Models\File;
use Carbon\Carbon;

$today = today();
$in90  = today()->copy()->addDays(90);
$firmId = $user->firm_id;

$isAttorney = optional($user_contact)->firm_role === 'Attorney';

$buildCounts = function (?int $attorneyContactId) use ($firmId, $today, $in90): array {
    $base = File::query()->where('firm_id', $firmId);

    if ($attorneyContactId !== null) {
        $base->whereHas('assignedAttorney', fn ($q) =>
            $q->where('contact_id', $attorneyContactId)
        );
    }

    $next90Total = (clone $base)
        ->whereBetween('date_sol', [$today, $in90])
        ->count();

    $next90Unfiled = (clone $base)
        ->whereBetween('date_sol', [$today, $in90])
        ->whereNull('date_filed')
        ->count();

    $expired = (clone $base)
        ->whereNotNull('date_sol')
        ->where('date_sol', '<', $today)
        ->where(fn ($q) => $q->whereNull('date_filed')
            ->orWhereColumn('date_filed', '>', 'date_sol'))
        ->count();

    $unspecified = (clone $base)
        ->whereNull('date_sol')
        ->whereHas('filetype', fn ($q) => $q->where('enable_file_SOL', true))
        ->count();

    return [
        'next90_total'   => $next90Total,
        'next90_unfiled' => $next90Unfiled,
        'expired'        => $expired,
        'unspecified'    => $unspecified,
    ];
};

$solSummary = [
    'is_attorney' => $isAttorney,
    'your_files'  => $isAttorney ? $buildCounts($user_contact_id) : null,
    'all_files'   => $buildCounts(null),
];
```

Add `'sol_summary' => $solSummary` to the `Inertia::render('Dashboard', [...])` payload.

### `app/Models/File.php`

No model changes required. Existing `assignedAttorney()` and `filetype()` relationships are sufficient.

## Frontend Changes

### `resources/js/Pages/Dashboard.vue`

1. Add `sol_summary` to `defineProps` (Object, default `null`).
2. Below the existing `<div class="overflow-hidden bg-base-100 shadow-sm sm:rounded-lg">` block (still inside `max-w-7xl`), add a second card containing the SOL table.

Template sketch:

```vue
<div v-if="sol_summary" class="mt-6 overflow-hidden bg-base-100 shadow-sm sm:rounded-lg">
    <div class="px-6 py-5">
        <h3 class="text-lg font-semibold text-base-content">
            Summary: Statute of Limitations
        </h3>
        <table class="table table-zebra mt-4 w-full">
            <thead>
                <tr>
                    <th></th>
                    <th class="text-center">Within Next 90 Days</th>
                    <th class="text-center">Expired (unfiled or late)</th>
                    <th class="text-center">Unspecified S.O.L. Date</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="sol_summary.is_attorney">
                    <th>Your files</th>
                    <td class="text-center">
                        {{ sol_summary.your_files.next90_total }}
                        ({{ sol_summary.your_files.next90_unfiled }} unfiled)
                    </td>
                    <td class="text-center">{{ sol_summary.your_files.expired }}</td>
                    <td class="text-center">{{ sol_summary.your_files.unspecified }}</td>
                </tr>
                <tr>
                    <th>All Files</th>
                    <td class="text-center">
                        {{ sol_summary.all_files.next90_total }}
                        ({{ sol_summary.all_files.next90_unfiled }} unfiled)
                    </td>
                    <td class="text-center">{{ sol_summary.all_files.expired }}</td>
                    <td class="text-center">{{ sol_summary.all_files.unspecified }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

Uses existing DaisyUI table classes for consistency with the rest of the app.

## Edge Cases / Notes

- User has no `contact` record → `$user_contact_id` is null → `is_attorney` is false → only "All Files" row shows. Correct.
- User is firm member but role is not 'Attorney' (Paralegal/Clerical) → "Your files" row hidden. Correct per spec.
- "Within Next 90 Days" uses `whereBetween` which is inclusive of both endpoints (today and today+90).
- "Expired" intentionally excludes rows where `date_sol IS NULL` (those go to column 3 if filetype is SOL-enabled).
- "Unspecified" only counts files whose `filetype.enable_file_SOL` is true — files with SOL-disabled filetypes (e.g. transactional matters) are not flagged as missing.
- Performance: four `COUNT` queries × up to two scopes = max 8 queries. All hit the `files_firm_id_date_sol_index` composite index. Acceptable for a dashboard load.

## Files Modified

- `app/Http/Controllers/DashboardController.php` — add SOL summary computation and prop
- `resources/js/Pages/Dashboard.vue` — add `sol_summary` prop and table markup

## Phase 1 Out of Scope

- Clickable cells (handled in Phase 2 below)
- Deferred-prop loading with skeleton (only if load time becomes an issue)
- Additional buckets (e.g., "Filed late" as its own column)

---

# Phase 2 — Clickable Cells / Drill-down

## Goal

Each non-zero cell becomes a link that opens a filtered list of exactly the files counted in that cell. Zero-count cells render as plain text (no link).

## Approach

Reuse the existing **Files index** (`route('files.index')` → `app/Http/Controllers/FileController.php@index`) by adding SOL-related query parameters. Counts on the dashboard and the rows shown on the files index must use the **same query logic** — extract the filter logic into a shared place to keep them in sync.

### Filter parameters (passed via query string)

| Param | Values | Meaning |
|---|---|---|
| `sol_filter` | `next90` \| `next90_unfiled` \| `expired` \| `unspecified` | Which bucket to apply |
| `scope` | `mine` \| `all` | Restrict to current user's assigned-attorney files (`mine`) or firm-wide (`all`) |

Example links generated from the dashboard:
- "Your files / Within Next 90 Days" total → `/files?sol_filter=next90&scope=mine`
- "Your files / Within Next 90 Days" unfiled subcount → `/files?sol_filter=next90_unfiled&scope=mine`
- "All Files / Expired" → `/files?sol_filter=expired&scope=all`
- "All Files / Unspecified" → `/files?sol_filter=unspecified&scope=all`

## Backend Changes

### New: `app/Support/SolFileFilter.php` (or a query scope on `File`)

Extract the four bucket conditions into a single helper so `DashboardController` (counts) and `FileController@index` (listing) cannot drift apart. Options:

1. **Static helper class** with `apply(Builder $query, string $filter, ?int $attorneyContactId): Builder`.
2. **Local query scopes** on `File` (`scopeSolNext90`, `scopeSolNext90Unfiled`, `scopeSolExpired`, `scopeSolUnspecified`, `scopeAssignedTo`).

Recommend **option 2** (scopes) — idiomatic Laravel, easy to chain, no new namespace. Sketch:

```php
// app/Models/File.php
public function scopeSolNext90($q) {
    return $q->whereBetween('date_sol', [today(), today()->copy()->addDays(90)]);
}
public function scopeSolNext90Unfiled($q) {
    return $q->solNext90()->whereNull('date_filed');
}
public function scopeSolExpired($q) {
    return $q->whereNotNull('date_sol')
             ->where('date_sol', '<', today())
             ->where(fn ($w) => $w->whereNull('date_filed')
                                  ->orWhereColumn('date_filed', '>', 'date_sol'));
}
public function scopeSolUnspecified($q) {
    return $q->whereNull('date_sol')
             ->whereHas('filetype', fn ($f) => $f->where('enable_file_SOL', true));
}
public function scopeAssignedTo($q, int $contactId) {
    return $q->whereHas('assignedAttorney', fn ($a) => $a->where('contact_id', $contactId));
}
```

**Refactor Phase 1** to use these scopes in `DashboardController` so counts and list stay in sync.

### `app/Http/Controllers/FileController.php@index`

Read `sol_filter` and `scope` from the request and apply the matching scope(s):

```php
$query = File::query()->where('firm_id', $request->user()->firm_id);

if ($request->scope === 'mine' && $request->user()->contact) {
    $query->assignedTo($request->user()->contact->id);
}

match ($request->sol_filter) {
    'next90'         => $query->solNext90(),
    'next90_unfiled' => $query->solNext90Unfiled(),
    'expired'        => $query->solExpired(),
    'unspecified'    => $query->solUnspecified(),
    default          => null,
};
```

Also pass back the active filter labels so the files index can show a "Filtered by: SOL within next 90 days (Your files) — Clear filter" banner. This avoids the user wondering why they're seeing a subset.

## Frontend Changes

### `resources/js/Pages/Dashboard.vue`

Replace each cell value with a conditional `<Link>` (Inertia) when count > 0, plain text when 0. Helper computed or small render function to keep the template tidy:

```vue
<script setup>
import { Link } from '@inertiajs/vue3';

function fileLink(scope, filter) {
    return route('files.index', { sol_filter: filter, scope });
}
</script>

<!-- Within Next 90 Days cell -->
<td class="text-center">
    <template v-if="row.next90_total > 0">
        <Link :href="fileLink(scopeKey, 'next90')" class="link link-primary">
            {{ row.next90_total }}
        </Link>
        (<Link
            v-if="row.next90_unfiled > 0"
            :href="fileLink(scopeKey, 'next90_unfiled')"
            class="link link-primary"
        >{{ row.next90_unfiled }}</Link>
        <span v-else>{{ row.next90_unfiled }}</span> unfiled)
    </template>
    <span v-else>0 (0 unfiled)</span>
</td>
```

Apply the same pattern to the Expired and Unspecified cells (single link wrapping the number).

### `resources/js/Pages/Files/Index.vue` (or wherever the files list renders)

- Show a small "Filter active: SOL — Within Next 90 Days (Your files)" pill near the top when `sol_filter` is set, with a "Clear" link back to the unfiltered files index.
- No other changes — existing table/list rendering stays the same; the controller just narrows the result set.

## Edge Cases / Notes

- Zero counts must not render as links (avoid empty result pages).
- `scope=mine` with a user lacking a contact record: treat as `scope=all` or block — recommend silently ignoring `mine` and falling through to firm-wide (matches Phase 1 behavior where the "Your files" row is hidden for non-attorneys).
- Bookmarked filtered URLs should keep working; treat invalid `sol_filter` values as no-op (the `default => null` arm).
- Keep parameter names short and stable — they'll appear in shared URLs.

## Files Modified (Phase 2)

- `app/Models/File.php` — add SOL query scopes
- `app/Http/Controllers/DashboardController.php` — refactor counts to use the new scopes
- `app/Http/Controllers/FileController.php` — read `sol_filter` / `scope` from request and apply scopes; pass active-filter info to the view
- `resources/js/Pages/Dashboard.vue` — wrap non-zero cell values in `<Link>`
- `resources/js/Pages/Files/Index.vue` — display active-filter pill with clear action

## Phase 2 Out of Scope

- Persisting/saving SOL filter as a user preference
- Combining SOL filter with other files-index filters (search, filetype, etc.) — should "just work" if existing filters compose, but explicit testing is a follow-up
- Sort order changes (default sort on the files index applies)
