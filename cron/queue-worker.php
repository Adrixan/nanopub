<?php
declare(strict_types=1);

/**
 * NanoPub Queue Worker
 * 
 * Processes queued activities and deliveries for ActivityPub federation.
 * Should be run via cron every minute or as a daemon.
 * 
 * Usage:
 *   php cron/queue-worker.php [batch_size]
 * 
 * Example crontab entry:
 *   * * * * * cd /path/to/nanopub && php cron/queue-worker.php 10
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

// Get batch size from argument or default to 10
$batchSize = isset($argv[1]) && is_numeric($argv[1]) 
    ? max(1, (int) $argv[1]) 
    : 10;

// Set unlimited time for long-running processes
set_time_limit(0);

// Increase memory limit for large batches
ini_set('memory_limit', '256M');

try {
    $queueService = new QueueService();
    
    // Process activities (incoming federation)
    $activitiesProcessed = $queueService->processActivityBatch($batchSize);
    
    // Process deliveries (outgoing federation)
    $deliveriesProcessed = $queueService->processDeliveryBatch($batchSize);
    
    // Get current stats
    $stats = $queueService->getStats();
    
    // Output results
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Processed: {$activitiesProcessed} activities, {$deliveriesProcessed} deliveries\n";
    echo "[{$timestamp}] Pending: {$stats['activity']['pending']} activities, {$stats['delivery']['pending']} deliveries\n";
    
    // Show failed counts if any
    if ($stats['activity']['failed'] > 0 || $stats['delivery']['failed'] > 0) {
        echo "[{$timestamp}] Failed: {$stats['activity']['failed']} activities, {$stats['delivery']['failed']} deliveries\n";
    }
    
    exit(0);
    
} catch (\Throwable $e) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[{$timestamp}] Queue worker error: " . $e->getMessage());
    echo "[{$timestamp}] Error: " . $e->getMessage() . "\n";
    exit(1);
}
