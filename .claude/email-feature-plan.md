# Email Integration Feature Plan

## Overview

Add email sending and reading to Vantage so users can manage emails associated with files directly within the application. Emails are stored and displayed separately from entries, but a summary entry (Correspondence folder, "Email" entrytype) is created to link each email into the file's entry timeline.

**Core principles:**
- Read-only access to the mailbox (no deleting or moving emails on the server)
- Outbound emails embed the file number for easy future matching
- Human-in-the-loop confirmation — emails are only added to the system when a user assigns them to a file
- Incremental sync after initial setup
- Full email bodies stored locally only for file-linked emails (not the entire mailbox)
- This is not a primary email client — no spam handling, no drafts, no mailbox management

---

## Current State (What Already Exists)

- **"Email" entrytype** already exists under the Correspondence folder (folder_id=1)
- **`contacts.email`** and **`contacts.email_alt`** fields exist for matching
- **`contact_roles`** table links contacts to files with roles (client, attorney, etc.)
- **`entries`** table has `from_contact_id`, `to_contact_id`, `note` (5000 chars), `date1`
- **`filetypes`** table has `has_*` booleans controlling which folders appear per file type
- **`files.file_number`** exists and will be embedded in outbound email subjects

### Limitations of Current Schema for Email
- No place to store email message IDs, thread IDs, or subjects
- `note` field is 5000 chars — suitable for an email summary but not full bodies
- No email account configuration storage
- No staging/review workflow tables
- `entries` only supports `from_contact_id` and `to_contact_id` — no CC/BCC fields

### FK Type Note
Existing tables (`firms`, `users`, `files`, `contacts`, `entries`, etc.) use `unsignedInteger` for their primary keys. New email tables use `unsignedBigInteger` for their own PKs (to accommodate high email volumes) but must use `unsignedInteger` for all FKs referencing existing tables to match their PK types.

---

## Data Model

### New Tables

#### 1. `email_accounts`
Stores each user's email provider configuration and sync state.

```
email_accounts
├── id                          unsignedBigInteger PK
├── firm_id                     unsignedInteger FK
├── user_id                     unsignedInteger FK (unique - one account per user)
├── email_address               string(255)
├── display_name                string(255) nullable — name shown on outbound emails
├── provider                    string(50) — 'gmail', 'microsoft', 'other'
├── imap_host                   string(255) nullable — only for 'other' provider
├── imap_port                   unsignedSmallInteger nullable (default 993)
├── imap_encryption             string(10) nullable — 'ssl', 'tls'
├── smtp_host                   string(255) nullable — only for 'other' provider
├── smtp_port                   unsignedSmallInteger nullable (default 587)
├── smtp_encryption             string(10) nullable — 'tls', 'ssl'
├── auth_type                   string(20) — 'oauth2', 'password'
├── oauth_access_token          text nullable (encrypted) — Gmail/Microsoft
├── oauth_refresh_token         text nullable (encrypted) — Gmail/Microsoft
├── oauth_token_expires_at      datetime nullable
├── imap_username               string(255) nullable (encrypted) — only for 'other'
├── imap_password               string(255) nullable (encrypted) — only for 'other'
├── smtp_username               string(255) nullable (encrypted) — only for 'other'
├── smtp_password               string(255) nullable (encrypted) — only for 'other'
├── is_active                   boolean default true
├── last_synced_at              datetime nullable
├── last_synced_uid             string(255) nullable — IMAP UID or API page token/cursor
├── initial_scan_status         string(20) default 'pending'
│                               — 'pending', 'scanning', 'review', 'completed'
├── initial_scan_start_date     date nullable — how far back to scan
├── timestamps
```

#### 2. `email_messages`
Stores full email data for emails that have been linked to a file. During scanning, only metadata (subject, addresses, dates) is stored temporarily in `email_scan_matches`. Once a user confirms a match, the full message body is fetched and stored here permanently as the authoritative record.

```
email_messages
├── id                          unsignedBigInteger PK
├── firm_id                     unsignedInteger FK
├── email_account_id            unsignedBigInteger FK
├── file_id                     unsignedInteger FK — the file this email belongs to
├── entry_id                    unsignedInteger FK nullable — linked summary entry
├── message_id                  string(512) — RFC Message-ID header (globally unique)
├── in_reply_to                 string(512) nullable — for threading
├── thread_id                   string(255) nullable — provider thread ID if available
├── subject                     string(1000)
├── from_address                string(255)
├── from_name                   string(255) nullable
├── to_addresses                json — array of {address, name}
├── cc_addresses                json nullable
├── bcc_addresses               json nullable — stored for outbound emails
├── body_html                   longText nullable — sanitized HTML body
├── body_text                   longText nullable — plain text body
├── date_sent                   datetime
├── snippet                     string(500) nullable — preview text for list views
├── has_attachments             boolean default false
├── direction                   string(10) — 'inbound', 'outbound'
├── provider_uid                string(255) nullable — Gmail message ID, Graph message ID, or IMAP UID
├── raw_headers                 text nullable — stored for debugging/future parsing
├── timestamps
│
├── UNIQUE INDEX (email_account_id, message_id)
```

#### 3. `email_scan_matches`
Staging table for the scan review process. Each row represents a potential match between an email and a file, awaiting user confirmation. Multiple rows can exist for the same email (one per candidate file), but only one can be confirmed — an email belongs to exactly one file.

Metadata about the email is stored directly here during scanning. The full message body is only fetched and stored in `email_messages` after the user confirms which file the email belongs to.

```
email_scan_matches
├── id                          unsignedBigInteger PK
├── firm_id                     unsignedInteger FK
├── email_account_id            unsignedBigInteger FK
├── scan_message_id             string(512) — RFC Message-ID of the scanned email
├── scan_subject                string(1000) — email subject (for display during review)
├── scan_from_address           string(255)
├── scan_from_name              string(255) nullable
├── scan_to_addresses           json nullable
├── scan_date_sent              datetime
├── scan_snippet                string(500) nullable — preview text
├── scan_has_attachments        boolean default false
├── scan_direction              string(10) — 'inbound', 'outbound'
├── scan_provider_uid            string(255) nullable — Gmail/Graph message ID or IMAP UID; needed to fetch full body later
├── file_id                     unsignedInteger FK nullable — suggested file match
├── match_reason                string(255) — e.g. 'contact_email', 'file_number_in_subject',
│                                              'client_name_in_body', 'docket_number'
├── match_confidence            unsignedTinyInteger — 0-100 score for sorting/thresholding
├── status                      string(20) default 'pending'
│                               — 'pending', 'confirmed', 'reassigned', 'rejected'
├── confirmed_file_id           unsignedInteger FK nullable — set if user reassigns to different file
├── email_message_id            unsignedBigInteger FK nullable — set after email_message is created
├── reviewed_at                 datetime nullable
├── timestamps
│
├── INDEX (email_account_id, scan_message_id) — for dedup during scanning
```

**Note:** The previous `email_entries` pivot table has been removed. The link between an email and its summary entry is handled directly via `email_messages.entry_id` and `entries` having a `hasOne EmailMessage` relationship. This simplifies the data model — the `email_messages` table is the authoritative record for file-linked emails, and the entry is just a summary pointer in the file's timeline.

### Changes to Existing Tables

#### `filetypes` — add column
```
has_email                       boolean default true
```

#### `entries.note` — no change needed
Full email bodies are stored in `email_messages.body_html` / `body_text`. The entry's `note` field (5000 chars) holds only a summary: subject line, sender/recipient info, CC/BCC indicators, and a brief snippet. This is sufficient for the timeline view.

### New Models

| Model | Table | Key Relationships |
|---|---|---|
| `EmailAccount` | `email_accounts` | belongsTo User, Firm; hasMany EmailMessage |
| `EmailMessage` | `email_messages` | belongsTo EmailAccount, File, Entry; hasMany EmailScanMatch |
| `EmailScanMatch` | `email_scan_matches` | belongsTo EmailAccount; belongsTo File (nullable) |

### Relationship Additions to Existing Models

- **Entry** → `hasOne EmailMessage` (an entry may optionally be the summary for an email)
- **File** → `hasMany EmailMessage` (emails linked to this file)
- **User** → `hasOne EmailAccount`

---

## Email-to-File Matching Algorithm

### Match Sources (in priority order)

1. **Tagged file number in subject** — `[File #XYZ]` format from outbound emails or replies — confidence 90-100
2. **Exact file number string in subject or body** — literal match of `files.file_number` values — confidence 80-90
3. **Sender/recipient email matches a contact's email** AND that contact has a `contact_role` on a file — confidence 50-70 (higher if combined with other signals)
4. **Client name or company in subject/body** — confidence 30-50
5. **Opponent name in subject/body** — confidence 30-50

**Important:** A single email can only be linked to one file. The scan may produce multiple candidate matches (multiple `email_scan_matches` rows for the same email, each pointing to a different file), but the user must choose exactly one. An email is not added to `email_messages` until the user confirms which file it belongs to.

### Matching Process

1. Extract all email addresses, subject, and first ~2000 chars of body
2. Check subject for tagged file number format `[File #...]` — if found, exact match against `files.file_number`
3. Check subject and body for exact string matches against all active `files.file_number` values for the firm
4. Look up sender/recipient emails against `contacts.email` and `contacts.email_alt`
5. For matched contacts, find their `contact_roles` to identify associated files
6. Search for client names, file names, and other identifiers in the subject/body
7. Create `email_scan_matches` rows for each potential match with numeric confidence score
8. For contact-only matches (no file number or name corroboration), require at least one additional signal before creating a match row — a contact on 20 files shouldn't generate 20 low-value matches

---

## Phased Implementation Plan

### Phase 1: Data Model & Email Account Setup
**Goal:** Users can configure their email account in the app.

**Tasks:**
1. Create migrations for `email_accounts`, `email_messages`, `email_scan_matches`
2. Add `has_email` to `filetypes` table
3. Create Eloquent models with relationships
4. Create `EmailAccountController` with setup wizard UI
5. Create settings page where user enters:
   - Email provider (Gmail / Microsoft 365 / Other IMAP)
   - For IMAP: host, port, encryption, username, password
   - For OAuth: redirect to provider auth flow (see Phase 3)
   - SMTP settings (or derive from provider)
   - How far back to scan (date picker, default 1 year)
6. Store credentials encrypted using Laravel's `encrypted` cast
7. Test connection validation (attempt IMAP login, report success/failure)

**New files:**
- `app/Models/EmailAccount.php`
- `app/Models/EmailMessage.php`
- `app/Models/EmailScanMatch.php`
- `app/Http/Controllers/EmailAccountController.php`
- `app/Http/Requests/StoreEmailAccountRequest.php`
- `resources/js/Pages/Email/Setup.vue`
- Migration files (3)

---

### Phase 2: Sending Emails
**Goal:** Users can compose and send emails from within a file, recorded as entries.

**Why send first:** Sending is simpler than reading, and outbound emails will include file numbers making future scanning easier.

**Tasks:**
1. Add "Send Email" action within the file entries view
2. Create email compose form: to, cc, bcc, subject (pre-filled with file number), body
3. Send using the appropriate method per provider:
   - **Gmail**: Gmail API `messages.send` (requires OAuth token from Phase 3, or SMTP as interim)
   - **Microsoft**: Microsoft Graph API `sendMail` (requires OAuth token from Phase 3, or SMTP as interim)
   - **Other**: Laravel Mail via user's configured SMTP
4. On successful send:
   - Create an `email_messages` row (direction: outbound, file_id set, full body stored)
   - Create an `entries` row (Correspondence folder, "Email" entrytype)
   - Link the two via `email_messages.entry_id`
   - `entries.from_contact_id` = sending user's contact record
   - `entries.to_contact_id` = primary recipient (if matched to a contact)
   - `entries.note` = summary with subject, recipients, CC/BCC indicators, and snippet
5. Subject line format: `[File #<file_number>] Subject text` — file number also appended at end of body as a fallback for email clients that strip subjects on reply
6. Support reply-to (when viewing an existing email, user can reply)

**Entry note format for emails:**
```
Subject: [File #ABC-2024-001] Re: Settlement discussion
To: john@example.com
CC: jane@example.com, bob@example.com
BCC: partner@firm.com
---
First 200 chars of email body as preview...
```

**New files:**
- `app/Http/Controllers/EmailComposeController.php`
- `app/Mail/OutboundFileEmail.php`
- `app/Jobs/SendFileEmail.php` (queued)
- `resources/js/Pages/Email/Compose.vue` (or modal component)
- `resources/js/Components/EmailComposeModal.vue`

---

### Phase 3: OAuth2 & Provider API Integration
**Goal:** Connect to Gmail and Microsoft 365 via their native APIs using OAuth2.

**Why now:** Gmail and Microsoft 365 are increasingly restrictive about IMAP with app passwords. Microsoft has disabled basic auth, and Gmail requires App Passwords with 2FA. Most users will need OAuth to connect at all, so this must be in place before email reading (Phase 4) is useful.

**Provider strategy:**
- **Gmail** → OAuth2 + Gmail API for both reading and sending. No IMAP.
- **Microsoft 365 / Outlook** → OAuth2 + Microsoft Graph API for both reading and sending. No IMAP.
- **Other providers** → IMAP for reading, SMTP for sending (Phase 4).

**Tasks:**
1. Register app with Google Cloud Console and Microsoft Entra ID (Azure AD)
2. Implement OAuth2 authorization code flow for each provider
3. Store and refresh tokens automatically using encrypted cast
4. Create provider adapter interface (`EmailProviderInterface`) with methods:
   - `listMessages(since, cursor)` — fetch message metadata for scanning
   - `getMessage(id)` — fetch full message body
   - `sendMessage(message)` — send an email
   - `getAttachment(messageId, attachmentId)` — stream attachment
5. Implement `GmailProvider` using `google/apiclient` (Gmail API v1)
6. Implement `MicrosoftGraphProvider` using `microsoft/microsoft-graph` (Graph API v1.0)
7. Handle token revocation and re-authorization gracefully
8. Integrate OAuth flow into the email account setup wizard (Phase 1 UI)

**New files:**
- `app/Contracts/EmailProviderInterface.php`
- `app/Services/Email/GmailProvider.php`
- `app/Services/Email/MicrosoftGraphProvider.php`
- `app/Services/Email/ImapSmtpProvider.php` (stub, implemented in Phase 4)
- `app/Http/Controllers/OAuthCallbackController.php`
- `config/email-providers.php` — OAuth client IDs, secrets, scopes

---

### Phase 4: Reading Emails (Sync via Provider APIs)
**Goal:** The app can connect to the user's mailbox and fetch email metadata for scanning.

Gmail and Microsoft accounts use their native APIs (built in Phase 3). This phase completes the IMAP fallback for "Other" providers and builds the sync orchestration layer that works across all providers.

**Tasks:**
1. Complete `ImapSmtpProvider` implementation using `webklex/php-imap`
   - Implements `EmailProviderInterface` from Phase 3
   - Only used for "Other" provider type
2. Create `EmailSyncService` that:
   - Resolves the correct provider adapter based on `email_accounts.provider`
   - Calls `provider->listMessages(since, cursor)` to fetch metadata
   - Stores results in `email_scan_matches` table as candidate matches
   - Tracks sync position via `last_synced_uid` (IMAP UID, Gmail history ID, or Graph deltaLink)
3. Create `SyncEmailsJob` (queued) that runs the sync
4. Handle connection failures gracefully with retry and user notification

**New files:**
- `app/Services/Email/ImapSmtpProvider.php` (full implementation)
- `app/Services/EmailSyncService.php`
- `app/Jobs/SyncEmailsJob.php`

---

### Phase 5: Initial Scan & Matching
**Goal:** After syncing emails, the app identifies potential file associations and presents them for user review.

**Tasks:**
1. Create `EmailMatchingService` that implements the matching algorithm
2. Run matching as a queued job after each sync batch
3. Create scan review UI:
   - List of matched emails grouped by file (highest confidence first)
   - For each match: show email subject, date, from/to, matched file, match reason, confidence score
   - Actions: Confirm (assigns to suggested file), Reassign to different file (with file lookup), Reject
   - Multiple match rows for the same email shown grouped — user picks one file
4. On confirmation:
   - Fetch full email body from server via IMAP/API
   - Create `email_messages` row with full body, linked to confirmed file
   - Create `entries` row (Correspondence folder, "Email" entrytype) as summary
   - `entries.note` = formatted summary (subject, recipients, CC/BCC, snippet)
   - `entries.from_contact_id` = matched contact (or create new contact)
   - `entries.to_contact_id` = matched recipient contact if available
   - `entries.date1` = email sent date
   - Link via `email_messages.entry_id`
   - Update `email_scan_matches.status` to 'confirmed', set `email_message_id`
   - Reject all other match rows for the same email
5. Bulk actions: confirm all high-confidence matches (score >= 90), reject remaining
6. Update `email_accounts.initial_scan_status` as review progresses

**New files:**
- `app/Services/EmailMatchingService.php`
- `app/Jobs/MatchEmailsJob.php`
- `app/Http/Controllers/EmailScanReviewController.php`
- `resources/js/Pages/Email/ScanReview.vue`
- `resources/js/Components/EmailMatchCard.vue`

---

### Phase 6: Ongoing Sync & Auto-Matching
**Goal:** After initial setup, new emails are automatically synced and matched.

**Tasks:**
1. Schedule `SyncEmailsJob` to run periodically (every 15 min or on login)
2. For new emails that match with high confidence (score >= 90, file number in subject), auto-create entries and store full email
3. For medium/low confidence matches, queue for user review (badge/notification)
4. Add email sync status indicator to the UI (last synced time, any errors)
5. Add "Sync Now" button for manual trigger
6. Handle edge cases: email account password changed, token expired, server unreachable
7. Periodic cleanup: purge rejected/expired `email_scan_matches` rows older than 30 days

**New files:**
- `app/Console/Commands/SyncAllEmailAccounts.php` (scheduled command)
- Notification components

---

### Phase 7: Email Viewing & Attachments
**Goal:** Users can view the full email content from within a file's entry list.

Since full email bodies are already stored in `email_messages` when confirmed, viewing does not require fetching from the mail server. The body is served directly from the database.

**Tasks:**
1. When user clicks an email entry, load the linked `email_message` with full body
2. Render email body (HTML sanitized, or plain text fallback)
3. Show email metadata: subject, all recipients (to, cc, bcc), date, direction
4. Email thread view: group related emails by `thread_id` or `in_reply_to` chain
5. Attachments: if the email has attachments, allow user to fetch/download them on demand from the mail server (IMAP/API). Attachments are NOT stored in the database — they are streamed through the app from the mail server to the user's browser for download/viewing only
6. Reply action: pre-fill compose form with quoted body and recipient info

**New files:**
- `app/Http/Controllers/EmailViewController.php`
- `app/Services/EmailAttachmentService.php`
- `resources/js/Pages/Email/View.vue` or modal component
- `resources/js/Components/EmailThread.vue`

---

## Folder Decision: Use Existing Correspondence Folder

Email summary entries use the existing Correspondence folder (folder_id=1) with the existing "Email" entrytype. The entry serves as a lightweight pointer in the file's timeline — the full email content lives in `email_messages` and is viewed through a dedicated email viewer, not through the standard entry form.

This approach was chosen over a dedicated Email folder because:
- Email data lives primarily in `email_messages`, not in entry fields — a separate folder's custom prompts wouldn't add much value
- The entry is just a summary/link, not the primary way users interact with email content
- Keeps the folder structure simpler
- The `has_email` boolean on `filetypes` still controls whether email features are available per file type

---

## Security Considerations

- All credentials stored with Laravel `encrypted` cast (AES-256-CBC via APP_KEY)
- OAuth tokens preferred over passwords where possible
- Full email bodies stored only for file-linked emails (not the entire mailbox)
- Scan metadata (`email_scan_matches`) is temporary and purged after review/expiry
- User can only access their own email account and messages
- Policy classes for all email-related models
- Rate limiting on email send endpoints
- HTML email bodies sanitized before rendering to prevent XSS
- Attachments streamed on demand from mail server — never stored locally

---

## Package Dependencies

| Package | Purpose | Phase |
|---|---|---|
| `google/apiclient` | Gmail API — reading, sending, attachments for Gmail accounts | Phase 3 |
| `microsoft/microsoft-graph` | Microsoft Graph API — reading, sending, attachments for Microsoft accounts | Phase 3 |
| `league/oauth2-client` | OAuth2 flows (if provider SDKs don't handle it fully) | Phase 3 |
| `webklex/php-imap` | IMAP fallback — reading for "Other" (non-Gmail, non-Microsoft) providers only | Phase 4 |

---

## Resolved Decisions

1. **One email account per user.** No multi-account support. Schema supports it via `email_account_id` FKs if needed later.
2. **No shared mailbox support.** Skip entirely for now.
3. **Email folder available for all file types.** `has_email` defaults to `true` on `filetypes`, following the existing `has_*` pattern.
4. **No signature management.** Users type freely; signature is just part of the email body.
5. **No drafts.** Compose and send in one action. No saved draft support.
6. **One email per file.** An email can only be linked to a single file. The scan may suggest multiple candidate files, but the user chooses one.
7. **Full body stored for linked emails only.** The entire mailbox is not imported. Only emails the user confirms as file-related get their full body stored in `email_messages`. Scan candidates store only metadata in `email_scan_matches`.
8. **Attachments streamed, not stored.** Attachments are fetched on demand from the mail server and streamed to the user's browser. No permanent local storage. User can download/save via their browser.
9. **No spam/unsubscribe handling.** This is not a primary email client.
10. **CC and BCC supported on compose.** Entry summary notes include CC/BCC indicators since `entries` only has `from_contact_id`/`to_contact_id`. Full recipient lists are in `email_messages`.
11. **File number format is firm-specific alphanumeric.** No regex pattern matching — instead:
    - Outbound emails use a tagged format in subject: `[File #ABC-2024-001] Subject text`
    - Inbound matching uses exact string match of all active `files.file_number` values against email subject and body
    - Reply chains naturally carry the tagged subject forward
    - The bracket/tag format makes parsing reliable without needing to know the firm's numbering convention
12. **Match confidence is numeric (0-100).** Allows flexible sorting and threshold configuration.
13. **Provider-native APIs preferred over IMAP.** Gmail uses Gmail API, Microsoft uses Graph API — both via OAuth2. IMAP/SMTP is a fallback only for "Other" providers. This avoids IMAP auth restrictions, gives richer metadata (thread IDs, labels), and enables sending through the same API used for reading.

---

## Status

- [x] Feature design and data model
- [ ] Phase 1: Data model & email account setup
- [ ] Phase 2: Sending emails
- [ ] Phase 3: OAuth2 provider integration (Gmail, Microsoft 365)
- [ ] Phase 4: Reading emails (IMAP/API)
- [ ] Phase 5: Initial scan & matching
- [ ] Phase 6: Ongoing sync & auto-matching
- [ ] Phase 7: Email viewing & attachments
