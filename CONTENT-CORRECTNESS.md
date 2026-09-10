# Content correctness pass

Implemented 8 September 2026, following the public-boundary work. This pass repairs metadata filtering/sorting, schema validation and revision restoration. Existing configuration and content are not migrated or rewritten.

## Queries

Both public API aliases and authenticated content lists use the same schema-aware query parser:

```text
/_edit/api/public/property?fields[details.price_gte]=200000&order_by=details.price&order_dir=ASC&limit=10&offset=0
```

`fields[price_gte]` and `order_by=price` also work when that bare key is unique. If groups share a key, use `group.field`. Storage and deserialization now use the qualified key too, so differently typed fields in separate groups no longer overwrite one another's field handlers.

Supported suffixes are `_gte`, `_lte`, `_not`, and `_like`; no suffix means equality. Filters combine with AND. LIKE retains SQL wildcard behavior and wraps the value in `%` for substring matching. Repeater, flexible-content and multiple-value fields cannot be filtered or sorted. Core sort keys are `id`, `slug`, `created_at` and `updated_at`; scalar metadata keys are also supported. Public queries reject private fields and login-user relationships.

Number comparisons and ordering use one finite-number conversion shared with validation, registered as the SQLite function `edit_number`. Invalid legacy numeric strings become NULL rather than zero. Missing/invalid values sort last in either direction, with content ID as a stable ascending tie-break. Metadata query values remain bound parameters. Counts and result queries apply the same filters.

Public pagination defaults to 10, permits limits 1–100 and offsets 0–1,000,000. Malformed parameters return 400. Authenticated lists retain the existing all-record behavior when no limit is supplied, because the current admin screen expects the full collection.

## Saving and publishing

- Drafts can omit required values. Supplied values must still have valid types, references and configured bounds.
- Publishing validates the complete resulting record, including when a status-only update is submitted. Required values are checked inside groups, repeater rows and registered flexible-content blocks.
- Zero and false are valid required values. Null remains distinct from false in boolean storage.
- Unknown fields/groups/blocks are rejected. Flexible content requires `allow_open`; its fields follow the selected block schema. Nesting is capped at 12 levels.
- Numbers enforce finite values and configured min/max/step. Selects use the builder's `config.choices` keys (or legacy `options`). Media and relationship values normalize to existing positive IDs; configured relationship post types are checked.
- Dates/datetimes reject invalid calendar values; datetime values use explicit supported formats and are stored in UTC. This does not add rich-text HTML sanitization.
- A submitted `fields` object still replaces all fields, matching the existing API contract. Omitting `fields` retains the stored fields, which are validated before publishing or updating a published record.
- Invalid content returns 422 with a field path; malformed JSON returns 400 and requests larger than 2 MiB return 413. Unexpected backend errors return a generic 500 response with private server diagnostics.

**Compatibility:** existing incomplete published records must be completed or changed to draft before saving. Old undeclared fields and removed blocks require schema/data reconciliation. Previously accepted invalid selections, references or numeric bounds now fail explicitly. No automatic cleanup of existing data is performed.

## Revisions

Update and restore each own exactly one transaction. Restore saves the replaced version as a revision, restores the selected slug/status/fields, and keeps the latest ten snapshots using revision number rather than ambiguous timestamps. Author attribution retains the existing behavior: restore does not change the current content author.

Revision reads and restores are scoped to the requested content type and content ID. Validation or database failures roll back the record, metadata and new revision together. A snapshot incompatible with the current schema fails without changing history; it is retained for manual recovery. Revision restoration is not a full installation backup.

## Verification

- `php tests/content-correctness.php`: 25 isolated SQLite checks covering filtering, counts, pagination, null ordering, malformed legacy numbers, collisions, drafts/publishing, nested validation, typed values, media IDs, restores, schema changes, forced database rollback and retention ties.
- `python3 tests/content-integration.py [dist/_edit-content-fixes.zip]`: real local HTTP CRUD, both public aliases, authenticated queries, visibility, malformed/oversized requests, 422 responses, and restoration with synthetic configuration/data.
- Earlier public-boundary, upload, deployment and CAPTCHA/email regression suites remain applicable.

Browser editing and full production PHP-FPM integration remain unverified. Session revocation, backup/recovery and the other remaining audit findings are separate work.
