# NanoPub Project State

## Environment

- **OS**: Linux
- **PHP Version**: 8.1+
- **Database**: MySQL/MariaDB
- **Web Server**: Apache
- **Memory Limit**: 128MB per process

## Stack

- **Language**: PHP 8.1+ with strict types
- **Architecture**: Custom micro-MVC framework
- **No external dependencies**: Runtime dependency-free design
- **Protocol**: ActivityPub (federation), Mastodon API compatibility

## Key Directories

- `src/Core/` - Framework core (Router, Database, Request, Response, etc.)
- `src/Controllers/` - Web, API (v1, v2), ActivityPub controllers
- `src/Models/` - Data models
- `src/Services/` - Business logic services
- `src/Middleware/` - Request middleware
- `src/Helpers/` - Utility functions
- `src/Views/` - HTML templates
- `config/` - Configuration files
- `cron/` - Background processing scripts

## Current Status

- Basic framework implemented
- Core controllers and models in place
- ActivityPub federation partially implemented
- Mastodon API compatibility layer present
- Installation wizard available
- NO tests configured yet

## Recent Work

- Last commit: "Add GLM-5 instructions" - Added agents.md configuration
- Fixed SQL schema: Escaped MySQL reserved keywords (`sensitive`, `language`, `visibility`) with backticks in database/schema.sql

## Bug Fixes

- **SQL Syntax Error (v2)**: The installer uses a secondary copy of the schema at `public/storage/database/schema.sql`. Fixed both copies:
  - `database/schema.sql`
  - `public/storage/database/schema.sql`
  
  Escaped reserved keywords `sensitive`, `language`, and `visibility` with backticks in the statuses table definition.

- **Column Not Found Error**: Fixed INSERT statement in install.php that was trying to insert into `name` column in the `instance` table. The schema uses `title` instead.

- **Missing admin_email Column**: Fixed instance table INSERT to use `contact_email` instead of `admin_email` and added all missing columns (short_description, registrations_open, approval_required, max_toot_chars, max_media_attachments, max_image_size, max_video_size, updated_at).

- **Missing accounts Columns**: Fixed accounts table INSERT to include all required columns: actor_url, avatar_url, header_url, is_locked, is_bot, followers_count, following_count, statuses_count. Added proper ActivityPub actor_url generation.

- **Database Connection Failed (500 Error)**: The database host was set to `10.35.47.130` which caused "Connection refused" errors on the production server. Fixed by changing the host from `10.35.47.130` to `localhost` in both config files:
  - `config/database.php`
  - `public/storage/config/database.php`

- **Login Form Missing Username/Email Option**: The login form only showed "Email address" but the backend already supports login with either username or email. Fixed by updating the login form UI in:
  - `src/Views/web/auth/login.php`
  - `public/storage/src/Views/web/auth/login.php`
  Also updated the AuthController to handle the new 'login' field:
  - `src/Controllers/Web/AuthController.php`
  - `public/storage/src/Controllers/Web/AuthController.php`
  
   Changes include: Changed label from "Email address" to "Username or email address", changed input type from "email" to "text", changed field name from "email" to "login", updated placeholder text.

## Configuration Refactoring

- Removed public/storage/config directory - all configuration now only in project root config/
- Updated Config.php in both src/Core/ and public/storage/src/Core/ to:
  - Only use project root config/ directory
  - Use correct path resolution (dirname(__DIR__, 3) for public/storage location)
  - Always use public/storage for file storage
  
- Updated config/database.php to use environment variables instead of hardcoded credentials:
  - DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_CHARSET, DB_COLLATION
  
- Created .gitignore to protect sensitive files from being committed:
  - Environment files (.env)
  - Vendor dependencies
  - Uploads and queue directories
  - IDE files
  - Logs

This ensures no instance-specific credentials are stored in the codebase.

## Decisions Made

1. Custom micro-MVC over framework - for memory optimization
2. No Composer dependencies - shared hosting compatibility
3. Database-as-cache pattern - no in-memory caching due to memory constraints
4. Cron-based processing - no long-running daemons

## History

- Project initialized as federated social network
- Designed for shared hosting deployment
- Focus on memory optimization (128MB limit)
