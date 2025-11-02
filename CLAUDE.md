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
cd _edit/admin
npm run dev
```

Access the admin at: http://localhost:5173/_edit/admin/

### Building for Production

```bash
cd _edit/admin
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
- `/_edit/api/*` → Routes to `_edit/api/index.php`
- `/_edit/uploads/*` → Static file serving
- Direct `/admin/` or `/api/` access is blocked (404)

### Backend Structure

**PSR-4 Autoloading:**
- Namespace: `Edit\Core\`
- Base path: `_edit/core/`
- Configured in: `_edit/core/bootstrap.php`

**Core Components:**

1. **Database** (`_edit/core/Database/Database.php`)
   - Custom SQLite wrapper with query builder
   - No external dependencies
   - Methods: `query()`, `execute()`, `beginTransaction()`, `commit()`, `rollback()`

2. **Auth** (`_edit/core/Auth/`)
   - Custom JWT implementation (no libraries)
   - JWT secret stored in `_edit/config/jwt-secret.txt`
   - Auth check: `$auth->verifyRequest()` returns user ID or false

3. **Content Type System** (`_edit/core/ContentTypes/`)
   - **ContentTypeRegistry**: Loads post types and field groups from `_edit/config/config.json`
   - **ContentType**: Handles CRUD for dynamic content types
   - Post types and field groups are defined in JSON, NOT in database
   - Registry combines post types with their assigned field groups to build content schemas

4. **Field System** (`_edit/core/Fields/`)
   - 12 field types: Text, Textarea, Wysiwyg, Number, Boolean, Select, Date, Datetime, Slug, Media, Relationship, Repeater
   - All extend `BaseField` and implement `FieldType` interface
   - Each field handles validation, sanitization, and database serialization
   - Methods: `validate()`, `sanitize()`, `toDatabase()`, `fromDatabase()`

### API Structure (`_edit/api/index.php`)

Single-file REST API with pattern matching:
- Auth endpoints: `/auth/login`, `/auth/register`, `/auth/me`
- Config: `GET /config`, `PUT /config` (edits config.json)
- Post types: `GET /post-types` (read-only from JSON)
- Field groups: `GET /field-groups` (read-only from JSON)
- Media: `POST /media`, `GET /media`, `DELETE /media/:id`
- Dynamic content: `GET|POST|PUT|DELETE /{post-type}(/:id)`

**Key Pattern:** Content types are dynamically routed using regex: `#^/([a-z_-]+)(/(\d+))?$#`

### Frontend Structure

**Vue 3 + Vite + Tailwind + DaisyUI**

Composables:
- `useApi.js` - API request handling with auth headers
- `useAuth.js` - Authentication state management

Views:
- `ConfigView.vue` - JSON editor for config.json (uses Monaco Editor)
- `ContentListView.vue` - Lists content items for a post type
- `ContentEditorView.vue` - Creates/edits content items
- `MediaView.vue` - Media library management

Components:
- `FieldRenderer.vue` - Dynamically renders field inputs based on type
- `RepeaterField.vue` - Handles nested repeating field groups
- `MediaField.vue` + `MediaModal.vue` - Media selection interface
- `JsonEditor.vue` - Monaco Editor wrapper for JSON editing

**Vite Config Notes:**
- Base path: `/_edit/admin/`
- API proxy: `/_edit/api` → `http://localhost:8001`
- Uploads proxy: `/_edit/uploads` → `http://localhost:8001`
- Output dir: `../admin-dist`

### Configuration System

**Single source of truth:** `_edit/config/config.json`

This JSON file defines:
- `post_types[]` - Content types with labels, icons, descriptions
- `field_groups[]` - Field collections with location rules

**Key Concept:** Post types and field groups are linked by `locations` array. A field group with `"locations": ["post"]` attaches its fields to the "post" content type.

ConfigView.vue provides a Monaco-based JSON editor for this file.

### Content Storage

Content is stored in two tables:
- `content` - Core fields (id, type, slug, status, author_id, timestamps)
- `content_meta` - Custom fields (content_id, meta_key, meta_value)

Field values are serialized using field-specific `toDatabase()` methods and deserialized with `fromDatabase()`.

### Media System

Media files are stored in: `_edit/uploads/YYYY/MM/`
Database tracks: filename, path, mime_type, size, timestamps

URL format: `/_edit/uploads/2025/10/[unique-id].[ext]`

### Database Schema

Created automatically on first run:
- `users` - Admin users (id, name, email, password_hash)
- `content` - All content items across types
- `content_meta` - Field values (EAV pattern)
- `media` - Uploaded files metadata

## Development Patterns

### Adding a New Field Type

1. Create `_edit/core/Fields/[Type]Field.php` extending `BaseField`
2. Implement `validate()`, `sanitize()`, `toDatabase()`, `fromDatabase()`
3. Add rendering logic to `FieldRenderer.vue` for the new type
4. Field will automatically work in content types once added to config.json

### Adding a New Post Type

Edit `_edit/config/config.json`:
1. Add entry to `post_types[]` array with key, labels, icon
2. Create field group(s) with matching location(s)
3. No code changes needed - API routes generate automatically

### Debugging

PHP errors logged to: `server.log` (when using built-in server)

Enable error display in `_edit/core/bootstrap.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', '1');
```

Vue dev tools work normally with Vite dev server.

## Important Constraints

- All API requests require JWT auth (except `/auth/register` and `/auth/login`)
- Slugs must be unique per content type
- Config.json must be valid JSON or the entire CMS breaks
- PHP 8.1+ required for typed properties and modern syntax
- SQLite3 extension must be enabled
- No database migrations - schema is auto-created
