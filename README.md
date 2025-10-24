# _edit CMS

A lightweight, ACF-style headless CMS built for shared hosting environments.

## Features

- **ACF-Style Field Builder** - WordPress Advanced Custom Fields inspired interface
- **Dynamic Content Types** - Create custom post types with flexible field groups
- **12 Field Types** - Text, Textarea, WYSIWYG, Number, Boolean, Select, Date, DateTime, Slug, Media, Relationship, Repeater
- **Vue 3 + Vite Admin** - Modern, fast admin interface with hot module reloading
- **SQLite Database** - No MySQL required, perfect for shared hosting
- **Zero External Dependencies** - Custom JWT auth, no composer packages
- **RESTful API** - Auto-generated endpoints for all content types

## Requirements

- PHP 8.1+
- PHP SQLite3 extension
- Node.js 18+ (for admin development)

## Quick Start

### 1. Install Dependencies

```bash
cd _edit/admin
npm install
```

### 2. Start Development Servers

**Terminal 1 - PHP Backend:**
```bash
cd /path/to/scms
php -S localhost:8000 router.php
```

**Terminal 2 - Vue Admin:**
```bash
cd _edit/admin
npm run dev
```

### 3. Access Admin

Open http://localhost:5173/_edit/admin/

On first run, you'll be prompted to create an admin account.

## Project Structure

```
scms/
├── _edit/
│   ├── admin/           # Vue 3 + Vite admin interface
│   │   ├── src/
│   │   │   ├── composables/   # useApi, useAuth
│   │   │   └── views/         # PostTypes, FieldGroups, Content, Media
│   │   └── vite.config.js
│   ├── api/
│   │   └── index.php    # REST API router
│   ├── core/
│   │   ├── Auth/        # JWT authentication
│   │   ├── Database/    # SQLite wrapper & seeder
│   │   ├── PostTypes/   # Post type manager
│   │   ├── FieldGroups/ # Field group manager
│   │   ├── Fields/      # 12 field type classes
│   │   └── ContentTypes/# Content CRUD logic
│   ├── config/
│   │   └── jwt-secret.txt
│   ├── database/
│   │   └── site.sqlite
│   └── uploads/
└── router.php           # PHP dev server router
```

## Building for Production

```bash
cd _edit/admin
npm run build
```

Built files will be in `_edit/admin-dist/`. Deploy the entire `_edit/` directory to your server.

## API Usage

All endpoints require JWT authentication (except initial registration).

### Authentication

```bash
# Register
POST /_edit/api/auth/register
{
  "name": "Admin",
  "email": "admin@example.com",
  "password": "password"
}

# Login
POST /_edit/api/auth/login
{
  "email": "admin@example.com",
  "password": "password"
}
```

### Post Types

```bash
# List all post types
GET /_edit/api/post-types

# Create post type
POST /_edit/api/post-types
{
  "label": "Product",
  "label_plural": "Products",
  "description": "Product catalog"
}
```

### Field Groups

```bash
# List all field groups
GET /_edit/api/field-groups

# Create field group with fields
POST /_edit/api/field-groups
{
  "title": "Product Details",
  "locations": ["product"],
  "fields": [
    {
      "label": "Price",
      "key": "price",
      "type": "number",
      "required": true,
      "config": {"min": 0, "step": 0.01}
    }
  ]
}
```

### Content

```bash
# List all products
GET /_edit/api/product

# Create product
POST /_edit/api/product
{
  "title": "My Product",
  "slug": "my-product",
  "status": "published",
  "fields": {
    "price": 29.99
  }
}
```

## Development

- **Backend:** PHP 8.1+ with custom autoloader (PSR-4)
- **Frontend:** Vue 3 Composition API with Vite
- **Database:** SQLite with custom query builder
- **Auth:** Custom JWT implementation (no libraries)

## License

MIT
