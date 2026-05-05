# Theme Support: Semantic Color Migration

## Summary

Converted all hardcoded Tailwind color classes to DaisyUI semantic color tokens across the entire Vue codebase. This eliminates the need for `dark:` variant classes and the `.dark` CSS class toggling shim, enabling full multi-theme support where any DaisyUI theme "just works" by setting `data-theme`.

## What Changed

### CSS (`resources/css/app.css`)
- Removed `@variant dark (&:where(.dark, .dark *));` — no longer needed
- Changed `@layer base` border-color from `var(--color-gray-200)` to `var(--color-base-300)`

### Shared Components (15 files)
- `NavLink.vue` — border-indigo/text-gray → border-primary/text-base-content
- `ResponsiveNavLink.vue` — all indigo/gray variants → primary/base-content semantic classes
- `DropdownLink.vue` — text-gray/hover:bg-gray → text-base-content/hover:bg-base-200
- `Dropdown.vue` — bg-white dark:bg-gray-700 → bg-base-100
- `HoverDropdown.vue` — same as Dropdown
- `Checkbox.vue` — border-gray/text-indigo/focus:ring-indigo → border-base-300/text-primary/focus:ring-primary
- `InputError.vue` — text-red-600 dark:text-red-400 → text-error
- `PrimaryButton.vue` — bg-gray-800/text-white → bg-primary/text-primary-content
- `SecondaryButton.vue` — border-gray/bg-white/text-gray → border-base-300/bg-base-100/text-base-content
- `DangerButton.vue` — bg-red-600 → bg-error/text-error-content
- `Modal.vue` — bg-gray-500 overlay → bg-base-content/40; bg-white panel → bg-base-100
- `Pagination.vue` — text-gray-400 → text-base-content/50
- `ContactLookup.vue` — border-gray/bg-white/text-gray → border-base-300/bg-base-100/text-base-content
- `AddContactForm.vue` — text-red-600 → text-error (all required-field indicators)

### Layouts (2 files)
- `AuthenticatedLayout.vue` — navbar bg-gray-50 → bg-base-200; border-gray → border-base-300; text-gray → text-base-content; bg-neutral-600 → bg-neutral; all dropdown link colors converted
- `GuestLayout.vue` — bg-white → bg-base-100

### Index/Table Pages (9 files)
- `Files/Index.vue` — already mostly semantic; no major changes needed
- `Entries/Index.vue` — selected row bg-blue-200 → bg-primary/20; thead bg-gray-300 → bg-base-300; border-gray → border-base-300/border-base-content; text-green/red → text-success/text-error; text-blue-800 → text-info; removed all dark: variants
- `Views/Index.vue` — same patterns as Entries/Index; all table headers, borders, and status colors converted
- `Contacts/Index.vue` — selected row, thead, card border all converted
- `Users/Index.vue` — selected row, thead, header text all converted
- `Filetypes/Index.vue` — setClass() green/red → success/error; selected row, thead converted
- `Folders/Index.vue` — bg-white → bg-base-100; active row bg-blue-800 → bg-primary; text-gray → text-base-content
- `Folders/Create.vue` — text-gray-800 → text-base-content; bg-white → bg-base-100; text-blue-800 → text-primary
- `Folders/Edit.vue` — same as Create

### Form/Detail Pages (7 files)
- `Files/Create.vue` — text-gray → text-base-content
- `Files/Edit.vue` — text-gray-800 → text-base-content; bg-white → bg-base-100
- `Files/FileForm.vue` — border-gray → border-base-300; removed dark:border-base-content/20
- `Files/FileLookup.vue` — border-gray/bg-white → border-base-300/bg-base-100
- `Files/FileLookup_form.vue` — same as FileLookup
- `Entries/EntryForm.vue` — response colors text-red/green → text-error/text-success; divider bg-slate-400 → bg-base-300; hover:bg-gray-200 → hover:bg-base-200; disabled:border-gray → disabled:border-base-300
- `Calendar/Index.vue` — bg-white → bg-base-100; border-gray → border-base-300; border-red-400 → border-error; hover:bg-gray-200 → hover:bg-base-200

### Auth Pages (5 files)
- `Login.vue` — text-green-600 → text-success; bg-gray-200 → bg-base-300; border-black → border-base-content; text-gray-600 → text-base-content/60; focus:ring-indigo → focus:ring-primary
- `Register.vue` — text-gray-600 → text-base-content/60
- `ForgotPassword.vue` — text-gray-600 → text-base-content/60; text-green-600 → text-success
- `ConfirmPassword.vue` — text-gray-600 → text-base-content/60
- `VerifyEmail.vue` — text-gray-600 → text-base-content/60; text-green-600 → text-success

### Profile Pages (3 files)
- `Profile/Edit.vue` — text-gray-800 → text-base-content; bg-white → bg-base-100
- `UpdateProfileInformationForm.vue` — text-gray-900/600/800 → text-base-content variants; text-green-600 → text-success; focus:ring-indigo → focus:ring-primary
- `UpdatePasswordForm.vue` — text-gray-900/600 → text-base-content variants
- `DeleteUserForm.vue` — text-gray-900/600 → text-base-content variants

### Other Pages (5 files)
- `Dashboard.vue` — bg-white/text-gray → bg-base-100/text-base-content; removed dark: variants
- `Welcome.vue` — bg-gray-50/200/300 → bg-base-200/300; text-indigo-800 → text-primary; removed dark: variants
- `WelcomeAdmin.vue` — bg-neutral-600 → bg-neutral; text-gray-800 → text-base-content; bg-white → bg-base-100; text-green/red → text-success/text-error; border-indigo-300 → border-primary
- `Firm/Edit.vue` — text-red-600 → text-error
- `Firm/ProtocolSetup.vue` — text-green-600 → text-success
- `Preferences/Index.vue` — border-gray-600/300 → border-base-300; removed dark: variants

## Color Mapping Reference

| Hardcoded Class | Semantic Replacement |
|---|---|
| `bg-white` | `bg-base-100` |
| `bg-gray-50`, `bg-gray-100` | `bg-base-200` |
| `bg-gray-200`, `bg-gray-300` | `bg-base-300` |
| `text-gray-900`, `text-gray-800` | `text-base-content` |
| `text-gray-700` | `text-base-content/80` |
| `text-gray-500`, `text-gray-600` | `text-base-content/60` |
| `text-gray-400` | `text-base-content/50` |
| `border-gray-200` thru `border-gray-500` | `border-base-300` |
| `border-gray-700` thru `border-gray-900` | `border-base-content` |
| `*-indigo-*` (primary actions) | `*-primary` |
| `*-red-*` (errors/danger) | `*-error` |
| `*-green-*` (success) | `*-success` |
| `bg-blue-200` (selected rows) | `bg-primary/20` |
| `text-blue-800` (info/status) | `text-info` |
| `bg-neutral-600` | `bg-neutral` |

## Not Changed
- `*_original.vue` archived files (not in active use)
- Commented-out code in InputLabel.vue, TextInput.vue
- DaisyUI component classes (`btn`, `input`, `select`, etc.) — already semantic
- FullCalendar theme CSS (`fullcalendar-theme.css`) — already uses CSS variables
