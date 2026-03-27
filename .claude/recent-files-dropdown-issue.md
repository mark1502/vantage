# Recent Files Dropdown — Z-Index / Clipping Issue (RESOLVED)

## Problem

The HoverDropdown for recent files in the nav bar was clipped at the **top**, not the bottom. The bottom of the dropdown aligned with the bottom of the header and was fully visible. However, the top of the dropdown disappeared — it grew upward from somewhere near the header, and the upper portion was cut off because there was no room above it.

## Solution

Changed the dropdown panel from `position: absolute` to `position: fixed` in `HoverDropdown.vue`. Fixed positioning escapes any overflow clipping from ancestor elements since it positions relative to the viewport.

Key changes:
- Added a `triggerRef` on the trigger element to get its bounding rect
- Compute `top` and `left`/`right` inline styles from the trigger's position on open
- Removed the static `style="display: none"` which conflicted with Vue's `v-show` when combined with dynamic `:style` binding

## Files changed

| File | Change |
|------|--------|
| `resources/js/Components/HoverDropdown.vue` | `absolute` → `fixed` positioning with computed coordinates |
