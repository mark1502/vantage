# Calendar Event Hover Tooltip — Placement Preference

## Overview
Added a user preference to control where the event hover tooltip displays on the calendar page. Two options: "Upper Right Corner" (fixed viewport position) or "Near Cursor" (follows mouse). Defaults to "Upper Right Corner".

## Seeder changes
- `Pref_defaultSeeder.php` — Added `event_hover_placement` default (value: `upper_right`, prompt: "Event Hover Display Placement:")

## Controller changes
- `CalendarController.php` — Queries the logged-in user's `event_hover_placement` preference and passes it as a `hover_placement` prop to the Calendar page (defaults to `upper_right` if no preference exists)
- `PreferenceController.php` — Added `hover_placement_update()` method to validate and save the preference (accepts `upper_right` or `near_cursor`)

## Route changes
- `web.php` — Added `POST /preferences/hover_placement` route pointing to `PreferenceController::hover_placement_update`

## Frontend changes
- `Calendar/Index.vue`
  - Added `hover_placement` prop (default: `upper_right`)
  - Moved tooltip out of the header into a `<Teleport to="body">` with `position: fixed` so it never scrolls off screen
  - Added `mousePos` reactive tracking and `onMouseMove` listener (attached only during hover when `near_cursor` mode is active)
  - `upper_right` mode: tooltip fixed at top-right of viewport (`top: 80px, right: 40px`)
  - `near_cursor` mode: tooltip follows cursor with 16px offset
  - Added cleanup of `mousemove` listener in `onUnmounted`
- `Preferences/Index.vue`
  - Added `event_hover_placement` to `user_prefs` reactive state
  - Added dropdown select with "Upper Right Corner" and "Near Cursor" options
  - Added `saveHoverPlacement()` function that POSTs to `/preferences/hover_placement`

## Setup for existing databases
Insert the pref_default row manually, then visit any user's Preferences page to auto-create their user preference:
```sql
INSERT INTO pref_defaults (name, prompt, setting, created_at, updated_at)
VALUES ('event_hover_placement', 'Event Hover Display Placement:', 'upper_right', NOW(), NOW());
```
