# Theme Preference Plan

## Goal

Persist the user's theme choice as a server-side preference, with localStorage as the runtime cache. The server preference is the durable source of truth (survives browser clears / new devices). localStorage is what the app reads on every page load so there's no per-request DB query and no theme flicker.

## Behavior Summary

- **On login**: Check for the user's `theme` preference in the DB.
  - If found → apply it and write it to localStorage.
  - If not found → create a `theme` preference with value `'light'`, apply it, and write to localStorage.
- **Page load (already logged in)**: `useTheme` reads localStorage. Falls back to `'light'` if empty.
- **Navbar theme picker**: Changes theme immediately and writes to localStorage. Then prompts "Update your theme preference?" — Yes saves to server, No leaves it as a session-only change.
- **Preferences page**: User explicitly selects their preferred theme. Saves to server AND localStorage immediately.
- **Theme list**: Single source of truth in `useTheme.js` — both the navbar picker and preferences page import it.

---

## Implementation Steps

### 1. Create `theme` preference on login

In the login flow (e.g., `AuthenticatedSessionController` or a login event listener), after authentication:

- Query `Preference::where('user_id', $user->id)->where('name', 'theme')->first()`.
- If not found, create it: `Preference::create(['user_id' => $user->id, 'name' => 'theme', 'setting' => 'light'])`.
- Pass the theme value to the frontend via the Inertia redirect (e.g., as a session flash or a one-time prop on the dashboard page).

No `pref_defaults` row or migration needed.

### 2. Add `theme_update` method to `PreferenceController`

New endpoint to save the theme preference:

```php
public function theme_update(Request $request): void
{
    $validated = $request->validate([
        'user_id' => 'numeric|integer|required',
        'theme' => 'required|string|max:50',
    ]);

    if ($request->user()->id == $request->user_id || $request->user()->user_type == 'Admin') {
        Preference::where('user_id', $request->user_id)
            ->where('name', 'theme')
            ->update(['setting' => $request->theme]);
    }
}
```

### 3. Add route

In `routes/web.php`, add:

```php
Route::post('/preferences/theme', [PreferenceController::class, 'theme_update']);
```

### 4. Update `useTheme.js` composable

- Keep localStorage as the runtime read/write mechanism.
- `initTheme(savedTheme)` — if `savedTheme` is provided (login flow), write it to localStorage and apply it. Otherwise read from localStorage, falling back to `'light'`.
- `setTheme(newTheme)` — applies theme to DOM and writes to localStorage.
- Export `AVAILABLE_THEMES` as a named constant.

```js
import { ref } from 'vue'

export const AVAILABLE_THEMES = [
    'light', 'dark', 'corporate', 'retro',
    'aqua', 'aqua2', 'dracula', 'business', 'night', 'coffee',
    'caramellatte', 'nord', 'dim', 'winter',
    'cmyk', 'fantasy', 'abyss', 'bumblebee',
]

export const useTheme = () => {
    const theme = ref('light')

    const setTheme = (newTheme) => {
        theme.value = newTheme
        document.documentElement.setAttribute('data-theme', newTheme)
        localStorage.setItem('theme', newTheme)
    }

    const initTheme = (savedTheme = null) => {
        const themeToApply = savedTheme
            ?? localStorage.getItem('theme')
            ?? 'light'
        const validated = AVAILABLE_THEMES.includes(themeToApply) ? themeToApply : 'light'
        setTheme(validated)
    }

    return {
        theme,
        themes: AVAILABLE_THEMES,
        setTheme,
        initTheme,
    }
}
```

### 5. Seed localStorage on login

On the page that loads after login (e.g., Dashboard), check for a flashed/one-time `theme_preference` prop. If present, call `initTheme(themePreference)` which writes it to localStorage. On subsequent page loads, `initTheme()` (no argument) reads from localStorage.

### 6. Update `AuthenticatedLayout.vue`

- On mount, call `initTheme()` (reads from localStorage).
- When user selects a theme from the navbar picker:
  - Call `setTheme(t)` (applies immediately, writes to localStorage).
  - If the selected theme differs from the current `theme` ref value before change, show a confirmation dialog: "Update your theme preference?"
  - If confirmed, POST to `/preferences/theme` to save to server.
  - If declined, theme stays changed for the session via localStorage (no server save).

```js
const { theme, themes, setTheme, initTheme } = useTheme()

onMounted(() => {
    initTheme()
})

const showThemeDialog = ref(false)
const pendingTheme = ref(null)

function onThemeSelected(t) {
    setTheme(t)
    pendingTheme.value = t
    showThemeDialog.value = true
}

function confirmThemeSave() {
    const form = useForm({ user_id: page.props.auth.user.id, theme: pendingTheme.value })
    form.post('/preferences/theme', { preserveState: true })
    showThemeDialog.value = false
}

function declineThemeSave() {
    showThemeDialog.value = false
}
```

Add a DaisyUI modal in the template for the confirmation prompt.

### 7. Update `Preferences/Index.vue`

Add a "Theme" section:

- Dropdown/select populated from `AVAILABLE_THEMES` (imported from `useTheme.js`).
- Current value from the user's `theme` preference (passed in props from `PreferenceController::index`).
- On change: POST to `/preferences/theme` to save to server, AND call `setTheme()` to update localStorage and DOM immediately.

### 8. Verify other `useTheme` consumers

`Entries/Index.vue` and `Contacts/Index.vue` also import `useTheme`. They likely inherit the theme from the layout's `initTheme()` call (which sets the DOM attribute). Verify they don't call `initTheme()` independently in a conflicting way. Adjust if needed.

---

## Files Modified

| File | Change |
|------|--------|
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Create theme preference on login if missing, flash value |
| `app/Http/Controllers/PreferenceController.php` | Add `theme_update` method |
| `routes/web.php` | Add POST route for theme preference |
| `resources/js/Composables/useTheme.js` | Use localStorage as cache, accept server seed value |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Init from localStorage, add confirmation dialog |
| `resources/js/Pages/Preferences/Index.vue` | Add theme selection section |

## Edge Cases

- **New browser / cleared localStorage**: `initTheme()` finds no localStorage value, falls back to `'light'`. On next login, the server preference re-seeds localStorage.
- **User not logged in (guest pages)**: Falls back to `'light'` from localStorage or default.
- **Invalid theme in localStorage or DB**: `initTheme` validates against `AVAILABLE_THEMES`, falls back to `'light'`.
- **Theme removed from list**: Users with that saved value fall back to `'light'` on next `initTheme()`.
