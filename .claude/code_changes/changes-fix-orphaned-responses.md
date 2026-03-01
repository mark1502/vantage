# Fix: Prevent Deletion of Entries That Have Responses

## Problem
When a user deletes an entry that other entries are responsive to, the response records and responsive entries become orphaned.

## Changes Made

### 1. `app/Http/Controllers/EntryController.php` (line 49)
- Added `.with('entry:id,date1,folder_id', 'entry.folder:id,name')` to the `responses_received` eager load, so each response now includes the responding entry's date and folder name.

### 2. `resources/js/Pages/Entries/Index.vue`
- **Delete button** (line 734): Changed from directly opening `confirm_delete` modal to calling `handle_delete_click()`.
- **New `handle_delete_click()` function**: Checks if the current entry has `responses_received`. If yes, opens the warning dialog. If no, opens the existing confirm delete modal.
- **New `has_responses_data` ref**: Holds the response data for the warning modal.
- **New `<dialog>` warning modal**: Shows "Cannot Delete This Entry" with a table listing each linked response (type, response date, entry date, folder name) and a single OK button to close.
