# Document Generation Feature

## Overview
Feature to allow firms to maintain template documents and generate filled-in documents using data from the app.

## Key Decisions
- **Format**: DOCX — it's an open standard (Office Open XML, ISO/IEC 29500) and universally supported by Word, Google Docs, LibreOffice
- **Library**: PHPWord (or a Laravel wrapper) for template variable substitution
- **No versioning**: Each generation is a one-off based on the template at the time
- **No format translation**: Stay in DOCX the whole way through

## Proposed Workflow
1. Firm uploads a DOCX template with `${placeholder}` merge fields
2. User triggers generation from a File/Entry
3. App substitutes data into the template using PHPWord
4. User downloads the resulting DOCX

## Technical Considerations

### Template Management
- Store templates per-firm (multi-tenant isolation)
- UI for uploading and managing templates

### Data Mapping
- Merge data from entities: File, Contact, Entry, Firm, User, etc.
- Need to define which placeholders map to which fields
- Handle null/missing data gracefully

### Performance
- Complex generation should be queued rather than synchronous

### Formats Considered & Rejected
- **HTML**: Poor fidelity when opened in word processors
- **RTF**: Arcane format, limited PHP library support
- **ODT**: Viable open standard but users expect DOCX; Word's ODT support has formatting gaps
