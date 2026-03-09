<?php

declare(strict_types=1);

namespace NanoPub\Core;

/**
 * Static configuration loader with caching.
 * 
 * Loads configuration from project root config/ directory as PHP arrays.
 * Supports dot notation for accessing nested values.
 */
final class Config
{
    /**
     * Cached configuration data.
     * 
     * @var array<string, array<string, mixed>>
     */
    private static array $cache = [];

    /**
     * Detected config path (cached after first detection)
     */
    private static ?string $configPath = null;

    /**
     * Detect the config directory path.
     * 
     * @return string Path to the config directory
     */
    private static function detectConfigPath(): string
    {
        if (self::$configPath !== null) {
            return self::$configPath;
        }

        self::$configPath = dirname(__DIR__, 2) . '/config';
        return self::$configPath;
    }

    /**
     * Get the detected config path.
     * 
     * @return string
     */
    public static function getConfigPath(): string
    {
        return self::detectConfigPath();
    }

    /**
     * Get the storage path.
     * 
     * @return string
     */
    public static function getStoragePath(): string
    {
        return dirname(__DIR__, 2) . '/public/storage';
    }

    /**
     * Get a configuration value using dot notation.
     * 
     * @param string $key Dot notation key (e.g., 'app.name')
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key, 2);
        $file = $segments[0];
        $path = $segments[1] ?? null;

        // Load file if not cached
        if (!isset(self::$cache[$file])) {
            $configDir = self::detectConfigPath();
            $filePath = $configDir . "/{$file}.php";
            
            if (!file_exists($filePath)) {
                return $default;
            }

            self::$cache[$file] = require $filePath;
        }

        // Return entire file if no path specified
        if ($path === null) {
            return self::$cache[$file];
        }

        // Navigate nested array using dot path
        $value = self::$cache[$file];
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value at runtime.
     * 
     * @param string $key Dot notation key
     * @param mixed $value Value to set
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key, 2);
        $file = $segments[0];
        $path = $segments[1] ?? null;

        // Initialize file cache if needed
        if (!isset(self::$cache[$file])) {
            self::$cache[$file] = [];
        }

        // Set entire file if no path specified
        if ($path === null) {
            self::$cache[$file] = $value;
            return;
        }

        // Navigate and set nested value
        $current = &self::$cache[$file];
        foreach (explode('.', $path) as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }
        $current = $value;
    }

    /**
     * Check if a configuration key exists.
     * 
     * @param string $key Dot notation key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Clear all cached configuration.
     */
    public static function clear(): void
    {
        self::$cache = [];
    }

    /**
     * Clear cached configuration for a specific file.
     * 
     * @param string $file File name without extension
     */
    public static function clearFile(string $file): void
    {
        unset(self::$cache[$file]);
    }
}