# Closed File Filter Plan

## Overview
Add a dropdown filter to the Files Index page that allows users to filter files by their open/closed status based on the `date_closed` column. Also relocate the subscription file limit indicator.

## Filter Logic
- **Open Files**: `date_closed IS NULL` (default)
- **Closed Files**: `date_closed IS NOT NULL`
- **All Files**: No filter on `date_closed`

## Changes Required

### 1. FileController — `index()` method
**File**: `app/Http/Controllers/FileController.php`

- Accept a new `status` query parameter (values: `open`, `closed`, `all`; default: `open`)
- Add a `->when()` clause to the query:
  ```php
  ->when($status !== 'all', function ($query) use ($status) {
      if ($status === 'open') {
          $query->whereNull('date_closed');
      } else {
          $query->whereNotNull('date_closed');
      }
  })
  ```
- Pass the current `status` value to the Inertia render so the frontend knows the active filter

### 2. Files/Index.vue — Add Status Dropdown
**File**: `resources/js/Pages/Files/Index.vue`

- Add `status` to props (string, default `'open'`)
- Add a reactive `state.status` initialized from the prop
- Place a new `<select>` dropdown **next to** the existing "Show" dropdown (no label) in the paginator line (line ~229–243), with options:
  - `open` — "Open Files"
  - `closed` — "Closed Files"
  - `all` — "All Files"
- Style it consistently with the existing "Show" select (same DaisyUI classes: `select select-bordered select-sm`)
- **Disable the Add button** when `state.status === 'closed'` (users shouldn't add a file directly into the closed state). Use the same disabled button markup already used for the `atFileLimit` case.
- Add a `statusChanged()` function that navigates with the new status parameter:
  ```js
  function statusChanged() {
      router.get(route('files.index'), { page: 1, show: state.show, status: state.status });
  }
  ```
- Update `showChanged()` to also pass `state.status`
- Update the `search` watcher to also pass `status: state.status` in the router.get parameters

### 3. Files/Index.vue — Move File Limit Indicator
**File**: `resources/js/Pages/Files/Index.vue`

- **Remove** the file limit indicator from its current position inside the paginator line (lines ~246–258)
- **Add** it below the button row (`name="control_buttons"` div), centered, appearing just above or just below the Open button area
- Keep the same content and conditional (`v-if="subscription?.file_limit"`)
- Center it using `text-center` and add appropriate top margin (`mt-2`)

### 4. Preserve Filter State Across Navigation
- Ensure `status` is included in `withQueryString()` (already handled by Laravel's `withQueryString()` on the paginator)
- Ensure the `disp.createurl` includes the status parameter so returning from file creation preserves the filter
- Ensure the search watcher passes `status` so filtering is maintained during search

## Implementation Order
1. Update `FileController::index()` to accept and apply the `status` filter
2. Add the status dropdown to the Vue template next to the "Show" dropdown
3. Add the reactive state, prop, and navigation functions for the new dropdown
4. Move the file limit indicator below the button row
5. Verify all router.get calls pass the `status` parameter

## UI Layout (Paginator Line — After Changes)
```
[ Show: [10▾] ]  [Open Files▾]          [ < 1 2 3 > ]
```

## UI Layout (Below Buttons — File Limit Indicator)
```
[+ Add]     [🗁 Open]     [- Delete]
       5 of 10 files used (free plan)       <-- centered with the button row
```

## Add Button Disable Logic
The Add button should be disabled when **either** of these conditions is true:
- `atFileLimit` is true (existing behavior)
- `state.status === 'closed'` (new — can't add a file as closed)

Combine into a computed: `const addDisabled = computed(() => atFileLimit.value || state.status === 'closed');`
