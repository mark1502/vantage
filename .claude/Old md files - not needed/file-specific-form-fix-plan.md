# Plan: "Not File Related" Support for EntryForm.vue in View Mode

## Context

When EntryForm.vue is used from Views/Index.vue (memos, events, todos, phone), the form currently requires a file to be selected via FileLookup. There's no way to create entries that aren't tied to a specific file. The reserved file `id=1` ("Not File Specific") already exists in the database (seeded by FileSeeder) and is used by the Calendar for the same purpose. This plan adds a "File Related" / "Not File Related" radio toggle to the form when in view mode, following the Calendar's established pattern.

**Scope:** Folders 5 (Memos), 6 (Events), 7 (Todos), 8 (Phone) — all accessed via Views/Index.vue.  
**Not affected:** EntryForm used from Entries/Index.vue (`file_view='file'`) — always file-related, no toggle shown.

---

## Files to Modify

1. `resources/js/Pages/Entries/EntryForm.vue` — main UI and logic changes
2. `app/Http/Controllers/ViewController.php` — guard contact role saving
3. `app/Http/Requests/StoreViewRequest.php` — make file_id required

**No changes needed:** `Views/Index.vue` (list tables don't display file names), `ContactLookup.vue` (firm-only rules already apply by folder)

---

## Implementation Steps

### Step 1: Add `fileSpecific` ref — EntryForm.vue (~line 34)

Add alongside other reactive declarations:
```js
const fileSpecific = ref("true");
```
Uses string `"true"`/`"false"` to match HTML radio value convention (same as Calendar pattern).

---

### Step 2: Replace file selection template — EntryForm.vue (lines 1003–1021)

**When adding (`entry_add` mode, `file_view === 'view'`):** Replace the simple FileLookup block with radio buttons + conditional FileLookup:

```html
<div v-if="props.file_view === 'view' && state.mode === 'entry_add'" class="flex items-baseline mb-4">
    <label class="text-sm font-semibold w-28">File:</label>
    <div>
        <div class="flex items-baseline">
            <input type="radio" id="fileSpecific" v-model="fileSpecific" value="true" />
            <label for="fileSpecific" class="ml-1 text-sm"> - File Related</label>
        </div>
        <div v-if="fileSpecific === 'true'" class="ml-4 mt-1">
            <FileLookup_form
                v-model:file_id="entry_form.file_id"
                id="file_lookup_input"
                :state="state"
            />
        </div>
        <div class="flex items-baseline pt-2">
            <input type="radio" id="not_fileSpecific" v-model="fileSpecific" value="false" />
            <label for="not_fileSpecific" class="ml-1 text-sm"> - Not File Related</label>
        </div>
    </div>
</div>
```

Key: Use `v-if` (not `v-show`) for FileLookup_form so it remounts fresh when toggling back — the component doesn't watch its model for external resets.

**When browsing/editing (`file_view === 'view'`, not adding):** Update disabled display to handle file_id===1:

```html
<div v-else-if="props.file_view === 'view'">
    <div class="flex items-baseline mb-4">
        <label for="display_casename" class="text-sm font-semibold w-28">File:</label>
        <input :value="entry_form.file_id === 1 ? 'Not File Related' : display_name.file"
            id="display_casename" autocomplete="off" disabled
            class="input input-sm text-sm rounded-sm w-64 disabled:border-base-300 disabled:text-base-content"/>
    </div>
</div>
```

---

### Step 3: Add watcher on `fileSpecific` — EntryForm.vue (~line 250)

```js
watch(fileSpecific, (newVal) => {
    if (newVal === "false") {
        entry_form.file_id = 1;
    } else {
        entry_form.file_id = null;
    }
});
```

---

### Step 4: Reset `fileSpecific` in `setup_add()` — EntryForm.vue (~line 900)

After `entry_form.reset()` (line 899), add:
```js
fileSpecific.value = "true";
```

---

### Step 5: Set `fileSpecific` in `update_disp()` — EntryForm.vue (~line 661)

After `entry_form.file_id = theEntry.file_id` (line 661), add:
```js
fileSpecific.value = (theEntry.file_id !== 1) ? "true" : "false";
```

At line 662, update display name:
```js
display_name.file = (theEntry.file_id === 1) ? 'Not File Related' : getFileName();
```

---

### Step 6: Guard contact-role logic for file_id === 1 — EntryForm.vue

**6a.** Watch on `entry_form.file_id` (line 253): After `if (suppress_role_check || !newVal) return;`, add:
```js
if (newVal === 1) return;
```

**6b.** `checkContactRole()` function (line 615): After `if (!contact_id) return;`, add:
```js
if (entry_form.file_id === 1) return;
```

---

### Step 7: Update `getFileName()` — EntryForm.vue (line 811)

Add at the start of the function:
```js
if (props.p1.entries.data[props.state.row]?.file_id === 1) return 'Not File Related';
```

---

### Step 8: Guard `savePendingContactRoles` — ViewController.php

**store()** (line 134): Wrap in guard:
```php
if ($entry->file_id !== 1) {
    $this->savePendingContactRoles($request, $entry->file_id);
}
```

**update()** (line 207): Same guard:
```php
if ($entry->file_id !== 1) {
    $this->savePendingContactRoles($request, $entry->file_id);
}
```

---

### Step 9: Update validation — StoreViewRequest.php (line 25)

Change:
```php
'file_id' => 'numeric|integer|nullable',
```
To:
```php
'file_id' => 'required|integer',
```

Since view entries now always have a file_id (either a real file or 1).

---

## Verification

1. **Add a memo with a file** — select "File Related", pick a file, add from/to contacts → role modal should appear, entry saves with correct file_id
2. **Add a memo without a file** — select "Not File Related" → FileLookup hides, add from/to contacts → role modal should NOT appear, entry saves with file_id=1
3. **Browse a non-file entry** — file field shows "Not File Related" (disabled), editing other fields works normally
4. **Browse a file-related entry** — file field shows file name (disabled), as before
5. **Repeat for phone, todo, and events** — same behavior across all four view types
6. **Test from Entries/Index.vue** — no toggle should appear, behavior unchanged
7. **Verify no ContactRole records** created for file_id=1 in the database after saving non-file entries
