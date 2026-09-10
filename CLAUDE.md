# Project guide

_edit is a PHP/SQLite headless CMS with a Vue admin. See DEVELOPMENT.md for the current architecture and test commands, and DEPLOYMENT.md for installation and upgrade requirements.

## Build rule

Always build with `bash build.sh [version]` from the repository root (or `make build`). Do not run `npm run build` or Vite directly unless explicitly requested. The builder packages the complete CMS, server rules, vendored libraries and browser assets into `dist/_edit-[version].zip`.

## Working conventions

- PHP classes live under `_edit/core/` with namespace `Edit\Core\`.
- Authenticated routes live under `_edit/admin-api/`; public routes under `_edit/api/`.
- Vue source lives under `_edit/admin-source/`; generated `_edit/admin/` is ignored.
- Preserve `config.php`, SQLite data, uploads and the complete configuration directory. Use synthetic fixtures for tests; never send real email from a test.
- After configuration activation, use the interface/API. `.active.json` selects an immutable generation; legacy root JSON files are no longer active.
- Validate writes through the field schema and sanitize HTML. Public output must use the public serializer; login-user data is not public content.
- Require administrator permission for configuration, account and email-settings changes. New accounts default to Editor.
- Run relevant regression fixtures and the complete distribution build after changes. Browser checks remain necessary for editor and media workflows.
- Keep documentation concise and accurate; preserve third-party source licenses and notices.
