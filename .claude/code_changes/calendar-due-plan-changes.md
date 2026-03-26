# Calendar Due Dates Rework — Code Changes

Implemented from `calendar-due-dates-plan.md` on 2026-03-20.

---

## Backend: CalendarController

### `index()`
- Added queries for `due_bg` and `due_text` preference types (per firm member)
- Passes `due_bg_colors` and `due_text_colors` to the frontend alongside existing event color props

### `get_events()`
Fully rewritten:
- **Color loading**: Single query fetches all four preference types (`event_bg`, `event_text`, `due_bg`, `due_text`) at once
- **Query**: Properly grouped `orWhere` — calendar events (`folder_id=6`, filtered by `date1` range) and due dates (`expecting_response=true`, filtered by `date_response_expected` range) are separate subqueries within an outer `where`
- **Calendar event filter**: Uses `to_contact_id` for firm member filtering; date range on `date1`
- **Due date filter**: Respects `due_to` (from_contact_id = user1) and `due_from` (to_contact_id = user1) params; when `user1=1` (all), no contact filter
- **Eager loading**: Added `contact_from` alongside existing `contact_to`, `file`, `entrytype`
- **Calendar events response**: `start` = `date1`, `end` = `date2`, `allDay` = `$event->all_day`, `editable: true`
- **Due date response**: `start` = `date_response_expected`, `end` = null, `allDay` = true, `editable: false`
- **Color for due dates**: Color owner contact depends on filter direction:
  - `due_to` only → `contact_from->user_id` (sender awaiting reply)
  - `due_from` only → `contact_to->user_id` (recipient whose reply is due)
  - Both / user1=1 → defaults to `contact_from->user_id`
  - Preference chain: `due_bg`/`due_text` → fallback to `event_bg`/`event_text` → fallback to `#e0e0e0`/`#333333`
- **`is_due_date`** added to `extendedProps` on all events (true/false)
- **Due date title**: `"Due: (initials) EntryType - note"` using `contact_from->member_initials`
- **Default fallback colors**: Calendar events `#fff68f`/`#000000`, due dates `#e0e0e0`/`#333333`

### `store()`
- Removed `date_response_expected = date1` assignment — calendar events (folder_id=6) no longer use `date_response_expected`

### `event_placement()`
- Removed `date_response_expected = date1` update — calendar events use `date1` only

---

## Backend: EntryController + ViewController

### `handleThisResponse()` in both files
- Removed all `on_calendar = true/false` and `all_day = true/false` assignments
- Only `expecting_response` is toggled — calendar now identifies due dates by this flag, not `on_calendar`

---

## Frontend: Calendar/Index.vue

### State
- Added `dueTo: true` and `dueFrom: true` to `state` reactive object

### Props
- Added `due_bg_colors` and `due_text_colors` props

### Initial events URL
- Updated to include `&due_to=` and `&due_from=` params

### `refresh_calendar()`
- Updated URL to include `&due_to=` and `&due_from=` params

### `clicked_due_to_from(which)`
- New function: ensures at least one of `dueTo`/`dueFrom` is always checked (mirrors Views/Index pattern), then calls `refresh_calendar()`

### `click_event()`
- Returns early (no-op) when `extendedProps.is_due_date` is true — due dates are not editable

### Tooltip
- Added `heading` field to `tooltip` reactive object
- `eventMouseEnter`: Sets `tooltip.heading` to `'Event Information:'` for calendar events, `'Due Date:'` for due dates
- Uses `extendedProps.is_due_date` instead of title character check to determine event type
- `eventMouseLeave`: Clears `tooltip.heading`
- Both tooltip instances (upper_right and near_cursor) updated to render `{{ tooltip.heading }}`

### Header controls
- "Due To" / "Due From" checkboxes appear when `state.includeDueDates && calendarFor.id != 1`
- Both default to checked; at-least-one-must-be-checked enforced by `clicked_due_to_from()`
- Hidden when viewing all members (`****`) or when "Include Due Dates" is unchecked

---

## Migration: `2026_03_20_231009_update_entries_calendar_indexes`

- **Dropped** `[firm_id, on_calendar, date_response_expected]` — no longer used
- **Dropped** `[firm_id, folder_id, date2]` — not used by any query
- **Added** `[firm_id, folder_id, date1]` — supports new calendar event query (`folder_id=6` + `date1` range)
- Existing `[firm_id, expecting_response, date_response_expected]` index retained — supports due date query

---

## Notes
- `on_calendar` column retained in DB (not dropped) for safety — can be removed in a future migration
- Due dates are non-draggable/non-resizable via `editable: false` on those FullCalendar events
- `due_bg`/`due_text` preference records do not exist yet in DB — Preferences UI additions are a future task
