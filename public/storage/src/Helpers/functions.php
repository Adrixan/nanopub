<?php

declare(strict_types=1);

/**
 * NanoPub Global Helper Functions
 * 
 * Helper functions available globally throughout the application.
 */

/**
 * Get environment variable with default value.
 * 
 * @param string $key Environment variable name
 * @param mixed $default Default value if not found
 * @return mixed The environment value or default
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // Handle boolean-like string values
    return match (strtolower((string) $value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        'empty', '(empty)' => '',
        default => $value,
    };
}

/**
 * Escape HTML output to prevent XSS.
 * 
 * @param string $string String to escape
 * @return string Escaped string
 */
function e(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Generate a cryptographically secure random string.
 * 
 * @param int $length Length of the string (default 32)
 * @return string Random string
 */
function random_string(int $length = 32): string
{
    $length = max(1, $length);
    $bytes = (int) ceil($length / 2);
    return substr(bin2hex(random_bytes($bytes)), 0, $length);
}

/**
 * Debug helper - dump variables and die.
 * 
 * @param mixed ...$vars Variables to dump
 * @return never
 */
function dd(mixed ...$vars): never
{
    // Set headers for HTML output if not already sent
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    
    echo '<pre style="background: #1a1a2e; color: #eee; padding: 20px; border-radius: 8px; overflow-x: auto; font-family: monospace; font-size: 14px;">';
    
    foreach ($vars as $var) {
        var_export($var, true);
        echo htmlspecialchars(print_r($var, true), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        echo "\n\n";
    }
    
    echo '</pre>';
    exit(1);
}

/**
 * Get path relative to project root.
 * 
 * @param string $path Optional path to append
 * @return string Full path
 */
function base_path(string $path = ''): string
{
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    
    if ($path === '') {
        return $basePath;
    }
    
    return $basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

/**
 * Get URL relative to app URL.
 * 
 * @param string $path Optional path to append
 * @return string Full URL
 */
function url(string $path = ''): string
{
    $baseUrl = null;
    
    // Check Config first if available
    if (class_exists('NanoPub\Core\Config')) {
        $baseUrl = \NanoPub\Core\Config::get('app.url');
    }
    
    // Check environment variable
    if (empty($baseUrl)) {
        $baseUrl = env('APP_URL');
    }
    
    // If still empty, detect from request
    if (empty($baseUrl)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
    }
    
    $baseUrl = rtrim($baseUrl, '/');
    
    if ($path === '') {
        return $baseUrl;
    }
    
    return $baseUrl . '/' . ltrim($path, '/');
}

/**
 * Get path to storage directory.
 * 
 * Supports open_basedir restricted hosting by checking multiple locations:
 * 1. public/storage/ (for restricted hosting)
 * 2. storage/ at project root (standard)
 * 
 * @param string $path Optional path to append
 * @return string Full path to storage
 */
function storage_path(string $path = ''): string
{
    static $detectedStoragePath = null;
    
    if ($detectedStoragePath === null) {
        // Try to use Config class if available
        if (class_exists(\NanoPub\Core\Config::class)) {
            $detectedStoragePath = \NanoPub\Core\Config::getStoragePath();
        } else {
            // Fallback detection
            $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            
            // Check for public/storage first (open_basedir restricted)
            $publicStorage = $basePath . '/public/storage';
            if (is_dir($publicStorage) && is_writable($publicStorage)) {
                $detectedStoragePath = $publicStorage;
            } else {
                // Fall back to standard storage location
                $detectedStoragePath = $basePath . '/storage';
            }
        }
    }
    
    if ($path === '') {
        return $detectedStoragePath;
    }
    
    return $detectedStoragePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}
