<?php
declare(strict_types=1);

/**
 * NanoPub Cleanup Tasks
 * 
 * Handles cleanup of old data:
 * - Old queue items
 * - Expired sessions
 * - Old rate limit entries
 * - Temporary files
 * 
 * Should be run via cron daily.
 * 
 * Usage:
 *   php cron/cleanup.php [days_old]
 * 
 * Example crontab entry (daily at 3 AM):
 *   0 3 * * * cd /path/to/nanopub && php cron/cleanup.php 7
 * 
 * @package NanoPub\Cron
 */

// Ensure running from CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Register autoloader
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

// Load helper functions
require_once BASE_PATH . '/src/Helpers/functions.php';
require_once BASE_PATH . '/src/Helpers/crypto.php';
require_once BASE_PATH . '/src/Helpers/http.php';
require_once BASE_PATH . '/src/Helpers/json.php';
require_once BASE_PATH . '/src/Helpers/time.php';
require_once BASE_PATH . '/src/Helpers/text.php';
require_once BASE_PATH . '/src/Helpers/validation.php';
require_once BASE_PATH . '/src/Helpers/pagination.php';

use NanoPub\Services\QueueService;
use NanoPub\Core\Database;

// Get days old from argument or default to 7
$daysOld = isset($argv[1]) && is_numeric($argv[1]) 
    ? max(1, (int) $argv[1]) 
    : 7;

// Set time limit
set_time_limit(300);

try {
    $timestamp = date('Y-m-d H:i:s');
    $db = Database::getConnection();
    
    echo "[{$timestamp}] Starting cleanup (items older than {$daysOld} days)...\n";
    
    // Cleanup old queue items
    $queueService = new QueueService();
    $cleaned = $queueService->cleanup($daysOld);
    echo "[{$timestamp}] Cleaned {$cleaned['activity']} activities, {$cleaned['delivery']} deliveries from queue\n";
    
    // Cleanup expired sessions
    $stmt = $db->prepare('DELETE FROM sessions WHERE expires_at < NOW()');
    $stmt->execute();
    $sessionsCleaned = $stmt->rowCount();
    echo "[{$timestamp}] Cleaned {$sessionsCleaned} expired sessions\n";
    
    // Cleanup old rate limits
    $stmt = $db->prepare('DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $stmt->execute();
    $rateLimitsCleaned = $stmt->rowCount();
    echo "[{$timestamp}] Cleaned {$rateLimitsCleaned} rate limit entries\n";
    
    // Cleanup old notifications (optional - keep for 30 days by default)
    $notificationDays = max(30, $daysOld);
    $stmt = $db->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$notificationDays]);
    $notificationsCleaned = $stmt->rowCount();
    if ($notificationsCleaned > 0) {
        echo "[{$timestamp}] Cleaned {$notificationsCleaned} old notifications\n";
    }
    
    // Cleanup temporary upload files older than 1 day
    $tempDir = storage_path('uploads/cache');
    if (is_dir($tempDir)) {
        $tempCleaned = 0;
        $files = glob($tempDir . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > 86400) {
                if (unlink($file)) {
                    $tempCleaned++;
                }
            }
        }
        
        if ($tempCleaned > 0) {
            echo "[{$timestamp}] Cleaned {$tempCleaned} temporary files\n";
        }
    }
    
    // Optimize tables (optional, can help with performance)
    $db->exec('OPTIMIZE TABLE activity_queue');
    $db->exec('OPTIMIZE TABLE delivery_queue');
    $db->exec('OPTIMIZE TABLE sessions');
    $db->exec('OPTIMIZE TABLE rate_limits');
    
    echo "[{$timestamp}] Cleanup completed successfully\n";
    
    exit(0);
    
} catch (\Throwable $e) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[{$timestamp}] Cleanup error: " . $e->getMessage());
    echo "[{$timestamp}] Error: " . $e->getMessage() . "\n";
    exit(1);
}
