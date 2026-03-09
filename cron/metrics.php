<?php
declare(strict_types=1);

/**
 * NanoPub Metrics Collection
 * 
 * Collects and stores instance metrics for monitoring and statistics.
 * 
 * Usage:
 *   php cron/metrics.php
 * 
 * Example crontab entry (hourly):
 *   0 * * * * cd /path/to/nanopub && php cron/metrics.php
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

use NanoPub\Core\Database;

// Set time limit
set_time_limit(60);

try {
    $timestamp = date('Y-m-d H:i:s');
    $db = Database::getConnection();
    
    echo "[{$timestamp}] Collecting metrics...\n";
    
    // Count total users
    $stmt = $db->query('SELECT COUNT(*) FROM accounts WHERE is_local = 1');
    $totalUsers = (int) $stmt->fetchColumn();
    
    // Count active users (last 30 days)
    $stmt = $db->query('SELECT COUNT(*) FROM accounts WHERE is_local = 1 AND last_activity_at > DATE_SUB(NOW(), INTERVAL 30 DAY)');
    $activeUsersMonthly = (int) $stmt->fetchColumn();
    
    // Count active users (last 7 days)
    $stmt = $db->query('SELECT COUNT(*) FROM accounts WHERE is_local = 1 AND last_activity_at > DATE_SUB(NOW(), INTERVAL 7 DAY)');
    $activeUsersWeekly = (int) $stmt->fetchColumn();
    
    // Count active users (last 24 hours)
    $stmt = $db->query('SELECT COUNT(*) FROM accounts WHERE is_local = 1 AND last_activity_at > DATE_SUB(NOW(), INTERVAL 1 DAY)');
    $activeUsersDaily = (int) $stmt->fetchColumn();
    
    // Count total statuses
    $stmt = $db->query('SELECT COUNT(*) FROM statuses WHERE local = 1');
    $totalStatuses = (int) $stmt->fetchColumn();
    
    // Count statuses in last 24 hours
    $stmt = $db->query('SELECT COUNT(*) FROM statuses WHERE local = 1 AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)');
    $statusesLast24h = (int) $stmt->fetchColumn();
    
    // Count remote instances known
    $stmt = $db->query('SELECT COUNT(DISTINCT domain) FROM accounts WHERE is_local = 0');
    $knownInstances = (int) $stmt->fetchColumn();
    
    // Count follows
    $stmt = $db->query('SELECT COUNT(*) FROM follows');
    $totalFollows = (int) $stmt->fetchColumn();
    
    // Count local follows (outgoing)
    $stmt = $db->query('SELECT COUNT(*) FROM follows f JOIN accounts a ON f.account_id = a.id WHERE a.is_local = 1');
    $localFollows = (int) $stmt->fetchColumn();
    
    // Queue stats
    $stmt = $db->query("SELECT COUNT(*) FROM activity_queue WHERE status = 'pending'");
    $pendingActivities = (int) $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM delivery_queue WHERE status = 'pending'");
    $pendingDeliveries = (int) $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM delivery_queue WHERE status = 'failed'");
    $failedDeliveries = (int) $stmt->fetchColumn();
    
    // Database size (approximate)
    $stmt = $db->query("
        SELECT 
            SUM(data_length + index_length) / 1024 / 1024 as size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ");
    $dbSize = round((float) $stmt->fetchColumn(), 2);
    
    // Output metrics
    echo "[{$timestamp}] === User Metrics ===\n";
    echo "[{$timestamp}] Total Users: {$totalUsers}\n";
    echo "[{$timestamp}] Active (30d): {$activeUsersMonthly}\n";
    echo "[{$timestamp}] Active (7d): {$activeUsersWeekly}\n";
    echo "[{$timestamp}] Active (24h): {$activeUsersDaily}\n";
    
    echo "[{$timestamp}] === Content Metrics ===\n";
    echo "[{$timestamp}] Total Statuses: {$totalStatuses}\n";
    echo "[{$timestamp}] Statuses (24h): {$statusesLast24h}\n";
    
    echo "[{$timestamp}] === Federation Metrics ===\n";
    echo "[{$timestamp}] Known Instances: {$knownInstances}\n";
    echo "[{$timestamp}] Total Follows: {$totalFollows}\n";
    echo "[{$timestamp}] Local Follows: {$localFollows}\n";
    
    echo "[{$timestamp}] === Queue Metrics ===\n";
    echo "[{$timestamp}] Pending Activities: {$pendingActivities}\n";
    echo "[{$timestamp}] Pending Deliveries: {$pendingDeliveries}\n";
    echo "[{$timestamp}] Failed Deliveries: {$failedDeliveries}\n";
    
    echo "[{$timestamp}] === System Metrics ===\n";
    echo "[{$timestamp}] Database Size: {$dbSize} MB\n";
    
    // Store metrics in a JSON file for external monitoring
    $metrics = [
        'timestamp' => time(),
        'datetime' => $timestamp,
        'users' => [
            'total' => $totalUsers,
            'active_monthly' => $activeUsersMonthly,
            'active_weekly' => $activeUsersWeekly,
            'active_daily' => $activeUsersDaily,
        ],
        'content' => [
            'total_statuses' => $totalStatuses,
            'statuses_24h' => $statusesLast24h,
        ],
        'federation' => [
            'known_instances' => $knownInstances,
            'total_follows' => $totalFollows,
            'local_follows' => $localFollows,
        ],
        'queue' => [
            'pending_activities' => $pendingActivities,
            'pending_deliveries' => $pendingDeliveries,
            'failed_deliveries' => $failedDeliveries,
        ],
        'system' => [
            'database_size_mb' => $dbSize,
        ],
    ];
    
    $metricsFile = storage_path('metrics.json');
    file_put_contents($metricsFile, json_encode($metrics, JSON_PRETTY_PRINT));
    
    echo "[{$timestamp}] Metrics saved to {$metricsFile}\n";
    echo "[{$timestamp}] Metrics collection completed\n";
    
    exit(0);
    
} catch (\Throwable $e) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[{$timestamp}] Metrics collection error: " . $e->getMessage());
    echo "[{$timestamp}] Error: " . $e->getMessage() . "\n";
    exit(1);
}