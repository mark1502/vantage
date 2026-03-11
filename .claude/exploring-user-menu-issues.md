# User Menu Dropdown - Intermittent Click Failure

## Symptom
Sometimes clicking the user menu (upper-right dropdown in nav bar) does nothing. Everything else on the screen works. Navigating to another page fixes it. Mostly happens on Files/Index, also possibly Entries/Index and Contacts/Index. No errors generated.

## Key Architecture Context
- Dropdown component: `resources/js/Components/Dropdown.vue` (standard Breeze)
  - Uses a `fixed inset-0 z-40` overlay when open, dropdown menu at `z-50`
  - Trigger toggles a local `open` ref
- Nav bar (`<nav>` in AuthenticatedLayout.vue) has **no z-index**
- DaisyUI v5.5 is in use

## Most Likely Causes (ranked)

### 1. DaisyUI modals at z-999 blocking the nav bar
DaisyUI `.modal` CSS renders ALL modal elements as `position: fixed; inset: 0; z-index: 999; display: grid` at all times - even when closed. Closed modals rely on `pointer-events: none` and `visibility: hidden` to let clicks pass through. The nav bar has no z-index, so it sits below z-999.

If `pointer-events: none` fails (CSS layer specificity edge case, browser quirk with `visibility` transition using `allow-discrete`), the invisible modal div would swallow clicks in the nav area.

Pages with `<dialog class="modal">` elements:
- Calendar/Index.vue (multiple dialogs)
- Contacts/Index.vue (delete_modal dialog)

**How to test:** When broken, right-click the dropdown trigger -> Inspect. Check what element is at that coordinate. Or add `z-index: 1000; position: relative;` to the `<nav>` temporarily in DevTools.

### 2. Checkbox-based modals on Entries/Index left in checked state
Entries/Index uses `<input class="modal-toggle">` checkboxes (timeline_add_modal, confirm_delete_modal, has_responses_modal). CSS selector `.modal-toggle:checked + .modal` makes the modal `pointer-events: auto` at z-999. If a checkbox stays checked after modal "close", an invisible full-page overlay blocks everything.

**How to test:** When broken on Entries/Index, run in console:
```js
document.querySelectorAll('.modal-toggle').forEach(el => console.log(el.id, el.checked))
```
If any shows `checked: true`, that's the culprit.

### 3. DaisyUI tooltip pseudo-elements near the dropdown trigger
The theme toggle button wrapper (line 95 of AuthenticatedLayout.vue) has `tooltip tooltip-left tooltip-info` and sits immediately left of the dropdown trigger in a flex container. DaisyUI tooltips create `::before`/`::after` pseudo-elements with `z-index: 2` and `position: absolute`. Custom scoped styles add transition delays (500ms/300ms). During transition-out after hovering the theme toggle, pseudo-elements could briefly interfere.

**How to test:** Check if issue correlates with recently hovering the dark/light mode toggle. Try removing tooltip classes temporarily.

## Debugging Steps (next time it breaks)
1. Open DevTools, right-click the dropdown trigger area -> Inspect to see what element is actually at that coordinate
2. Run `document.elementFromPoint(x, y)` in console (use coordinates of the trigger button)
3. Check for any `.modal` or `.modal-toggle:checked` elements that might be overlaying
4. Check computed styles on any overlaying element for `pointer-events` value

## Potential Fix Direction
If cause #1 confirmed: add `relative z-[1000]` to the `<nav>` element. Note: this creates a new stacking context, so the Dropdown's `fixed inset-0 z-40` overlay would be contained within the nav's stacking context and would NOT cover the full page. The Dropdown component would need to be refactored (e.g., teleport the overlay to body, or use a click-outside composable instead of a full-screen overlay).
