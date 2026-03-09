<?php

declare(strict_types=1);

/**
 * Database configuration.
 * 
 * All sensitive values are loaded from environment variables.
 * Set these in your web server configuration or .env file:
 * - DB_HOST: Database host (default: localhost)
 * - DB_PORT: Database port (default: 3306)
 * - DB_NAME: Database name
 * - DB_USER: Database username
 * - DB_PASSWORD: Database password
 * - DB_CHARSET: Character set (default: utf8mb4)
 * - DB_COLLATION: Collation (default: utf8mb4_unicode_ci)
 */

return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => (int)(getenv('DB_PORT') ?: 3306),
    'name' => getenv('DB_NAME') ?: '',
    'user' => getenv('DB_USER') ?: '',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    'collation' => getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci',
];
