# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

_edit CMS is a lightweight, ACF-style headless CMS built for shared hosting environments. It features a Vue 3 admin interface and a PHP backend with SQLite storage, requiring zero external dependencies (custom JWT auth, no composer packages).

## Development Commands

### Starting Development Servers

Two servers must run simultaneously:

**Terminal 1 - PHP Backend (port 8000):**
```bash
php -S localhost:8000 router.php
```

**Terminal 2 - Vue Admin (port 5173):**
```bash
cd _edit/admin-source
npm run dev
```

Access the admin at: http://localhost:5173/_edit/admin/

### Building for Production

```bash
cd _edit/admin-source
npm run build
```

Built files go to `_edit/admin-dist/`. Deploy the entire `_edit/` directory to production.

### Database

SQLite database location: `_edit/database/site.sqlite`

No migrations system - schema is created on first run by the Database class.

## Architecture

### URL Routing (router.php)

The PHP router enforces a `/_edit/` prefix for all CMS requests:
- `/_edit/admin/` → Vue SPA (dev: Vite proxy, prod: static files)
- `/_edit/api/*` → Routes to `_edit/admin/api/index.php`
- `/_edit/uploads/*` → Static file serving
- Direct `/admin/` or `/api/` access is blocked (404)

### Backend Structure

**PSR-4 Autoloading:**
- Namespace: `Edit\Core\`
- Base path: `_edit/admin/core/`
- Configured in: `_edit/admin/core/bootstrap.php`

**Core Components:**

1. **Database** (`_edit/admin/core/Database/Database.php`)
   - Custom SQLite wrapper with query builder
   - No external dependencies
   - Methods: `query()`, `execute()`, `beginTransaction()`, `commit()`, `rollback()`
   - Auto-creates schema on first run

2. **Auth** (`_edit/admin/core/Auth/`)
   - Custom JWT implementation (no libraries)
   - JWT secret stored in `_edit/config/jwt-secret.txt`
   - Auth check: `$auth->verifyRequest()` returns user ID or false

3. **Content Type System** (`_edit/admin/core/ContentTypes/`)
   - **ContentTypeRegistry**: Loads post types and field groups from `_edit/config/config.json`
   - **BlockRegistry**: Manages Gutenberg-style blocks
   - **ContentType**: Handles CRUD for dynamic content types
   - Post types and field groups are defined in JSON, NOT in database
   - Registry combines post types with their assigned field groups to build content schemas

4. **Field System** (`_edit/admin/core/Fields/`)
   - 12 field types: Text, Textarea, Wysiwyg, Number, Boolean, Select, Date, Datetime, Slug, Media, Relationship, Repeater
   - All extend `BaseField` and implement `FieldType` interface
   - Each field handles validation, sanitization, and database serialization
   - Methods: `validate()`, `sanitize()`, `toDatabase()`, `fromDatabase()`

5. **Email System** (`_edit/admin/core/Email/Email.php`)
   - SMTP support with configurable settings
   - Fallback to PHP mail() function
   - Email logging and single-use token system
   - Settings stored in database `settings` table

### API Structure

**Entry Point:** `_edit/admin/api/index.php`

Main router loads individual route files and delegates to handler functions.

**Route Files:**

1. **auth.php** - Authentication (no auth required)
   - `POST /auth/login` - User login
   - `POST /auth/register` - First-time setup only
   - `GET /auth/me` - Get current user (requires auth)

2. **users.php** - User management (requires auth)
   - `GET /users` - List all users
   - `POST /users` - Create new user
   - `PUT /users/:id` - Update user password
   - `DELETE /users/:id` - Delete user

3. **media.php** - Media library (requires auth)
   - `POST /media` - Upload file
   - `GET /media` - List all media
   - `GET /media/:id` - Get single media item
   - `DELETE /media/:id` - Delete media

4. **content.php** - Dynamic content CRUD (requires auth)
   - `GET /{type}` - List all content of type
   - `GET /{type}/:id` - Get single content item
   - `POST /{type}` - Create new content item
   - `PUT /{type}/:id` - Update content item
   - `DELETE /{type}/:id` - Delete content item
   - Reserved types: auth, users, media, config, email-settings, email-logs, send-email, post-types, field-groups, blocks, health, public

5. **config.php** - Configuration management (requires auth)
   - `GET /config` - Get config.json
   - `PUT /config` - Update config.json
   - `POST /config/import` - Import module/field-group/block
   - `GET /post-types` - List post types
   - `GET /field-groups` - List field groups
   - `GET /blocks` - List blocks

6. **email.php** - Email system
   - Public routes (no auth):
     - `GET /send-email/token` - Generate single-use token (rate limited: 10/hour)
     - `POST /send-email` - Send email with token (rate limited: 20/hour)
   - Admin routes (requires auth):
     - `GET /email-settings` - Get SMTP settings
     - `PUT /email-settings` - Update SMTP settings
     - `GET /email-logs` - Get email send logs

7. **public.php** - Public query API (no auth required)
   - `GET /public/docs` - API documentation
   - `GET /public/{type}` - Query published content
   - `GET /public/{type}/{slug}` - Get single published content by slug
   - Rate limited: 100 requests per minute

8. **health.php** - System health check (no auth required)
   - `GET /health` - System status
   - `GET /health/db` - Database connectivity

**Helper Functions** (`_edit/admin/api/helpers.php`):
- `sendJson()` - Send JSON response
- `sendError()` - Send error response
- `getJsonBody()` - Parse JSON request body
- `checkRateLimit()` - Rate limiting with exponential backoff
- `getSettings()` - Batch fetch settings from database
- `requireFields()` - Validate required fields
- `saveSetting()` - Upsert setting key/value
- `addMediaUrl()` - Add URL to media items

### Frontend Structure

**Tech Stack:** Vue 3 + Vite + Vue Router + Tailwind CSS + DaisyUI

**Composables:**
- `useApi.js` - API request handling with auth headers
- `useAuth.js` - Authentication state management

**Views:**
- `ConfigView.vue` - JSON editor for config.json (Monaco Editor)
- `ContentListView.vue` - Lists content items for a post type
- `ContentEditorView.vue` - Creates/edits content items
- `MediaView.vue` - Media library management
- `UsersView.vue` - User management interface
- `EmailView.vue` - Email configuration and logs
- `HealthView.vue` - System health monitoring
- `DocumentationView.vue` - API documentation viewer

**Components:**
- `FieldRenderer.vue` - Dynamically renders field inputs based on type
- `RepeaterField.vue` - Handles nested repeating field groups
- `MediaField.vue` + `MediaModal.vue` - Media selection interface
- `JsonEditor.vue` - Monaco Editor wrapper for JSON editing
- `BlockEditor.vue` - Gutenberg-style block editor
- `FieldGroupEditor.vue` - Field group configuration
- `PostTypeEditor.vue` - Post type configuration
- `WysiwygField.vue` - Quill-based WYSIWYG editor
- `FlexibleContentField.vue` - Flexible content layouts

**Vite Config:**
- Base path: `/_edit/admin/`
- API proxy: `/_edit/api` → `http://localhost:8000`
- Uploads proxy: `/_edit/uploads` → `http://localhost:8000`
- Output dir: `../admin-dist`

### Configuration System

**Single source of truth:** `_edit/config/config.json`

This JSON file defines:
- `post_types[]` - Content types with labels, icons, descriptions, supports
- `field_groups[]` - Field collections with location rules
- `blocks[]` - Gutenberg-style content blocks

**Key Concept:** Post types and field groups are linked by `locations` array. A field group with `"locations": ["post"]` attaches its fields to the "post" content type.

ConfigView.vue provides a Monaco-based JSON editor for this file with:
- Syntax validation
- Import/export functionality
- Module system for reusable configurations

### Content Storage

Content is stored using Entity-Attribute-Value (EAV) pattern:
- `content` - Core fields (id, type, slug, status, author_id, created_at, updated_at)
- `content_meta` - Custom fields (content_id, meta_key, meta_value)

Field values are serialized using field-specific `toDatabase()` methods and deserialized with `fromDatabase()`.

### Media System

Media files are stored in: `_edit/uploads/YYYY/MM/`
Database tracks: filename, path, mime_type, size, timestamps

URL format: `/_edit/uploads/2025/01/[unique-id].[ext]`

Helper function `addMediaUrl()` automatically adds full URL to media items.

### Email System

**Configuration:**
- SMTP settings stored in database `settings` table
- Settings: host, port, username, password, encryption, from_email, from_name
- Fallback to PHP `mail()` if SMTP not configured

**Public API Security:**
- Single-use token system prevents spam
- Token expires after 30 seconds
- Rate limiting: 10 tokens/hour, 20 emails/hour per IP
- Tokens stored in `email_tokens` table

**Email Logging:**
- All send attempts logged to `email_logs` table
- Tracks: to_address, subject, success status, error messages, IP address, timestamp

### Rate Limiting

**Implementation:** `checkRateLimit()` helper function

**Features:**
- Per-endpoint, per-IP tracking
- Configurable window size (default: 15 minutes)
- Exponential backoff on violations
- Lockout durations: 1 min → 5 min → 15 min
- Auto-cleanup of old records (>1 hour)

**Protected Endpoints:**
- Login: 5 attempts / 15 minutes
- Register: 3 attempts / 15 minutes
- Email token: 10 attempts / 60 minutes
- Email send: 20 attempts / 60 minutes
- Public API: 100 requests / 1 minute

**Database:** `rate_limits` table stores attempts, window start, lockout status

### Database Schema

Created automatically on first run:

1. **users** - Admin users
   - id, name, email, password (hashed), created_at

2. **content** - All content items across types
   - id, type, slug, status, author_id, created_at, updated_at

3. **content_meta** - Field values (EAV pattern)
   - id, content_id, meta_key, meta_value, created_at

4. **media** - Uploaded files metadata
   - id, filename, path, mime_type, size, created_at

5. **settings** - System configuration
   - id, key (unique), value, updated_at

6. **email_tokens** - Single-use email tokens
   - id, token (unique), expires_at, created_at

7. **email_logs** - Email send history
   - id, to_address, subject, success, error_message, ip_address, created_at

8. **rate_limits** - Rate limiting tracking
   - id, ip_address, endpoint, attempts, window_start, locked_until
   - Unique constraint: (ip_address, endpoint)

## Development Patterns

### Adding a New Field Type

1. Create `_edit/admin/core/Fields/[Type]Field.php` extending `BaseField`
2. Implement `validate()`, `sanitize()`, `toDatabase()`, `fromDatabase()`
3. Add rendering logic to `FieldRenderer.vue` for the new type
4. Field will automatically work in content types once added to config.json

### Adding a New Post Type

Edit `_edit/config/config.json`:
1. Add entry to `post_types[]` array with key, labels, icon, supports
2. Create field group(s) with matching location(s)
3. No code changes needed - API routes generate automatically

### Adding a New API Route

1. Create route file in `_edit/admin/api/routes/[name].php`
2. Define handler function: `function handle[Name]Routes(...): bool`
3. Add to index.php routing logic
4. Use helper functions from `helpers.php` for common tasks

### Rate Limiting a New Endpoint

Use `checkRateLimit()` in your route handler:
```php
checkRateLimit($db, 'my-endpoint', $maxAttempts, $windowMinutes);
```

### Debugging

**PHP Backend:**
- Errors logged to: `server.log` (when using built-in server)
- Enable display in `_edit/admin/core/bootstrap.php`:
  ```php
  error_reporting(E_ALL);
  ini_set('display_errors', '1');
  ```

**Vue Frontend:**
- Vue DevTools work normally with Vite dev server
- Check browser console for API errors
- Network tab shows all API requests/responses

**Database:**
```bash
sqlite3 _edit/database/site.sqlite
.tables  # List all tables
.schema [table]  # Show table structure
SELECT * FROM [table] LIMIT 10;  # Query data
```

## Important Constraints

### Security
- All admin API routes require JWT auth (except public routes and auth endpoints)
- Rate limiting on all public-facing endpoints
- CSRF protection via JWT token validation
- SQL injection prevented via parameterized queries
- XSS prevention via proper escaping in Vue templates
- File upload restrictions (mime type, size)

### Data Integrity
- Slugs must be unique per content type
- Config.json must be valid JSON or the entire CMS breaks
- First user registration auto-disabled after one user exists
- Cannot delete last user or your own user account

### Technical Requirements
- PHP 8.1+ required (for typed properties, union types)
- SQLite3 extension must be enabled
- Node.js 16+ for frontend development
- Modern browser with ES6+ support

### File System
- `_edit/config/` must be writable (for config.json updates)
- `_edit/uploads/` must be writable (for media uploads)
- `_edit/database/` must be writable (for SQLite database)

## API Response Format

**Success Response:**
```json
{
  "key": "value",
  "items": []
}
```

**Error Response:**
```json
{
  "error": "Error message"
}
```

HTTP status codes:
- 200 - Success
- 201 - Created
- 400 - Bad Request (validation error)
- 401 - Unauthorized (missing/invalid auth)
- 403 - Forbidden (rate limited, registration disabled)
- 404 - Not Found
- 405 - Method Not Allowed
- 429 - Too Many Requests (rate limit)
- 500 - Internal Server Error

## Performance Optimizations

- Settings fetched in batch via `getSettings()` helper
- Media URLs added in single pass via `addMediaUrl()` helper
- Rate limit cleanup runs opportunistically during checks
- Database uses indexes on frequently queried columns
- Email tokens auto-expire and cleanup on next request
- Single-query validation via `requireFields()` helper
