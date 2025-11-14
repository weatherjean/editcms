# Installation Guide

## Quick Start

1. **Extract** the `_edit` folder to your website directory
2. **Visit** `https://yourdomain.com/_edit/admin/`
3. **Register** your admin account (first user only)
4. **Done!**

That's it. The CMS automatically creates all necessary directories and sets up the database.

---

## Requirements

- **PHP 8.1+** with extensions: SQLite3, PDO, JSON, fileinfo
- **Apache with mod_rewrite** OR **Nginx** (configuration included)

Most shared hosting providers (cPanel, Plesk, etc.) already meet these requirements.

---

## Server Configuration

### Apache (Most Common)
**Works automatically.** The included `.htaccess` file handles all routing.

If you get 404 errors, ask your hosting provider to enable `mod_rewrite`.

### Nginx
Include the configuration in your server block:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/yoursite;

    include /var/www/yoursite/_edit/nginx.conf;
}
```

Edit `_edit/nginx.conf` and adjust this line if needed:
```nginx
set $php_fpm unix:/var/run/php/php-fpm.sock;
```

Reload nginx: `nginx -s reload`

---

## First-Time Setup

### 1. Create Admin Account
Visit `/_edit/admin/` and register the first user.

**Important:** Registration automatically closes after the first account is created. Add more users through the Users page in the admin.

### 2. Configure Email (Optional)
Go to **Email** in the admin menu and configure SMTP:

Common providers:
- **Gmail**: `smtp.gmail.com:587` (TLS) - Requires App Password
- **SendGrid**: `smtp.sendgrid.net:587` (TLS)
- **Mailgun**: `smtp.mailgun.org:587` (TLS)

Use the "Send Test Email" button to verify it works.

---

## Troubleshooting

### 500 Error on First Load
The web server can't create directories. Using cPanel or FTP, create these folders manually:
```
_edit/database/
_edit/uploads/
_edit/config/
```

### 404 Errors on Admin/API
**Apache users**: Your host may not allow `.htaccess` files. Contact support to enable `AllowOverride All`.

**Nginx users**: Make sure you included `_edit/nginx.conf` in your server block.

### Can't Upload Media
The `uploads/` directory isn't writable. Using cPanel File Manager:
1. Navigate to `_edit/uploads/`
2. Right-click → Change Permissions
3. Set to **755** (rwxr-xr-x)

### Registration Says "Disabled"
A user already exists. Log in with the existing account and create more users via the Users page.

---

## Security Features

✅ **Auto-Protected Directories**: `/database/`, `/config/`, `/core/` return 403 errors
✅ **Rate Limiting**: Login and email endpoints have exponential backoff
✅ **Session Authentication**: Secure tokens auto-generated and stored in database sessions table
✅ **Registration Lock**: Automatically disables after first user is created

---

## Updating

1. **Backup** your `data/database/`, `uploads/`, and `data/config/` folders
2. **Replace** `admin/core/`, `admin/api/`, and `admin/dist/` with new versions
3. **Keep** your `data/database/`, `uploads/`, and `data/config/` folders unchanged

Database migrations run automatically on the first request after updating.

---

## Need Help?

**Check the system status**: Visit `/_edit/api/health` in your browser to see if everything is configured correctly.

**Common issues**: Most problems are web server configuration (mod_rewrite, PHP version, file permissions). Contact your hosting provider for assistance.
