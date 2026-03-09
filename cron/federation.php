<?php
declare(strict_types=1);

/**
 * NanoPub Federation Tasks
 * 
 * Handles federation maintenance tasks:
 * - Retry failed deliveries
 * - Fetch remote actor information
 * - Refresh actor keys
 * 
 * Should be run via cron periodically (every 5-15 minutes).
 * 
 * Usage:
 *   php cron/federation.php
 * 
 * Example crontab entry (every 5 minutes):
 *   0,5,10,15,20,25,30,35,40,45,50,55 * * * * cd /path/to/nanopub && php cron/federation.php
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
use NanoPub\Services\ActivityPubService;
use NanoPub\Core\Database;

// Set time limit
set_time_limit(300);

try {
    $timestamp = date('Y-m-d H:i:s');
    $queueService = new QueueService();
    
    // Retry failed deliveries
    $retriedDeliveries = $queueService->retryAllFailedDeliveries();
    echo "[{$timestamp}] Retried {$retriedDeliveries} failed deliveries\n";
    
    // Retry failed activities
    $retriedActivities = $queueService->retryAllFailedActivities();
    echo "[{$timestamp}] Retried {$retriedActivities} failed activities\n";
    
    // Clean up stale processing items (stuck for more than 1 hour)
    $db = Database::getConnection();
    
    $stmt = $db->prepare("
        UPDATE activity_queue 
        SET status = 'pending', 
            processing_at = NULL 
        WHERE status = 'processing' 
        AND processing_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute();
    $staleActivities = $stmt->rowCount();
    
    $stmt = $db->prepare("
        UPDATE delivery_queue 
        SET status = 'pending', 
            processing_at = NULL 
        WHERE status = 'processing' 
        AND processing_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute();
    $staleDeliveries = $stmt->rowCount();
    
    if ($staleActivities > 0 || $staleDeliveries > 0) {
        echo "[{$timestamp}] Reset {$staleActivities} stale activities, {$staleDeliveries} stale deliveries\n";
    }
    
    // Get final stats
    $stats = $queueService->getStats();
    echo "[{$timestamp}] Queue status: {$stats['activity']['pending']} pending activities, {$stats['delivery']['pending']} pending deliveries\n";
    
    exit(0);
    
} catch (\Throwable $e) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[{$timestamp}] Federation task error: " . $e->getMessage());
    echo "[{$timestamp}] Error: " . $e->getMessage() . "\n";
    exit(1);
}
