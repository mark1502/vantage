# Contact Faux-Delete Implementation Plan

## Goal
Replace hard-delete of contacts with a `faux_deleted` flag. Deleted contacts are hidden by default from contact lists and lookups, but their data remains intact wherever referenced (entries, files, contact roles). Users can view deleted contacts and restore them.

## Key Decisions
- Use the existing `faux_deleted` boolean column on the `contacts` table (already in migration, default false)
- Contact roles on files are NOT affected by faux-delete (historical data preserved)
- Entry `from_contact_id` / `to_contact_id` references are NOT filtered (contact info still displays)
- Firm member contacts are not affected (they use `account_status` for active/inactive)
- No migration needed - column already exists

---

## Step 1: Contact Model - Add Scope

**File:** `app/Models/Contact.php`

Add a `scopeNotFauxDeleted` scope:

```php
/**
 * Scope query to exclude faux-deleted contacts.
 */
public function scopeNotFauxDeleted($query)
{
    return $query->where('faux_deleted', false);
}
```

This scope will be applied in contact listing and lookup queries. It is NOT applied globally (no global scope) so that entries/files can still load deleted contacts via their relationships.

---

## Step 2: ContactController - Modify `destroy()` Method

**File:** `app/Http/Controllers/ContactController.php` (lines 189-197)

Replace the hard delete with a faux-delete:

```php
public function destroy(Request $request, Contact $contact)
{
    $contact->update(['faux_deleted' => true]);

    return redirect(route('contacts.index', ['page' => $request->page, 'show' => $request->show]))
        ->with('success', 'Contact deleted successfully.');
}
```

---

## Step 3: ContactController - Add `restore()` Method

**File:** `app/Http/Controllers/ContactController.php`

Add a new method to restore a faux-deleted contact:

```php
public function restore(Request $request, Contact $contact)
{
    $contact->update(['faux_deleted' => false]);

    return redirect(route('contacts.index', [
        'page' => $request->page,
        'show' => $request->show,
        'filter' => $request->filter,
    ]))->with('success', 'Contact restored successfully.');
}
```

---

## Step 4: Add Restore Route

**File:** `routes/web.php`

Add a PATCH route for restoring contacts, inside the authenticated + welcomed middleware group:

```php
Route::patch('/contacts/{contact}/restore', [ContactController::class, 'restore'])->name('contacts.restore');
```

---

## Step 5: ContactController - Modify `index()` to Support Filter

**File:** `app/Http/Controllers/ContactController.php` (lines 22-40)

Add a `filter` parameter to the query. The filter values are:
- `current` (default) - `faux_deleted = false`
- `deleted` - `faux_deleted = true`
- `all` - no faux_deleted filter

```php
public function index(Request $request)
{
    $show = $request->show ?? 10;
    $filter = $request->query('filter', 'current');

    $contacts = Contact::query()
        ->select('id', 'title', 'last_name', 'first_name', 'middle_name', 'srjr', 'esqphd',
                 'company', 'business_title', 'display_name', 'display_last_first', 'address',
                 'email', 'email_alt', 'home_phone', 'work_phone', 'cell_phone', 'fax_phone',
                 'other_phone', 'note', 'faux_deleted')
        ->with('files:id,name')
        ->where('firm_id', $request->user()->firm_id)
        ->where('is_firm_member', '=', false)
        ->when($filter === 'current', fn($query) => $query->where('faux_deleted', false))
        ->when($filter === 'deleted', fn($query) => $query->where('faux_deleted', true))
        // 'all' applies no faux_deleted filter
        ->when($request->query('search'), fn($query, $search) =>
            $query->where('display_last_first', 'like', $search . '%')
        )
        ->orderBy('display_last_first')
        ->paginate($show)->onEachSide(2)
        ->withQueryString();

    return Inertia::render('Contacts/Index', compact('contacts', 'filter'));
}
```

Note: `faux_deleted` is added to the select so the frontend knows each contact's status. The `filter` value is passed to the frontend.

---

## Step 6: EntryController - Filter `lookup_contact()` 

**File:** `app/Http/Controllers/EntryController.php` (lines 377-394)

Add `->where('faux_deleted', false)` to the lookup query so deleted contacts don't appear in entry form lookups:

```php
public function lookup_contact(Request $request)
{
    $contacts_found = Contact::query()
        ->select('id', 'display_last_first')
        ->where('firm_id', $request->user()->firm_id)
        ->where('faux_deleted', false)                          // exclude faux-deleted contacts
        ->when($request->firm_only == true, function ($query) {
            $query->where('is_firm_member', '=', true);
        })
        ->where('display_last_first', 'like', $request->search.'%')
        ->orderBy('display_last_first')
        ->paginate(8)
        ->withQueryString();

    return $contacts_found;
}
```

---

## Step 7: EntryController - Filter `getFileContacts()`

**File:** `app/Http/Controllers/EntryController.php` (lines 846-861)

Add `->where('faux_deleted', false)` to the file contacts query so deleted contacts don't appear in the file contacts dropdown used by ContactLookup. Note: this uses `DB::table` not Eloquent, so we add the where clause directly:

```php
$file_contacts = DB::table('contacts')
    ->select('id', 'display_last_first')
    ->where('faux_deleted', false)
    ->where(function ($query) use ($from_contacts, $to_contacts) {
        $query->whereIn('id', $from_contacts)
              ->orWhereIn('id', $to_contacts);
    })
    ->get();
```

Note: The existing query has a scoping issue (`whereIn` + `orWhereIn` without grouping). Wrapping them in a closure fixes that and adds the faux_deleted filter correctly.

---

## Step 8: Contacts/Index.vue - Add Filter Toggle and Restore Button

**File:** `resources/js/Pages/Contacts/Index.vue`

### 8a. Accept `filter` prop

```js
const props = defineProps({ contacts: Object, filter: String });
```

### 8b. Add reactive filter state

```js
const activeFilter = ref(props.filter || 'current');
```

### 8c. Add filter change function

```js
function filterChanged(newFilter) {
    activeFilter.value = newFilter;
    router.get('/contacts', { filter: newFilter, show: state.show }, {
        preserveState: true,
        replace: true,
        onSuccess: () => {
            state.current_row = 0;
            update_disp();
        },
    });
}
```

### 8d. Update search watcher to include filter

In the existing `watch(search, ...)`, include `filter: activeFilter.value` in the router.get params.

### 8e. Add filter toggle UI

Above the search bar (or alongside it), add a segmented button group:

```html
<div class="flex gap-1">
    <button class="btn btn-sm" :class="activeFilter === 'current' ? 'btn-primary' : 'btn-ghost'"
        @click="filterChanged('current')">Current</button>
    <button class="btn btn-sm" :class="activeFilter === 'deleted' ? 'btn-primary' : 'btn-ghost'"
        @click="filterChanged('deleted')">Deleted</button>
    <button class="btn btn-sm" :class="activeFilter === 'all' ? 'btn-primary' : 'btn-ghost'"
        @click="filterChanged('all')">All</button>
</div>
```

### 8f. Conditionally show Delete vs Restore button

Replace the existing delete button with conditional logic:

- When `filter === 'current'`: Show "Delete" button (as today)
- When `filter === 'deleted'`: Show "Restore" button instead of Delete
- When `filter === 'all'`: Show "Delete" if contact is not faux_deleted, "Restore" if it is

The restore button calls a new `restoreContact()` function:

```js
function restoreContact() {
    router.patch(route('contacts.restore', {
        contact: contact1.value.id,
        page: props.contacts.current_page,
        show: state.show,
        filter: activeFilter.value,
    }));
}
```

### 8g. Visual indicator for deleted contacts

When viewing "all" contacts, add a subtle visual indicator (e.g., strikethrough text or a small badge) on faux-deleted contacts so the user can distinguish them at a glance.

### 8h. Update delete modal text

Update the delete confirmation dialog text to say something like: "Remove contact from active list?" to reflect that the contact is being hidden, not permanently destroyed.

---

## Step 9: Update `showChanged()` and navigation links

Ensure the `filter` query parameter is preserved across pagination, show-count changes, and other navigation. The `withQueryString()` on the backend handles pagination links. For manual navigation:

- `showChanged()`: Include `filter: activeFilter.value` in the route params
- `state.createurl`: Include `filter: activeFilter.value`
- `state.editurl`: Include `filter: activeFilter.value`

---

## Step 10: Contacts/Edit.vue - Show deleted status banner

**File:** `resources/js/Pages/Contacts/Edit.vue`

If the contact being edited is faux_deleted, show a small info banner at the top: "This contact has been deleted. You can restore it from the contacts list."

This requires passing `faux_deleted` in the `edit()` method's contact data (ContactController line 104-129).

---

## Files Modified (Summary)

| File | Change |
|------|--------|
| `app/Models/Contact.php` | Add `scopeNotFauxDeleted` |
| `app/Http/Controllers/ContactController.php` | Modify `destroy()`, `index()`; add `restore()` |
| `app/Http/Controllers/EntryController.php` | Filter `lookup_contact()` and `getFileContacts()` |
| `routes/web.php` | Add `contacts.restore` route |
| `resources/js/Pages/Contacts/Index.vue` | Filter toggle UI, restore button, visual indicators |
| `resources/js/Pages/Contacts/Edit.vue` | Deleted status banner |

## Files NOT Modified (by design)

- Entry display pages - deleted contacts still show their names
- File pages - contact roles and associations remain visible
- ContactRole model - no changes
- AddContactForm component - new contacts aren't deleted
- ContactLookup component - server-side filtering handles this
