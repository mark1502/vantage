<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed document base roots
    |--------------------------------------------------------------------------
    |
    | Absolute directories under which a firm's `document_base_path` must
    | reside. Firms store their documents on their own network shares, so in
    | production set DOCUMENT_ALLOWED_ROOTS to the mount point(s) of those
    | shares (comma-separated), e.g. "/mnt/firm-shares" on Linux.
    |
    | When this list is empty, no allow-list is enforced and only the
    | application-directory exclusion applies (see Firm::safeDocumentBasePath).
    | Leaving it empty is acceptable in local development; it SHOULD be set in
    | production so a firm admin cannot point the base at arbitrary server
    | locations.
    |
    */

    'allowed_roots' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DOCUMENT_ALLOWED_ROOTS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Reserved (non-file-specific) file id
    |--------------------------------------------------------------------------
    |
    | A single shared file record used by every firm as the bucket for entries
    | that are not tied to a real case/matter (memos, phone messages, to-dos,
    | calendar events). The file record itself is owned by one firm and is not
    | editable by others, but any firm may attach and view ITS OWN entries on
    | it. Isolation rides on each entry's `firm_id`, never on this file's owner.
    |
    */

    'reserved_file_id' => (int) env('RESERVED_FILE_ID', 1),

];
