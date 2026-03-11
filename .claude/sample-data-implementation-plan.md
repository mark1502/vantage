# Sample Data Mode Implementation Plan

## Overview

Read-only "Sample Data Mode" that lets users explore the app using pre-seeded data from `firm_id=2` without affecting their real firm's data. Uses a session-based firm swap with middleware-enforced read-only protection.

---

## Phase 1: Backend Foundation — User Model & Session Toggle

### 1A. Add methods to User model

**File:** `app/Models/User.php`

```php
public function effectiveFirmId(): int
{
    return session('sample_firm_id', $this->firm_id);
}

public function isInSampleMode(): bool
{
    return session()->has('sample_firm_id');
}

public function effectiveInitials(): ?string
{
    if ($this->isInSampleMode()) {
        return 'JA'; // John Adams — sample firm member
    }
    return $this->memberInitials();
}
```

`effectiveFirmId()` is the single source of truth for which firm's data to display. Every controller that currently uses `$request->user()->firm_id` for data scoping will be updated to use `effectiveFirmId()` instead.

`effectiveInitials()` solves the problem that the real user has no contact record in firm 2. Office views default to "JA" (John Adams) when in sample mode.

### 1B. Create SampleModeController

**File (new):** `app/Http/Controllers/SampleModeController.php`

Two actions:
- `enable()` — POST. Sets `session('sample_firm_id', 2)`, redirects to dashboard.
- `disable()` — POST. Forgets `sample_firm_id` from session, redirects to dashboard.

### 1C. Add routes

**File:** `routes/web.php`

Inside the `auth + welcomed` middleware group:

```php
Route::post('/sample-mode/enable', [SampleModeController::class, 'enable'])->name('sample-mode.enable');
Route::post('/sample-mode/disable', [SampleModeController::class, 'disable'])->name('sample-mode.disable');
```

---

## Phase 2: Read-Only Enforcement Middleware

### 2A. Create `EnforceSampleReadOnly` middleware

**File (new):** `app/Http/Middleware/EnforceSampleReadOnly.php`

Logic:
- If `session('sample_firm_id')` is not set → pass through.
- If request method is GET → pass through.
- If route is in the allow-list → pass through.
- Otherwise → block with redirect back + flash message.

**Allow-list** (POST routes that are actually read operations):
```php
private array $allowedRoutes = [
    'sample-mode.enable',
    'sample-mode.disable',
    'logout',
    'files.lookup_file',
    'entries.lookup_contact',
    'calendar.lookup_file',
    'calendar.get_events',
];
```

For Inertia requests: redirect back with flash message "Sample data is read-only. Return to live data to make changes."
For non-Inertia requests (e.g., calendar AJAX): return 403 JSON response.

### 2B. Register middleware

**File:** `bootstrap/app.php`

Append to the web middleware stack after `HandleInertiaRequests`:

```php
\App\Http\Middleware\EnforceSampleReadOnly::class,
```

---

## Phase 3: Share Sample Mode State via Inertia

**File:** `app/Http/Middleware/HandleInertiaRequests.php`

Add to shared props:

```php
'auth' => [
    'user' => $user,
    'sample_mode' => $user?->isInSampleMode() ?? false,
],
```

Also ensure flash messages are shared (if not already):

```php
'flash' => [
    'message' => fn () => $request->session()->get('message'),
],
```

---

## Phase 4: Frontend UI Changes

### 4A. Sample Data Banner

**File:** `resources/js/Layouts/AuthenticatedLayout.vue`

Add indicator to the right of the "Contact" nav link:

```html
<div v-if="$page.props.auth.sample_mode" class="flex items-center ml-4">
    <span class="bg-amber-400 text-amber-900 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">
        Using Sample Data
    </span>
</div>
```

### 4B. Modify User Dropdown Menu

When `$page.props.auth.sample_mode` is **true**:
- Show "Return to Live Data" (POST to `route('sample-mode.disable')`)
- Show "Log Out"
- **Hide** Profile and Admin Menu

When `$page.props.auth.sample_mode` is **false**:
- Show existing items: Profile, Admin Menu (if admin), Log Out
- **Add** "Use Sample Data" (POST to `route('sample-mode.enable')`)

Both sample-mode links use `<DropdownLink method="post" as="button">`.

### 4C. Responsive Navigation Menu

Apply same conditional logic to the responsive/mobile nav section.

### 4D. Flash Message Display

Add flash message banner below nav in `AuthenticatedLayout.vue`:

```html
<div v-if="$page.props.flash?.message" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2">
    <div class="bg-amber-100 border border-amber-400 text-amber-700 px-4 py-3 rounded">
        {{ $page.props.flash.message }}
    </div>
</div>
```

---

## Phase 5: Update Controllers to Use effectiveFirmId()

Replace every `$request->user()->firm_id` (used for data scoping) with `$request->user()->effectiveFirmId()`.

Pattern in each method:
```php
$firmId = $request->user()->effectiveFirmId();
```

### Controllers requiring changes:

| Controller | Key locations |
|---|---|
| **FileController** | `index()`, `create()`, `store()`, `lookup_file()` |
| **EntryController** | `index()`, `store()`, `edit()`, `update()`, `destroy()`, `contact_add_modal()`, `contact_add_modal2()`, `new_contact_modal()`, `toggle_read()`, `add_new_entrytype()`, helper methods |
| **ContactController** | `index()`, `store()`, `update()` |
| **CalendarController** | `index()`, `store()`, `get_events()`, `move_event()`, `event_placement()`, `resize_event()`, `lookup_file()`, `add_new_event_type()` |
| **ViewController** | `index()` — also change default `view_for` to use `effectiveInitials()` |
| **UserController** | `index()`, `store()` |
| **PreferenceController** | `update_entrytypes()` — also add sample mode guard on `index()` |

### ViewController special handling:

```php
$firm_id = $user->effectiveFirmId();
$view_for = $request->query('view_for') ?? $user->effectiveInitials();
```

Add fallback if initials not found in sample firm:
```php
$for = $this->getContactFor($view_for, $firm_id);
if (!$for && $request->user()->isInSampleMode()) {
    $view_for = $request->user()->effectiveInitials();
    $for = $this->getContactFor($view_for, $firm_id);
}
```

---

## Phase 6: Edge Cases

### 6A. Calendar event colors
`get_events()` queries Preference records by firm_id. Sample firm won't have preferences. Existing fallback defaults (`'#fff68f'`) handle this — all events get default colors. Acceptable.

### 6B. PreferenceController::index()
Creates missing preference records on GET. In sample mode, skip creation to avoid writing to sample firm:
```php
if ($request->user()->isInSampleMode()) {
    return redirect()->route('dashboard');
}
```

### 6C. Subscription data
`HandleInertiaRequests` shares subscription data from `$user->firm`. Keep this on the **real** firm (not sample firm). `$user->firm` still returns the real firm — only `effectiveFirmId()` returns the swapped value. No change needed.

### 6D. Admin routes in sample mode
Admin menu link is hidden, but users could type URLs directly. Middleware blocks writes. For reads, showing sample firm admin data is harmless. Optional: redirect admin routes when in sample mode.

### 6E. Document serving
`EntryController::serve_document()` serves files from disk. Sample entries won't have real documents. Existing null/404 handling suffices.

### 6F. Session lifecycle
`sample_firm_id` persists in session until explicitly cleared or user logs out. Laravel destroys the session on logout, automatically clearing sample mode.

### 6G. EntryController document_base_path
`Firm::find($request->user()->firm_id)` is used to get `document_base_path`. In sample mode, this should use `effectiveFirmId()` so it looks at the sample firm's path.

---

## Implementation Order

1. **Phase 1** — User model methods (foundation everything depends on)
2. **Phase 2** — Middleware + routes (safety net before controller changes)
3. **Phase 3** — Inertia shared props (needed before frontend work)
4. **Phase 4A–4D** — Layout UI (banner, menu, flash messages)
5. **Phase 5** — Controller updates (the bulk of the work, one controller at a time)
6. **Phase 6** — Edge cases (address as they surface during testing)

---

## Files to Create

| File | Purpose |
|---|---|
| `app/Http/Controllers/SampleModeController.php` | Enable/disable sample mode |
| `app/Http/Middleware/EnforceSampleReadOnly.php` | Block writes in sample mode |

## Files to Modify

| File | Changes |
|---|---|
| `app/Models/User.php` | Add `effectiveFirmId()`, `isInSampleMode()`, `effectiveInitials()` |
| `bootstrap/app.php` | Register `EnforceSampleReadOnly` middleware |
| `routes/web.php` | Add sample-mode routes |
| `app/Http/Middleware/HandleInertiaRequests.php` | Share `sample_mode` prop + flash messages |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Banner, menu changes, flash message display |
| `app/Http/Controllers/FileController.php` | Replace `firm_id` with `effectiveFirmId()` |
| `app/Http/Controllers/EntryController.php` | Replace `firm_id` with `effectiveFirmId()` |
| `app/Http/Controllers/ContactController.php` | Replace `firm_id` with `effectiveFirmId()` |
| `app/Http/Controllers/CalendarController.php` | Replace `firm_id` with `effectiveFirmId()` |
| `app/Http/Controllers/ViewController.php` | Replace `firm_id` + use `effectiveInitials()` |
| `app/Http/Controllers/UserController.php` | Replace `firm_id` with `effectiveFirmId()` |
| `app/Http/Controllers/PreferenceController.php` | Add sample mode guard + replace `firm_id` |
