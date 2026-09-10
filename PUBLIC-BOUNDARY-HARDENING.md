# Public boundary hardening

Implemented 8 September 2026. This addresses public serialization, upload naming, and server routing findings in REVIVAL-ASSESSMENT.md. It does not complete the remaining filtering, validation, revision, authentication, or recovery backlog.

## Public content contract

Both `/_edit/api/public/*` and `/_edit/admin-api/public/*` now use the same routes and schema-directed serializer. Admin content hydration remains unchanged. Public reads use unpopulated field values, then resolve only schema-declared media and relationships.

- Only published content in public types is returned, including relationship summaries and populated records.
- Login-user relationships and author IDs are omitted. Use a separate content type for public agent profiles.
- Add `"public": false` to a post type, field group, field, or block definition to exclude it from public output. Defined content defaults to public for compatibility; mark owner details and internal notes private before storing them.
- Unknown/open fields are excluded. Flexible content remains supported through registered block definitions. Repeaters use their child schemas. Undeclared block fields and unknown blocks are omitted.
- Unavailable single relationships become `null`; unavailable entries in multiple relationships are omitted. No draft ID or slug fallback is returned.
- `populate` expands only declared content relationships; numeric values are never guessed to be media. Expansion is bounded to three relationship levels, with published summaries beyond that. Structural nesting is also bounded.
- Field selection runs after visibility enforcement and cannot restore a hidden field.
- Visibility flags are configured in JSON. Existing flags survive saves in the visual schema editors; this pass does not add visibility controls to those editors.

**Compatibility:** clients relying on undeclared fields, login-user metadata, draft relationship IDs, or the old admin alias's differing population behavior must update. Previously stored content is not modified. This is an output policy, not rich-text HTML sanitization, editor authorization, or private media storage. Upload URLs remain public.

## Upload and routing changes

Upload extensions are derived from server-detected MIME types and names use 128 bits of randomness. SVG uploads are rejected; previously uploaded SVGs and files with unsafe/multiple extensions are blocked by the server rules. Existing affected assets need review and re-upload or conversion. No existing uploads are deleted or renamed.

Apache requires 2.4, mod_rewrite, and AllowOverride All. Uploads use the default static handler, deny symlinks and directory listings, allow only approved single-extension names, and send nosniff and sandbox headers. Deploy the updated root and uploads `.htaccess` files together.

Nginx now uses the actual `api/index.php`, `admin-api/index.php`, and `admin/index.html` paths. The `^~ /_edit/` boundary takes precedence over generic site PHP regex handlers. Set the FPM socket in `_edit/nginx.conf`; use a canonical document root without symlinks, since `disable_symlinks on` is enabled. Include the file inside the site server block, then validate with `nginx -t` before reload. Avoid competing CMS location rules.

The PHP development router serves only the two API front controllers and approved admin/upload assets, with realpath containment checks. It never delegates uploaded files to PHP execution. Private files and dot paths are denied.

References: [Nginx location precedence](https://nginx.org/en/docs/http/ngx_http_core_module.html#location), [Apache SetHandler](https://httpd.apache.org/docs/2.4/mod/core.html#sethandler).

## Verification

- `php tests/public-boundaries.php` and `php tests/public-boundaries.php admin-api`: isolated SQLite fixtures covering root/nested drafts, private types/groups/fields, user relationships, unknown fields/blocks, numeric fields, expansion, and both API aliases.
- `python3 tests/upload-boundaries.py [dist/_edit-hardened.zip]`: disposable real PHP CMS, authenticated multipart uploads, PHP/SVG rejection, spoofed extensions, random filenames, and image/PHP polyglot static serving.
- `python3 tests/deployment-boundaries.py [dist/_edit-hardened.zip]`: actual local PHP, Nginx, and Apache servers; private-path denial, traversal, symlinks, unsafe extensions, SPA/assets, and API routing. The server test currently uses macOS/Homebrew server paths. Apache uses harmless static front-controller stubs; Nginx checks routing with an unavailable FPM upstream (502). Full production PHP-FPM integration is not established by this test.
- All core/API PHP files linted; existing CAPTCHA and email integration tests retained.

Tests use temporary installations and synthetic credentials/files. They do not modify the existing CMS database, config, uploads, or send real mail. Browser rendering and production hosting remain separate checks.
