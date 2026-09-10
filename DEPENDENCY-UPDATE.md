# Dependency refresh — 8 September 2026

Follow-up: ALTCHA widget 3.2.2 and PHP library 2.1.0 were subsequently added and fully vendored. See [ALTCHA-INTEGRATION.md](ALTCHA-INTEGRATION.md). The table and ten-package count below describe the preceding dependency refresh.

Vue is retained. All ten remaining direct npm dependencies match the registry's latest stable release at verification time. The PHP backend has no Composer manifest or managed third-party packages to update.

| Package | Previous installed version | Current version |
| --- | --- | --- |
| Vue | 3.5.22 | 3.5.42 |
| Vue Router | 4.6.3 | 5.3.1 |
| Vite | 7.1.12 | 8.2.2 |
| Vue Vite plugin | 6.0.1 | 6.0.8 |
| Tailwind CSS | 4.1.16 | 4.3.3 |
| Tailwind Vite plugin | 4.1.16 | 4.3.3 |
| DaisyUI | 5.3.9 | 5.7.28 |
| Marked | 17.0.0 | 18.0.12 |
| Highlight.js | 11.11.1 | 11.12.0 |
| Quill | 2.0.3 | 2.0.3 — already latest |

Compatible transitive packages were refreshed and the lockfile regenerated. Transitive dependencies retain their upstream compatibility constraints; they were not forcibly moved across major versions.

The unused `JsonEditor.vue`, Monaco, its React wrapper, and its unused Vite plugin were removed. No screen imported this component; the active visual configuration editors remain. This also removes the vulnerable DOMPurify dependency pinned and vendored by Monaco. A lockfile override alone would not replace Monaco's vendored copy.

The project now declares Node >=22.12, with Node 24 LTS selected by `.nvmrc`. Installation instructions and the distribution builder use `npm ci` for reproducibility. The machine's global Node/npm installations were not modified. Verification ran on the available Node 25.4.0; the Node 24 recommendation follows the [official Node release schedule](https://nodejs.org/en/about/previous-releases), but has not yet been separately tested here.

Migration references: [Vue Router 5](https://router.vuejs.org/guide/migration/v4-to-v5), [Vite 8](https://vite.dev/guide/migration), and [Marked 18](https://github.com/markedjs/marked/releases/tag/v18.0.0). The existing routing APIs and Markdown renderer signatures required no application changes.

**Verification**

- `npm outdated --json` returned `{}` for direct dependencies.
- `npm ls --depth=0` passed with no missing or invalid peers.
- `bash build.sh dependency-refresh` passed, including a clean `npm ci` and the complete ZIP packaging path.
- Archive contents include the admin assets, API documentation, PHP entry points, protection files, and injected health-check hashes. No local database, local secret configuration, source tree, or node_modules was packaged.
- The built archive served HTML/JS/CSS and passed fixture HTTP checks for health, registration, authenticated identity, content-type listing, media listing, documentation, and logout. These checks used synthetic records in a temporary directory and a test-only PHP router, not the existing CMS database. They do not validate Apache/Nginx configuration.
- Marked 18 rendered the existing API documentation using the component's actual renderer configuration; heading anchors, highlighted code, and escaping of unknown-language code blocks passed targeted checks.
- `git diff --check` passed. Existing working changes were preserved.
- A visual/browser smoke test could not run because no browser connection was available. Nested editor, media-selection, and responsive layout behavior still need interactive verification.

The production bundle is about 536 kB JavaScript / 163 kB gzip and 163 kB CSS / 24 kB gzip. The existing large-JavaScript-chunk warning remains. No artificial warning threshold or forced dependency downgrade was added.

**Remaining advisory and hardening work**

The post-cleanup npm audit reports **one low-severity advisory, in Quill 2.0.3**, with no high or critical findings. The [Quill HTML-export advisory](https://github.com/advisories/GHSA-v3m3-f69x-jf25) lists no patched release. npm's suggested automatic action is a downgrade to 2.0.2, not a patched upgrade; it was not applied. Quill remains on the current release, so this is not a clean security audit.

The subsequent HTML pass added server-side HTML Purifier and browser DOMPurify; see HTML-AND-CONFIG-HARDENING.md. The specific exploitability of the Quill advisory through this CMS was not established. Package updates do not resolve the separate filtering, revision, SMTP, authorization, upload, and deployment issues in [REVIVAL-ASSESSMENT.md](REVIVAL-ASSESSMENT.md).

Subsequent backend, boundary, recovery and setup fixes are documented in the linked follow-ups. Test actual content and browser workflows on staging before deployment.

The HTML/configuration pass additionally vendors HTML Purifier 4.19.0 (unmodified PHP library and LGPL license) and pins DOMPurify 3.4.15 in the locally bundled admin. See [HTML-AND-CONFIG-HARDENING.md](HTML-AND-CONFIG-HARDENING.md) for the compatibility contract and shipped notices.
