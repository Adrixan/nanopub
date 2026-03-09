# NanoPub

A lightweight federated social network server implementing the ActivityPub protocol. Designed for minimal web hosting packages with no external runtime dependencies.

## Features

- **ActivityPub Compatible**: Federates with Mastodon, Pleroma, Pixelfed, and other ActivityPub implementations
- **Lightweight**: No external PHP dependencies at runtime, works on shared hosting
- **Mastodon API**: Compatible with most Mastodon API endpoints for client compatibility
- **Modern PHP**: Built on PHP 8.1+ with strict types and modern practices
- **Secure**: Implements HTTP signatures, rate limiting, and security best practices

## Requirements

- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Required PHP extensions:
  - PDO & PDO_MySQL
  - JSON
  - Mbstring
  - OpenSSL
  - cURL
  - Fileinfo
  - Intl

## Installation

### Quick Install (Web-based)

1. Upload all files to your web server
2. Navigate to `https://your-domain.com/install.php` in your browser
3. Follow the installation wizard to:
   - Check server requirements
   - Configure database connection
   - Set up your instance
   - Create admin account
4. Delete `public/install.php` after installation

### Manual Install

1. **Configure Database**

   Copy `config/database.php` and update with your credentials:

   ```php
   return [
       'host' => 'localhost',
       'port' => 3306,
       'database' => 'nanopub',
       'username' => 'your_username',
       'password' => 'your_password',
   ];
   ```

2. **Create Database Tables**

   Import the schema:

   ```bash
   mysql -u username -p database_name < database/schema.sql
   ```

3. **Configure Instance**

   Update `config/instance.php`:

   ```php
   return [
       'name' => 'My Instance',
       'description' => 'A NanoPub instance',
       'domain' => 'social.example.com',
       'admin_email' => 'admin@example.com',
   ];
   ```

4. **Set Permissions**

   Make storage directories writable:

   ```bash
   chmod -R 755 storage/
   chmod -R 755 config/
   ```

5. **Create Admin Account**

   Use the web interface or create manually via MySQL.

## Web Server Configuration

### Apache

The included `.htaccess` files handle URL rewriting automatically. Ensure `mod_rewrite` is enabled.

### Nginx

Use this configuration:

```nginx
server {
    listen 80;
    server_name social.example.com;
    root /var/www/nanopub/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(htaccess|git|env) {
        deny all;
    }

    location ~* \.(sql|md|json|lock)$ {
        deny all;
    }
}
```

## Cron Jobs

Set up cron jobs for background processing:

```crontab
# Process queue every minute
* * * * * cd /path/to/nanopub && php cron/queue-worker.php 10

# Federation tasks every 5 minutes
0,5,10,15,20,25,30,35,40,45,50,55 * * * * cd /path/to/nanopub && php cron/federation.php

# Cleanup daily at 3 AM
0 3 * * * cd /path/to/nanopub && php cron/cleanup.php 7

# Metrics collection hourly
0 * * * * cd /path/to/nanopub && php cron/metrics.php
```

## Directory Structure

```
nanopub/
config/           # Configuration files
  app.php         # Application settings
  database.php    # Database credentials
  instance.php    # Instance information
  routes.php      # URL routing
  middleware.php  # Middleware configuration
cron/             # Background processing scripts
  queue-worker.php
  federation.php
  cleanup.php
  metrics.php
database/
  schema.sql      # Database schema
public/           # Web-accessible files
  index.php       # Main entry point
  install.php     # Installation wizard
  .htaccess       # URL rewriting
  assets/         # CSS, JS, images
src/              # Application source code
  Core/           # Framework core
  Controllers/    # Request handlers
  Models/         # Data models
  Services/       # Business logic
  Middleware/     # Request middleware
  Helpers/        # Utility functions
  Views/          # HTML templates
  Exceptions/     # Custom exceptions
storage/          # User uploads and data
  uploads/
    accounts/     # Avatar uploads
    media/        # Media attachments
    cache/        # Temporary files
  queue/          # Queue state
```

## API Endpoints

### Mastodon API (v1)

- `GET /api/v1/accounts/:id` - Get account
- `GET /api/v1/accounts/:id/statuses` - Get account statuses
- `POST /api/v1/statuses` - Post new status
- `GET /api/v1/timelines/home` - Home timeline
- `GET /api/v1/timelines/public` - Public timeline
- `POST /api/v1/statuses/:id/favourite` - Favourite status
- `POST /api/v1/statuses/:id/reblog` - Boost status
- `POST /api/v1/accounts/:id/follow` - Follow account

### ActivityPub

- `GET /.well-known/webfinger` - WebFinger discovery
- `GET /.well-known/host-meta` - Host metadata
- `GET /users/:username` - Actor profile
- `POST /users/:username/inbox` - ActivityPub inbox
- `GET /users/:username/outbox` - ActivityPub outbox

## Development

### With Composer (Optional)

```bash
# Install development dependencies
composer install

# Run tests
composer test

# Run linter
composer lint

# Run static analysis
composer analyse
```

### Without Composer

The application works without Composer. The autoloader in `public/index.php` handles class loading.

## Security

- All passwords hashed with bcrypt (cost 12)
- HTTP signatures for ActivityPub authentication
- Rate limiting on API endpoints
- CSRF protection on web forms
- Content Security Policy headers
- No hardcoded secrets

## License

GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later)

## Contributing

Contributions are welcome! Please read the architecture documentation in `ARCHITECTURE.md` for development guidelines.

## Support

- Issues: GitHub Issues
- Discussion: GitHub Discussions
- ActivityPub: `@admin@your-instance.tld`
