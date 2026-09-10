# First-run setup and account permissions

Implemented 8 September 2026. This adds a first-account claim boundary and two staff roles. Existing accounts remain administrators after the additive database upgrade. No existing account is automatically demoted, and existing passwords or sessions are not changed by the migration.

## Fresh installations

After deploying the CMS, run as the same filesystem user as PHP (or with correctly shared data-directory permissions):

```sh
php /srv/site/_edit/core/Operations/setup.php
```

The command initializes the installation if necessary and prints a one-time setup code. Enter it with the first administrator's name, email and password at `/_edit/admin/`. The code expires after one hour. Running the command again invalidates the previous code; once an account exists, the command refuses to issue another code.

The database stores only the SHA-256 code hash and expiry. Claiming the installation checks the code and empty-user state inside one SQLite write transaction, creates the administrator and consumes the code. Simultaneous claims cannot create multiple first administrators. Incorrect or expired codes return 403, and existing installations continue to reject public registration.

Initial config.php creation is serialized and published atomically with owner-only permissions, preventing competing first requests from generating different encryption keys. Newly generated configurations default to debug mode off. Existing config.php files are retained unchanged.

This workflow requires server-side PHP CLI access for first setup; hosting environments without CLI need a hosting-provider-assisted setup. The HTTP API never issues setup codes. Treat the code as a temporary credential and use HTTPS when entering it. No code is generated for the real installation by the regression tests.

## Roles

| Capability | Administrator | Editor |
| --- | --- | --- |
| Read, create, update, publish and delete content; use revisions | Yes | Yes |
| Upload, read and delete media | Yes | Yes |
| Read post-type, field-group and block definitions needed by the editor | Yes | Yes |
| Manage schema/configuration files or import/export configuration | Yes | No |
| Manage users, roles and passwords | Yes | No |
| Read/manage SMTP settings, email logs or send authenticated test email | Yes | No |

The Users screen defaults new accounts to Editor and allows administrators to choose or change a role. Changes use `PUT /_edit/admin-api/users/:id/role` with `{"role":"editor"}` or `{"role":"admin"}`. Role changes revoke that user's sessions; self-changes return the user to login. The last administrator cannot be deleted or demoted, and that invariant is checked within a serialized transaction. Self-deletion remains prohibited.

Backend authorization reads the current database role on every authenticated request and uses an explicit editor allowlist. Hiding navigation is only a usability measure. Unknown roles are denied. Reserved management routes cannot gain editorial access by creating a content type with the same name.

Editors collaborate on the entire catalogue, including drafts and private editorial fields. This is not per-property, per-agency, or per-author authorization. Editors remain **trusted publishing staff**: rich-text/raw-HTML sanitization remains separate work, and these roles are not a sandbox for hostile contributors. Public content/email/health endpoints retain their separate existing policies. An editor's password is currently reset by an administrator; a self-service profile/password screen is not included.

## Recovery

The backup tool retains roles with the users table. Restore clears setup-code hashes/expiry along with sessions and other one-time credentials. An unclaimed restored installation therefore needs a new CLI-generated code. See BACKUP-RECOVERY.md for the complete workflow.

## Verification

- `php tests/setup-permissions.php`: hashing, rotation, expiry, consumption, first-admin role, role allowlist/denials, reserved-name protection, session revocation, last-admin invariants and legacy database migration.
- `python3 tests/setup-integration.py [dist/_edit-access.zip]`: a disposable deployed CMS with multiple local PHP workers, competing first-account claims, direct editor requests to privileged routes, editorial CRUD, role changes and session revocation.
- Earlier content, CAPTCHA/email, uploads, routing, password/reset and recovery regression tests continue to apply.

No live accounts, configuration or site data are changed by these tests. Browser rendering and production PHP-FPM remain unverified.
