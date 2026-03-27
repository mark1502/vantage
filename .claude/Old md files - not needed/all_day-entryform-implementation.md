# All-Day Event Checkbox — EntryForm Implementation Plan

## Goal
Allow users to designate an event as "all day" when creating or editing an event entry from the EntryForm (used on File and View pages), matching the capability that already exists when adding events directly from the Calendar.

## Current State

### What works
- The `entries.all_day` boolean column exists in the database (default: `false`)
- CalendarController reads/writes `all_day` correctly
- Calendar/Index.vue passes `allDay` to the server and uses it for date formatting
- FullCalendar respects `allDay` when rendering events
- `reformat_date()` in EntryForm.vue already accepts an `all_day` parameter and displays "(all day)" — but it's never called with `true` from EntryForm context

### What's missing
- `entry_form` in EntryForm.vue has no `all_day` field
- No UI checkbox to toggle all-day in EntryForm
- `update_disp()` doesn't load `all_day` from existing entries
- `setup_add()` doesn't initialize `all_day`
- `isTimePicker()` doesn't account for `all_day`
- EntryController store/update hardcode `all_day = false` (or skip it) for folder_id === 6
- ViewController store/update don't set `all_day` for folder_id === 6
- StoreEntryRequest and StoreViewRequest don't validate the `all_day` field

---

## Implementation Steps

### Step 1: Add `all_day` to `entry_form` (EntryForm.vue)

**File:** `resources/js/Pages/Entries/EntryForm.vue` (~line 45-78)

Add `all_day: false` to the `entry_form` useForm definition, after the `date2` field:
```javascript
date2: null,
all_day: false,    // <-- add this
note: "",
```

### Step 2: Load `all_day` in `update_disp()` (EntryForm.vue)

**File:** `resources/js/Pages/Entries/EntryForm.vue` (~line 596-601)

After loading `date2` from `theEntry`, add:
```javascript
entry_form.date2 = theEntry.date2;                      // date2
entry_form.all_day = theEntry.all_day ?? false;          // all_day  <-- add this
```

This ensures that when displaying/editing an existing event entry, the all_day state is loaded from the database.

### Step 3: Initialize `all_day` in `setup_add()` (EntryForm.vue)

**File:** `resources/js/Pages/Entries/EntryForm.vue` (~line 827)

In the `setup_add()` function, after setting `input_time` and `hide_date2_prompt`, add:
```javascript
entry_form.input_time = props.getFolderData('input_time');
entry_form.hide_date2_prompt = props.getFolderData('hide_date2_prompt');
entry_form.all_day = false;                              // <-- add this
```

### Step 4: Update `isTimePicker()` to respect `all_day` (EntryForm.vue)

**File:** `resources/js/Pages/Entries/EntryForm.vue` (~line 214-225)

The time picker should be disabled when `all_day` is checked, regardless of the folder's `input_time` setting:
```javascript
function isTimePicker() {
    if (entry_form.all_day === true) return false;       // <-- add this line at the top

    let sendback = null;
    // ... rest of existing logic unchanged
}
```

### Step 5: Add the "All Day" checkbox to the template (EntryForm.vue)

**File:** `resources/js/Pages/Entries/EntryForm.vue` (~line 957-964)

In the Dates row, after the date1 `</div>` and before the Read/Unread conditional block, add a new conditional block for the All Day checkbox. It should appear only for event entries (folder_id === 6):

```html
<!-- All Day checkbox (for events only) -->
<div v-if="entry_form.folder_id === 6" class="flex items-center ml-6">
    <input v-model="entry_form.all_day" type="checkbox" id="all_day_checkbox"
        :value="true" @change="checkEditMode()">
    <span class="text-sm ml-1">- All Day</span>
</div>
```

**Placement:** This should go between the Date 1 `</div>` (line ~957) and the Read/Unread `<div v-if="entry_form.folder_id === 5 || ...">` block (line ~959). The structure will be:

```
Date 1 block
All Day checkbox (v-if folder_id === 6)
Read/Unread checkbox (v-if folder_id === 5 || folder_id === 8)
Date 2 block (v-else-if)
```

**Note on mutual exclusivity:** The All Day checkbox (folder_id === 6) and Read/Unread checkbox (folder_id === 5 or 8) and Date 2 (everything else) are naturally mutually exclusive because an entry can only belong to one folder. However, events DO use date2 (end date/time). When all_day is checked, date2 should still be available but also without a time picker. Verify whether `hide_date2_prompt` is true for events folder (folder_id 6). If it IS hidden for events, then no change needed for date2. If it is NOT hidden, the date2 datepicker also needs its time picker disabled when `all_day` is true.

**Important consideration about the v-if/v-else-if chain:** Currently the template uses:
- `v-if` for Read/Unread (folder 5 or 8)
- `v-else-if` for Date 2

Since events (folder 6) don't match folder 5 or 8, they currently fall through to the Date 2 `v-else-if`. Adding the All Day checkbox as a separate `v-if` (not part of this chain) alongside the existing chain is cleanest — it can coexist with Date 2 since events may need both.

### Step 6: Add `all_day` validation to StoreEntryRequest

**File:** `app/Http/Requests/StoreEntryRequest.php` (~line 23-46)

Add to the rules array:
```php
'all_day' => 'boolean|nullable',
```

### Step 7: Add `all_day` validation to StoreViewRequest

**File:** `app/Http/Requests/StoreViewRequest.php` (~line 23-57)

Add to the rules array:
```php
'all_day' => 'boolean|nullable',
```

### Step 8: Update EntryController to use `all_day` from request

**File:** `app/Http/Controllers/EntryController.php`

**store() method** (~line 146-161): In the `if ($entry->folder_id === 6)` block, add:
```php
$entry->all_day = $request->boolean('all_day');
```

The existing `$entry->all_day = false;` in the else block (non-event entries) should remain.

**update() method**: Find the equivalent `if ($entry->folder_id === 6)` block and add the same line. Keep the `$entry->all_day = false;` in the else block.

### Step 9: Update ViewController to use `all_day` from request

**File:** `app/Http/Controllers/ViewController.php`

**store() method** (~line 106-122): In the `if ($entry->folder_id === 6)` block, add:
```php
$entry->all_day = $request->boolean('all_day');
```

**update() method** (~line 178-194): Same change in the `if ($entry->folder_id === 6)` block.

Keep `$entry->all_day = false;` in all else blocks.

---

## Date Format Consideration

**Important:** The `date1` validation in both StoreEntryRequest and StoreViewRequest currently requires `date_format:Y-m-d H:i:s`. The VueDatePicker with `model-type="'yyyy-MM-dd HH:mm:ss'"` will always emit a full datetime string even in date-only mode (with time as `00:00:00`). This means **no change is needed to the date validation rules** — the format will always include the time component regardless of whether the time picker is shown.

This is different from CalendarController, which validates `date_format:Y-m-d` when `allDay` is true — because FullCalendar itself strips the time component for all-day events. The EntryForm's VueDatePicker does not strip time, so the existing datetime validation works as-is.

---

## Edge Cases & Notes

1. **Toggling all_day mid-edit:** If a user checks "All Day" after picking a time, the time portion (`HH:mm:ss`) stays in the model but becomes irrelevant. The time picker disappears. If they uncheck it, the time picker reappears and they can pick a new time. No data loss scenario.

2. **Existing all-day events created from Calendar:** These have `all_day = true` in the database. When loaded in EntryForm via `update_disp()`, the checkbox will correctly show as checked and the time picker will be hidden. If the user edits and saves without changing the checkbox, `all_day` remains `true`.

3. **date2 for events:** Need to verify whether `hide_date2_prompt` is set for the events folder. If date2 IS shown for events, its time picker also needs to respect `all_day`. The `isTimePicker()` function is already used for both date1 and date2 datepickers, so the fix in Step 4 covers both.

4. **Calendar sync:** Events created as all-day from EntryForm will appear correctly on the calendar because CalendarController's `get_events()` already reads `$event->all_day` from the database and passes it as `allDay` to FullCalendar.

5. **saved_entry_form clone:** The clone at `saved_entry_form = { ...entry_form }` (line ~830 in `setup_add()`) will automatically include `all_day` once it's added to the form — no additional change needed for dirty-checking.

---

## Files Modified (Summary)

| File | Change |
|------|--------|
| `resources/js/Pages/Entries/EntryForm.vue` | Add `all_day` to form, checkbox UI, update `isTimePicker()`, `update_disp()`, `setup_add()` |
| `app/Http/Requests/StoreEntryRequest.php` | Add `all_day` validation rule |
| `app/Http/Requests/StoreViewRequest.php` | Add `all_day` validation rule |
| `app/Http/Controllers/EntryController.php` | Read `all_day` from request in store/update for folder_id 6 |
| `app/Http/Controllers/ViewController.php` | Read `all_day` from request in store/update for folder_id 6 |

**No migration needed** — the `all_day` column already exists.
