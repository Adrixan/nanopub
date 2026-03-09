<?php
declare(strict_types=1);

/**
 * NanoPub - Single Entry Point
 * 
 * This is the main entry point for all HTTP requests to the NanoPub application.
 * It handles autoloading, configuration, and bootstraps the application.
 * 
 * @package NanoPub
 */

/**
 * Detect the base paths considering open_basedir restrictions
 * 
 * On shared hosting, open_basedir may restrict access to directories
 * outside the web root. This function detects such restrictions and
 * returns appropriate paths.
 * 
 * @return array Path information including project_root, src_path, and root_accessible status
 */
function detectBasePaths(): array
{
    $publicPath = __DIR__; // public/ directory
    $projectRoot = dirname(__DIR__); // Project root (may not be accessible with open_basedir)
    
    // Check if project root is accessible (typical development environment)
    $rootAccessible = @is_dir($projectRoot) && @is_readable($projectRoot);
    
    // Check if src directory at project root is accessible
    $rootSrcAccessible = $rootAccessible && @is_dir($projectRoot . '/src') && @is_readable($projectRoot . '/src');
    
    // Check if Helpers directory at project root is accessible
    $rootHelpersAccessible = $rootSrcAccessible && @is_dir($projectRoot . '/src/Helpers') && @is_readable($projectRoot . '/src/Helpers');
    
    // Determine the source path for helpers
    $srcPath = $rootSrcAccessible ? $projectRoot . '/src/' : null;
    $helpersPath = $rootHelpersAccessible ? $projectRoot . '/src/Helpers/' : null;
    
    // Check for fallback location in public/storage/src/
    $publicStorageSrcPath = $publicPath . '/storage/src/';
    $publicStorageHelpersPath = $publicPath . '/storage/src/Helpers/';
    
    $publicStorageSrcAccessible = @is_dir($publicStorageSrcPath) && @is_readable($publicStorageSrcPath);
    $publicStorageHelpersAccessible = @is_dir($publicStorageHelpersPath) && @is_readable($publicStorageHelpersPath);
    
    if ($publicStorageHelpersAccessible) {
        $srcPath = $publicStorageSrcPath;
        $helpersPath = $publicStorageHelpersPath;
        $rootAccessible = false; // Mark as restricted since we're using public storage
    }
    
    return [
        'project_root' => $projectRoot,
        'public_path' => $publicPath,
        'src_path' => $srcPath,
        'helpers_path' => $helpersPath,
        'root_accessible' => $rootAccessible,
        'public_storage_available' => $publicStorageHelpersAccessible,
    ];
}

/**
 * Load a helper file, trying multiple locations
 * 
 * @param string $file The helper file to load
 * @param array $paths Array of paths to try
 * @throws RuntimeException If the helper cannot be loaded from any path
 */
function loadHelper(string $file, array $paths): void
{
    foreach ($paths as $path) {
        $fullPath = $path . '/' . $file;
        if (file_exists($fullPath)) {
            require_once $fullPath;
            return;
        }
    }
    throw new RuntimeException(
        "Cannot load helper file '{$file}'. Tried paths: " . implode(', ', $paths)
    );
}

// Detect paths based on open_basedir restrictions
$paths = detectBasePaths();

// Define BASE_PATH based on what we can access
if ($paths['root_accessible']) {
    define('BASE_PATH', $paths['project_root']);
} elseif ($paths['public_storage_available']) {
    define('BASE_PATH', $paths['public_path'] . '/storage');
} else {
    define('BASE_PATH', $paths['project_root']); // Will likely fail, but let it fail gracefully
}

define('SRC_PATH', $paths['src_path']);
define('HELPERS_PATH', $paths['helpers_path']);

// Register autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'NanoPub\\';
    $baseDir = SRC_PATH ?? BASE_PATH . '/src/';
    
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

// Load helper functions - try multiple locations
$helperFiles = [
    'functions.php',
    'crypto.php',
    'http.php',
    'json.php',
    'time.php',
    'text.php',
    'validation.php',
    'pagination.php',
];

$helperLoadPaths = [];
if ($paths['root_accessible'] && $paths['helpers_path']) {
    $helperLoadPaths[] = $paths['helpers_path'];
}
if ($paths['public_storage_available']) {
    $helperLoadPaths[] = $paths['public_path'] . '/storage/src/Helpers';
}

// If we have no accessible paths, show error
if (empty($helperLoadPaths)) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Error - NanoPub</title></head><body>';
    echo '<h1>Configuration Error</h1>';
    echo '<p>NanoPub cannot find its helper files due to server restrictions.</p>';
    echo '<h2>Problem</h2>';
    echo '<p>The server\'s open_basedir configuration restricts access to the project files.</p>';
    echo '<h2>Solution</h2>';
    echo '<p>Copy the <code>src/Helpers/</code> directory to <code>public/storage/src/Helpers/</code> and ensure it is readable.</p>';
    echo '<p>Example command (run via SSH or FTP):</p>';
    echo '<pre>cp -r ' . escapeshellarg($paths['project_root'] . '/src/Helpers') . ' ' . escapeshellarg($paths['public_path'] . '/storage/src/') . '</pre>';
    echo '</body></html>';
    exit;
}

// Try to load each helper file
$loadedHelpers = [];
$failedHelpers = [];

foreach ($helperFiles as $helperFile) {
    try {
        loadHelper($helperFile, $helperLoadPaths);
        $loadedHelpers[] = $helperFile;
    } catch (Throwable $e) {
        $failedHelpers[] = $helperFile;
    }
}

// If any helpers failed to load, show error
if (!empty($failedHelpers)) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Error - NanoPub</title></head><body>';
    echo '<h1>Loading Error</h1>';
    echo '<p>The following helper files could not be loaded:</p>';
    echo '<ul>';
    foreach ($failedHelpers as $file) {
        echo '<li>' . htmlspecialchars($file) . '</li>';
    }
    echo '</ul>';
    echo '<p>Please ensure all helper files are accessible.</p>';
    if ($paths['root_accessible']) {
        echo '<p>Project root is accessible. Please check file permissions.</p>';
    } else {
        echo '<p>Using public storage fallback. Please verify files exist in:</p>';
        echo '<code>' . htmlspecialchars($paths['public_path'] . '/storage/src/Helpers/') . '</code>';
    }
    echo '</body></html>';
    exit;
}

// Set error reporting based on environment
if (\NanoPub\Core\Config::get('app.debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Set timezone
date_default_timezone_set(\NanoPub\Core\Config::get('app.timezone', 'UTC'));

// Run the application
try {
    $app = new \NanoPub\Core\App();
    $app->run();
} catch (\NanoPub\Exceptions\NotFoundException $e) {
    http_response_code(404);
    
    if (\NanoPub\Core\Config::get('app.debug', false)) {
        echo \NanoPub\Core\View::render('error/404', [
            'message' => $e->getMessage(),
        ]);
    } else {
        echo \NanoPub\Core\View::render('error/404', [
            'message' => 'Page not found',
        ]);
    }
} catch (\Throwable $e) {
    // Log the error
    error_log(sprintf(
        '[%s] %s in %s:%d\nStack trace:\n%s',
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
    
    if (\NanoPub\Core\Config::get('app.debug', false)) {
        throw $e;
    }
    
    http_response_code(500);
    echo \NanoPub\Core\View::render('error/500', [
        'message' => 'An error occurred',
    ]);
}
