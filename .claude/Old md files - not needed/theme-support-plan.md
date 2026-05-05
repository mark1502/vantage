# Plan: Multi-Theme Support with DaisyUI 5

## Context

The app currently has a simple light/dark toggle. We want to offer 12 DaisyUI themes: **light, dark, cupcake, corporate, retro, forest, aqua, dracula, business, night, coffee, caramellatte**. The theme picker will be a dropdown in the navbar where the current toggle button lives. Persistence stays in localStorage for now.

## The `dark:` Variant Concern

**Problem:** ~59 uses of Tailwind `dark:` utilities across 20 Vue files (e.g., `dark:bg-gray-700`, `dark:border-gray-600`). The current `@variant dark` rule in `app.css` triggers on the `.dark` CSS class on `<html>`. With multiple themes, we need `dark:` utilities to activate for ALL dark-category themes (dark, dracula, business, night, coffee, forest), not just `dark`.

**Solution:** Keep the `@variant dark` rule unchanged. The `useTheme.js` composable will maintain a set of dark-category theme names and add/remove the `.dark` class on `<html>` whenever the theme changes. This means zero changes to existing `dark:` utility usage across the codebase.

**Longer-term note:** Hardcoded colors like `bg-white dark:bg-gray-700` won't perfectly match every theme's aesthetic (e.g., cupcake's background is pinkish, not white). Migrating these to DaisyUI semantic classes (`bg-base-100`, `text-base-content`) is a separate follow-up task, not part of this plan.

## FullCalendar

`fullcalendar-theme.css` already uses DaisyUI CSS variables exclusively (`--color-base-100`, `--color-primary`, etc.). **No changes needed** — it will automatically adapt to any theme.

## Files to Change (5 files, 1 new)

### 1. `resources/css/app.css`

Replace `@plugin "daisyui";` with:

```css
@plugin "daisyui" {
  themes: light --default, dark --prefersdark, cupcake, corporate, retro,
          forest, aqua, dracula, business, night, coffee, caramellatte;
}
```

This restricts the CSS bundle to only the 12 themes we need. The `@variant dark` line stays unchanged.

---

### 2. `resources/js/Composables/useTheme.js`

Rewrite to support multiple themes:

- Export a `THEMES` array of `{ name, label, dark }` objects for all 12 themes
- `DARK_THEMES` set: `dark`, `dracula`, `business`, `night`, `coffee`, `forest`
- `setTheme(themeName)`:
  - Sets `data-theme` on `document.documentElement` (not body — DaisyUI 5 convention)
  - Adds `.dark` class if theme is in DARK_THEMES, removes it otherwise
  - Stores in localStorage
- `initTheme()`: reads localStorage, falls back to system `prefers-color-scheme`
- Export `isDarkTheme(themeName)` helper for child components
- Remove old logic that added theme name as a CSS class

---

### 3. `resources/js/Components/ThemeSwitcher.vue` (NEW)

A self-contained dropdown component (not reusing Dropdown.vue — we need a grid layout with color swatches, not a simple link list).

- Trigger: a palette/swatch icon button, sized to match navbar elements
- Dropdown: list of 12 themes, each showing:
  - Color preview swatches (4 small colored bars using `bg-primary`, `bg-secondary`, `bg-accent`, `bg-neutral` inside a div with `data-theme` set to that theme — so colors render correctly without JS)
  - Theme label
  - Checkmark on active theme
- Click-to-select, close on select/escape/outside-click
- Uses DaisyUI semantic classes (`bg-base-100`, `text-base-content`) so the dropdown itself adapts to the current theme

---

### 4. `resources/js/Layouts/AuthenticatedLayout.vue`

- Import `ThemeSwitcher` component
- Replace lines 155-157 (tooltip-wrapped toggle button) with `<ThemeSwitcher />`
- Remove dead code: `toggleTheme()`, `currentIcon`, `iconAlt`, `tooltipText`
- Keep `provide('currentTheme', theme)` and `provide('setThemeFunction', setTheme)` — they're used by `EntryForm.vue`

---

### 5. `resources/views/app.blade.php`

Add inline script in `<head>` before Vite directives to prevent FOUC:

```html
<script>
(function() {
    var theme = localStorage.getItem('theme');
    if (!theme) {
        theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    var darkThemes = ['dark','dracula','business','night','coffee','forest'];
    document.documentElement.setAttribute('data-theme', theme);
    if (darkThemes.indexOf(theme) !== -1) {
        document.documentElement.classList.add('dark');
    }
})();
</script>
```

Also: move `data-theme` application from `<body>` to `<html>` (the script handles this; the composable's `setTheme` already targets `documentElement`).

---

### 6. `resources/js/Pages/Entries/EntryForm.vue` (minor fix)

The `isDark` computed (line 16-18) currently only checks `=== 'dark'`. Update to check all dark-category themes:

```js
const isDark = computed(() => {
    const darkThemes = ['dark', 'dracula', 'business', 'night', 'coffee', 'forest'];
    return darkThemes.includes(currentTheme.value);
});
```

This ensures vue-datepicker components get `:dark="true"` for all dark themes.

---

## Verification

1. Run `npm run dev` and test each of the 12 themes via the dropdown
2. Confirm FullCalendar adapts colors when switching themes
3. Confirm vue-datepicker in EntryForm uses dark mode for dark-category themes
4. Confirm `dark:` utility classes still work (check Dropdown, AuthenticatedLayout nav items)
5. Hard-refresh the page to verify no FOUC (theme applied before paint)
6. Verify theme persists across page refreshes via localStorage
