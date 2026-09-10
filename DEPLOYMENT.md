# Deployment

## Install

Use PHP 8.4 (tested) with PDO SQLite, SQLite3, fileinfo, OpenSSL, DOM and Zip, plus Apache 2.4 or Nginx/PHP-FPM. Use HTTPS in production. No Node.js or Composer is needed on the host.

1. Build with `bash build.sh` and extract the ZIP's `_edit` folder into your website root.
2. Install the server rules below. The PHP filesystem user must be able to create `config.php` on first boot and write `data/` and `uploads/`.
3. Run `php /path/to/site/_edit/core/Operations/setup.php` as that user.
4. Open `https://yourdomain.com/_edit/admin/` and use the one-time code to create the first administrator.

New accounts default to Editor. See [setup and permissions](SETUP-AND-PERMISSIONS.md) for role capabilities.

## Server rules

**Apache:** enable mod_rewrite and `AllowOverride All`. Keep every included `.htaccess`, including the root and uploads rules.

**Nginx:** include `/path/to/site/_edit/nginx.conf` inside your existing server block. Set its PHP-FPM socket for your host, use a canonical document root without symlinks, avoid competing `/_edit/` locations, and run `nginx -t` before reloading.

Private data/core paths must be inaccessible and uploads must never execute PHP. Check `/_edit/api/health` and verify those boundaries after deployment. The PHP development router is for local use.

## Contact forms

Configure SMTP, **Contact Form Recipient** and ALTCHA in Email. The widget/verifier ship locally. See [ALTCHA integration](ALTCHA-INTEGRATION.md) and `/_edit/admin/altcha/contact-example.html`; submitting the example sends a real message to the configured recipient.

Old color-CAPTCHA forms must be migrated: `/send-email/token` now returns 410. Existing explicit verification opt-outs are retained; new installations enable verification.

## Upgrade and recovery

1. Use `core/Operations/backup.php` to make and verify a full backup; keep a copy off-host. See [backup/recovery commands](BACKUP-RECOVERY.md).
2. Test the new build on an isolated restore before switching the live site.
3. Replace `admin/`, `admin-api/`, `api/`, `core/` and the supplied server rules together.
4. Retain `config.php` (encryption key), all of `data/`, and `uploads/`. Hidden configuration pointers/generations are part of the data.

Public responses exclude private/undeclared fields and login-user relationships; SVG uploads are blocked. HTML fields now permit safe formatting only. Configuration changes validate the entire candidate and require a backup; after activation, use the admin/API instead of editing legacy root JSON files. Review [public boundaries](PUBLIC-BOUNDARY-HARDENING.md) and [HTML/configuration compatibility](HTML-AND-CONFIG-HARDENING.md) before upgrading.

## Troubleshooting

- **500:** inspect PHP logs, required extensions and filesystem ownership. Do not enable public error output.
- **404 on admin/API:** check Apache overrides or the Nginx include and PHP-FPM socket.
- **Uploads fail:** check PHP-user write access, `upload_max_filesize`, `post_max_size` and the web server's request limit. The packaged `.user.ini` sets a 50 MB PHP upload limit; endpoint-specific limits still apply.
- **Configuration change fails:** review the reported validation error and available disk space. Reconcile duplicate legacy definitions in one import. Old configuration generations/backups are retained, so monitor their size.
