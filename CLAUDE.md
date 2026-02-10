# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Laravel 12 + Inertia.js + Vue 3** application called "Vantage" - a file and document management system with calendar integration. The application uses a multi-tenant architecture where users manage files, entries, contacts, and folders organized by firms.

## Development Commands

### Start Development Server
```bash
composer dev
```
This runs three concurrent processes:
- PHP development server (`php artisan serve`)
- Queue worker (`php artisan queue:listen --tries=1`)
- Vite dev server (`npm run dev`)

### Individual Commands
```bash
# Start PHP server only
php artisan serve

# Start Vite (frontend) only
npm run dev

# Build frontend assets for production
npm run build

# Run migrations
php artisan migrate

# Run migrations with fresh database
php artisan migrate:fresh

# Run database seeder
php artisan db:seed
```

### Testing & Code Quality
```bash
# Run tests
composer test
# or
php artisan test

# Run specific test
php artisan test --filter TestName

# Run code formatter (Laravel Pint)
./vendor/bin/pint

# Clear application caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Architecture Overview

### Frontend Stack
- **Inertia.js**: Bridges Laravel backend with Vue frontend (no API endpoints needed)
- **Vue 3**: Component-based UI framework
- **Tailwind CSS + DaisyUI**: Utility-first CSS with pre-built components
- **FullCalendar**: Calendar integration with Vue 3
- **@vuepic/vue-datepicker**: Date/time picker component
- **Ziggy**: Laravel route helper for JavaScript

### Backend Stack
- **Laravel 12**: PHP framework with Eloquent ORM
- **MySQL**: Database (configured via Laragon)
- **Queue System**: Database-backed job queue
- **Sanctum**: API authentication (configured but primarily using session auth)

### File Structure
```
app/
├── Http/
│   ├── Controllers/       # Main application controllers
│   ├── Middleware/        # Custom middleware (EnsureUserWelcomed)
│   └── Requests/          # Form request validation classes
├── Models/                # Eloquent models for database tables
└── Providers/             # Service providers

resources/
├── js/
│   ├── Pages/             # Inertia.js Vue pages (one per route)
│   ├── Components/        # Reusable Vue components
│   ├── Layouts/           # Page layouts (AuthenticatedLayout, GuestLayout)
│   ├── Composables/       # Vue composables for shared logic
│   └── app.js             # Vue/Inertia application entry point
├── css/
│   └── app.css            # Tailwind CSS imports
└── views/                 # Blade templates (minimal - only app.blade.php)

routes/
├── web.php                # Application routes
├── auth.php               # Authentication routes (Laravel Breeze)
└── console.php            # Artisan console commands

database/
├── migrations/            # Database schema migrations
└── seeders/               # Database seeders
```

## Core Domain Models

### Primary Entities
- **File**: Central entity representing a case/matter with associated entries
  - Belongs to: Firm, Contact (client), Filetype
  - Has many: Entries, Contacts
- **Entry**: Individual communications/events within a File
  - Belongs to: File, Folder, Entrytype, Contact (from), Contact (to)
  - Has one: Response
  - Has many: Responses received
- **Contact**: People associated with files
  - Belongs to: Firm
  - Has many: Files
- **Folder**: Organizational category for files
  - Has many: Entrytypes
- **Filetype**: Categorization for files
- **Entrytype**: Categorization for entries within folders
- **Firm**: Top-level tenant/organization
- **User**: System users with user_type (Admin/User) and welcomed status
- **Preference**: User-specific settings and preferences

### Key Relationships
- Files contain multiple Entries (like a thread or case log)
- Entries can have from/to contacts and response relationships
- Folders define which Entrytypes are available
- Users belong to Firms (multi-tenant)

## Important Patterns & Conventions

### Inertia.js Pattern
- Controllers return `Inertia::render('PageName', $data)` instead of JSON/views
- Pages are Vue components in `resources/js/Pages/`
- No API routes needed - Inertia handles data passing automatically
- Use `<Link>` component for navigation to preserve SPA experience

### Authentication & Authorization
- Uses Laravel Breeze for authentication scaffolding
- Custom `EnsureUserWelcomed` middleware redirects new users to welcome flow
- Users must complete welcome process before accessing main application
- Welcome routes bypass the 'welcomed' middleware check

### Routing Structure
- Resource routes for CRUD operations: `Route::resource('users', UserController::class)`
- Nested resources: `Route::resource('files.entries', EntryController::class)`
- Two middleware groups:
  - `auth` only: Welcome routes
  - `auth + welcomed`: Main application routes
- Modal forms use both GET and POST for same route (error passback pattern)

### Frontend Conventions
- Pages follow folder structure matching routes (e.g., `Pages/Files/Index.vue`)
- Shared components in `Components/` directory
- Use `route()` helper from Ziggy for route generation in Vue
- DaisyUI component classes preferred for UI elements

### Database Conventions
- Models use `$guarded = []` pattern (mass assignment protection via request validation)
- Timestamps enabled by default
- Some models hide attributes (e.g., `firm_id`) from JSON serialization
- Queue connection uses database driver

### Modal Pattern
- Modal forms submit to specific endpoints (e.g., `/contact_add_modal`)
- GET routes exist for the same endpoints to handle error passback
- Validation errors redirect back to modal with old input

## Key Features

### Calendar Integration
- FullCalendar with drag-drop and resize functionality
- Events can be linked to files via lookup
- Custom event types per user preferences
- Event placement/movement updates via AJAX

### File Lookup System
- Multiple controllers implement file lookup (FileController, CalendarController)
- Used to associate files with entries and calendar events
- Returns file data for autocomplete/selection

### Contact Management
- Contacts can be added via modal within entry creation flow
- Contact lookup for quick selection
- Contacts belong to firms for multi-tenancy

### User Preferences
- Stored in preferences table
- Include event colors, entry type visibility, etc.
- Updated via PreferenceController

## Environment Setup

### Required Environment Variables (.env)
- `DB_DATABASE=boxtoo` (or your database name)
- `DB_CONNECTION=mysql`
- `QUEUE_CONNECTION=database`
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`

### Local Development (Laragon)
- Database: MySQL via Laragon
- Web server: Apache or Nginx via Laragon
- PHP version: 8.2+

## Common Gotchas

### Vite/Frontend Issues
- If frontend changes don't appear, ensure `npm run dev` is running
- Build assets with `npm run build` before deploying
- Clear browser cache if styles don't update

### Inertia.js Specifics
- Always return Inertia responses from controllers, not views or JSON
- Use `Inertia::share()` in HandleInertiaRequests for global props
- Validation errors are automatically passed to frontend

### Queue Jobs
- Queue worker must be running for background jobs
- Use `php artisan queue:listen` in development
- Failed jobs are stored in database

### Middleware Order
- 'welcomed' middleware must come after 'auth'
- Welcome routes must NOT include 'welcomed' middleware
