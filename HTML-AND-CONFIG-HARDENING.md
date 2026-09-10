# HTML and configuration hardening

HTML fields, WYSIWYG fields and HTML email now use locally vendored HTML Purifier 4.19.0. Its unmodified PHP library, LGPL license and provenance manifest ship under `core/ThirdParty/HTMLPurifier`. The editor additionally uses DOMPurify 3.4.15 at all direct HTML insertion points. DOMPurify is pinned in the npm lockfile, bundled into the admin JavaScript, and its upstream license ships in `admin/licenses`. Neither sanitizer needs a CDN or runtime service.

## Compatibility

HTML fields now accept safe formatting, links, images, tables and Quill formatting classes. Scripts, event handlers, forms, embedded frames, SVG/MathML, data URLs, arbitrary classes and unsafe CSS are removed. Site-owned templates should contain executable code and embeds. The browser editor also strips inline styles; use its supported formatting controls. This intentionally narrows the former raw HTML field contract.

Legacy HTML is cleaned on content reads (including list results, nested repeaters, blocks and revision previews), without rewriting historical records. Saving or restoring a record sanitizes stored HTML through the normal field validator. Undeclared legacy fields are still excluded from public serialization. Text fields remain text: site templates must escape them when rendering HTML.

## Atomic configuration changes

Individual JSON uploads, ZIP imports and deletions now share one writer. It locks configuration writes, merges changes into the latest complete configuration, validates the entire result, stages and verifies a new immutable generation, makes a verified ZIP backup, then atomically renames the active pointer. A rejected change or failed backup leaves the active configuration unchanged. Concurrent writers retain each other's unrelated changes. Each PHP request pins one snapshot for both content and block registries.

The first read uses the existing `data/config/{modules,field-groups,blocks}` layout. After the first successful mutation, `data/config/.active.json` selects a complete directory under `.versions/`. **Use the admin configuration interface/API after activation; editing the old root JSON files no longer changes active configuration.** Export returns the active snapshot in the familiar three-directory ZIP layout. Imports retain merge semantics: omitted files remain; explicit deletes are separately validated.

Validation rejects duplicate definition keys, unresolved field-group locations and relationship targets, unsupported field types, duplicate sibling fields, malformed nested fields and invalid number bounds. Shared groups may no longer silently override module groups. Fix conflicting legacy definitions together in one import. Existing legacy configuration remains readable until changed; updates validate the complete candidate, so pre-existing invalid definitions must be corrected in that candidate too.

ZIPs reject unexpected paths, symlinks, duplicate entries and oversized contents (10 MiB compressed, 50 MiB total uncompressed, 2 MiB per JSON file, 1,000 entries). Every successful mutation creates a backup under `data/config/backups/`. Old generations and backups are retained; there is no automatic garbage collection. Monitor disk usage. Keep `.active.json` and `.versions` together during deployment/recovery. The full CMS backup CLI preserves these and excludes the transient writer lock.

Atomic rename protects against partial publication during request failures/process interruption on a local filesystem. This is not a guarantee against storage loss or power failure; keep off-host backups. The storage model assumes ordinary PHP request lifetimes and a filesystem supporting flock and atomic same-directory rename.

## Verification

- `php tests/html-config.php`: hostile HTML corpus, retained formatting, idempotence, nested legacy values, snapshot isolation, rejected imports/deletions, required backups, ZIP paths/symlinks and duplicate schemas.
- `python3 tests/config-integration.py dist/_edit-html-config.zip`: real multipart uploads/import/export, concurrent writers and authenticated/public HTML reads on disposable localhost workers.
- `python3 tests/recovery-integration.py dist/_edit-html-config.zip`: includes active configuration generation backup/restore.
- Existing CAPTCHA, public boundary, content, upload, deployment, email, setup and permission suites passed against the packaged build. PHP lint and full production build passed.

The local demo configuration, property editor and email screens were rendered and captured on 10 September 2026. This is a visual smoke check, not a complete interaction test; review editing, saving, nested fields, media and responsive layouts on staging before upgrading a live site.
