# Email Integration Feature Plan

## Overview

Add email reading and sending to Vantage so users can view and manage emails associated with files directly within the application. Emails appear as entries within a file, linking the legal workflow to actual email correspondence.

**Core principles:**
- Read-only access to the mailbox (no deleting or moving emails on the server)
- Outbound emails embed the file number for easy future matching
- Human-in-the-loop confirmation for the initial scan
- Incremental sync after initial setup

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
- `note` field is 5000 chars — may be too small for full email bodies
- No email account configuration storage
- No staging/review workflow tables

---

## Data Model

### New Tables

#### 1. `email_accounts`
Stores each user's email provider configuration and sync state.

```
email_accounts
├── id                          bigint PK
├── firm_id                     unsignedInteger FK
├── user_id                     unsignedInteger FK (unique - one account per user)
├── email_address               string(255)
├── display_name                string(255) nullable — name shown on outbound emails
├── provider                    string(50) — 'gmail', 'microsoft', 'imap_smtp'
├── imap_host                   string(255) nullable
├── imap_port                   unsignedSmallInteger nullable (default 993)
├── imap_encryption             string(10) nullable — 'ssl', 'tls'
├── smtp_host                   string(255) nullable
├── smtp_port                   unsignedSmallInteger nullable (default 587)
├── smtp_encryption             string(10) nullable — 'tls', 'ssl'
├── auth_type                   string(20) — 'oauth2', 'password'
├── oauth_access_token          text nullable (encrypted)
├── oauth_refresh_token         text nullable (encrypted)
├── oauth_token_expires_at      datetime nullable
├── imap_username               string(255) nullable (encrypted)
├── imap_password               string(255) nullable (encrypted)
├── smtp_username               string(255) nullable (encrypted)
├── smtp_password               string(255) nullable (encrypted)
├── is_active                   boolean default true
├── last_synced_at              datetime nullable
├── last_synced_uid             string(255) nullable — IMAP UID or provider cursor
├── initial_scan_status         string(20) default 'pending'
│                               — 'pending', 'scanning', 'review', 'completed'
├── initial_scan_start_date     date nullable — how far back to scan
├── timestamps
```

#### 2. `email_messages`
Stores metadata for every email the system has seen. This is the reference table — not the entry itself, but the raw email record. Bodies are fetched on demand from the server; only a preview is stored locally.

```
email_messages
├── id                          bigint PK
├── firm_id                     unsignedInteger FK
├── email_account_id            unsignedInteger FK
├── message_id                  string(512) — RFC Message-ID header (globally unique)
├── in_reply_to                 string(512) nullable — for threading
├── thread_id                   string(255) nullable — provider thread ID if available
├── subject                     string(1000)
├── from_address                string(255)
├── from_name                   string(255) nullable
├── to_addresses                json — array of {address, name}
├── cc_addresses                json nullable
├── date_sent                   datetime
├── snippet                     string(500) nullable — preview text
├── has_attachments             boolean default false
├── direction                   string(10) — 'inbound', 'outbound'
├── imap_uid                    string(255) nullable — for IMAP retrieval
├── provider_id                 string(255) nullable — Gmail/Graph message ID
├── raw_headers                 text nullable — stored for debugging/future parsing
├── is_processed                boolean default false — true after scan review
├── timestamps
│
├── UNIQUE INDEX (email_account_id, message_id)
```

#### 3. `email_scan_matches`
Staging table for the initial scan review process. Each row represents a potential match between an email and a file, awaiting user confirmation.

```
email_scan_matches
├── id                          bigint PK
├── firm_id                     unsignedInteger FK
├── email_account_id            unsignedInteger FK
├── email_message_id            unsignedInteger FK
├── file_id                     unsignedInteger FK nullable — suggested file match
├── match_reason                string(255) — e.g. 'contact_email', 'file_number_in_subject',
│                                              'client_name_in_body', 'docket_number'
├── match_confidence            string(10) — 'high', 'medium', 'low'
├── status                      string(20) default 'pending'
│                               — 'pending', 'confirmed', 'reassigned', 'rejected'
├── confirmed_file_id           unsignedInteger FK nullable — set if user reassigns to different file
├── entry_id                    unsignedInteger FK nullable — set after entry is created
├── reviewed_at                 datetime nullable
├── timestamps
```

#### 4. `email_entries` (pivot/extension table)
Links an entry to its source email message. This keeps the entries table clean while adding email-specific metadata.

```
email_entries
├── id                          bigint PK
├── entry_id                    unsignedInteger FK (unique)
├── email_message_id            unsignedInteger FK
├── email_account_id            unsignedInteger FK
├── subject                     string(1000)
├── is_outbound                 boolean default false
├── timestamps
```

### Changes to Existing Tables

#### `filetypes` — add column
```
has_email                       boolean default true
```

#### `folders` — add row (optional, see discussion below)
A new "Email" folder (id=12) with prompts tailored for email display. Alternatively, emails can live under the existing Correspondence folder using the existing "Email" entrytype.

#### `entries.note` — consider increasing
May want to increase from 5000 to 10000 chars, or store email body text in `email_messages` and only put a summary in `entries.note`.

### New Models

| Model | Table | Key Relationships |
|---|---|---|
| `EmailAccount` | `email_accounts` | belongsTo User, Firm; hasMany EmailMessage |
| `EmailMessage` | `email_messages` | belongsTo EmailAccount; hasMany EmailScanMatch; hasOne EmailEntry |
| `EmailScanMatch` | `email_scan_matches` | belongsTo EmailMessage, File, EmailAccount |
| `EmailEntry` | `email_entries` | belongsTo Entry, EmailMessage, EmailAccount |

### Relationship Additions to Existing Models

- **Entry** → `hasOne EmailEntry` (an entry may optionally be linked to an email)
- **File** → `hasManyThrough` email entries (via entries)
- **User** → `hasOne EmailAccount`

---

## Email-to-File Matching Algorithm

### Match Sources (in priority order)

1. **Tagged file number in subject** — `[File #XYZ]` format from outbound emails or replies — HIGH confidence, auto-confirm
2. **Exact file number string in subject or body** — literal match of `files.file_number` values — HIGH confidence
3. **Sender/recipient email matches a contact's email** AND that contact has a `contact_role` on a file — MEDIUM-HIGH confidence
4. **Client name or company in subject/body** — MEDIUM confidence
5. **Opponent name in subject/body** — MEDIUM confidence

### Matching Process

1. Extract all email addresses, subject, and first ~2000 chars of body
2. Check subject for tagged file number format `[File #...]` — if found, exact match against `files.file_number`
3. Check subject and body for exact string matches against all active `files.file_number` values for the firm
4. Look up sender/recipient emails against `contacts.email` and `contacts.email_alt`
5. For matched contacts, find their `contact_roles` to identify associated files
6. Search for client names, file names, and other identifiers in the subject/body
7. Create `email_scan_matches` rows for each potential match with confidence level

---

## Phased Implementation Plan

### Phase 1: Data Model & Email Account Setup
**Goal:** Users can configure their email account in the app.

**Tasks:**
1. Create migrations for `email_accounts`, `email_messages`, `email_scan_matches`, `email_entries`
2. Add `has_email` to `filetypes` table
3. Create Eloquent models with relationships
4. Create `EmailAccountController` with setup wizard UI
5. Create settings page where user enters:
   - Email provider (Gmail / Microsoft 365 / Other IMAP)
   - For IMAP: host, port, encryption, username, password
   - For OAuth: redirect to provider auth flow
   - SMTP settings (or derive from provider)
   - How far back to scan (date picker, default 1 year)
6. Store credentials encrypted using Laravel's `encrypted` cast
7. Test connection validation (attempt IMAP login, report success/failure)
8. Write tests for account creation and validation

**New files:**
- `app/Models/EmailAccount.php`
- `app/Models/EmailMessage.php`
- `app/Models/EmailScanMatch.php`
- `app/Models/EmailEntry.php`
- `app/Http/Controllers/EmailAccountController.php`
- `app/Http/Requests/StoreEmailAccountRequest.php`
- `resources/js/Pages/Email/Setup.vue`
- Migration files (4)

---

### Phase 2: Sending Emails
**Goal:** Users can compose and send emails from within a file, recorded as entries.

**Why send first:** Sending is simpler than reading (Laravel has SMTP built in), and outbound emails will include file numbers making future scanning easier.

**Tasks:**
1. Add "Send Email" action within the file entries view
2. Create email compose form: to, cc, subject (pre-filled with file number), body
3. Send via Laravel Mail using the user's configured SMTP
4. On successful send:
   - Create an `email_messages` row (direction: outbound)
   - Create an `entries` row (folder: correspondence or email, entrytype: email)
   - Create an `email_entries` row linking the two
5. Subject line format: `[File #<file_number>] Subject text` — file number also appended at end of body as a fallback for email clients that strip subjects on reply
6. Support reply-to (when viewing an existing email entry, user can reply)
7. Write tests for email composition, sending, and entry creation

**New files:**
- `app/Http/Controllers/EmailComposeController.php`
- `app/Mail/OutboundFileEmail.php`
- `app/Jobs/SendFileEmail.php` (queued)
- `resources/js/Pages/Email/Compose.vue` (or modal component)
- `resources/js/Components/EmailComposeModal.vue`

---

### Phase 3: Reading Emails (IMAP/API Integration)
**Goal:** The app can connect to the user's mailbox and fetch email metadata.

**Tasks:**
1. Install `webklex/php-imap` package (or evaluate alternatives)
2. Create `EmailSyncService` that:
   - Connects to IMAP using stored credentials
   - Fetches message headers and metadata (not full bodies yet)
   - Stores results in `email_messages` table
   - Tracks sync position via `last_synced_uid`
3. Create `SyncEmailsJob` (queued) that runs the sync
4. For OAuth providers: implement token refresh logic
5. Handle connection failures gracefully with retry and user notification
6. Write tests with mocked IMAP responses

**New files:**
- `app/Services/EmailSyncService.php`
- `app/Services/ImapConnector.php`
- `app/Jobs/SyncEmailsJob.php`
- Provider-specific adapters if needed

---

### Phase 4: Initial Scan & Matching
**Goal:** After syncing emails, the app identifies potential file associations and presents them for user review.

**Tasks:**
1. Create `EmailMatchingService` that implements the matching algorithm
2. Run matching as a queued job after each sync batch
3. Create scan review UI:
   - List of matched emails grouped by file (highest confidence first)
   - For each match: show email subject, date, from/to, matched file, match reason
   - Actions: Confirm, Reassign to different file (with file lookup), Reject
4. On confirmation:
   - Create entry in the file (correspondence folder, email entrytype)
   - `entries.note` = email subject + snippet/summary
   - `entries.from_contact_id` = matched contact (or create new contact)
   - `entries.date1` = email sent date
   - Create `email_entries` row linking entry to `email_messages`
   - Update `email_scan_matches.status` to 'confirmed'
5. Bulk actions: confirm all high-confidence matches, reject remaining
6. Update `email_accounts.initial_scan_status` as review progresses
7. Write tests for matching logic and review workflow

**New files:**
- `app/Services/EmailMatchingService.php`
- `app/Jobs/MatchEmailsJob.php`
- `app/Http/Controllers/EmailScanReviewController.php`
- `resources/js/Pages/Email/ScanReview.vue`
- `resources/js/Components/EmailMatchCard.vue`

---

### Phase 5: Ongoing Sync & Auto-Matching
**Goal:** After initial setup, new emails are automatically synced and matched.

**Tasks:**
1. Schedule `SyncEmailsJob` to run periodically (every 15 min or on login)
2. For new emails that match with HIGH confidence (file number in subject), auto-create entries
3. For medium/low confidence matches, queue for user review (badge/notification)
4. Add email sync status indicator to the UI (last synced time, any errors)
5. Add "Sync Now" button for manual trigger
6. Handle edge cases: email account password changed, token expired, server unreachable
7. Write tests for incremental sync and auto-matching

**New files:**
- `app/Console/Commands/SyncAllEmailAccounts.php` (scheduled command)
- Notification components

---

### Phase 6: Email Viewing
**Goal:** Users can view the full email content from within a file's entry list.

**Tasks:**
1. When user clicks an email entry, fetch full message from server via IMAP/API
2. Render email body (HTML sanitized, or plain text)
3. Show email metadata: full headers, all recipients, attachments list
4. Attachment viewing/downloading (fetch from server on demand)
5. Email thread view: group related emails by `thread_id` or `in_reply_to` chain
6. Cache fetched email bodies temporarily (Redis/file cache, TTL ~1 hour)
7. Write tests for email retrieval and display

**New files:**
- `app/Http/Controllers/EmailViewController.php`
- `app/Services/EmailFetchService.php`
- `resources/js/Pages/Email/View.vue` or modal component
- `resources/js/Components/EmailThread.vue`

---

### Phase 7: OAuth2 Provider Integration
**Goal:** Support Gmail and Microsoft 365 with OAuth2 (no stored passwords).

**Tasks:**
1. Register app with Google Cloud Console and Microsoft Azure AD
2. Implement OAuth2 authorization code flow for each provider
3. Store and refresh tokens automatically
4. Use Gmail API / Microsoft Graph API instead of IMAP where beneficial
5. Handle token revocation and re-authorization gracefully
6. Write tests for OAuth flows

**New files:**
- `app/Services/GmailOAuthService.php`
- `app/Services/MicrosoftOAuthService.php`
- `app/Http/Controllers/OAuthCallbackController.php`
- Config file for OAuth credentials

**Note:** This phase can be moved earlier if most users are on Gmail/O365. Starting with IMAP in Phase 3 allows the feature to work with any provider first.

---

## Folder Decision: Correspondence vs. Dedicated Email Folder

### Option A: Use Existing Correspondence Folder
- Emails use folder_id=1 (Correspondence) with the existing "Email" entrytype
- **Pro:** No schema changes to folders/filetypes, simpler
- **Con:** Email entries mixed with letters, faxes, etc.; can't customize prompts for email-specific fields

### Option B: New Dedicated Email Folder (Recommended)
- Add folder_id=12 "Email" with short_name='email'
- Add `has_email` boolean to `filetypes`
- Custom prompts: "Subject" instead of generic note prompt, "From Email" / "To Email"
- Update `get_folder_info` in EntryController to include 'email' at position 12
- **Pro:** Clean separation, email-specific UI, filterable in entries index
- **Con:** More migration/seeder work, update filetypes

**Recommendation:** Option B. Emails are different enough from letters/faxes that a separate folder provides better UX and cleaner filtering. The entries index already supports folder tabs.

---

## Security Considerations

- All credentials stored with Laravel `encrypted` cast (AES-256-CBC via APP_KEY)
- OAuth tokens preferred over passwords where possible
- Email bodies fetched on demand, not stored permanently (reduces data liability)
- Only `email_messages` metadata stored long-term (subject, addresses, dates)
- User can only access their own email account and messages
- Policy classes for all email-related models
- Rate limiting on email send endpoints

---

## Package Dependencies

| Package | Purpose | Phase |
|---|---|---|
| `webklex/php-imap` | IMAP email reading | Phase 3 |
| `league/oauth2-client` | OAuth2 flows (if not using provider SDKs) | Phase 7 |
| `google/apiclient` | Gmail API (optional, alternative to IMAP) | Phase 7 |
| `microsoft/microsoft-graph` | Microsoft Graph API (optional) | Phase 7 |

---

## Resolved Decisions

1. **One email account per user.** No multi-account support. Schema supports it via `email_account_id` FKs if needed later.
2. **No shared mailbox support.** Skip entirely for now.
3. **Email folder available for all file types.** `has_email` defaults to `true` on `filetypes`, following the existing `has_*` pattern.
4. **No signature management.** Users type freely; signature is just part of the email body.
5. **Attachments fetched on demand.** Short-lived cache (1 hour) if performance is an issue. No permanent local storage.
6. **File number format is firm-specific alphanumeric.** No regex pattern matching — instead:
   - Outbound emails use a tagged format in subject: `[File #ABC-2024-001] Subject text`
   - Inbound matching uses exact string match of all active `files.file_number` values against email subject and body
   - Reply chains naturally carry the tagged subject forward
   - The bracket/tag format makes parsing reliable without needing to know the firm's numbering convention

---

## Status

- [x] Feature design and data model
- [ ] Phase 1: Data model & email account setup
- [ ] Phase 2: Sending emails
- [ ] Phase 3: Reading emails (IMAP)
- [ ] Phase 4: Initial scan & matching
- [ ] Phase 5: Ongoing sync & auto-matching
- [ ] Phase 6: Email viewing
- [ ] Phase 7: OAuth2 providers
