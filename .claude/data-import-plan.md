# Data Import Feature — Implementation Plan

## Overview

Admin-only feature allowing a firm to bulk-import data from CSV, TSV, JSON, XLSX, or ODS files into the `files` and `contacts` tables. Two separate import flows (files vs external contacts) share the same wizard infrastructure but have different mapping targets, validation rules, and duplicate-resolution logic.

User experience is a 4-step wizard:

1. **Upload** — pick import target (files or contacts), upload the source file.
2. **Map Fields** — match source columns/keys to Vantage fields; resolve distinct-value mappings for filetype/attorney; build `display_name` rule for contacts.
3. **Resolve** — review duplicates (in-source and against DB), choose include-with-suffix or skip, set defaults for missing filetype/attorney.
4. **Confirm & Import** — show preview of first N transformed rows + summary counts; on confirm, queue the import job and poll for progress.

## Preconditions Shown to the User Before Upload

A pre-flight checklist screen must be shown before file upload:

- All firm members / attorneys have been created in **Users**.
- All file types have been created in **File Types**.
- For contact import: confirm this is for **external contacts only** (firm members are managed through Users).

A checkbox "I have completed the above" gates the upload step.

---

## Scope Decisions (locked)

| Topic | Decision |
| --- | --- |
| Duplicate definition — files | Case-insensitive `name` match. Unique within source file. Unique within DB scoped to `firm_id` (matches existing `files.unique(firm_id, name)`). |
| Duplicate definition — contacts | Case-insensitive `display_name` match. Unique within source file. Unique within DB scoped to `firm_id` (matches existing `contacts.unique(firm_id, display_name)`). |
| Duplicate resolution UX | Bulk action: **Include all with suffix** OR **Skip all duplicates**. Per-row override available on a duplicates review screen. Final error/skip report downloadable as CSV at end. |
| display_name construction | Mirror the existing JS `buildDisplayNames()` logic from `resources/js/Pages/Contacts/Create.vue` and `Edit.vue`. Port it to a PHP helper (single source of truth, reusable). |
| Sync vs queued | Parse + preview on a sampled subset synchronously. Full import runs as a queued job; Inertia v2 polling drives a progress UI. |
| Partial failure | Continue on row-level errors. Collect errors. Show downloadable error report at end. No transactional rollback of the whole batch. |
| Filetype / attorney resolution | When source data contains these values, present a distinct-value-to-Vantage-id mapping screen (not per-row). User also picks a fallback default for unmapped/blank values. When source data does NOT contain them, user picks a single default applied to all imported files. |
| Library | `phpoffice/phpspreadsheet` (handles xlsx + ods). CSV/TSV/JSON parsed natively. |
| Authorization | Admin only — gate via `user_type === 'Admin'` check (matches existing admin route conventions). |
| Multi-tenancy | Every imported row gets `firm_id = auth()->user()->firm_id`. Firm scoping enforced on all queries. |
| Resumability | Wizard state stored in a temporary `imports` table keyed by `user_id`. User can leave and return. |

---

## Architecture

### Database

New table `imports`:

| Column | Type | Notes |
| --- | --- | --- |
| `id` | PK | |
| `firm_id` | unsignedInt | |
| `user_id` | unsignedInt | who initiated |
| `target` | string | `'files'` or `'contacts'` |
| `original_filename` | string | |
| `stored_path` | string | path within `storage/app/imports/` |
| `source_format` | string | `csv`, `tsv`, `json`, `xlsx`, `ods` |
| `status` | string | `uploaded`, `mapping`, `resolving`, `previewing`, `queued`, `running`, `completed`, `failed`, `abandoned` |
| `mapping` | json | source-field → vantage-field |
| `value_maps` | json | filetype/attorney distinct-value → id; default fallbacks |
| `duplicate_strategy` | json | `{ in_source: 'suffix'\|'skip', vs_db: 'suffix'\|'skip', overrides: { rowIndex: action } }` |
| `total_rows` | int | |
| `imported_count` | int | |
| `skipped_count` | int | |
| `error_count` | int | |
| `error_report_path` | string nullable | CSV path |
| `created_at`/`updated_at` | timestamps | |

No new columns needed on `files` or `contacts`.

### Backend Components

```
app/
  Http/
    Controllers/
      Admin/
        ImportController.php       # wizard endpoints
    Requests/
      Imports/
        StoreImportRequest.php
        UpdateMappingRequest.php
        UpdateResolutionRequest.php
        RunImportRequest.php
  Models/
    Import.php
  Services/
    Import/
      Parsers/
        ParserInterface.php
        CsvParser.php
        TsvParser.php
        JsonParser.php
        SpreadsheetParser.php      # xlsx + ods via phpspreadsheet
        ParserFactory.php
      FieldMaps/
        FileFieldMap.php           # vantage field list + validators for files target
        ContactFieldMap.php        # vantage field list + validators for contacts target
      DisplayNameBuilder.php       # PHP port of buildDisplayNames()
      DuplicateScanner.php
      RowTransformer.php           # maps a source row → eloquent attributes
      ImportPreviewer.php          # sync preview on first N rows
      ImportRunner.php             # invoked by job, processes all rows
  Jobs/
    RunImportJob.php
```

### Frontend Components

```
resources/js/Pages/Admin/Imports/
  Index.vue              # list of past/in-progress imports
  Start.vue              # pre-flight + target picker + upload (step 1)
  Map.vue                # field mapping + distinct-value mapping (step 2)
  Resolve.vue            # duplicates + defaults (step 3)
  Preview.vue            # final preview + confirm (step 4)
  Progress.vue           # polling progress + final report download
```

### Routes (admin-gated)

```
GET    /admin/imports                       # Index
GET    /admin/imports/create                # Start
POST   /admin/imports                       # store upload, parse headers, advance to map
GET    /admin/imports/{import}/map
PUT    /admin/imports/{import}/map
GET    /admin/imports/{import}/resolve
PUT    /admin/imports/{import}/resolve
GET    /admin/imports/{import}/preview
POST   /admin/imports/{import}/run          # dispatch job
GET    /admin/imports/{import}/progress     # polled by Progress.vue
GET    /admin/imports/{import}/errors       # download error CSV
DELETE /admin/imports/{import}              # abandon / cleanup
```

---

## Vantage Field Targets

### Files target

Required: `name`, `filetype_id`, file-attorney (`contact_id` for ContactRole with `is_file_attorney=true`).

Optional, directly mappable from source columns:
`summary`, `date_sol`, `date_opened`, `date_filed`, `date_closed`, `date_archived`, `court_filed`, `docket_number`, `file_number`, `referred_by`, `referral_amount`, `fee_arrangement`, `fee_amount`, `final_disposition`.

Special-handling targets (mapped, but resolved through value-mapping screen rather than raw assignment):
- **filetype** — source column resolved to a `filetypes.id` via distinct-value map + default.
- **attorney** — source column resolved to a `contacts.id` (firm member) via distinct-value map + default. Written as a `contact_roles` row with `is_file_attorney = true` after the `files` row is inserted.

### Contacts target (external contacts only)

Direct mappable columns: `title`, `first_name`, `middle_name`, `last_name`, `srjr`, `esqphd`, `company`, `business_title`, `address`, `email`, `email_alt`, `home_phone`, `work_phone`, `cell_phone`, `fax_phone`, `other_phone`, `note`.

Computed:
- `display_name` and `display_last_first` — generated by `DisplayNameBuilder` from the mapped name fields, mirroring the existing JS logic exactly.
- `firm_id` — from authed user.
- `is_firm_member` — forced to `false`.
- `account_status` — forced to `'N'`.
- `faux_deleted` — forced to `false`.

User must map enough of: `last_name`, `first_name`, or `company` for `display_name` to be non-empty.

---

## Wizard Flow Detail

### Step 1 — Upload (`Start.vue`)

- Pre-flight checklist + acknowledgement.
- Target radio: **Files** | **External Contacts**.
- File input (accept: `.csv,.tsv,.json,.xlsx,.ods`).
- On submit:
  - Validate mime + size (cap e.g. 10 MB).
  - Store file to `storage/app/imports/{import_id}/source.{ext}`.
  - Use `ParserFactory` to detect headers (first row or top-level JSON keys).
  - Create `imports` row with `status='mapping'`.
  - Redirect to map step.

### Step 2 — Field Mapping (`Map.vue`)

- Two-column UI: left = source fields (detected headers), right = Vantage fields (from `FileFieldMap` or `ContactFieldMap`).
- User maps each Vantage field to at most one source field (some Vantage fields can be left unmapped if optional).
- For **files** target: when user maps the special "filetype" or "attorney" Vantage slots, a sub-panel reveals on save showing distinct values from that source column with a dropdown to map each to a Vantage `filetype` / firm-member, plus a "default for unmapped" selector.
- For **contacts** target: must map at least one of `last_name` / `first_name` / `company`.
- Save persists `mapping` + `value_maps` json on `imports` row, status → `resolving`.

### Step 3 — Resolve (`Resolve.vue`)

`DuplicateScanner` runs across **all** source rows (full parse, not sample) before this screen renders. It produces:

- `in_source_duplicates` — groups of rows in the upload sharing the same case-insensitive key.
- `vs_db_duplicates` — source rows whose key matches an existing DB row for this firm.

UI sections:

1. **Bulk strategy** — two radio groups:
   - In-source dupes: `Include all with suffix` (default) | `Skip all`.
   - DB dupes: `Include with suffix` | `Skip all` (default).
2. **Per-row override table** — paginated table of all flagged rows; each row has its own action override + an "edit this value to make unique" inline input.
3. **Defaults** — when filetype / attorney source columns are absent or have blanks, show the default-selector here.
4. Suffix scheme is `" (n)"` starting at 2, incrementing until unique against the combined target set (source ∪ DB).

Save persists `duplicate_strategy` on `imports` row, status → `previewing`.

### Step 4 — Preview & Confirm (`Preview.vue`)

- `ImportPreviewer` transforms the first 25 rows using mapping + value_maps + duplicate strategy and shows them in a table of final Vantage-shaped columns.
- Summary: total rows, will-import count, will-skip count, will-suffix count, flagged rows count.
- **Import** button → POST to run endpoint → dispatch `RunImportJob` → redirect to Progress.

### Step 5 — Progress (`Progress.vue`)

- Inertia v2 polling every 2s on `/admin/imports/{import}/progress`.
- Shows progress bar based on `imported_count + skipped_count + error_count` / `total_rows`.
- On `completed` or `failed`: show summary + link to download error/skip report CSV.

---

## Import Job — `RunImportJob`

Pseudocode:

```
foreach row in parsed source:
  transformed = RowTransformer::transform(row, mapping, value_maps, target)
  if row was marked skip in duplicate_strategy: increment skipped; continue
  if row needs suffix: apply suffix to key field
  try:
      validate transformed against target rules
      DB::transaction(fn() => persist row, plus contact_roles entries for files):
  catch ValidationException | QueryException:
      increment error_count; append to error CSV
  update imports.imported_count periodically (every 50 rows) for UI polling
mark import status completed
finalize error CSV path
```

Notes:

- Job uses Laravel's `ShouldQueue` interface and runs on the `database` queue.
- For `files`: after inserting the file, write a `contact_roles` row with `is_file_attorney=true` for the resolved attorney contact.
- All inserts go through Eloquent (per `laravel-boost-guidelines`: prefer `Model::query()` over `DB::`).

---

## Phases

### Phase 1 — Foundation
- Add `phpoffice/phpspreadsheet` to composer.
- Create `imports` migration + `Import` model.
- Build `ParserInterface` + `CsvParser`, `TsvParser`, `JsonParser`, `SpreadsheetParser`, `ParserFactory`. Each returns: list of header field names, plus an iterable of associative-array rows.
- Build `FileFieldMap` and `ContactFieldMap` (PHP classes exposing field metadata: key, label, required, validation rule).
- Port `buildDisplayNames()` to `DisplayNameBuilder` PHP service.

### Phase 2 — Wizard scaffolding
- `ImportController` with all wizard routes.
- Form Requests.
- Admin route group + policy/middleware check.
- `Start.vue` (pre-flight + upload).
- `Index.vue` (list imports).

### Phase 3 — Mapping UI
- `Map.vue` with field-mapping two-column UI.
- Distinct-value mapper sub-component for filetype/attorney (files target only).
- Backend: persist `mapping` and `value_maps`.

### Phase 4 — Duplicate resolution
- `DuplicateScanner` service.
- `Resolve.vue` with bulk strategies + per-row overrides + defaults.
- Suffix-generation logic.

### Phase 5 — Preview + transform
- `RowTransformer` service (one method handles either target, branching on `target`).
- `ImportPreviewer` (first 25 rows).
- `Preview.vue`.

### Phase 6 — Queued import + progress
- `RunImportJob`.
- `Progress.vue` with Inertia v2 polling.
- Error/skip CSV writer.
- Download endpoint.

### Phase 7 — Polish
- Tighten validation messages, error CSV columns, cancel/abandon flow, cleanup of orphaned uploaded files (scheduled task).

---

## Open Questions (for confirmation before Phase 1)

1. **Max file size** — propose 10 MB; OK?
2. **Max rows per import** — propose hard cap at 25,000 to keep memory + queue time predictable; OK?
3. **Date parsing** — accept any format Carbon can parse, fall back to per-row error if unparseable? Or require user to specify a date format on the mapping step?
4. **`firm_role` for attorney lookup** — when matching the attorney value-map dropdown, should we restrict the list to active firm members where `firm_role = 'Attorney'`, or include all active firm members?
5. **Past imports view** — do you want a history page (Index.vue) listing prior imports with counts and a re-download-errors link, or only show in-progress ones?

---

## Out of Scope

- Importing entries, folders, entry types (firm members are also out — managed via Users).
- Updating existing records (this is insert-only; duplicates either skip or insert-with-suffix as a new row).
- Round-tripping (no export-then-import workflow).
- Field transformations beyond date normalization (no concatenation, splitting, regex extraction in v1).
