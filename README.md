# _edit CMS

A lightweight, ACF-style headless CMS built for shared hosting environments.

## Features

- ACF-Style Field Builder - WordPress Advanced Custom Fields inspired interface
- Dynamic Content Types - Create custom post types with flexible field groups
- 13 Field Types - Text, Textarea, WYSIWYG, HTML, Number, Boolean, Select, Date, DateTime, Slug, Media, Relationship, Repeater
- Vue 3 Admin Interface - Modern, fast admin with hot module reloading
- SQLite Database - No MySQL required, perfect for shared hosting
- Zero External Dependencies - Custom session-based auth, no composer packages
- RESTful API - Auto-generated endpoints for all content types
- Modular Configuration - JSON-based post types and field groups

## Requirements

- PHP 8.1+
- PHP SQLite3 extension
- Node.js 18+ (for development only)

## Quick Start (Development)

### 1. Start PHP Backend

```bash
php -S localhost:8000 router.php
```

### 2. Start Vue Admin

```bash
cd _edit/admin-source
npm install
npm run dev
```

### 3. Access Admin

Open http://localhost:5173/_edit/admin/

On first run, create an admin account.

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for production deployment instructions.

## Build for Production

```bash
bash build.sh
```

Output: `dist/_edit-dev.zip` - Ready to deploy to your server.

## Project Structure

```
_edit/
├── admin/              # Built Vue admin (production)
├── admin-api/          # Admin API (authenticated)
├── api/                # Public API (no auth)
├── core/               # PHP classes (Auth, Database, Fields, Security)
├── admin-source/       # Vue 3 source code (development)
├── data/
│   ├── config/        # Post types, field groups, blocks (JSON)
│   └── database/      # SQLite database
└── uploads/           # Media files
```

## Configuration System

All post types, field groups, and blocks are defined in JSON files:

- `data/config/modules/*.json` - Post types with their field groups
- `data/config/field-groups/*.json` - Reusable field groups
- `data/config/blocks/*.json` - Content blocks

Import/export configurations via the admin interface or use the example-configs.zip.

## Development

See [DEVELOPMENT.md](DEVELOPMENT.md) for development workflow, architecture, and API documentation.

## License

MIT
