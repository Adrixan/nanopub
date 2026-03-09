<?php

declare(strict_types=1);

namespace NanoPub\Core;

use RuntimeException;

/**
 * Session management with minimal data storage.
 * 
 * Provides secure session handling with regeneration support.
 */
final class Session
{
    /**
     * Whether session has been started.
     */
    private static bool $started = false;

    /**
     * Session flash data key.
     */
    private const FLASH_KEY = '_flash';

    /**
     * Session CSRF token key.
     */
    private const CSRF_KEY = '_csrf_token';

    /**
     * Start the session.
     * 
     * @throws RuntimeException If session cannot be started
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Configure session for security
        if (headers_sent($file, $line)) {
            throw new RuntimeException(
                "Session cannot be started - headers already sent in {$file} on line {$line}"
            );
        }

        // Set secure session cookie parameters
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Use strict mode to prevent session fixation
        ini_set('session.use_strict_mode', '1');
        
        // Prevent session ID in URLs
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        if (!session_start()) {
            throw new RuntimeException('Failed to start session');
        }

        self::$started = true;

        // Generate CSRF token if not exists
        if (!isset($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Get a session value.
     * 
     * @param string $key Session key
     * @param mixed $default Default value
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value.
     * 
     * @param string $key Session key
     * @param mixed $value Value to store
     */
    public static function set(string $key, mixed $value): void
    {
        self::ensureStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * Check if a session key exists.
     * 
     * @param string $key Session key
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::ensureStarted();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session value.
     * 
     * @param string $key Session key
     */
    public static function remove(string $key): void
    {
        self::ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * Destroy the session.
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            // Delete session cookie
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            
            session_destroy();
        }
        
        self::$started = false;
    }

    /**
     * Regenerate the session ID.
     * 
     * Should be called after login to prevent session fixation.
     * 
     * @param bool $deleteOld Whether to delete old session data
     * @throws RuntimeException If regeneration fails
     */
    public static function regenerate(bool $deleteOld = true): void
    {
        self::ensureStarted();
        
        if (!session_regenerate_id($deleteOld)) {
            throw new RuntimeException('Failed to regenerate session ID');
        }

        // Regenerate CSRF token
        $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
    }

    /**
     * Get the session ID.
     * 
     * @return string
     */
    public static function id(): string
    {
        self::ensureStarted();
        return session_id();
    }

    /**
     * Get the CSRF token.
     * 
     * @return string
     */
    public static function csrfToken(): string
    {
        self::ensureStarted();
        return $_SESSION[self::CSRF_KEY] ?? '';
    }

    /**
     * Validate a CSRF token.
     * 
     * @param string $token Token to validate
     * @return bool
     */
    public static function validateCsrf(string $token): bool
    {
        self::ensureStarted();
        return isset($_SESSION[self::CSRF_KEY]) 
            && hash_equals($_SESSION[self::CSRF_KEY], $token);
    }

    /**
     * Flash a value to the session for the next request.
     * 
     * @param string $key Flash key
     * @param mixed $value Value to flash
     */
    public static function flash(string $key, mixed $value): void
    {
        self::ensureStarted();
        $_SESSION[self::FLASH_KEY][$key] = $value;
    }

    /**
     * Get a flashed value and remove it from session.
     * 
     * @param string $key Flash key
     * @param mixed $default Default value
     * @return mixed
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::ensureStarted();
        
        $value = $_SESSION[self::FLASH_KEY][$key] ?? $default;
        
        // Remove after reading
        unset($_SESSION[self::FLASH_KEY][$key]);
        
        return $value;
    }

    /**
     * Check if a flash key exists.
     * 
     * @param string $key Flash key
     * @return bool
     */
    public static function hasFlash(string $key): bool
    {
        self::ensureStarted();
        return isset($_SESSION[self::FLASH_KEY][$key]);
    }

    /**
     * Keep a flash value for another request.
     * 
     * @param string $key Flash key
     */
    public static function keepFlash(string $key): void
    {
        self::ensureStarted();
        // Flash data is only removed when accessed via getFlash
        // This method is a no-op but provided for API clarity
    }

    /**
     * Reflash all flash data.
     */
    public static function reflash(): void
    {
        // Flash data persists until accessed, so this is a no-op
        // Provided for API compatibility
    }

    /**
     * Get all flash data.
     * 
     * @return array<string, mixed>
     */
    public static function allFlash(): array
    {
        self::ensureStarted();
        return $_SESSION[self::FLASH_KEY] ?? [];
    }

    /**
     * Ensure session is started.
     */
    private static function ensureStarted(): void
    {
        if (!self::$started && session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }
    }

    /**
     * Get the current authentication ID if logged in.
     * 
     * @return int|string|null
     */
    public static function authId(): int|string|null
    {
        return self::get('auth_id');
    }

    /**
     * Set the authenticated user ID.
     * 
     * @param int|string $id User ID
     */
    public static function setAuth(int|string $id): void
    {
        self::set('auth_id', $id);
        self::regenerate();
    }

    /**
     * Clear authentication.
     */
    public static function clearAuth(): void
    {
        self::remove('auth_id');
        self::regenerate();
    }

    /**
     * Check if user is authenticated.
     * 
     * @return bool
     */
    public static function isAuthenticated(): bool
    {
        return self::has('auth_id');
    }
}