# _edit CMS revival assessment

Reviewed 8 September 2026 against the working tree, including the six existing modified files. No application code, existing configuration, database, or uploads were changed during this review.

## Current status

This document records the **pre-repair assessment from 8 September 2026**. The historical findings below are retained for context; they do not describe the current implementation.

Vue was retained. Completed repair passes cover [dependencies](DEPENDENCY-UPDATE.md), [contact-form verification and SMTP](ALTCHA-INTEGRATION.md), [public output and uploads](PUBLIC-BOUNDARY-HARDENING.md), [content validation and revisions](CONTENT-CORRECTNESS.md), [sessions and recovery](BACKUP-RECOVERY.md), [installation and roles](SETUP-AND-PERMISSIONS.md), and [HTML sanitization and configuration imports](HTML-AND-CONFIG-HARDENING.md).

Automated local tests cover those repairs, including packaged HTTP and Apache/Nginx routing fixtures. Browser screenshots establish that the demo renders, but do not establish complete interactive coverage. Production PHP-FPM, load testing, image processing, editor conflict handling and a project-specific inquiry workflow remain outside that verification. The bundled example configuration now passes the whole-configuration validator. Content lists distinguish loading, failed requests and empty results; CI and automatic ZIP release workflows are included.

## Historical assessment

**Decision: a conditional go for a small, internally managed real estate catalogue. Budget for backend repairs before production use.** There is enough working structure to justify reuse. The public API and recovery features currently have correctness problems that directly affect a property website. A public marketplace, multiple independent agencies, or owner self-service would require a substantially different authorization and operational design.

This assessment assumes a single organization, a few trusted editors, a single server, and relatively infrequent listing changes. Listing volume, languages, imports, hosting constraints, and launch timing remain unspecified. No capacity limit has been established by load testing.

**What exists and is worth retaining**

| Area | Inventory | Assessment |
| --- | --- | --- |
| Backend | Plain PHP, custom autoloading, separate admin/public entry points, route modules | Small enough to understand and improve incrementally |
| Persistence | PDO SQLite; content, text metadata, users, sessions, media, settings, email records, revisions | Suitable starting structure; property search needs typed queries |
| Content configuration | JSON modules, shared field groups, blocks, import/export, visual field builder | Valuable for defining properties, agents, and supporting pages |
| Editing | Draft/published content, grouped fields, repeaters, flexible blocks, relationships, rich text | Considerable reusable functionality, with validation gaps |
| Media | MIME/image checks, library, multiple selection, usage check, alt-text database column | Missing a property-photo processing and delivery pipeline |
| Recovery | Revision snapshots, last ten retained, restore UI | Restore currently fails; revisions are not a backup system |
| Admin | Vue 3, Vue Router, Tailwind/DaisyUI, Quill; 34 Vue files | Working build; substantial dynamic form behavior |
| Public website | Headless content API and documentation | No real estate website, listing templates, or search experience implemented |
| Delivery | Vite build, ZIP packaging, Apache rules, Nginx example | Deployment paths and documented requirements have drifted |

Positive implementation choices include password hashing, random session tokens, parameterized values in database queries, transactions around content writes, foreign keys, batched metadata loading, and upload MIME checks. They provide useful foundations, but do not establish production readiness on their own.

**Highest-priority findings**

1. **Public email accepts an arbitrary recipient. Fix before enabling SMTP on an exposed instance.** `admin-api/routes/email.php:108` accepts caller-controlled `to`, subject, and message without authentication. Tokens can be obtained publicly; a token limits reuse, not who may receive mail. The color CAPTCHA exposes colored characters as HTML and has no challenge expiry or single-use binding. It is not an effective barrier to automated extraction. Replace this endpoint with a property-inquiry operation: validate a property reference, select the receiving address on the server, persist the inquiry, and send a constrained notification. Token consumption also needs an atomic check-and-consume operation.

2. **The custom SMTP transport needs replacement or extensive repair.** In `core/Email/SMTP.php`, headers interpolate subject/from-name without CR/LF rejection, DATA lacks dot-stuffing, and protocol responses are accepted using a broad 2xx/3xx check. `sendCommand()` embeds the failed command in its exception; during authentication this can contain a base64-encoded credential. Errors flow to email logs and the public error response. These are source-confirmed paths; no SMTP connection or message was sent during review. Use a maintained mail transport and redact errors at the boundary. Submitted `reply_to` is logged but never passed into the actual outgoing message.

3. **Public serialization does not consistently protect unpublished information.** `core/ContentTypes/ContentType.php:685` resolves relationship metadata without a published-only condition. The public handlers use that same object before their optional population step. An isolated test reproduced a published record containing the slug and ID of a related draft. A user-target relationship can also expose the selected user's email by design. Separate admin and public serializers, explicitly allow public fields, and apply publication checks at every relationship depth. A private field/type policy is necessary before storing owner details or internal notes beside public listings.

4. **Deployment configuration is inconsistent and upload safety depends too heavily on it.** `_edit/nginx.conf` points the API at the nonexistent `admin/api/index.php`, points the SPA at `admin/dist`, lacks an admin-api handler, and protects the old `admin/core` location. The development `router.php` permits static files under `data/` to reach the built-in server; `.htaccess` cannot protect them there. These are source findings, not a verified production-server exploit. `admin-api/routes/media.php:58` validates MIME but retains the original filename extension. Derive extensions from accepted MIME types, block executable handling in uploads at every server, and explicitly deny private directories. Test Apache and Nginx against the actual distribution archive.

5. **Property filtering is broken at three separate layers.** Confirmed with synthetic in-memory data:

   - PHP parses `fields[price_gte]=200000` into a nested `fields` array. `api/routes/public.php:190` expects a literal top-level bracketed key, so the documented filter is silently ignored. The admin copy has the same parser problem.
   - Grouped values are stored under keys such as `details.price`, while the parser emits `price`; an otherwise valid filter does not match the stored key.
   - `content_meta.meta_value` is TEXT and comparisons have no numeric conversion. With the key corrected, a `>= 200000` query included 90000 and excluded 1000000.

   Ordering also targets the content table rather than property metadata. A test of `order_by=price&order_dir=ASC` retained the insertion ordering instead of sorting the prices. Implement a schema-aware query contract with numeric comparison, explicit sortable fields, bounded pagination, and stable tie-break ordering. Merely repairing the URL parser is insufficient.

6. **Revision restoration fails.** `core/ContentTypes/ContentType.php:904` starts a transaction and calls `update()`, which starts another transaction. The test failed with `There is already an active transaction`. Choose a single transaction owner and test update → restore → read-back, including media values and configuration evolution.

7. **Validation does not enforce the whole schema.** `saveMeta()` validates submitted keys only. A record with a required price omitted was accepted and published. Unknown fields are stored; repeater validation does not recursively validate its child schema; flexible content does not have an equivalent complete validation pass. `NumberField` uses `empty()`, turning zero into null. Required booleans and zero-valued numbers also need explicit handling. Validate the complete record before publishing and reject invalid types, unknown keys, invalid choices, and out-of-range values.

8. **Population can change the meaning of a number.** `api/routes/public.php:357` tries numeric nested fields as media IDs without consulting their field types. With media ID 1 in the fixture, a nested `bedrooms: 1` became a media object. This path is reached during optional population of flexible content. Replace numeric guessing with schema-directed relationship/media expansion.

**Other material repair work**

| Finding | Evidence and implication | Next action |
| --- | --- | --- |
| Every account has full administrative powers | `admin-api/index.php` has one authentication checkpoint; users can change any user's password and edit configuration/SMTP | Explicit administrator/editor capabilities, or consciously restrict all accounts to fully trusted administrators |
| Existing sessions survive password changes | Isolated test reproduced a valid old token after the same password update performed by the user route | Revoke sessions on reset; establish account recovery and session-expiry behavior |
| Browser-readable bearer tokens | `src/composables/useAuth.js` stores tokens in localStorage | Evaluate HttpOnly cookie sessions with CSRF protection; fix rendering trust boundaries regardless of storage choice |
| Rich content is treated as trusted | `HtmlField` and `WysiwygField` return submitted HTML unchanged; SVG filtering is a regex denylist | Define trusted raw HTML separately; use robust sanitization for rich text; initially disallow SVG if unnecessary |
| Fresh and upgraded schemas differ | New `email_logs` lacks message/from_name/reply_to/is_html; migration adds them on a later initialization | Versioned migrations; create and upgrade must yield the same schema. Direct first-connection logging failed in the test; normal subsequent HTTP requests may already have migrated it |
| Public reads also write to SQLite | Public content requests invoke database-backed rate limiting with cleanup/upsert/update operations | Measure contention, add caching, and move appropriate request limiting to the serving layer |
| Config import is partial and weakly validated | Import overwrites valid files as it goes, can return success with errors, and validates only shallow structure | Stage, validate the whole registry and collisions, then atomically replace; require a successful backup |
| Schema identities can collide | Registry flattens fields by bare field key across groups; later definitions overwrite earlier ones | Use stable group-qualified identifiers and migrations for renames/type changes |
| Media usage check is heuristic | String matching over all metadata can confuse numbers with IDs and misses numeric JSON lists such as `[1,2]`; revisions are not included | Track explicit media references and define deletion/revision retention behavior |
| Originals only | Upload route moves the original; no resize, thumbnail generation, orientation correction, or metadata stripping | Process property photos, preserve ordered galleries, provide responsive variants, and handle large camera files |
| Incomplete operational recovery | Docs describe copying data, but no automated full restore workflow; encrypted settings depend on config.php's key | Back up database, uploads, schema configuration, and encryption key together; demonstrate restoration on a clean instance |
| Error boundaries are inconsistent | Malformed JSON can return null into array-typed validation; handlers catch Exception rather than all Throwable cases | Uniform bounded input parsing, structured 4xx errors, safe generic 5xx responses, private diagnostic logs |
| First account is claimed publicly | Registration is allowed when the user count is zero, without an installer secret or transactional claim | Secure first-run provisioning before exposing an empty installation |

**Admin usability and maintainability**

The content list currently shows slug/status/created date, fetches the whole list, and has no search or pagination UI. A load failure is displayed like an empty collection. Property editors will need reference/title, cover photo, price, location, availability, useful filters, and unmistakable loading/error states. Add unsaved-change protection, explicit publish validation, predictable session-expiry handling, and conflict detection when two editors save the same record. Deletion currently permanently removes content and cascades its revisions; add trash or a clear recovery policy.

The public route implementations and helpers are duplicated between `api` and `admin-api` and already differ. Consolidate shared services and serializers while retaining explicit authorization boundaries. The 940-line `ContentType` class mixes persistence, validation, serialization, population, filtering, and revisions; split those responsibilities around the confirmed bugs. Avoid a broad rewrite before behavior is covered.

The dependency list contains React's Monaco wrapper and a Monaco Vite plugin even though this is a Vue app and the Vite configuration does not install that plugin. Check actual reachability before removing packages: the standalone `JsonEditor.vue` imports Monaco but may not be used by the current screen tree. All route components are imported eagerly. Lazy loading and dependency cleanup can improve the current frontend independently of replacing Vue.

**Fit for the real estate project**

Keep the JSON schema facility for ordinary editorial fields. Give frequently searched property attributes a dependable typed representation: either schema-aware typed metadata queries for the initial small catalogue, or a dedicated one-to-one property table/index when filtering complexity warrants it. Do not migrate databases merely because this is SQLite. Single-server, modest-write websites are an intended use case; operational characteristics and concurrent writes determine fit. See [SQLite's guidance](https://www.sqlite.org/whentouse.html). If enabling WAL, consider filesystem constraints and the backup implications described in [SQLite's WAL documentation](https://www.sqlite.org/wal.html).

| Property data | Suggested representation |
| --- | --- |
| Identity | Stable reference, title, URL slug |
| Publication | Draft/published, optional archive state |
| Commercial availability | Available/reserved/sold/rented, independently of publication |
| Transaction and property type | Sale/rent; controlled categories |
| Pricing | Integer minor units plus currency and rental period; separate price-on-request flag |
| Location | Country/region/locality, coordinates, separately controlled street-address visibility |
| Dimensions | Numeric living/plot area with explicit units; rooms/bedrooms/bathrooms |
| Marketing | Description, amenities, ordered gallery, cover image, floor plans |
| Contacts | Public agent profile separated from login users and private owner information |
| SEO | Titles/descriptions, canonical URL policy, redirects after slug changes |
| Inquiries | Separate private records linked to property; delivery status and retention policy |

The public website still needs rendering, listing/detail pages, filters, gallery interactions, canonical URLs, sitemap, and appropriate metadata. Choose languages, address visibility, and import sources before stabilizing the schema. Persist inquiries before attempting delivery so a mail outage does not lose leads. No legal-compliance assessment was performed.

**Replacing Vue with handrolled JavaScript**

This is feasible and can be a reasonable longevity choice for a deliberately bounded CMS, but feature stability alone does not remove the existing UI complexity. The frontend contains 34 Vue files totaling 6,219 lines, plus 718 JavaScript lines and 72 stylesheet lines. Those counts include templates, comments, and icons; they are a scope indicator, not an estimate of equivalent vanilla code.

The backend is already separated by JSON endpoints, so a replacement UI can be built alongside the existing admin. Keep its API contract explicit and repair known backend bugs instead of reproducing their accidental behavior.

| Migration area | Relative difficulty |
| --- | --- |
| Navigation, fetch wrapper, login, simple lists, ordinary forms | Low to moderate |
| Toasts, dialogs, focus handling, errors, loading, cleanup | Moderate |
| Media upload/selection, ordered galleries, relationships | Moderate to high |
| Schema-driven field editing, nested repeaters and flexible blocks | High |
| Visual schema builder, editor synchronization, revision reloads | High |
| Rich-text editing | Retain a maintained editor library; do not build a text editor from scratch |

Use native ES modules, explicit screen-level state, a small field-renderer registry, event delegation, native form controls and dialogs, and a disciplined mount/dispose lifecycle. Keep stable IDs for repeated items. Preserve cursor/focus and editor instances rather than replacing the entire form DOM on each keystroke. Render ordinary text with textContent; establish a separate trusted/sanitized HTML boundary. Avoid inventing a generic reactive system or component framework merely to recreate Vue implicitly.

Two coherent options exist: a compact vanilla admin retaining the JSON API, or PHP-rendered admin pages with JavaScript enhancement for media and nested editing. The second reduces client-side navigation/state infrastructure but moves presentation work into PHP. Neither eliminates the complexity of dynamic schemas if all current schema-builder features remain.

The cleanest scope reduction would be to keep content schemas configurable in files while making the real estate project's schema stable, reducing the need for a full visual CMS-construction interface. That is a product decision, not permission to remove existing features. A generic CMS with fixed features can still receive arbitrary nested content configurations; test those boundaries explicitly.

My recommendation is to repair the backend and then prototype one demanding vertical slice: log in, edit a property, reorder gallery/repeater items, validate, save, reopen, restore a revision, and handle unsaved navigation. A simple list screen proves too little about replacement cost. Run the replacement under a separate admin path so the existing interface remains available while parity is checked.

For planning only, allow roughly **10–20 focused engineering days for a deliberately narrower admin**, or **20–35 days for broad existing-feature parity and regression work**, assuming one developer familiar with the code and a retained rich-text library. Backend repairs, a public property website, and substantial visual redesign are additional scope. These are preliminary ranges; use the vertical slice to replace them with evidence. A smaller bundle is plausible, but no Vue-only size attribution or vanilla prototype has been measured.

**Recommended sequence and acceptance gates**

1. **Establish a reproducible baseline.** Preserve existing working changes; pin supported runtime requirements; add a small executable integration harness and clean fixture database. Verify fresh install and upgrades produce equivalent schemas. Confirm packaging includes the intended configuration and routing files.
2. **Repair public boundaries and data behavior.** Constrain email, replace/redact transport handling, protect private serialization and uploads, fix filters/numeric values, enforce schemas, revoke sessions on password reset, and make revision restore work. Acceptance: automated fixtures for the confirmed failures pass, including public draft exclusion and representative prices.
3. **Build the property model and inquiry flow.** Use representative listings, galleries, sold/reserved states, and any multilingual requirements. Acceptance: correct result sets/order/pagination, safe public output, durable inquiries, and usable editor workflows.
4. **Decide the admin technology using the vertical slice.** Compare code size, clarity, accessibility, field behavior, and maintenance burden. If vanilla wins, migrate screen by screen with a written parity checklist. Avoid simultaneous backend contract churn and wholesale frontend replacement.
5. **Verify deployment and recovery.** Test the actual Apache/Nginx package, HTTPS sessions, private-path denial, nonexecutable uploads, realistic media storage, concurrent access, backup, restore, and an application update retaining site data and encryption configuration.

**Verification performed and limits**

- All 40 PHP files in core/API/admin API plus the root router passed `php -l` on local PHP 8.4.1. This verifies syntax, not compatibility across the documented PHP range.
- The installed Vite 7.1.12 production build succeeded into a temporary directory. Output: approximately 542 kB JavaScript / 167 kB gzip and 128 kB CSS / 21 kB gzip. Warnings concerned a CSS `@property` rule and a chunk over 500 kB. No dependency installation or lockfile change was performed.
- In-memory PHP/SQLite probes reproduced: ignored bracket-form filters, grouped-key mismatch, lexical price comparison, ineffective metadata price sorting, missing required field acceptance, zero-to-null conversion, numeric-to-media population, draft relationship metadata exposure, nested-transaction restore failure, surviving sessions after password change, and fresh email-log schema mismatch.
- Fixtures were synthetic. No existing user records, passwords, session tokens, SMTP secrets, or uploaded assets were read for test data. No email was sent.
- A localhost HTTP test was attempted but the sandbox blocked socket access. Consequently browser flows and real server routing remain unverified; deployment findings above are from source inspection.
- No project-owned automated test suite or CI workflow was found in the reviewed tree. Installed dependency tests are not application coverage.
- The optional npm advisory check did not complete. Automatic approval review rejected sending dependency metadata to the public npm registry; permission was requested separately. This is not a clean vulnerability-audit result.
- The README's PHP 8.1 minimum is obsolete for deployment: PHP 8.1 is now unsupported; choose an actively maintained branch and test on it. See [PHP support policy](https://www.php.net/supported-versions.php) and [unsupported branches](https://www.php.net/eol.php). The locked Vite package requires Node `^20.19.0 || >=22.12.0`, inconsistent with the README's Node 18 claim; [Vite's guide](https://vite.dev/guide/) also documents the newer requirement. Select a maintained Node release satisfying the lockfile.

The review establishes a concrete repair backlog and a viable reuse path. It does not establish a production security certification, a full dependency audit, measured capacity, or tested frontend parity.
