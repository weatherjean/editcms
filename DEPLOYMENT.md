# Deployment Guide

## Installation

1. Extract the `_edit` folder to your website root
2. Visit `https://yourdomain.com/_edit/admin/`
3. Create your admin account (first user only)

The system automatically creates all necessary directories and database on first run.

## Requirements

- PHP 8.1+ with extensions: SQLite3, PDO, JSON, fileinfo
- Apache with mod_rewrite enabled OR Nginx

Most shared hosting (cPanel, Plesk) meets these requirements by default.

## Server Configuration

### Apache

Works automatically. The included `.htaccess` files handle all routing.

If you get 404 errors, contact your hosting provider to enable mod_rewrite.

### Nginx

Add this to your server block:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/yoursite;

    include /var/www/yoursite/_edit/nginx.conf;
}
```

Then reload: `nginx -s reload`

## Email Setup (Optional)

Go to Email in the admin menu to configure SMTP.

Common providers:
- Gmail: smtp.gmail.com:587 (TLS, requires App Password)
- SendGrid: smtp.sendgrid.net:587 (TLS)
- Mailgun: smtp.mailgun.org:587 (TLS)

## Troubleshooting

### 500 Error on First Load

Create these folders manually via FTP/cPanel:
```
_edit/data/database/
_edit/uploads/
```

### 404 Errors on Admin

Apache: Contact hosting to enable AllowOverride All for .htaccess
Nginx: Verify you included _edit/nginx.conf in server block

### Cannot Upload Media

Set uploads directory permissions to 755:
```bash
chmod 755 _edit/uploads/
```

Or via cPanel File Manager: Right-click uploads > Change Permissions > 755

### File Upload Size Limits

Default limit is 50MB (configured in .user.ini).

To increase:
- cPanel/Plesk: PHP Options > upload_max_filesize and post_max_size
- VPS: Edit php.ini and set both values
- Nginx: Add `client_max_body_size 50M;` to server block

### Registration Disabled

A user already exists. Log in and create more users via the Users page.

## System Status

Visit `/_edit/api/health` to check system configuration and requirements.

## Updating

1. Backup: data/database/, uploads/, and data/config/
2. Replace: admin/, admin-api/, api/, and core/ folders
3. Keep: Your data/database/, uploads/, and data/config/ unchanged

## Security

- Protected directories: /data/, /core/ return 403
- Rate limiting on login and email endpoints
- Session-based authentication
- Registration auto-locks after first user
- File upload validation and type restrictions
