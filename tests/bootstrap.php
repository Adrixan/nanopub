<?php

declare(strict_types=1);

/**
 * NanoPub Test Bootstrap
 * 
 * Sets up the environment for running PHPUnit tests.
 * Handles autoloading and test-specific configuration.
 */

// Increase memory limit for tests (but stay within shared hosting limits)
ini_set('memory_limit', '256M');

// Set error reporting for tests
error_reporting(E_ALL);

// Define test environment constant
if (!defined('NANOPUB_TEST')) {
    define('NANOPUB_TEST', true);
}

// Define base path for tests
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 1));
}

// Define storage path for tests
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', BASE_PATH . '/storage');
}

// Define public storage path for tests
if (!defined('PUBLIC_STORAGE_PATH')) {
    define('PUBLIC_STORAGE_PATH', BASE_PATH . '/public/storage');
}

// Load Composer autoloader
$autoloader = require BASE_PATH . '/vendor/autoload.php';

// Register custom autoloader for NanoPub classes
spl_autoload_register(function (string $class): void {
    $prefix = 'NanoPub\\';
    $baseDir = BASE_PATH . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load helper functions (only text.php needed for basic tests)
// Note: Some helper files have PHP 8.2+ compatibility issues
require_once BASE_PATH . '/src/Helpers/functions.php';
require_once BASE_PATH . '/src/Helpers/text.php';

// Set up simple error handler for tests that don't want to crash
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    // Throw errors as exceptions in test environment
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
}, E_ALL);

// Return the autoloader for potential further configuration
return $autoloader;
