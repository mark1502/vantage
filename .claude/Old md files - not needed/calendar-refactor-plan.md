# Calendar Refactor Plan

## Phase 1: Dead Code Cleanup (Low risk, high clarity)

### Controller
- [ ] Remove `move_event()` method (lines 254-290) — unused, `event_placement()` handles both move and resize
- [ ] Remove `resize_event()` method (lines 334-366) — same reason
- [ ] Remove empty stub methods: `create()`, `show()`, `edit()`, `update()`, `destroy()`
- [ ] Verify routes: confirm no routes point to the removed methods

### Vue Component
- [ ] Remove commented-out VueDatePicker imports (lines 15-16)
- [ ] Remove commented-out debounce import (line 6)
- [ ] Remove empty `keypress_handler` and its mount/unmount listeners (lines 486-563) — handler body is entirely commented out
- [ ] Remove the `v-if="false"` left sidebar panel (lines 661-693) — permanently hidden dead UI
- [ ] Remove commented-out Transition examples (lines 593-622)
- [ ] Remove unused CSS blocks for datepicker classes (lines 899-929, 957-964)
- [ ] Remove commented-out `debouncedLookup` function (lines 498-506)

---

## Phase 2: Fix `fileSpecific` Boolean Issue

- [ ] Change `calendar_form.fileSpecific` from string `"true"`/`"false"` to actual boolean `true`/`false`
- [ ] Update all comparisons: `== "true"` → `=== true`, `== "false"` → `=== false`, `!= "true"` → `!== true`
- [ ] Update radio button values in template from `value="true"` to `:value="true"` (bound)
- [ ] Update `hold_calendar_form` comparisons in `submit_test_2()`

---

## Phase 3: Extract Shared FileLookup Component

- [ ] Create `resources/js/Components/FileLookup.vue`
  - Props: `modelValue` (file name string), `fileId`
  - Emits: `update:modelValue`, `update:fileId`, `file-selected`
  - Encapsulates: the input field, axios lookup call, results dropdown, "not found" message
- [ ] Replace file lookup markup in `Calendar/Index.vue` (lines 772-799) with `<FileLookup>`
- [ ] Replace equivalent file lookup markup in `Entries/EntryForm.vue` with same component

---

## Phase 4: Extract Vue Child Components

### CalendarEventForm.vue (the main add/edit modal)
- [ ] Extract lines 707-816 into `resources/js/Components/Calendar/CalendarEventForm.vue`
- [ ] Props: `firmMembers`, `eventTypes`, `calendarForm`, `calErrors`, `relatedFile`, `title`
- [ ] Emits: `submit`, `cancel`, `delete`, `add-type`
- [ ] Include the FileLookup component inside it

### EventTooltip.vue
- [ ] Extract the tooltip Transition block (lines 623-650) into `resources/js/Components/Calendar/EventTooltip.vue`
- [ ] Props: `visible`, `timespan`, `text`, `fileName`, `styles`

### ConfirmDialog.vue (reusable)
- [ ] Create `resources/js/Components/ConfirmDialog.vue`
- [ ] Props: `heading`, `statement`, `question`, `id`
- [ ] Emits: `confirm`, `cancel`
- [ ] Replace all 3 confirmation modals (confirm_event_for, confirm_file_change, confirm_delete_event)

### EntrytypeModal.vue
- [ ] Extract lines 868-894 into `resources/js/Components/Calendar/EntrytypeModal.vue`
- [ ] Move `entrytype_form`, `matching`, `lookup_entrytype()`, `clicked_matchingEntrytype()`, and `clicked_entrytypeModal_button()` into it

---

## Phase 5: Rename Functions for Clarity

- [ ] `submit_test_1()` → `confirmUserChange()`
- [ ] `submit_test_2()` → `confirmFileChange()`
- [ ] `click_date()` → `handleDateClick()`
- [ ] `click_event()` → `handleEventClick()`
- [ ] `event_placement()` → `handleEventPlacement()`
- [ ] `clear_calendarform()` → `resetForm()` (and use `useForm.reset()` internally)
- [ ] `display_modal()` → `toggleModal()` or switch to template refs

---

## Phase 6: Controller Refactor

### Extract Date Validation
- [ ] Create `App\Http\Requests\CalendarEventRequest` Form Request class
  - Handles both the main fields and the conditional date validation (allDay vs timed)
  - Replaces inline validation in `store()` and `event_placement()`

### Consolidate store() Logic
- [ ] Extract shared field assignment (file_id, entrytype_id, from_contact_id, note, dates, etc.) into a private method like `fillEventFromRequest(Entry $event, Request $request)`
- [ ] Simplify add/edit/delete branches to:
  ```php
  if ($action === 'add') {
      $event = new Entry;
      $event->firm_id = $request->user()->firm_id;
      $this->fillEventFromRequest($event, $request);
  } elseif ($action === 'edit') {
      $event = Entry::findOrFail($request->entry_id);
      $this->fillEventFromRequest($event, $request);
  } elseif ($action === 'delete') {
      Entry::findOrFail($request->entry_id)->delete();
  }
  ```

### Move `add_new_event_type()` Out
- [ ] Move to `EntrytypeController` (or create one if it doesn't exist)
- [ ] Update the route accordingly
- [ ] Update the frontend POST URL

### Replace Hard-coded folder_id
- [ ] Define a constant, e.g. `Entry::EVENTS_FOLDER_ID = 6` or a config value
- [ ] Replace all `folder_id = 6` / `where('folder_id', 6)` references

---

## Phase 7: Standardize on Inertia for Form Submission

- [ ] Replace `axios.post('/calendar', calendar_form)` in `submit_calendarform()` with `calendar_form.post(route('calendar.store'))` to use Inertia's built-in error handling, loading state (`calendar_form.processing`), and progress indicators
- [ ] Remove the manual axios error handling block and use Inertia's `onError` callback
- [ ] Replace `axios.post('/lookup_file4cal')` with either a debounced axios call (acceptable for autocomplete) or a dedicated composable

---

## Notes
- Each phase is independent and can be done as a separate pass
- Phase 1 is pure cleanup with zero behavioral change — safest starting point
- Phases 3-4 (component extraction) will yield the biggest reduction in Index.vue complexity
- Phase 6 can be done independently of frontend changes
