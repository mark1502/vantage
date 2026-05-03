# Theme Preference Plan

## Goal

Replace localStorage-based theme persistence with a server-side user preference. The theme picker in the navbar remains for quick switching, but now prompts the user to save the selection as their preference. On login, the user's saved theme preference is applied.

## Behavior Summary

- **On login**: User's saved `theme` preference is loaded and applied (no localStorage).
- **Theme picker (navbar)**: Changes theme immediately. A dialog then asks "Update your theme preference?" — Yes saves to server, No leaves the new theme active for the session but doesn't persist.
- **Preferences page**: User can select their preferred theme from the same list used in the navbar picker. Saves immediately (auto-save pattern like other prefs).
- **New users**: Default to `light`.
- **Theme list**: Single source of truth in `useTheme.js` — both the navbar picker and preferences page use it.

---

## Implementation Steps

### 1. Add `theme` to `pref_defaults` table

Insert a new row into the `pref_defaults` table so the PreferenceController's index method auto-creates the preference for existing users.

- `name`: `'theme'`
- `prompt`: `'Theme'`
- `setting`: `'light'`

Create a migration to insert this default.

### 2. Add `theme_preference_update` method to `PreferenceController`

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

### 4. Share theme preference via Inertia (HandleInertiaRequests)

In `HandleInertiaRequests::share()`, add the user's theme preference to shared props so it's available on every page load:

```php
'theme_preference' => $user ? (
    \App\Models\Preference::where('user_id', $user->id)
        ->where('name', 'theme')
        ->value('setting') ?? 'light'
) : 'light',
```

### 5. Update `useTheme.js` composable

- Remove all `localStorage` reads/writes.
- Accept an optional `savedTheme` parameter for initialization.
- `initTheme(savedTheme)` applies the saved theme from the server prop.
- `setTheme(newTheme)` updates the DOM attribute only (no persistence).
- Export `AVAILABLE_THEMES` as a named constant so other components can import it.

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
    }

    const initTheme = (savedTheme) => {
        const themeToApply = (savedTheme && AVAILABLE_THEMES.includes(savedTheme))
            ? savedTheme
            : 'light'
        setTheme(themeToApply)
    }

    return {
        theme,
        themes: AVAILABLE_THEMES,
        setTheme,
        initTheme,
    }
}
```

### 6. Update `AuthenticatedLayout.vue`

- Read `usePage().props.theme_preference` as the saved preference.
- Pass it to `initTheme()` on mount.
- When user selects a theme from the picker that differs from the saved preference, show a confirmation dialog.
- If user confirms, POST to `/preferences/theme` to save the new preference.
- If user declines, theme stays changed for the session (no save).

Key changes:
```js
const page = usePage()
const savedThemePreference = ref(page.props.theme_preference)

onMounted(() => {
    initTheme(savedThemePreference.value)
})

// Theme picker handler
const showThemeDialog = ref(false)
const pendingTheme = ref(null)

function onThemeSelected(t) {
    setTheme(t) // apply immediately
    if (t !== savedThemePreference.value) {
        pendingTheme.value = t
        showThemeDialog.value = true
    }
}

function confirmThemeSave() {
    const form = useForm({ user_id: page.props.auth.user.id, theme: pendingTheme.value })
    form.post('/preferences/theme', {
        preserveState: true,
        onSuccess: () => {
            savedThemePreference.value = pendingTheme.value
        }
    })
    showThemeDialog.value = false
}

function declineThemeSave() {
    showThemeDialog.value = false
}
```

Add a simple DaisyUI modal/dialog in the template for the confirmation prompt.

### 7. Update `Preferences/Index.vue`

Add a new "Theme" section (similar pattern to existing sections):

- Dropdown/select populated from `AVAILABLE_THEMES` (imported from `useTheme.js`).
- Current value from the `theme` preference passed in props.
- Auto-saves on change via POST to `/preferences/theme`.
- Also calls `setTheme()` so the page reflects the change immediately.

### 8. Update other `useTheme` consumers

`Entries/Index.vue` and `Contacts/Index.vue` also import `useTheme` — check if they call `initTheme()` or just use the provided theme. They likely use the `inject`ed theme from the layout, so they should need no changes. Verify and adjust if needed.

### 9. Remove localStorage references

After all changes, grep for any remaining `localStorage.*theme` references and remove them. The theme is now fully server-driven (with session-only overrides from the picker).

---

## Files Modified

| File | Change |
|------|--------|
| `database/migrations/xxxx_add_theme_pref_default.php` | New migration to seed pref_defaults |
| `app/Http/Controllers/PreferenceController.php` | Add `theme_update` method |
| `routes/web.php` | Add POST route for theme preference |
| `app/Http/Middleware/HandleInertiaRequests.php` | Share `theme_preference` prop |
| `resources/js/Composables/useTheme.js` | Remove localStorage, accept server value |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Use server prop, add confirmation dialog |
| `resources/js/Pages/Preferences/Index.vue` | Add theme selection section |

## Edge Cases

- **User not logged in (guest pages)**: Falls back to `light` theme since no preference exists. Guest layout would need its own `initTheme('light')` call.
- **Invalid theme in DB**: `initTheme` already guards against this by checking `AVAILABLE_THEMES.includes()`.
- **Theme removed from list**: If a theme is removed from `AVAILABLE_THEMES`, users with that saved value will fall back to `light` on next load.
