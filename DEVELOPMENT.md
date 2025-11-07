# Development Guide

## Project Structure

```
scms/  (Development repository)
  ├── _edit/                    Deployable CMS
  │   ├── admin/               System files (replace on update)
  │   │   ├── core/           PHP backend classes
  │   │   ├── api/            API entry point
  │   │   └── dist/           Built Vue frontend (generated)
  │   ├── admin-source/        Vue source code (dev only)
  │   │   ├── src/            Vue components
  │   │   ├── package.json
  │   │   └── vite.config.js
  │   ├── data/                User data (keep on update)
  │   │   ├── database/       SQLite database
  │   │   └── config/         Post types & field groups
  │   ├── uploads/             User media files
  │   ├── .htaccess           Apache configuration
  │   └── nginx.conf          Nginx configuration
  │
  ├── build.sh                  Distribution builder
  ├── DEPLOYMENT.md             End-user installation guide
  ├── DEVELOPMENT.md            This file
  ├── CLAUDE.md                 Project instructions for Claude Code
  ├── dist/                     Build output directory
  ├── router.php                PHP dev server router
  └── .gitignore
```

## Setup Development Environment

### Prerequisites

- **PHP 8.1+** with extensions: SQLite3, PDO, JSON, fileinfo
- **Node.js 18+** and npm
- **Git**

### Initial Setup

```bash
# Clone the repository
git clone <repository-url>
cd scms

# Install Vue dependencies
cd _edit/admin-source
npm install
cd ../..
```

## Running Development Servers

You need TWO servers running simultaneously:

**Terminal 1 - PHP Backend (port 8000):**
```bash
php -S localhost:8000 router.php
```

**Terminal 2 - Vue Frontend (port 5173):**
```bash
cd _edit/admin-source
npm run dev
```

Then access: http://localhost:5173/_edit/admin/

The Vite dev server proxies API requests to the PHP server automatically.

## Building for Production

### Create Distribution Archive

```bash
./build.sh 1.0.0
```

This will:
1. Install dependencies (`npm install`)
2. Build Vue frontend (`npm run build` → `_edit/admin/dist/`)
3. Create archive in `dist/_edit-1.0.0.tar.gz`

**Build output includes:**
- `admin/core/` - PHP backend
- `admin/api/` - API entry point
- `admin/dist/` - Built Vue frontend
- `data/` - Empty folders for user data
- `uploads/` - Empty folder for media
- Configuration files (`.htaccess`, `nginx.conf`)
- `DEPLOYMENT.md` - Installation guide

**Build excludes:**
- `admin-source/` - Vue source code (dev only)
- `node_modules/`
- User data from development
- Git files

## Architecture

### Backend (PHP)

**PSR-4 Autoloading:**
- Namespace: `Edit\Core\`
- Base path: `_edit/admin/core/`
- No Composer dependencies - zero external requirements

**Key Components:**
- `Database/` - Custom SQLite wrapper
- `Auth/` - Custom JWT authentication
- `ContentTypes/` - Dynamic content type system
- `Fields/` - 12 field types (Text, Textarea, Wysiwyg, etc.)
- `Email/` - Custom SMTP implementation

**API Routing:**
- Single file: `_edit/admin/api/index.php`
- Pattern-based routing
- JWT authentication on all endpoints (except auth and public email)

### Frontend (Vue 3)

**Stack:**
- Vue 3 + Vite
- Tailwind CSS + DaisyUI
- Vue Router (hash mode)

**Key Files:**
- `App.vue` - Main layout, auth, navigation
- `router.js` - Route definitions
- `composables/useApi.js` - API request wrapper
- `composables/useAuth.js` - Authentication state

**Views:**
- `ConfigView.vue` - Manage post types & field groups
- `ContentListView.vue` - List content items
- `ContentEditorView.vue` - Create/edit content
- `MediaView.vue` - Media library
- `UsersView.vue` - User management
- `EmailView.vue` - SMTP configuration
- `HealthView.vue` - System diagnostics

**Components:**
- `FieldRenderer.vue` - Dynamically renders field inputs
- `RepeaterField.vue` - Nested repeating fields
- `MediaField.vue` + `MediaModal.vue` - Media selection

## Update Process

Users replace the `admin/` folder while keeping `data/` and `uploads/`:

```bash
# User's update workflow:
1. Backup data/ and uploads/
2. Delete admin/ folder
3. Extract new admin/ from update archive
4. Done - database migrations run automatically
```

This is why the directory structure separates:
- **System files** (`admin/`) - replaceable
- **User data** (`data/`, `uploads/`) - permanent

## Key Concepts

### Zero Dependencies

This project has ZERO external PHP dependencies:
- No Composer
- No packages
- Custom JWT, SMTP, routing, everything

**Why?** Works on any shared hosting without modification.

### Configuration Storage

Post types and field groups are stored as:
- Individual JSON files in `data/config/modules/`
- Individual JSON files in `data/config/field-groups/`
- Managed through the UI (ConfigView.vue)

### Content Storage

Content uses EAV (Entity-Attribute-Value) pattern:
- `content` table - Core fields (id, type, slug, status)
- `content_meta` table - Custom fields (meta_key, meta_value)

### Authentication

Custom JWT with secret stored in database:
- Auto-generated on first run
- No file-based secrets
- 1 hour token expiration

### Email System

Custom SMTP implementation:
- Native PHP sockets (`stream_socket_client()`)
- TLS/SSL support
- Single-use token system for public endpoints
- Rate limiting with exponential backoff

## Git Workflow

**.gitignore strategy:**
- Tracks `admin/` folder (system code)
- Tracks `admin-source/` folder (Vue source)
- Ignores `admin/dist/` (generated)
- Ignores `data/database/` (user data)
- Ignores `uploads/` (user media)
- Ignores `dist/` (build output)

## Testing

Currently no automated tests. For manual testing:

```bash
# Start dev servers
php -S localhost:8000 router.php
cd _edit/admin-source && npm run dev

# Test endpoints
curl http://localhost:8000/_edit/api/health

# Test admin
open http://localhost:5173/_edit/admin/
```

## Common Development Tasks

### Add a New Field Type

1. Create `_edit/admin/core/Fields/NewField.php` extending `BaseField`
2. Implement required methods
3. Add rendering in `admin-source/src/components/FieldRenderer.vue`
4. No registration needed - works automatically

### Add a New API Endpoint

Edit `_edit/admin/api/index.php`:
```php
if ($path === '/my-endpoint' && $method === 'GET') {
    $userId = $auth->verifyRequest(); // Add auth if needed
    if (!$userId) sendError('Unauthorized', 401);

    // Your logic here
    sendJson(['result' => 'data']);
}
```

### Modify Database Schema

Edit `_edit/admin/core/Database/Database.php`:
- Add migration in `runMigrations()` method
- Check if table/column exists before creating
- Migrations run automatically on first request

## Security Considerations

**Protected by default:**
- `/data/` directory - 403 forbidden
- `/admin/core/` and `/admin/api/` - 403 forbidden (direct access)
- `.sqlite`, `.txt`, `.log`, `.env` files - blocked
- Rate limiting on all public endpoints
- Registration locked after first user

**API Authentication:**
- All endpoints require JWT except:
  - `/auth/login`
  - `/auth/register` (only if no users exist)
  - `/send-email` (requires single-use token)
  - `/health` (public diagnostics)

## Performance

**Production optimizations:**
- PHP error display disabled
- Vite production build (minified, tree-shaken)
- SQLite with indexes on common queries
- Static asset caching headers in nginx.conf

**Size:**
- Distribution archive: ~100KB
- Installed size: ~500KB
- Database size: Varies by content

## Troubleshooting

**Dev server won't start:**
- Check PHP version: `php -v` (need 8.1+)
- Check if port 8000 is in use: `lsof -i :8000`
- Check if port 5173 is in use: `lsof -i :5173`

**Build fails:**
- Run `cd _edit/admin-source && npm install`
- Check Node version: `node -v` (need 18+)
- Clear Vite cache: `rm -rf _edit/admin-source/.vite`

**API returns 500 errors:**
- Check PHP error logs
- Ensure `_edit/data/database/` is writable
- Verify SQLite3 extension: `php -m | grep sqlite`

## Resources

- **Vite Documentation**: https://vitejs.dev/
- **Vue 3 Documentation**: https://vuejs.org/
- **DaisyUI Components**: https://daisyui.com/
- **Tailwind CSS**: https://tailwindcss.com/

## Contributing

When making changes:
1. Test locally with dev servers
2. Build distribution: `./build.sh test`
3. Test the built archive on a clean install
4. Update documentation if needed
5. Commit changes

The build process is your friend - it ensures what you develop is what gets deployed.
