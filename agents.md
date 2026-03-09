# agents.md — GLM-5 Project Configuration

This file configures GLM-5 behavior for this project. Place in project root.

## Project Context

- **Type**: Web Application / API / Federated Social Network (ActivityPub)
- **Stack**: PHP 8.1+ with custom micro-MVC framework
- **Deployment**: Apache (shared hosting)

## Coding Standards

### Language-Specific

<!-- Uncomment and fill in as needed -->

#### PHP

- Version: 8.1+
- Framework: Custom micro-MVC (no external framework)
- Strict types: `declare(strict_types=1);` required on all PHP files
- No Composer dependencies at runtime (dev dependencies only for tooling)
- Memory constraint: 128MB maximum

### General

- Max function length: 50 lines
- Max class length: 300 lines
- Documentation: Public APIs must have docstrings
- Commits: Conventional format (`feat:`, `fix:`, `docs:`, `refactor:`)

## File Organization

```
src/
├── Controllers/
│   ├── Web/              # Web UI controllers
│   ├── Api/
│   │   ├── v1/          # REST API v1
│   │   └── v2/          # REST API v2
│   └── ActivityPub/    # ActivityPub endpoints (inbox, outbox, actor, webfinger)
├── Services/            # Business logic services
├── Models/              # Data models (Account, Status, Follow, etc.)
├── Middleware/         # Request middleware (Auth, CORS, RateLimit, Signature)
├── Core/               # Framework core (Router, Database, Session, View, Request, Response)
├── Helpers/            # Utility functions (crypto, http, json, text, time, validation)
├── Views/              # HTML templates
└── Exceptions/        # Custom exceptions

config/                 # Configuration files
public/                 # Public web root (index.php, assets)
cron/                   # Cron jobs (cleanup, federation, queue worker)
database/              # Database schema
```

## Security Rules

### Restricted Files

GLM-5 MUST NOT read or modify:

```
.env
.env.*
credentials.json
*.pem
*.key
secrets.*
config/instance.php
```

### Required Checks

- [x] All inputs validated before processing
- [x] Parameterized queries for all database operations
- [x] No hardcoded secrets
- [x] Dependencies scanned for CVEs
- [x] Error messages sanitized for users

### NanoPub Security Features

- **HTTP Signatures**: ActivityPub requests signed with Ed25519/RS256
- **Rate Limiting**: Per-IP and per-account rate limits
- **CSRF Protection**: Token-based CSRF protection for state-changing operations
- **Password Hashing**: bcrypt with cost factor 12
- **Input Validation**: Strict validation via `src/Helpers/validation.php`

## Testing Requirements

- **Coverage**: 80% minimum for business logic
- **Critical paths**: 100% (auth, payments, data mutations)
- **Framework**: Project-specific (pytest, Jest, JUnit, PHPUnit)

## Documentation

- API endpoints: OpenAPI/Swagger spec
- Complex logic: Inline comments explaining "why"
- README: Setup, usage, deployment instructions

## Deployment

### Apache (Shared Hosting)

- **PHP Version**: 8.1+ required
- **Mod Rewrite**: `.htaccess` for URL routing
- **Document Root**: `public/` directory
- **Memory Limit**: 128M (enforced in `.htaccess`)
- **Upload Size**: 50M max (configurable)

### Cron Jobs

- `cron/federation.php`: Outbox delivery and inbox fetching
- `cron/cleanup.php`: Database maintenance and old data purging
- `cron/queue-worker.php`: Process queued activities

## Project-Specific Rules

### Memory Optimization

1. **128MB Memory Limit**: All code must operate within 128MB memory constraint
2. **Streaming/Lazy Loading**: Use generators and cursor-based pagination for large datasets
3. **No In-Memory Caching**: Use database tables as cache (no Redis/Memcached)
4. **Efficient Queries**: Always use indexed columns, avoid N+1 queries with eager loading
5. **Image Processing**: Resize/compress images in chunks, never load full size into memory

### No External Runtime Dependencies

1. **Standalone**: No Composer packages required at runtime
2. **Native PHP**: Use native PHP functions (PDO, JSON, hashing) instead of libraries
3. **Self-Contained**: All utilities in `src/Helpers/` must be implemented without external packages

### ActivityPub Federation

1. **Outbox Paging**: Implement `first`/`next` links for collection pagination
2. **Inbox Handling**: Process activities asynchronously via queue
3. **HTTP Signatures**: Verify `Signature` header on all incoming requests
4. **Content Delivery**: Use `deliveryQueue` table for reliable federation

### Database Patterns

1. **Parameterized Queries**: Always use prepared statements (no string interpolation)
2. **Transactions**: Wrap multi-step mutations in transactions
3. **Queue Tables**: Use `activityQueue` and `deliveryQueue` tables for async processing
4. **File Storage**: Store uploads outside webroot in `storage/uploads/`

---

*This file is loaded automatically by Kilo Code when present in project root.*
