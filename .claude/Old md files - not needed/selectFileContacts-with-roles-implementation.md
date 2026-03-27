# Plan: Display Contact Roles in File Contacts Dialog

## Goal
When a user clicks the button to show the file contacts list in EntryForm, display each contact's role in the file alongside their name. Contacts without an assigned role show "Not specified".

## Current State
- `file_contacts` (from `getFileContacts()`): `[{ id, display_last_first }]` — built from entry from/to references, includes ALL contacts in the file's entries
- `file_contact_roles` (from `getFileContactRoles()`): `[{ id, contact_name, role_name, role, is_file_attorney, is_file_client }]` — only contacts with assigned roles, and `id` is the ContactRole id (not the contact id)
- The dialog currently iterates `props.p1.file_contacts` and displays only `display_last_first`
- `selectFileContact()` sets `entry_form.from_contact_id` / `to_contact_id` using the contact's `id`
- `findFileContact()` looks up `display_last_first` from `file_contacts` by contact id

## Changes

### 1. Backend: Add `contact_id` to `getFileContactRoles()`
**File:** `app/Http/Controllers/EntryController.php` (~line 970)

Add `'contact_id' => $cr->contact_id` to the mapped array so the frontend can match contacts to their roles.

```php
// BEFORE:
->map(fn ($cr) => [
    'id' => $cr->id,
    'contact_name' => $cr->contact?->display_last_first ?? '',
    ...

// AFTER:
->map(fn ($cr) => [
    'id' => $cr->id,
    'contact_id' => $cr->contact_id,
    'contact_name' => $cr->contact?->display_last_first ?? '',
    ...
```

### 2. Frontend: Add computed property to merge contacts with roles
**File:** `resources/js/Pages/Entries/EntryForm.vue`

Add a computed property (near other computed/utility code) that merges `file_contacts` with role data from `file_contact_roles`:

```js
const fileContactsWithRoles = computed(() => {
    if (!props.p1.file_contacts || !props.p1.file_contacts.length) return [];

    // Build a map of contact_id -> role_name from file_contact_roles
    const roleMap = {};
    if (props.p1.file_contact_roles && props.p1.file_contact_roles.length) {
        props.p1.file_contact_roles.forEach(cr => {
            // A contact may have multiple roles; collect them
            if (!roleMap[cr.contact_id]) {
                roleMap[cr.contact_id] = [];
            }
            roleMap[cr.contact_id].push(cr.role_name);
        });
    }

    return props.p1.file_contacts.map(contact => ({
        id: contact.id,
        display_last_first: contact.display_last_first,
        role_display: roleMap[contact.id] ? roleMap[contact.id].join(', ') : 'Not specified',
    }));
});
```

### 3. Frontend: Update the dialog template to use the computed list
**File:** `resources/js/Pages/Entries/EntryForm.vue` (~lines 1301-1311)

Replace the `v-for` to iterate `fileContactsWithRoles` instead of `props.p1.file_contacts`, and display the role alongside the contact name:

```html
<!-- BEFORE: -->
<div
    v-for="(contact, index) in props.p1.file_contacts"
    :key="index"
    @click="disp.show_contact_id = contact.id; selectFileContact()"
    class="px-4 py-2 hover:bg-base-200 cursor-pointer border-b border-base-300 last:border-b-0"
>
    {{ contact.display_last_first }}
</div>

<!-- AFTER: -->
<div
    v-for="(contact, index) in fileContactsWithRoles"
    :key="index"
    @click="disp.show_contact_id = contact.id; selectFileContact()"
    class="flex justify-between items-center px-4 py-2 hover:bg-base-200 cursor-pointer border-b border-base-300 last:border-b-0"
>
    <span>{{ contact.display_last_first }}</span>
    <span class="text-xs text-base-content/50">{{ contact.role_display }}</span>
</div>
```

The `contact.id` is still the contact id (from `file_contacts`), so `selectFileContact()` continues to work without changes.

### 4. Frontend: Update `findFileContact()` (if needed)
**File:** `resources/js/Pages/Entries/EntryForm.vue` (~line 697-699)

Comment out existing line and add new one searching the computed list. This keeps the original for easy rollback during testing:

```js
function findFileContact( find_contact_id ) {
    // return props.p1.file_contacts.find( (contact) => contact.id === find_contact_id ).display_last_first;
    return fileContactsWithRoles.value.find( (contact) => contact.id === find_contact_id )?.display_last_first ?? '';
}
```

**Note:** This change is only needed if `file_contacts` could be empty while `fileContactsWithRoles` has data, which shouldn't happen since the computed derives from `file_contacts`. So this may not be strictly necessary — but it consolidates lookups to one source. The original is preserved as a comment for testing.

## Files Modified
1. `app/Http/Controllers/EntryController.php` — add `contact_id` to `getFileContactRoles()` map
2. `resources/js/Pages/Entries/EntryForm.vue` — add computed property, update dialog template, optionally update `findFileContact()`

## Notes
- No new backend queries — uses existing `file_contacts` and `file_contact_roles` props
- A contact with multiple roles in the same file will show them comma-separated (e.g., "Client, Witness")
- `selectFileContact()` requires no changes since the computed list preserves the contact `id`
- This feature only applies when `file_view === 'file'` (Entries Index), where `file_contact_roles` is available
