# Development

## Setup

PHP 8.4 is tested; enable PDO SQLite, SQLite3, fileinfo, OpenSSL, DOM and Zip. Use Node.js 22.12+ and npm (`.nvmrc` selects Node 24).

```sh
make install
make develop
```

PHP runs on port 8000; Vite runs on port 5173 and proxies API/uploads. Open http://localhost:5173/_edit/admin/. First-account setup requires `php _edit/core/Operations/setup.php`.

## Structure

| Path | Purpose |
| --- | --- |
| `_edit/admin-source/` | Vue components, routing and build dependencies |
| `_edit/admin/` | Generated production admin; ignored by Git |
| `_edit/admin-api/` | Authenticated API, with role checks |
| `_edit/api/` | Public content, contact forms and health |
| `_edit/core/` | Shared PHP classes; `Edit\Core\` autoloader |
| `_edit/data/` | SQLite database and JSON configuration |
| `_edit/uploads/` | Uploaded media |
| `tests/` | Isolated PHP and HTTP regression fixtures |

Content stores core attributes in `content` and custom values in `content_meta`. Field schemas drive validation, queries and public serialization. Configuration changes use immutable generations selected by an atomic pointer; use the admin/API after activation. The repository includes legacy example schemas, which may need conflicting definitions reconciled before importing together.

Sessions live in SQLite. Fresh setup requires a CLI-generated one-time code; users added later default to Editor. ALTCHA protects public contact submissions; SMTP and encryption use PHP extensions. ALTCHA PHP and HTML Purifier are vendored with licenses; Composer is not required.

## Build

```sh
bash build.sh dev
# or: make build
```

Always use the complete builder. It runs `npm ci`, builds the admin, copies PHP and server rules, and writes `dist/_edit-dev.zip`. It excludes local secrets, databases, content schemas, uploads, source dependencies and Git history. It does not install the build over your local data.

## Tests

```sh
for test in tests/captcha.php tests/public-boundaries.php tests/content-correctness.php tests/auth-sessions.php tests/setup-permissions.php tests/html-config.php; do
  php "$test" || exit 1
done
bash build.sh dev
for test in tests/content-integration.py tests/upload-boundaries.py tests/deployment-boundaries.py tests/email_integration.py tests/recovery-integration.py tests/setup-integration.py tests/config-integration.py; do
  python3 "$test" dist/_edit-dev.zip || exit 1
done
```

HTTP fixtures use temporary installations and localhost sockets. Email tests use a local SMTP fixture and do not send real mail. Deployment tests additionally exercise Apache/Nginx when those executables are installed. Run browser checks for editor formatting, nested fields, media and narrow layouts before releasing; command-line tests do not establish UI correctness.

See [content behavior](CONTENT-CORRECTNESS.md), [HTML/configuration](HTML-AND-CONFIG-HARDENING.md), [roles](SETUP-AND-PERMISSIONS.md) and [recovery](BACKUP-RECOVERY.md) for contracts. [Dependency notes](DEPENDENCY-UPDATE.md) record versions and the remaining Quill advisory. [The original assessment](REVIVAL-ASSESSMENT.md) is historical, not a list of current failures.

## Changes

Add PHP field implementations under `core/Fields/` and corresponding controls in `admin-source/src/components/FieldRenderer.vue`; update the schema validator and tests too. API route handlers belong under `api/routes/` or `admin-api/routes/`, with explicit public/role boundaries. Database schema changes belong in `core/Database/Database.php` and need upgrade coverage.

Build and test the ZIP after changes. Follow [deployment](DEPLOYMENT.md) for upgrades: the encryption key, database, complete configuration directory and uploads must be retained together.
