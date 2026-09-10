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

Content stores core attributes in `content` and custom values in `content_meta`. Field schemas drive validation, queries and public serialization. Configuration changes use immutable generations selected by an atomic pointer; use the admin/API after activation. The bundled example schemas are validated by `tests/example-config.php`. The redundant `post.json` module has been removed; `blog.json` is the canonical example for posts.

Sessions live in SQLite. Fresh setup requires a CLI-generated one-time code; users added later default to Editor. ALTCHA protects public contact submissions; SMTP and encryption use PHP extensions. ALTCHA PHP and HTML Purifier are vendored with licenses; Composer is not required.

## Build

```sh
bash build.sh dev
# or: make build
```

Always use the complete builder. It runs `npm ci`, builds the admin, copies PHP and server rules, and writes `dist/_edit-dev.zip`. It excludes local secrets, databases, content schemas, uploads, source dependencies and Git history. It does not install the build over your local data.

## Tests

```sh
bash tests/run.sh test
```

This lints PHP, builds the complete ZIP, checks the bundled example schemas, and runs content-list state, content API, permissions, CAPTCHA, upload, deployment, recovery and release regressions. Install Apache and Nginx for the server-boundary fixtures (`brew install nginx` plus the system Apache on macOS; `apt-get install nginx apache2` on Ubuntu).

HTTP fixtures use temporary installations and localhost sockets. Email tests use a local SMTP fixture and do not send real mail. Deployment tests require Apache and Nginx and fail if a required server is unavailable. Run browser checks for editor formatting, nested fields, media and narrow layouts before releasing; command-line tests do not establish UI correctness.

See [deployment](DEPLOYMENT.md) for roles, contact forms and recovery, and the admin’s [API reference](_edit/admin-source/PUBLIC-API.md) for content queries. Quill 2.0.3 has an outstanding low-severity HTML-export advisory; HTML is sanitized on the server and at editor insertion points.

## Changes

Add PHP field implementations under `core/Fields/` and corresponding controls in `admin-source/src/components/FieldRenderer.vue`; update the schema validator and tests too. API route handlers belong under `api/routes/` or `admin-api/routes/`, with explicit public/role boundaries. Database schema changes belong in `core/Database/Database.php` and need upgrade coverage.

Build and test the ZIP after changes. Follow [deployment](DEPLOYMENT.md) for upgrades: the encryption key, database, complete configuration directory and uploads must be retained together.

## Formatting

Project-owned PHP uses `.php-cs-fixer.dist.php` with PHP-CS-Fixer 3.95.25. Run `php-cs-fixer fix --sequential` to format or add `--dry-run --diff` to check. Vendored libraries are excluded and retain their original source. CI downloads the pinned formatter and verifies its checksum; it is not a runtime dependency.

## CI and releases

`.github/workflows/ci.yml` checks pull requests and pushes to `main` on Ubuntu with PHP 8.4 and Node 24. The jobs build and test one distribution, then pass that exact ZIP and `SHA256SUMS` to the release job. Only a successful push to `main` publishes; PRs and manual runs produce downloadable workflow artifacts without a release.

Versions use `vYYYY.MM.DD.RUN_NUMBER`, for example `v2026.09.10.12`. The date comes from the source commit; the run number distinguishes pushes. Releases target the tested commit and start as drafts until both assets are uploaded. Reruns leave published releases unchanged and can resume interrupted drafts. Only the release job receives repository write permission. No personal access token is required.

Actions must be enabled on the repository. The workflow and automatic release path have passed a hosted run.
