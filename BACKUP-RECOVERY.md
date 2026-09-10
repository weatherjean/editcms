# Sessions, backup and recovery

Implemented 8 September 2026. This workflow covers the deployed `_edit` CMS. A public website or other files outside `_edit` need their own backup.

## Password changes

`PUT /_edit/admin-api/users/:id` updates the password and deletes every session belonging to that user in one transaction. Other users remain signed in. Invalid passwords and database failures change neither password nor sessions. Login checks that the verified password hash still matches when issuing its token, closing the login/reset race.

Changing your own password returns `reauthenticate: true`; the Users screen clears its local sign-in and returns to the login flow. Other browsers receive 401 on their next authenticated request. Already-running requests are not cancelled. This does not introduce administrator/editor roles: the existing all-trusted-admin account model remains.

## Create a backup

Use PHP CLI with the `sqlite3` and `zip` extensions on the server. Run it as the same filesystem user as PHP, or configure shared ownership/access for the data directory and operation lock. Deploy this version of the backend first so all API requests participate in its maintenance lock. Stop external scripts that write directly to CMS data; filesystem locks cannot coordinate writers that ignore them.

```sh
php /srv/site/_edit/core/Operations/backup.php backup /srv/site/_edit /srv/backups/cms-2026-09-08.zip
php /srv/site/_edit/core/Operations/backup.php verify /srv/backups/cms-2026-09-08.zip
```

The output directory must already exist outside the website directory. An existing archive is never overwritten. Choose a new filename for each backup.

The tool waits up to 30 seconds for active CMS API requests to finish, then holds an exclusive operation lock until the snapshot and archive verification complete. New API requests receive 503 with Retry-After while the lock is held; static assets remain available. The lock is released automatically even if the process exits unexpectedly.

The archive includes deployed admin assets, API/core code, root deployment rules, config.php (including the encryption key), schema configuration, uploads, and a consistent SQLite snapshot created with the SQLite backup API. Committed WAL changes are included; live WAL/SHM/journal files and the operation lock are excluded. Source-only directories such as admin-source are excluded. The tool requires built admin assets and an existing config.php; it does not initialize a new installation.

Each file has a SHA-256 checksum and size in the manifest. Database integrity and foreign keys are checked. Archives are published only after verification and use owner-only permissions (0600). They are **not encrypted** and contain credentials and private data; keep them in protected storage and copy verified backups off the server. Checksums detect corruption, not malicious replacement—restore only trusted archives. Retention and scheduling are operator-managed; the tool never deletes previous backups.

## Restore into a new installation

Create a new offline site directory first. The `_edit` destination itself must not exist:

```sh
mkdir /srv/recovered-site
php /srv/site/_edit/core/Operations/backup.php restore /srv/backups/cms-2026-09-08.zip /srv/recovered-site/_edit
```

The tool validates every archive entry, rejects traversal, symlinks and duplicate/unexpected files, checks checksums and database integrity, and stages extraction before publishing the new directory. Limits are 100,000 payload files and 10 GiB uncompressed. A corrupt archive leaves no partial destination. Existing installations are never overwritten.

Restored sessions, CAPTCHA challenges, legacy email tokens, setup codes and rate-limit records are cleared. Account roles are retained. Users and password hashes, persistent settings, content/revisions, schema files and uploads are retained. Everyone signs in again.

Restored files/directories initially use owner-only permissions. Before activation:

1. Set ownership and permissions for the deployment's PHP/web-server users; retain private-directory protection and avoid world-writable modes.
2. Check config.php and the Nginx/FPM configuration for environment-specific paths, origins and settings. The original encryption key must remain intact. The standard database path uses EDIT_BASE_PATH and relocates automatically. If config.php uses an absolute old database path, correct it: a restored-database guard returns 503 until the configured database matches this restored copy.
3. Verify login, representative content, media, schema files, revisions and settings. Do not send a real SMTP test unless intended.
4. Validate the serving configuration and switch the website to the recovered directory only after those checks. Keep the previous installation for rollback.

The tool restores CMS code and data together to the captured version. It does not change DNS, switch document roots, reload servers, deploy over a live site, or automatically upgrade restored data. To test a later application update, do so on the recovered copy first, retaining config.php, data and uploads.

## Tests

- `php tests/auth-sessions.php`: multiple sessions, unrelated users, weak/missing-user resets, forced transactional rollback, cleared cached identity, and a simulated reset between password verification and token insertion.
- `python3 tests/recovery-integration.py [dist/_edit-recovery.zip]`: real local HTTP password changes and maintenance behavior; WAL-backed backup; verification and clean restore; content/config/upload comparison; successful secret decryption without printing credentials; old-token rejection and new login; corruption/traversal/overwrite rejection; prevention of reconnecting a restored site to the original database.

Fixtures use disposable sites and synthetic credentials. No live installation is backed up, altered or restored by these tests. Browser rendering and production PHP-FPM integration remain unverified.
