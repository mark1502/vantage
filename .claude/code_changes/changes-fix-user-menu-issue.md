# Fix: User Menu Dropdown Intermittent Click Failure

## Date: 2026-03-09

## Problem
The user menu dropdown (upper-right nav bar) intermittently stopped responding to clicks. Everything else on the page worked. Navigating away and back would fix it temporarily.

## Root Cause
DaisyUI checkbox-based modals (`<input class="modal-toggle">` + `<div class="modal">`) render as `position: fixed; inset: 0; z-index: 999` at all times — even when closed. Closed modals rely on `pointer-events: none` to let clicks pass through. When this failed (CSS specificity edge case or browser quirk), the invisible modal overlay at z-999 swallowed clicks on the nav bar (which had no z-index).

Confirmed by right-clicking the user menu and inspecting — the element at that coordinate was a DaisyUI modal overlay, not the dropdown trigger.

## Solution
Converted all 17 checkbox-based DaisyUI modals to native `<dialog>` elements across 5 files. The `<dialog>` element is only present in the DOM's top layer when opened via `.showModal()`, completely eliminating the invisible overlay problem.

### API Conversion Pattern
| Checkbox pattern | Dialog equivalent |
|---|---|
| `el.checked = true` | `el.showModal()` |
| `el.checked = false` | `el.close()` |
| `el.checked` (read status) | `el.open` |
| `el.checked = !el.checked` (toggle) | `el.open ? el.close() : el.showModal()` |

## Files Changed

### Entries/Index.vue
- Converted 3 modals: `timeline_add_modal`, `confirm_delete_modal`, `has_responses_modal`
- Updated `display_modal()` function from `.checked` to dialog API
- Updated 6 direct `getElementById().checked` references

### Entries/EntryForm.vue
- Converted 5 modals: `entrytype_modal`, `entrychanged_modal`, `addcancelled_modal`, `role_assign_modal`, `file_contacts_modal`
- Unified `display_modal()` function — removed special case for 'unsaved' dialog since all modals now use the same dialog API
- Updated 8 direct `getElementById().checked` references

### Views/Index.vue
- Converted 3 modals: `timeline_add_modal`, `confirm_delete_modal`, `events_filters_modal`
- Added missing `display_modal()` and `close_timeline_add_modal()` functions (were called from template but undefined — latent bug)
- Removed old commented-out `display_modal()` function
- Updated 6 direct `getElementById().checked` references

### Files/FileForm.vue
- Converted 2 modals: `sol_modal`, `filechanged_modal`
- Updated `display_modal()` function from `.checked` to dialog API

### Files/Create.vue
- Converted 2 modals: `sol_modal`, `cancelcreate_modal`
- Updated `show_modal()` function from `.checked` to dialog API
- Updated 3 direct `.checked` references in `handleEsc()`

## Notes
- Calendar/Index.vue and Contacts/Index.vue already used `<dialog>` elements — no changes needed
- The commented-out modals in Views/Index.vue (sol_modal, filechanged_modal) were left as-is since they are inactive code
- Non-modal `.checked` references (e.g., `read_checkbox` in EntryForm.vue) were not modified
