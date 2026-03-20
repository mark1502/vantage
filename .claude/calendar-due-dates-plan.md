# Calendar Due Dates Rework Plan

## Goal
Rework how calendar events and due dates are displayed on the calendar. Calendar events (folder_id=6) should use `date1`/`date2` directly. Due dates (entries with `expecting_response=true`) should appear in the all-day section filtered by from/to contact. Eliminate reliance on `on_calendar` flag.

---

## Phase 1: Backend — Rework `get_events()` in CalendarController

### 1A. Update query parameters
The frontend will now send these params:
- `user1` — contact_id of selected firm member (or `1` for all)
- `include_due` — `true`/`false`
- `due_to` — `true`/`false` (show entries where selected member is the sender awaiting response)
- `due_from` — `true`/`false` (show entries addressed to selected member awaiting response)
- `start`, `end` — date range (already exists)

### 1B. Rewrite the query logic
Replace the current broken `orWhere` query with properly grouped conditions:

```php
$events = Entry::query()->where('firm_id', $firm_id);

// Calendar events (folder_id = 6)
$events->where(function ($q) use ($request) {
    // Always include calendar events
    $q->where(function ($q2) use ($request) {
        $q2->where('folder_id', 6)
           ->whereBetween('date1', [$request->start, $request->end]);

        if ($request->user1 != '1') {
            $q2->where('to_contact_id', $request->user1);
        }
    });

    // Conditionally include due dates
    if ($request->include_due == 'true') {
        $q->orWhere(function ($q2) use ($request) {
            $q2->where('expecting_response', true)
               ->whereBetween('date_response_expected', [$request->start, $request->end]);

            // Firm member filtering for due dates
            if ($request->user1 != '1') {
                $q2->where(function ($q3) use ($request) {
                    if ($request->due_to == 'true' && $request->due_from == 'true') {
                        // "Due To" = from_contact_id matches (I sent it, waiting for reply)
                        // "Due From" = to_contact_id matches (sent to me, I haven't replied)
                        $q3->where('from_contact_id', $request->user1)
                           ->orWhere('to_contact_id', $request->user1);
                    } elseif ($request->due_to == 'true') {
                        $q3->where('from_contact_id', $request->user1);
                    } elseif ($request->due_from == 'true') {
                        $q3->where('to_contact_id', $request->user1);
                    }
                });
            }
            // When user1 = '1' (all), no contact filter — show all due dates
        });
    }
});
```

### 1C. Update the event array building
Change how events are mapped for FullCalendar response:

- **Calendar events (folder_id=6):** `start` = `date1`, `end` = `date2`, `allDay` from entry
- **Due dates:** `start` = `date_response_expected`, `end` = null, `allDay` = **always true**
- Add `is_due_date` to extendedProps so frontend can distinguish them
- Due date title format: `"Due: (initials) EntryType - note"` or similar

### 1D. Add due date color preferences
In `index()`, retrieve two new preference types: `due_bg` and `due_text`.
Pass them to the frontend as `due_bg_colors` and `due_text_colors`.
In `get_events()`, use these colors for due date entries instead of event colors.
Fallback colors: a muted tone distinct from event default (e.g., `#e0e0e0` bg, `#333333` text).

---

## Phase 2: Frontend — Update Calendar/Index.vue

### 2A. Add due date filter checkboxes to header
When "Include Due Dates" is checked AND a specific firm member is selected (not ****), show two stacked checkboxes (matching Views/Index pattern):

```
[x] Include Due Dates   [x] Due To
                         [x] Due From
```

- Both default to checked
- At least one must always be on — unchecking the last one toggles the other on (same logic as `clicked_from_to` in Views/Index)
- When **** is selected, hide the Due To/Due From checkboxes (all are shown)
- When "Include Due Dates" is unchecked, hide the Due To/Due From checkboxes

### 2B. Update `refresh_calendar()` and event URL
Add the new params to the events URL:
```js
'/get_events?user1=' + calendarFor.id
  + '&include_due=' + state.includeDueDates
  + '&due_to=' + state.dueTo
  + '&due_from=' + state.dueFrom
```

### 2C. Add state variables
```js
const state = reactive({
    includeDueDates: false,
    dueTo: true,
    dueFrom: true,
    weekdaysOnly: false,
});
```

### 2D. Update tooltip display
Use `extendedProps.is_due_date` to adjust tooltip:
- Due dates: show date, entry type, note, file name (no timespan)
- Events: keep existing tooltip format

---

## Phase 3: Due Date Color Preferences

### 3A. Add preference controls
In the Preferences page (or calendar settings), add color pickers for:
- `due_bg` — background color for due date entries on calendar
- `due_text` — text color for due date entries on calendar

### 3B. Update CalendarController `index()`
Query and pass `due_bg` and `due_text` preferences alongside existing event color preferences.

### 3C. Color fallback chain in `get_events()`
When building the events array for due date entries (`folder_id != 6`):
1. Use `due_bg`/`due_text` preference if set
2. Fall back to `event_bg`/`event_text` preference if set
3. Fall back to defaults (`#fff68f` bg, `#000000` text)

---

## Phase 4: Clean Up `on_calendar` Usage

### 4A. Remove `on_calendar` from query
The `get_events()` query will no longer filter on `on_calendar`. Instead:
- Calendar events identified by `folder_id = 6`
- Due dates identified by `expecting_response = true`

### 4B. Remove `on_calendar` toggles from response handling
In **EntryController** `handleThisResponse()`:
- Lines ~787, 800: Remove `$related_entry->on_calendar = true;` and `$related_entry->all_day = true;`
- Lines ~822-824: Remove the `on_calendar = false` / `all_day = false` block

In **ViewController** (similar response handling):
- Lines ~508, 521: Remove `on_calendar = true`
- Lines ~544: Remove `on_calendar = false`

### 4C. Simplify entry creation
In **EntryController** `store()` and `update()`:
- For folder_id=6: keep `on_calendar = true` (harmless, maintains data consistency)
- For other folders: stop setting `on_calendar` explicitly (default is false, which is fine)

In **ViewController** (if it has similar store/update logic):
- Same cleanup

### 4D. Keep the column for now
Do NOT drop the `on_calendar` column yet. It can be removed in a future migration once we're confident the new approach works.

---

## Phase 5: Database Index Update (Migration)

### 5A. Create migration to update indexes
- **Drop** index `[firm_id, on_calendar, date_response_expected]` — no longer used by the reworked query
- **Drop** index `[firm_id, folder_id, date2]` — not used by any query in the codebase
- **Add** index `[firm_id, folder_id, date1]` — supports the new calendar event query
- The existing index `[firm_id, expecting_response, date_response_expected]` already supports due date queries

---

## Execution Order

1. **Phase 1B + 1C** — Fix the query and event mapping (fixes the existing `orWhere` bug too)
2. **Phase 2A + 2B + 2C** — Add frontend filter controls and pass new params
3. **Phase 4A + 4B + 4C** — Remove `on_calendar` dependency
4. **Phase 1D + 3A-3C** — Add due date color preferences
5. **Phase 2D** — Update tooltip for due dates
6. **Phase 5** — Index migration

---

## Key Clarifications

- **"Due To"** = entries where `from_contact_id` = selected member (they sent it, awaiting reply back TO them)
- **"Due From"** = entries where `to_contact_id` = selected member (it was sent to them, response is due FROM them)
- **"All (****)"** always shows both from and to, no sub-filter needed
- A due date is "still due" until `expecting_response` is set to `false` (happens when a full response is received)
- Due dates always appear as **all-day events** on the `date_response_expected` date
- `date_response_expected` stays in the entries table — it's the due-by date for response tracking
- `on_calendar` column kept but no longer relied upon in queries
