# Vendored ALTCHA

The distribution contains ALTCHA widget 3.2.2 and the official PHP library 2.1.0. Both are MIT licensed; their license files and version/provenance metadata ship with the ZIP. No CDN, ALTCHA account, external verification service, Composer installation, or Node process is needed on the deployed host.

The standalone widget's embedded Svelte runtime notice is also included. The Vue admin remains unchanged in framework; Svelte is an implementation detail of the upstream widget.

The widget is a standalone asset at `/_edit/admin/altcha/altcha.min.js`; it does not require loading the Vue admin. Its default PBKDF2 worker and styling are embedded. Serve production forms over HTTPS. For a site with a restrictive Content Security Policy, allow this local script, local API connections, `worker-src 'self' blob:`, and the widget's injected styling. No third-party origin is needed.

**Endpoints and migration**

- `GET /_edit/api/captcha` issues an ALTCHA v2 protocol challenge (the widget's major version is 3). It replaces the old HTML/colored-letter response and sends `Cache-Control: no-store`.
- `POST /_edit/api/send-email` accepts JSON containing `subject`, `message`, optional `reply_to`/`from_name`/`is_html`, and the widget's base64 `altcha` payload. Proof verification happens on this request.
- The old `/send-email/token` endpoint returns **410 Gone**. Old email tokens and colored-letter answers cannot satisfy ALTCHA verification. Existing custom forms must be updated before deploying this release.
- Both public route aliases, `/api` and `/admin-api`, apply the same verification and share replay protection.
- The receiving address is configured in **Email → Contact Form Recipient**. On an upgrade, it defaults to the configured From Email until explicitly set. Forms should omit `to`; a supplied address is accepted only if it matches that configured recipient.
- **Send Test Email** now uses authenticated `POST /_edit/admin-api/email-test`. Administrators can test arbitrary destinations without solving a public challenge; unauthenticated requests cannot use this endpoint.
- New installations require ALTCHA by default. Existing `captcha_enabled=1` enables the replacement automatically; an explicit `captcha_enabled=0` is retained. Administrators can change this in Email settings. Keeping CAPTCHA disabled permits public submissions without proof, still subject to validation, recipient restrictions, and rate limits.

The packaged `/_edit/admin/altcha/contact-example.html` is a working plain-JavaScript contact form. Configure SMTP and the contact recipient first; submitting it sends a real message to that recipient. It demonstrates disabling submission before verification and resetting the widget after every send attempt. The script also works with the Vite development proxy.

**Verification and abuse controls**

The server issues PBKDF2/SHA-256 challenges with cost 1,000 and a randomly chosen target counter between 500 and 1,500. Challenges expire after ten minutes. These defaults need real-device testing and adjustment if observed spam or visitor performance warrants it; proof of work increases automation cost and does not prove humanity.

Challenge signatures use a purpose-specific key derived from the existing installation encryption key. PHP verifies the signed parameters, scope, expiry, and proof using the unmodified official library. It accepts only the expected algorithm, work parameters, and bounded payloads.

The `captcha_challenges` table tracks issued signatures and expiry. A conditional DELETE with an affected-row check consumes each proof exactly once, including concurrent requests. Expired records are cleaned up during challenge generation. No IP binding is used, so a normal mobile-network change does not invalidate the form; existing per-IP rate limits still apply (10 challenges/minute, 20 public send attempts/hour).

Public requests are limited to 32 KiB JSON; subject/message and header fields are validated before consuming the proof. A delivery failure consumes the proof too, so the form must reset and verify again. This integration does not add an inquiry queue or guarantee SMTP delivery.

Adjacent mail fixes prevent caller-selected recipients, reject header newlines, dot-stuff SMTP message bodies, propagate validated Reply-To addresses, and avoid exposing or persisting raw SMTP errors. Broader transport replacement and the remaining CMS hardening work are still tracked in the revival assessment.

**Updating the vendored code**

The widget is pinned in `package.json` and `package-lock.json`. After intentionally updating it, run `npm run vendor:altcha` from `_edit/admin-source`; commit the lockfile and generated `public/altcha/altcha.min.js`, `LICENSE.txt`, and `UPSTREAM.json`. The full build runs this step after `npm ci`. It copies the official standalone bundle without rewriting it.

PHP source is checked in under `_edit/core/ThirdParty/Altcha/src`, with its MIT license, upstream Composer metadata, and `UPSTREAM.json` identifying the exact release commit and downloaded archive hash. There are no runtime package dependencies. To update, retrieve a reviewed official release, replace these upstream files unchanged, update provenance, and rerun the integration tests. The existing autoloader includes its namespace and `build.sh` includes it through the core directory.

Run `php tests/captcha.php` and `python3 tests/email_integration.py`. The latter uses local sockets, temporary files, and a local SMTP recorder; it must never use real SMTP credentials or the existing site database. Run `bash build.sh altcha` to verify packaging.

For release verification, run `python3 tests/email_integration.py dist/_edit-altcha.zip` against the exact archive. The checks cover JS/PHP protocol compatibility, missing/invalid/expired/replayed proofs, concurrent submissions, payload limits, recipient enforcement, local SMTP framing and Reply-To, authenticated test mail, explicit CAPTCHA opt-out, and rate limits. Browser rendering and mobile-device challenge latency still need interactive verification; no connected browser was available in this session.
