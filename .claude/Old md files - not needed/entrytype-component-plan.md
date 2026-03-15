# Plan: Extract EntrytypeSelector Component

## Goal
Extract the entrytype select + add-new-type button + modal into a reusable `EntrytypeSelector.vue` component, used by both `EntryForm.vue` (Entries/Index and Views/Index) and `Calendar/Index.vue`.

---

## Current State

### EntryForm.vue (component used within Entries/Index.vue and Views/Index.vue)
- Select bound to `entry_form.entrytype_id`, options from `props.p1.folders[row].entrytypes`
- "Add New Type" button opens `entrytype_modal` dialog
- Modal has type-ahead lookup against the folder's entrytypes list
- POSTs to `/add_new_entrytype` with dynamic `folder_id` from `props.getFolderData('id')`
- Partial reload: `only: ['folders', 'new_entrytype']`
- New type ID read from `props.p1.new_entrytype.id`
- Has duplicate-name check before posting
- Has auto-focus on modal open
- Label is dynamic from `props.getFolderData('entrytype_prompt')`
- Visibility controlled by parent via `v-show`
- Sets `entry_form.new_entrytype_added = true` after creation

### Calendar/Index.vue (entrytype controls inline in the page)
- Select bound to `calendar_form.entrytype_id`, options from `props.event_types`
- Same modal pattern but simpler (no duplicate check, no auto-focus)
- POSTs to `/add_new_event_type` with hardcoded `folder_id = 6`
- Partial reload: `only: ['event_types', 'new_event_type']`
- New type ID read from `props.new_event_type.id`
- Label is static "Event Type:"
- Calendar uses axios for its own calendar CRUD calls, but the entrytype creation already uses Inertia (`useForm` + `.post()`), so the new component using Inertia is fully compatible

### Backend
- `EntryController::add_new_entrytype()` — validates, creates Entrytype with dynamic `folder_id`, returns Inertia render of `Entries/Index` with `folders` and `new_entrytype`
- `CalendarController::add_new_event_type()` — nearly identical, hardcodes `folder_id = 6`, returns Inertia render of `Calendar/Index` with `event_types` and `new_event_type`
- Both do the same thing: create an Entrytype record for a given folder

---

## Plan

### Step 1: Unify the backend route

**Why:** Both controllers do the same thing — create an Entrytype for a folder. A single endpoint simplifies the component.

**Changes:**
- Keep `EntryController::add_new_entrytype()` as the single endpoint (route: `POST /add_new_entrytype`)
- Modify it to return a **JSON response** instead of `Inertia::render()`, since the component will handle the result client-side. Return the new entrytype's `id` and `name`.
  - Alternatively, if we want to keep using Inertia partial reloads, we can keep both routes but have the component accept config for which route/props to use. **Decision: use a simple JSON response** — this is a small targeted action (create one record), the component can emit the result, and the parent can update its own state. This avoids the complexity of the component needing to know about Inertia page props.
- Remove `CalendarController::add_new_event_type()` and its routes
- Remove the GET error-passback route for `/add_new_event_type`
- Update the POST route: keep `/add_new_entrytype` but change the controller method to return JSON when called via the component (detect via `$request->wantsJson()` or `$request->header('X-Component-Request')`, or just always return JSON since this is only called from the modal)

**Revised approach — keep it simple:**
- Change `add_new_entrytype()` to return JSON: `{ id, name, folder_id }`
- The component will use `axios.post()` (or `useForm` if preferred) to call this endpoint
- The parent component is responsible for adding the new entrytype to its local list and updating the selected value (via emits from the component)
- Remove `add_new_event_type()` from CalendarController and its routes

**Wait — reconsideration:** The current Inertia partial reload pattern means the server re-queries and sends back the updated list. If we switch to JSON, the parent needs to manually push the new type into its list. This is actually simpler and more predictable for a shared component. The parent already has the list as a prop/reactive — it just needs to push the new item and sort.

**Final backend approach:**
- Create a new method (or modify existing) that returns JSON with the created entrytype
- **Or even simpler**: keep using `useForm().post()` from the component but use `router.post()` with `only` to do partial reload, and let each parent pass in the config. This keeps the Inertia pattern but requires the component to know about partial reloads.

**Simplest approach chosen:** Use `axios.post()` in the component for the creation call. Return JSON `{ id, name, folder_id }`. The component emits the new type to the parent. The parent is responsible for refreshing its own data (either by pushing to the local array or triggering an Inertia reload). This fully decouples the component from Inertia page-level concerns.

### Step 2: Create the new backend endpoint

Create a dedicated route/controller method (or reuse the existing one) that:
- Accepts: `name`, `folder_id`
- Validates the input
- Creates the Entrytype
- Returns JSON: `{ id, name, folder_id }`

Options:
1. **Modify `EntryController::add_new_entrytype()`** to detect JSON requests and return JSON, otherwise return Inertia (for backward compat during transition). After migration, remove the Inertia return path.
2. **Create a new route** like `POST /api/entrytypes` or `POST /entrytype` that always returns JSON.

**Chosen: Option 1** — modify the existing method to return JSON when the request has an `Accept: application/json` header (which axios sends by default). This means:
- `axios.post('/add_new_entrytype', data)` → gets JSON response
- The old Inertia `useForm().post()` path can be removed from both EntryForm and Calendar once migration is complete
- The GET error-passback routes can eventually be removed too

### Step 3: Create `EntrytypeSelector.vue` component

**File:** `resources/js/Components/EntrytypeSelector.vue`

**Props:**
- `entrytypes` — Array of `{ id, name }` objects (the available types to show in select and search)
- `folderId` — Number (which folder new types belong to; 6 for calendar, dynamic for entries)
- `label` — String (default: `'Type:'`; e.g. `'Event Type:'` or folder-specific prompt)

**defineModel:**
- `modelValue` (the selected entrytype_id) — uses `defineModel()` for clean v-model binding

**Emits:**
- `typeCreated` — emitted with `{ id, name, folder_id }` when a new type is successfully created, so the parent can push it into its entrytypes array

**Internal state:**
- `entrytype_form` reactive object: `{ name, id, isChosen, chosenName }`
- `matchingTypes` computed/reactive filtered list
- Modal open/close state

**Template structure:**
```
<div class="flex items-baseline">
  <label>{{ label }}</label>
  <div>
    <select v-model="model"> ... </select>
    <button @click="openModal">Add New Type</button>
    <slot name="error" />     <!-- for InputError passthrough -->
  </div>
</div>

<dialog ref="modalRef">
  <!-- type name input with type-ahead lookup -->
  <!-- matching list -->
  <!-- Ok / Cancel buttons -->
</dialog>
```

**Key behaviors:**
- Type-ahead lookup filters the `entrytypes` prop (case-insensitive, max 6 results)
- Clicking a match selects it (sets `model` value, closes modal)
- Clicking Ok with a match: sets `model`, closes modal
- Clicking Ok without a match (new name): checks for case-insensitive duplicate in `entrytypes` prop first; if found, selects it. Otherwise, POSTs to `/add_new_entrytype` via axios, emits `typeCreated`, sets `model` to new id, closes modal
- Auto-focus on the name input when modal opens
- Cancel resets form and closes modal

**Using `defineModel()`:**
```js
const model = defineModel({ type: Number, default: null })
```
This replaces the `modelValue` prop + `update:modelValue` emit pattern. The parent uses `v-model` directly:
```vue
<EntrytypeSelector v-model="entry_form.entrytype_id" ... />
```

### Step 4: Update EntryForm.vue

**Remove:**
- `entrytype_form` useForm declaration
- `matching.entrytypes` reactive
- `lookup_entrytype()` function
- `clicked_matchingEntrytype()` function
- `clicked_entrytypeModal_button()` function
- `clicked_AddTypeButton()` function
- The `<dialog id="entrytype_modal">` template block
- The inline entrytype select + button template block

**Replace with:**
```vue
<div v-show="props.getFolderData('hide_entrytype_prompt') === false" class="flex items-baseline mt-5">
  <EntrytypeSelector
    v-model="entry_form.entrytype_id"
    :entrytypes="props.p1.folders[props.getFolderRow()].entrytypes"
    :folder-id="props.getFolderData('id')"
    :label="props.getFolderData('entrytype_prompt')"
    @type-created="onEntrytypeCreated"
  >
    <template #error>
      <InputError class="mt-1 ml-5" :message="entry_form.errors.entrytype_id" />
    </template>
  </EntrytypeSelector>
</div>
```

**Add handler:**
```js
function onEntrytypeCreated(newType) {
  // Push into the folder's entrytypes array so the select updates
  const row = props.getFolderRow();
  props.p1.folders[row].entrytypes.push(newType);
  props.p1.folders[row].entrytypes.sort((a, b) => a.name.localeCompare(b.name));
  entry_form.new_entrytype_added = true;
}
```

**Note:** The `v-show` and `checkEditMode()` logic stays in the parent since those are EntryForm-specific concerns. The `@blur` and `@change` for `checkEditMode()` could be handled by watching the model value in EntryForm, or by exposing a `@change` emit from the component.

### Step 5: Update Calendar/Index.vue

**Remove:**
- `entrytype_form` useForm declaration
- `matching.event_types` reactive
- `lookup_entrytype()` function
- `clicked_matchingEntrytype()` function
- `clicked_entrytypeModal_button()` function
- `clicked_AddTypeButton()` function
- The `<dialog id="entrytype_modal">` template block
- The inline entrytype select + button template block

**Replace with:**
```vue
<EntrytypeSelector
  v-model="calendar_form.entrytype_id"
  :entrytypes="eventTypes"
  :folder-id="6"
  label="Event Type:"
  @type-created="onEventTypeCreated"
/>
```

**Add:**
```js
// Make event_types reactive so we can push to it
const eventTypes = ref([...props.event_types]);

function onEventTypeCreated(newType) {
  eventTypes.value.push(newType);
  eventTypes.value.sort((a, b) => a.name.localeCompare(b.name));
}
```

**Note:** The calendar already uses axios for its own CRUD. The EntrytypeSelector using axios for its one POST is consistent with that pattern.

### Step 6: Clean up backend

- Remove `CalendarController::add_new_event_type()` method
- Remove routes for `/add_new_event_type` (both POST and GET) from `web.php`
- Remove `'new_event_type'` from `CalendarController::index()` props if it's passed there
- Update `EntryController::add_new_entrytype()` to return JSON when `$request->wantsJson()`:

```php
public function add_new_entrytype(Request $request): JsonResponse|InertiaResponse
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'folder_id' => 'required|numeric|integer',
    ]);

    // Check for existing type with same name in this folder
    $existing = Entrytype::where('firm_id', $request->user()->firm_id)
        ->where('folder_id', $validated['folder_id'])
        ->whereRaw('LOWER(name) = ?', [strtolower(trim($validated['name']))])
        ->first();

    if ($existing) {
        return response()->json($existing->only('id', 'name', 'folder_id'));
    }

    $entrytype = Entrytype::create([
        'firm_id' => $request->user()->firm_id,
        'folder_id' => $validated['folder_id'],
        'name' => $validated['name'],
    ]);

    return response()->json($entrytype->only('id', 'name', 'folder_id'));
}
```

### Step 7: Handle `checkEditMode()` in EntryForm

The current select has `@blur="checkEditMode()" @change="checkEditMode()"`. Options:
1. **Watch the model value** in EntryForm and call `checkEditMode()` when it changes
2. **Add a `@change` emit** to EntrytypeSelector that the parent can listen to

**Chosen: Option 1** — use a watcher in EntryForm:
```js
watch(() => entry_form.entrytype_id, () => {
    checkEditMode();
});
```
This is cleaner than threading an extra emit through the component.

---

## File Changes Summary

| File | Action |
|------|--------|
| `resources/js/Components/EntrytypeSelector.vue` | **Create** — new component |
| `resources/js/Pages/Entries/EntryForm.vue` | **Edit** — remove entrytype logic, use component |
| `resources/js/Pages/Calendar/Index.vue` | **Edit** — remove entrytype logic, use component |
| `app/Http/Controllers/EntryController.php` | **Edit** — return JSON for `add_new_entrytype` |
| `app/Http/Controllers/CalendarController.php` | **Edit** — remove `add_new_event_type()` |
| `routes/web.php` | **Edit** — remove `/add_new_event_type` routes |

## Considerations

- **`defineModel()`**: Fully supported in Vue 3.4+ (this project uses Vue 3). Provides clean `v-model` binding without manual prop/emit boilerplate. No issues expected.
- **Calendar's axios usage**: Not affected. The calendar continues to use axios for its own event CRUD. The EntrytypeSelector uses axios independently for its single POST call.
- **EntryForm as a component**: EntryForm is already a component used by Entries/Index and Views/Index. The EntrytypeSelector becomes a child component of EntryForm — no impact on how EntryForm is consumed.
- **Modal ID conflicts**: The current code uses `id="entrytype_modal"` in both files. The new component should use a `ref` instead of `getElementById` to avoid DOM ID collisions if both were ever on the same page. Use `const modalRef = ref(null)` and `<dialog ref="modalRef">`.
- **Error passback GET routes**: The GET route for `/add_new_entrytype` was for Inertia error passback. Since we're switching to JSON/axios, this may no longer be needed. Can be removed or kept for safety — it won't hurt.
- **Prop reactivity for entrytypes**: In EntryForm, the entrytypes come from `props.p1.folders[row].entrytypes`. When a new type is pushed to this array, the component's `entrytypes` prop updates reactively. In Calendar, we copy `props.event_types` to a local ref so we can mutate it.
