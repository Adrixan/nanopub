<?php
declare(strict_types=1);

/**
 * NanoPub Installation Wizard
 * 
 * This script handles the initial setup of a NanoPub instance:
 * - Checks server requirements
 * - Configures database connection
 * - Creates database tables
 * - Creates admin account
 * - Writes configuration files
 * 
 * @package NanoPub
 */

/**
 * Detect the base paths considering open_basedir restrictions
 * 
 * On shared hosting, open_basedir may restrict access to directories
 * outside the web root. This function detects such restrictions and
 * returns appropriate paths.
 */
function detectBasePaths(): array
{
    $publicPath = __DIR__; // public/ directory
    $projectRoot = dirname(__DIR__); // Project root (may not be accessible)
    
    // Check if project root is accessible (typical development environment)
    $rootAccessible = @is_dir($projectRoot) && @is_readable($projectRoot);
    
    // Check if config directory at project root is accessible
    $rootConfigAccessible = $rootAccessible && @is_dir($projectRoot . '/config') && @is_writable($projectRoot . '/config');
    
    // Check if storage directory at project root is accessible  
    $rootStorageAccessible = $rootAccessible && @is_dir($projectRoot . '/storage') && @is_writable($projectRoot . '/storage');
    
    // Determine if we need to use public directory for storage
    $usePublicStorage = !$rootConfigAccessible || !$rootStorageAccessible;
    
    if ($usePublicStorage) {
        // Use public/storage for restricted hosting
        return [
            'config_path' => $publicPath . '/storage/config',
            'storage_path' => $publicPath . '/storage',
            'public_path' => $publicPath,
            'project_root' => $publicPath, // For this context, public is the effective root
            'root_accessible' => $rootAccessible,
            'using_public_storage' => true,
        ];
    }
    
    // Standard development/production setup
    return [
        'config_path' => $projectRoot . '/config',
        'storage_path' => $projectRoot . '/storage',
        'public_path' => $publicPath,
        'project_root' => $projectRoot,
        'root_accessible' => true,
        'using_public_storage' => false,
    ];
}

$paths = detectBasePaths();
define('BASE_PATH', $paths['project_root']);
define('CONFIG_PATH', $paths['config_path']);
define('STORAGE_PATH', $paths['storage_path']);
define('PUBLIC_PATH', $paths['public_path']);
define('USING_PUBLIC_STORAGE', $paths['using_public_storage']);

// Prevent access if already installed
$lockFile = CONFIG_PATH . '/installed.lock';
if (file_exists($lockFile)) {
    http_response_code(403);
    echo '<h1>Already Installed</h1><p>NanoPub is already installed. Delete <code>storage/config/installed.lock</code> to reinstall.</p>';
    exit;
}

// Session for installation steps
session_start();

/**
 * Installation state management
 */
function getInstallState(): array
{
    return isset($_SESSION['install']) ? $_SESSION['install'] : [
        'step' => 1,
        'requirements' => [],
        'db_config' => [],
        'admin_config' => [],
        'instance_config' => [],
    ];
}

function setInstallState(array $state): void
{
    $_SESSION['install'] = $state;
}

/**
 * Check if a directory is really writable by trying to create a test file
 */
function isReallyWritable(string $path): bool
{
    // First check if directory exists
    if (!is_dir($path)) {
        return false;
    }
    
    // Try to create a test file
    $testFile = rtrim($path, '/\\') . '/.write_test_' . uniqid();
    $fp = @fopen($testFile, 'w');
    if ($fp) {
        fclose($fp);
        @unlink($testFile);
        return true;
    }
    return false;
}

/**
 * Check and create required directories
 */
function checkAndCreateDirectories(): array
{
    // Use the globally defined paths
    $configPath = CONFIG_PATH;
    $storagePath = STORAGE_PATH;
    
    $directories = [
        'config' => $configPath,
        'storage' => $storagePath,
        'storage/uploads' => $storagePath . '/uploads',
        'storage/uploads/accounts' => $storagePath . '/uploads/accounts',
        'storage/uploads/media' => $storagePath . '/uploads/media',
        'storage/uploads/cache' => $storagePath . '/uploads/cache',
        'storage/queue' => $storagePath . '/queue',
        'storage/queue/locks' => $storagePath . '/queue/locks',
        'storage/queue/state' => $storagePath . '/queue/state',
    ];
    
    $results = [];
    
    foreach ($directories as $name => $path) {
        if (!is_dir($path)) {
            // Try to create the directory
            if (@mkdir($path, 0755, true)) {
                // Verify it's writable after creation
                if (isReallyWritable($path)) {
                    $results[$name] = [
                        'status' => 'created',
                        'path' => $path,
                        'passed' => true,
                    ];
                } else {
                    $results[$name] = [
                        'status' => 'created_not_writable',
                        'path' => $path,
                        'passed' => false,
                        'error' => 'Directory created but not writable. Check permissions.',
                    ];
                }
            } else {
                $error = error_get_last();
                $results[$name] = [
                    'status' => 'failed',
                    'path' => $path,
                    'passed' => false,
                    'error' => isset($error['message']) ? $error['message'] : 'Unknown error',
                ];
            }
        } elseif (isReallyWritable($path)) {
            $results[$name] = [
                'status' => 'writable',
                'path' => $path,
                'passed' => true,
            ];
        } else {
            $results[$name] = [
                'status' => 'not_writable',
                'path' => $path,
                'passed' => false,
                'error' => 'Directory exists but is not writable. Check ownership/permissions.',
            ];
        }
    }
    
    return $results;
}

/**
 * Create .htaccess file to protect a directory from web access
 */
function createHtaccessProtection(string $path): bool
{
    $htaccessPath = rtrim($path, '/\\') . '/.htaccess';
    $htaccessContent = "# Deny all web access to this directory\n"
        . "<IfModule mod_authz_core.c>\n"
        . "    Require all denied\n"
        . "</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n"
        . "    Order deny,allow\n"
        . "    Deny from all\n"
        . "</IfModule>\n"
        . "\n"
        . "# Disable directory listing\n"
        . "Options -Indexes\n";
    
    return @file_put_contents($htaccessPath, $htaccessContent) !== false;
}

/**
 * Create index.html file to prevent directory listing (fallback)
 */
function createIndexHtml(string $path): bool
{
    $indexPath = rtrim($path, '/\\') . '/index.html';
    $content = "<!DOCTYPE html><html><head><title>Access Denied</title></head><body>Access denied.</body></html>";
    
    return @file_put_contents($indexPath, $content) !== false;
}

/**
 * Generate manual commands for directory creation
 */
function getManualCommands(array $dirResults): string
{
    $commands = [];
    $commands[] = "# Run these commands to create and set permissions for directories:\n";
    
    foreach ($dirResults as $name => $result) {
        if (!$result['passed']) {
            $path = $result['path'];
            $commands[] = "mkdir -p {$path}";
            $commands[] = "chmod 755 {$path}";
        }
    }
    
    // Add chown hint
    $commands[] = "\n# If permissions still fail, you may need to change ownership:";
    $commands[] = "# chown -R www-data:www-data " . STORAGE_PATH;
    $commands[] = "# chown -R www-data:www-data " . CONFIG_PATH;
    
    return implode("\n", $commands);
}

/**
 * Check server requirements
 */
function checkRequirements(): array
{
    $requirements = [];
    
    // PHP version
    $requirements['php_version'] = [
        'name' => 'PHP Version',
        'required' => '8.1+',
        'actual' => PHP_VERSION,
        'passed' => version_compare(PHP_VERSION, '8.1.0', '>='),
    ];
    
    // Required extensions
    $extensions = [
        'pdo' => 'PDO',
        'pdo_mysql' => 'PDO MySQL',
        'json' => 'JSON',
        'mbstring' => 'Mbstring',
        'openssl' => 'OpenSSL',
        'curl' => 'cURL',
        'fileinfo' => 'Fileinfo',
        'intl' => 'Intl',
    ];
    
    foreach ($extensions as $ext => $name) {
        $requirements['ext_' . $ext] = [
            'name' => $name . ' Extension',
            'required' => 'Required',
            'actual' => extension_loaded($ext) ? 'Loaded' : 'Not loaded',
            'passed' => extension_loaded($ext),
        ];
    }
    
    return $requirements;
}

/**
 * Test database connection
 */
function testDatabaseConnection(array $config): array
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        isset($config['host']) ? $config['host'] : 'localhost',
        isset($config['port']) ? $config['port'] : '3306',
        isset($config['database']) ? $config['database'] : ''
    );
    
    try {
        $pdo = new PDO(
            $dsn,
            isset($config['username']) ? $config['username'] : '',
            isset($config['password']) ? $config['password'] : '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        
        return ['success' => true, 'message' => 'Connection successful', 'pdo' => $pdo];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
    }
}

/**
 * Get list of NanoPub table names (without prefix)
 * These are the tables that belong to NanoPub and should be managed during installation
 */
function getNanoPubTableNames(): array
{
    return [
        'accounts',
        'statuses',
        'media_attachments',
        'follows',
        'follow_requests',
        'likes',
        'bookmarks',
        'boosts',
        'notifications',
        'instance',
        'domain_blocks',
        'reports',
        'activity_queue',
        'delivery_queue',
        'sessions',
        'oauth_tokens',
        'rate_limits',
    ];
}

/**
 * Check if NanoPub tables already exist in the database
 * @param PDO $pdo Database connection
 * @param string $tablePrefix Optional table prefix to check
 * @return bool True if any NanoPub tables exist
 */
function tablesExist(PDO $pdo, string $tablePrefix = ''): bool
{
    $tables = getNanoPubTableNames();
    
    foreach ($tables as $table) {
        $fullTableName = $tablePrefix . $table;
        try {
            $result = $pdo->query("SELECT 1 FROM `{$fullTableName}` LIMIT 1");
            if ($result !== false) {
                return true;
            }
        } catch (PDOException $e) {
            // Table doesn't exist, continue checking
        }
    }
    
    return false;
}

/**
 * Get list of existing NanoPub tables in the database
 * @param PDO $pdo Database connection
 * @param string $tablePrefix Optional table prefix to check
 * @return array List of existing NanoPub table names (with prefix)
 */
function getExistingNanoPubTables(PDO $pdo, string $tablePrefix = ''): array
{
    $existingTables = [];
    $tables = getNanoPubTableNames();
    
    foreach ($tables as $table) {
        $fullTableName = $tablePrefix . $table;
        try {
            $result = $pdo->query("SELECT 1 FROM `{$fullTableName}` LIMIT 1");
            if ($result !== false) {
                $existingTables[] = $fullTableName;
            }
        } catch (PDOException $e) {
            // Table doesn't exist
        }
    }
    
    return $existingTables;
}

/**
 * Drop NanoPub tables only (not all tables in database)
 * @param PDO $pdo Database connection
 * @param string $tablePrefix Optional table prefix
 * @return array Result with success status and details
 */
function dropNanoPubTables(PDO $pdo, string $tablePrefix = ''): array
{
    $tables = getNanoPubTableNames();
    $dropped = [];
    $errors = [];
    
    try {
        // Disable foreign key checks temporarily
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($tables as $table) {
            $fullTableName = $tablePrefix . $table;
            try {
                $pdo->exec("DROP TABLE IF EXISTS `{$fullTableName}`");
                $dropped[] = $fullTableName;
            } catch (PDOException $e) {
                $errors[] = "Failed to drop table '{$fullTableName}': " . $e->getMessage();
            }
        }
        
        // Re-enable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        return [
            'success' => empty($errors),
            'dropped' => $dropped,
            'errors' => $errors,
        ];
    } catch (PDOException $e) {
        // Ensure foreign key checks are re-enabled even on error
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (PDOException $e2) {
            // Ignore
        }
        return [
            'success' => false,
            'dropped' => $dropped,
            'errors' => array_merge($errors, [$e->getMessage()]),
        ];
    }
}

/**
 * Create database tables with optional prefix
 * @param PDO $pdo Database connection
 * @param string $tablePrefix Optional table prefix (e.g., 'np_')
 * @return array Result with success status and message
 */
function createTables(PDO $pdo, string $tablePrefix = ''): array
{
    // Try multiple possible locations for the schema file
    $schemaLocations = [
        __DIR__ . '/storage/database/schema.sql',   // Public storage location (preferred for restricted hosting)
        dirname(__DIR__) . '/database/schema.sql',  // Standard location
        dirname(__DIR__) . '/public/storage/database/schema.sql',  // Alternative public storage
    ];
    
    $schemaFile = null;
    foreach ($schemaLocations as $location) {
        if (file_exists($location)) {
            $schemaFile = $location;
            break;
        }
    }
    
    if (!$schemaFile) {
        return ['success' => false, 'message' => 'Schema file not found. Please ensure database/schema.sql exists.'];
    }
    
    $sql = file_get_contents($schemaFile);
    
    // Apply table prefix if specified
    if (!empty($tablePrefix)) {
        $tableNames = getNanoPubTableNames();
        foreach ($tableNames as $tableName) {
            // Replace CREATE TABLE statements
            $sql = preg_replace(
                '/CREATE TABLE\s+`?' . preg_quote($tableName, '/') . '`?\s*\(/i',
                'CREATE TABLE `' . $tablePrefix . $tableName . '` (',
                $sql
            );
            // Replace FOREIGN KEY references
            $sql = preg_replace(
                '/REFERENCES\s+`?' . preg_quote($tableName, '/') . '`?\s*\(/i',
                'REFERENCES `' . $tablePrefix . $tableName . '`(',
                $sql
            );
        }
    }
    
    // Split by semicolons and execute each statement
    // Note: DDL statements (CREATE TABLE) implicitly commit in MySQL, so
    // transactions cannot be used for rollback. We execute without transaction wrapping.
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    try {
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        return ['success' => true, 'message' => 'Tables created successfully'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to create tables: ' . $e->getMessage()];
    }
}

/**
 * Write configuration files
 */
function writeConfigFiles(array $dbConfig, array $instanceConfig): array
{
    $results = [
        'success' => true,
        'files' => [],
        'manual_content' => [],
    ];
    
    // Ensure config directory exists
    if (!is_dir(CONFIG_PATH)) {
        @mkdir(CONFIG_PATH, 0755, true);
    }
    
    // Database config
    $dbConfigContent = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n";
    $dbConfigContent .= "    'host' => '" . addslashes($dbConfig['host']) . "',\n";
    $dbConfigContent .= "    'port' => " . (int) ($dbConfig['port'] ?? 3306) . ",\n";
    $dbConfigContent .= "    'name' => '" . addslashes($dbConfig['database']) . "',\n";
    $dbConfigContent .= "    'user' => '" . addslashes($dbConfig['username']) . "',\n";
    $dbConfigContent .= "    'password' => '" . addslashes($dbConfig['password']) . "',\n";
    $dbConfigContent .= "    'table_prefix' => '" . addslashes($dbConfig['table_prefix'] ?? '') . "',\n";
    $dbConfigContent .= "];\n";
    
    $dbConfigPath = CONFIG_PATH . '/database.php';
    if (@file_put_contents($dbConfigPath, $dbConfigContent) === false) {
        $results['success'] = false;
        $results['files']['database'] = [
            'path' => $dbConfigPath,
            'written' => false,
            'error' => error_get_last()['message'] ?? 'Unable to write file',
        ];
        $results['manual_content']['database.php'] = $dbConfigContent;
    } else {
        @chmod($dbConfigPath, 0644);
        $results['files']['database'] = [
            'path' => $dbConfigPath,
            'written' => true,
        ];
    }
    
    // Instance config
    $instanceConfigContent = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n";
    $instanceConfigContent .= "    'name' => '" . addslashes($instanceConfig['name']) . "',\n";
    $instanceConfigContent .= "    'description' => '" . addslashes($instanceConfig['description'] ?? '') . "',\n";
    $instanceConfigContent .= "    'domain' => '" . addslashes($instanceConfig['domain']) . "',\n";
    $instanceConfigContent .= "    'admin_email' => '" . addslashes($instanceConfig['admin_email'] ?? '') . "',\n";
    $instanceConfigContent .= "];\n";
    
    $instanceConfigPath = CONFIG_PATH . '/instance.php';
    if (@file_put_contents($instanceConfigPath, $instanceConfigContent) === false) {
        $results['success'] = false;
        $results['files']['instance'] = [
            'path' => $instanceConfigPath,
            'written' => false,
            'error' => error_get_last()['message'] ?? 'Unable to write file',
        ];
        $results['manual_content']['instance.php'] = $instanceConfigContent;
    } else {
        @chmod($instanceConfigPath, 0644);
        $results['files']['instance'] = [
            'path' => $instanceConfigPath,
            'written' => true,
        ];
    }
    
    // Create .htaccess protection for config directory if using public storage
    if (USING_PUBLIC_STORAGE) {
        createHtaccessProtection(CONFIG_PATH);
        createIndexHtml(CONFIG_PATH);
        
        // Also protect the storage directory
        createHtaccessProtection(STORAGE_PATH);
        createIndexHtml(STORAGE_PATH);
        
        // Protect subdirectories
        createHtaccessProtection(STORAGE_PATH . '/uploads');
        createIndexHtml(STORAGE_PATH . '/uploads');
        createHtaccessProtection(STORAGE_PATH . '/queue');
        createIndexHtml(STORAGE_PATH . '/queue');
    }
    
    return $results;
}

/**
 * Create admin account
 * @param PDO $pdo Database connection
 * @param array $adminConfig Admin configuration
 * @param string $domain Instance domain
 * @param string $tablePrefix Optional table prefix
 * @return bool Success status
 */
function createAdminAccount(PDO $pdo, string $username, string $email, string $password, array $adminConfig, string $domain, string $tablePrefix = ''): bool
{
    $accountsTable = $tablePrefix . 'accounts';
    
    // Generate key pair for ActivityPub
    $keyPair = generateKeyPair();
    
    $sql = "INSERT INTO `{$accountsTable}` (
        username, 
        email, 
        password_hash, 
        display_name,
        bio,
        avatar_url,
        header_url,
        actor_url,
        private_key,
        public_key,
        inbox_url,
        outbox_url,
        followers_url,
        following_url,
        is_local,
        is_locked,
        is_bot,
        is_admin,
        is_moderator,
        followers_count,
        following_count,
        statuses_count,
        created_at,
        updated_at
    ) VALUES (
        :username,
        :email,
        :password_hash,
        :display_name,
        :bio,
        :avatar_url,
        :header_url,
        :actor_url,
        :private_key,
        :public_key,
        :inbox_url,
        :outbox_url,
        :followers_url,
        :following_url,
        :is_local,
        :is_locked,
        :is_bot,
        :is_admin,
        :is_moderator,
        :followers_count,
        :following_count,
        :statuses_count,
        NOW(),
        NOW()
    )";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
        'display_name' => $adminConfig['display_name'] ?? $username,
        'bio' => $adminConfig['bio'] ?? '',
        'avatar_url' => '',
        'header_url' => '',
        'actor_url' => "https://{$domain}/users/{$username}",
        'private_key' => $keyPair['private'],
        'public_key' => $keyPair['public'],
        'inbox_url' => "https://{$domain}/users/{$username}/inbox",
        'outbox_url' => "https://{$domain}/users/{$username}/outbox",
        'followers_url' => "https://{$domain}/users/{$username}/followers",
        'following_url' => "https://{$domain}/users/{$username}/following",
        'is_local' => 1,
        'is_locked' => 0,
        'is_bot' => 0,
        'is_admin' => 1,
        'is_moderator' => 1,
        'followers_count' => 0,
        'following_count' => 0,
        'statuses_count' => 0,
    ]);
}

/**
 * Generate RSA key pair for ActivityPub
 */
function generateKeyPair(): array
{
    $config = [
        'digest_alg' => 'sha512',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];
    
    $keyPair = openssl_pkey_new($config);
    
    if ($keyPair === false) {
        throw new RuntimeException('Failed to generate key pair: ' . openssl_error_string());
    }
    
    openssl_pkey_export($keyPair, $privateKey);
    $publicKey = openssl_pkey_get_details($keyPair)['key'];
    
    return [
        'private' => $privateKey,
        'public' => $publicKey,
    ];
}

/**
 * Create instance record
 * @param PDO $pdo Database connection
 * @param array $instanceConfig Instance configuration
 * @param string $tablePrefix Optional table prefix
 * @return bool Success status
 */
function createInstanceRecord(PDO $pdo, array $instanceConfig, string $tablePrefix = ''): bool
{
    $instanceTable = $tablePrefix . 'instance';
    
    $sql = "INSERT INTO `{$instanceTable}` (
        domain,
        title,
        description,
        short_description,
        contact_email,
        registrations_open,
        approval_required,
        max_toot_chars,
        max_media_attachments,
        max_image_size,
        max_video_size,
        created_at,
        updated_at
    ) VALUES (
        :domain,
        :title,
        :description,
        :short_description,
        :contact_email,
        :registrations_open,
        :approval_required,
        :max_toot_chars,
        :max_media_attachments,
        :max_image_size,
        :max_video_size,
        NOW(),
        NOW()
    )";
    
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        'domain' => $instanceConfig['domain'],
        'title' => $instanceConfig['name'],
        'description' => $instanceConfig['description'] ?? '',
        'short_description' => $instanceConfig['short_description'] ?? '',
        'contact_email' => $instanceConfig['admin_email'] ?? '',
        'registrations_open' => 1,
        'approval_required' => 0,
        'max_toot_chars' => 500,
        'max_media_attachments' => 4,
        'max_image_size' => 8388608,
        'max_video_size' => 41943040,
    ]);
}

/**
 * Render a simple HTML template
 */
function render(string $title, string $content, string $styles = ''): string
{
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - NanoPub Installer</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
            padding: 2rem;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        h1 { color: #2c3e50; margin-bottom: 1rem; }
        h2 { color: #34495e; margin-bottom: 1rem; }
        .step { color: #666; font-size: 0.875rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        input:focus { outline: none; border-color: #3498db; }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #3498db;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
        }
        .btn:hover { background: #2980b9; }
        .btn:disabled { background: #bdc3c7; cursor: not-allowed; }
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .alert-warning { background: #fff3cd; color: #856404; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .status-pass { color: #28a745; }
        .status-fail { color: #dc3545; font-weight: bold; }
        .status-warning { color: #856404; font-weight: bold; }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo h1 {
            font-size: 2rem;
            color: #3498db;
        }
        {$styles}
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1> NanoPub</h1>
        </div>
        {$content}
    </div>
</body>
</html>
HTML;
}

// Handle form submissions
$state = getInstallState();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_requirements':
            $requirements = checkRequirements();
            $dirResults = checkAndCreateDirectories();
            
            // Check if PHP requirements passed
            $phpPassed = true;
            foreach ($requirements as $req) {
                if (!$req['passed']) {
                    $phpPassed = false;
                    break;
                }
            }
            
            // Check if all directories are usable
            $dirPassed = true;
            foreach ($dirResults as $dir) {
                if (!$dir['passed']) {
                    $dirPassed = false;
                    break;
                }
            }
            
            // Allow proceeding if PHP requirements pass, even if some directories have issues
            // (we'll show warnings and manual instructions)
            if ($phpPassed) {
                $state['step'] = 2;
                $state['requirements'] = $requirements;
                $state['directories'] = $dirResults;
                
                if (!$dirPassed) {
                    $state['dir_warnings'] = true;
                }
            } else {
                $error = 'Some PHP requirements are not met. Please fix them before continuing.';
                $state['requirements'] = $requirements;
                $state['directories'] = $dirResults;
            }
            break;
            
        case 'database_config':
            $dbConfig = [
                'host' => trim($_POST['db_host'] ?? 'localhost'),
                'port' => (int) ($_POST['db_port'] ?? 3306),
                'database' => trim($_POST['db_database'] ?? ''),
                'username' => trim($_POST['db_username'] ?? ''),
                'password' => $_POST['db_password'] ?? '',
                'table_prefix' => trim($_POST['db_table_prefix'] ?? ''),
            ];
            
            // Validate table prefix (only alphanumeric and underscore)
            if (!empty($dbConfig['table_prefix']) && !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*_$/', $dbConfig['table_prefix'])) {
                if (!empty($dbConfig['table_prefix']) && !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $dbConfig['table_prefix'])) {
                    $error = 'Table prefix must start with a letter and contain only letters, numbers, and underscores. It will automatically have an underscore appended.';
                    $state['db_config'] = $dbConfig;
                    break;
                }
                // Auto-append underscore if missing
                if (!empty($dbConfig['table_prefix']) && substr($dbConfig['table_prefix'], -1) !== '_') {
                    $dbConfig['table_prefix'] .= '_';
                }
            }
            
            $result = testDatabaseConnection($dbConfig);
            
            if ($result['success']) {
                $state['db_config'] = $dbConfig;
                
                // Check if NanoPub tables already exist with the given prefix
                if (tablesExist($result['pdo'], $dbConfig['table_prefix'])) {
                    $existingTables = getExistingNanoPubTables($result['pdo'], $dbConfig['table_prefix']);
                    $state['existing_tables'] = $existingTables;
                    $state['confirmed_reinstall'] = false; // Reset confirmation
                    $state['step'] = 2.5; // Intermediate step for re-installation warning
                    $message = 'Connection successful, but existing NanoPub tables were detected.';
                } else {
                    $state['existing_tables'] = [];
                    $state['confirmed_reinstall'] = false;
                    $state['step'] = 3;
                    $message = $result['message'];
                }
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'confirm_reinstall':
            // User must type "CONFIRM" exactly to proceed
            $confirmation = trim($_POST['confirm_text'] ?? '');
            
            if ($confirmation === 'CONFIRM') {
                $state['confirmed_reinstall'] = true;
                $state['step'] = 3;
                $message = 'Re-installation confirmed. Existing tables will be dropped during installation. Please continue with setup.';
            } else {
                $error = 'Please type "CONFIRM" (in uppercase, without quotes) to confirm re-installation.';
                // Stay on step 2.5
            }
            break;
            
        case 'instance_config':
            $instanceConfig = [
                'name' => trim($_POST['instance_name'] ?? ''),
                'description' => trim($_POST['instance_description'] ?? ''),
                'domain' => trim($_POST['instance_domain'] ?? $_SERVER['HTTP_HOST'] ?? ''),
                'admin_email' => trim($_POST['admin_email'] ?? ''),
            ];
            
            if (empty($instanceConfig['name']) || empty($instanceConfig['domain'])) {
                $error = 'Instance name and domain are required.';
            } else {
                $state['step'] = 4;
                $state['instance_config'] = $instanceConfig;
            }
            break;
            
        case 'admin_config':
            $adminConfig = [
                'username' => trim($_POST['admin_username'] ?? ''),
                'email' => trim($_POST['admin_email'] ?? ''),
                'password' => $_POST['admin_password'] ?? '',
                'password_confirm' => $_POST['admin_password_confirm'] ?? '',
                'display_name' => trim($_POST['admin_display_name'] ?? ''),
            ];
            
            // Validation
            if (strlen($adminConfig['username']) < 3) {
                $error = 'Username must be at least 3 characters.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $adminConfig['username'])) {
                $error = 'Username can only contain letters, numbers, and underscores.';
            } elseif (!filter_var($adminConfig['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (strlen($adminConfig['password']) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($adminConfig['password'] !== $adminConfig['password_confirm']) {
                $error = 'Passwords do not match.';
            } else {
                $state['step'] = 5;
                $state['admin_config'] = $adminConfig;
            }
            break;
            
        case 'install':
            // Get database connection
            $dbConfig = $state['db_config'];
            $tablePrefix = $dbConfig['table_prefix'] ?? '';
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['database']
            );
            
            try {
                $pdo = new PDO(
                    $dsn,
                    $dbConfig['username'],
                    $dbConfig['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
                
                // Drop existing tables if this is a re-installation AND user confirmed
                if (!empty($state['existing_tables'])) {
                    // Check if user explicitly confirmed re-installation
                    if (empty($state['confirmed_reinstall'])) {
                        throw new Exception('Re-installation requires explicit confirmation. Please go back and confirm the re-installation.');
                    }
                    
                    $dropResult = dropNanoPubTables($pdo, $tablePrefix);
                    if (!$dropResult['success']) {
                        throw new Exception('Failed to drop existing tables: ' . implode(', ', $dropResult['errors']));
                    }
                }
                
                // Create tables
                $result = createTables($pdo, $tablePrefix);
                if (!$result['success']) {
                    throw new Exception($result['message']);
                }
                
                // Create instance record
                if (!createInstanceRecord($pdo, $state['instance_config'], $tablePrefix)) {
                    throw new Exception('Failed to create instance record');
                }
                
                // Create admin account
                $adminResult = createAdminAccount($pdo, $state['admin_config']['username'], $state['admin_config']['email'], $state['admin_config']['password'], $state['admin_config'], $state['instance_config']['domain'], $tablePrefix);
                if (!$adminResult) {
                    throw new Exception('Failed to create admin account');
                }
                
                // Write config files
                $configResult = writeConfigFiles($dbConfig, $state['instance_config']);
                if (!$configResult['success']) {
                    // Store manual content for display
                    $state['config_manual'] = $configResult['manual_content'];
                    $state['config_errors'] = [];
                    foreach ($configResult['files'] as $file) {
                        if (!$file['written']) {
                            $state['config_errors'][] = $file;
                        }
                    }
                }
                
                // Create installed.lock
                @file_put_contents(CONFIG_PATH . '/installed.lock', date('Y-m-d H:i:s'));
                
                $state['step'] = 6;
                $message = 'Installation completed successfully!';
                
            } catch (Exception $e) {
                $error = 'Installation failed: ' . $e->getMessage();
            }
            break;
    }
    
    setInstallState($state);
}

// Render current step
switch ($state['step']) {
    case 1:
        // Check requirements
        $requirements = !empty($state['requirements']) ? $state['requirements'] : checkRequirements();
        $dirResults = !empty($state['directories']) ? $state['directories'] : checkAndCreateDirectories();
        
        // Check PHP requirements
        $phpPassed = true;
        foreach ($requirements as $req) {
            if (!$req['passed']) {
                $phpPassed = false;
                break;
            }
        }
        
        // Check directory status
        $dirWarnings = [];
        $dirErrors = [];
        foreach ($dirResults as $name => $dir) {
            if (!$dir['passed']) {
                if ($dir['status'] === 'failed') {
                    $dirErrors[] = $dir;
                } else {
                    $dirWarnings[] = $dir;
                }
            }
        }
        
        // Build PHP requirements table
        $rows = '';
        foreach ($requirements as $req) {
            $status = $req['passed'] 
                ? '<span class="status-pass">Pass</span>' 
                : '<span class="status-fail">Fail</span>';
            $rows .= "<tr><td>{$req['name']}</td><td>{$req['required']}</td><td>{$req['actual']}</td><td>{$status}</td></tr>";
        }
        
        // Build directory status table
        $dirRows = '';
        foreach ($dirResults as $name => $dir) {
            $status = match($dir['status']) {
                'created' => '<span class="status-pass">Created</span>',
                'writable' => '<span class="status-pass">Writable</span>',
                'created_not_writable' => '<span class="status-fail">Created (not writable)</span>',
                'not_writable' => '<span class="status-warning">Not writable</span>',
                'failed' => '<span class="status-fail">Failed</span>',
                default => '<span class="status-fail">Unknown</span>'
            };
            $errorInfo = isset($dir['error']) ? "<br><small>{$dir['error']}</small>" : '';
            $dirRows .= "<tr><td>{$name}</td><td>{$dir['path']}</td><td>{$status}{$errorInfo}</td></tr>";
        }
        
        // Build warning message if directories have issues
        $dirWarningHtml = '';
        if (!empty($dirErrors) || !empty($dirWarnings)) {
            $manualCommands = getManualCommands($dirResults);
            $dirWarningHtml = '<div class="alert alert-warning">';
            $dirWarningHtml .= '<strong>Directory Issues Detected</strong><br>';
            $dirWarningHtml .= '<p>Some directories could not be created or are not writable. You may need to create them manually.</p>';
            $dirWarningHtml .= '<pre style="background: #f5f5f5; padding: 1rem; overflow-x: auto; font-size: 0.85rem;">' . htmlspecialchars($manualCommands) . '</pre>';
            $dirWarningHtml .= '</div>';
        }
        
        $disabledAttr = $phpPassed ? '' : ' disabled';
        
        $content = <<<HTML
<div class="step">Step 1 of 5: Server Requirements</div>
<h2>Server Requirements</h2>

{$error}

<h3>PHP Requirements</h3>
<table>
    <thead>
        <tr>
            <th>Requirement</th>
            <th>Required</th>
            <th>Actual</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>

<h3>Directory Status</h3>
<table>
    <thead>
        <tr>
            <th>Directory</th>
            <th>Path</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        {$dirRows}
    </tbody>
</table>

{$dirWarningHtml}

<form method="post">
    <input type="hidden" name="action" value="check_requirements">
    <button type="submit" class="btn"{$disabledAttr}>
        Continue to Database Setup
    </button>
</form>
HTML;
        break;
        
    case 2:
        // Database configuration
        $dbConfig = $state['db_config'] ?? [];
        $dbHost = $dbConfig['host'] ?? 'localhost';
        $dbPort = $dbConfig['port'] ?? 3306;
        $dbDatabase = $dbConfig['database'] ?? '';
        $dbUsername = $dbConfig['username'] ?? '';
        $dbPassword = $dbConfig['password'] ?? '';
        $dbTablePrefix = $dbConfig['table_prefix'] ?? '';
        
        $content = <<<HTML
<div class="step">Step 2 of 5: Database Configuration</div>
<h2>Database Configuration</h2>
<p>Enter your MySQL database connection details.</p>

{$error}
{$message}

<form method="post">
    <input type="hidden" name="action" value="database_config">
    
    <div class="form-group">
        <label for="db_host">Database Host</label>
        <input type="text" id="db_host" name="db_host" value="{$dbHost}" placeholder="localhost">
    </div>
    
    <div class="form-group">
        <label for="db_port">Database Port</label>
        <input type="number" id="db_port" name="db_port" value="{$dbPort}" placeholder="3306">
    </div>
    
    <div class="form-group">
        <label for="db_database">Database Name</label>
        <input type="text" id="db_database" name="db_database" value="{$dbDatabase}" required>
    </div>
    
    <div class="form-group">
        <label for="db_username">Database Username</label>
        <input type="text" id="db_username" name="db_username" value="{$dbUsername}" required>
    </div>
    
    <div class="form-group">
        <label for="db_password">Database Password</label>
        <input type="password" id="db_password" name="db_password" value="{$dbPassword}">
    </div>
    
    <div class="form-group">
        <label for="db_table_prefix">Table Prefix (optional)</label>
        <input type="text" id="db_table_prefix" name="db_table_prefix" value="{$dbTablePrefix}" placeholder="np_">
        <small>Prefix for all NanoPub tables (e.g., "np_" creates tables like "np_accounts"). Leave empty for no prefix.</small>
    </div>
    
    <button type="submit" class="btn">Test Connection & Continue</button>
</form>
HTML;
        break;
        
    case 2.5:
        // Re-installation warning - existing tables detected
        $existingTables = $state['existing_tables'] ?? [];
        $tablesList = implode(', ', $existingTables);
        $tablesCount = count($existingTables);
        $tablePrefix = $state['db_config']['table_prefix'] ?? '';
        $prefixDisplay = !empty($tablePrefix) ? " (with prefix '{$tablePrefix}')" : '';
        
        $content = <<<HTML
<div class="step">Step 2 of 5: Existing Installation Detected</div>
<h2>⚠️ Existing NanoPub Tables Detected</h2>

{$error}

<div class="alert alert-warning">
    <strong>Warning: Existing Database Tables Found</strong>
    <p>The database already contains {$tablesCount} NanoPub table(s){$prefixDisplay}:</p>
    <pre style="background: #f5f5f5; padding: 0.5rem; overflow-x: auto; font-size: 0.85rem; margin: 0.5rem 0;">{$tablesList}</pre>
</div>

<div class="alert alert-error">
    <strong>⚠️ Data Loss Warning</strong>
    <p>If you proceed with the re-installation:</p>
    <ul style="margin: 0.5rem 0 0 1.5rem;">
        <li><strong>All existing NanoPub tables will be dropped (deleted)</strong></li>
        <li><strong>All existing data will be permanently lost</strong></li>
        <li>A fresh installation will be created</li>
    </ul>
</div>

<div style="background: #fff3cd; border: 2px solid #ffc107; padding: 1rem; border-radius: 4px; margin: 1.5rem 0;">
    <h3 style="margin-bottom: 0.5rem;">Confirm Re-installation</h3>
    <p>To proceed with re-installation and delete all existing data, you must explicitly confirm by typing <strong>CONFIRM</strong> in the box below:</p>
    
    <form method="post" style="margin-top: 1rem;">
        <input type="hidden" name="action" value="confirm_reinstall">
        
        <div class="form-group">
            <label for="confirm_text">Type "CONFIRM" to proceed:</label>
            <input type="text" id="confirm_text" name="confirm_text" placeholder="Type CONFIRM here" autocomplete="off" required>
        </div>
        
        <button type="submit" class="btn" style="background: #e74c3c;">Confirm Re-installation</button>
    </form>
</div>

<p style="margin: 1.5rem 0;">Alternatively, you can:</p>

<form method="post" style="display: inline;">
    <input type="hidden" name="action" value="database_config">
    <input type="hidden" name="db_host" value="{$state['db_config']['host']}">
    <input type="hidden" name="db_port" value="{$state['db_config']['port']}">
    <input type="hidden" name="db_database" value="{$state['db_config']['database']}">
    <input type="hidden" name="db_username" value="{$state['db_config']['username']}">
    <input type="hidden" name="db_password" value="{$state['db_config']['password']}">
    <input type="hidden" name="db_table_prefix" value="{$tablePrefix}">
    <button type="submit" class="btn" style="background: #95a5a6;">Use Different Database</button>
</form>
HTML;
        break;
        
    case 3:
        // Instance configuration
        $instanceConfig = $state['instance_config'] ?? [];
        $defaultDomain = $_SERVER['HTTP_HOST'] ?? 'example.com';

        $instanceName = $instanceConfig['name'] ?? '';
        $instanceDescription = $instanceConfig['description'] ?? '';
        $instanceDomain = $instanceConfig['domain'] ?? $defaultDomain;
        $instanceAdminEmail = $instanceConfig['admin_email'] ?? '';

        $content = <<<HTML
<div class="step">Step 3 of 5: Instance Configuration</div>
<h2>Instance Configuration</h2>
<p>Configure your NanoPub instance.</p>

{$error}

<form method="post">
    <input type="hidden" name="action" value="instance_config">
    
    <div class="form-group">
        <label for="instance_name">Instance Name</label>
        <input type="text" id="instance_name" name="instance_name" value="{$instanceName}" required>
    </div>
    
    <div class="form-group">
        <label for="instance_description">Instance Description (optional)</label>
        <input type="text" id="instance_description" name="instance_description" value="{$instanceDescription}">
    </div>
    
    <div class="form-group">
        <label for="instance_domain">Instance Domain</label>
        <input type="text" id="instance_domain" name="instance_domain" value="{$instanceDomain}" required>
        <small>This is the domain where your instance is hosted (e.g., social.example.com)</small>
    </div>
    
    <div class="form-group">
        <label for="admin_email">Admin Email</label>
        <input type="email" id="admin_email" name="admin_email" value="{$instanceAdminEmail}">
    </div>
    
    <button type="submit" class="btn">Continue to Admin Setup</button>
</form>
HTML;
        break;
        
    case 4:
        // Admin account setup
        $adminConfig = $state['admin_config'] ?? [];

        $adminUsername = $adminConfig['username'] ?? '';
        $adminDisplayName = $adminConfig['display_name'] ?? '';
        $adminEmail = $adminConfig['email'] ?? '';

        $content = <<<HTML
<div class="step">Step 4 of 5: Admin Account</div>
<h2>Create Admin Account</h2>
<p>Create the administrator account for your instance.</p>

{$error}

<form method="post">
    <input type="hidden" name="action" value="admin_config">
    
    <div class="form-group">
        <label for="admin_username">Username</label>
        <input type="text" id="admin_username" name="admin_username" value="{$adminUsername}" required>
    </div>
    
    <div class="form-group">
        <label for="admin_display_name">Display Name</label>
        <input type="text" id="admin_display_name" name="admin_display_name" value="{$adminDisplayName}">
    </div>
    
    <div class="form-group">
        <label for="admin_email">Email</label>
        <input type="email" id="admin_email" name="admin_email" value="{$adminEmail}" required>
    </div>
    
    <div class="form-group">
        <label for="admin_password">Password</label>
        <input type="password" id="admin_password" name="admin_password" required>
        <small>At least 8 characters</small>
    </div>
    
    <div class="form-group">
        <label for="admin_password_confirm">Confirm Password</label>
        <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
    </div>
    
    <button type="submit" class="btn">Review Installation</button>
</form>
HTML;
        break;
        
    case 5:
        // Review and install
        // Show warning if this is a re-installation
        $reinstallWarning = '';
        if (!empty($state['existing_tables'])) {
            if (empty($state['confirmed_reinstall'])) {
                $reinstallWarning = '<div class="alert alert-error">';
                $reinstallWarning .= '<strong>⚠️ Re-installation Not Confirmed</strong><br>';
                $reinstallWarning .= '<p>Existing tables were detected but re-installation was not confirmed. Please go back and confirm the re-installation.</p>';
                $reinstallWarning .= '</div>';
            } else {
                $reinstallWarning = '<div class="alert alert-error">';
                $reinstallWarning .= '<strong>⚠️ Re-installation Mode</strong><br>';
                $reinstallWarning .= '<p>This will <strong>drop all existing NanoPub tables</strong> and create a fresh installation. All existing data will be permanently lost.</p>';
                $reinstallWarning .= '</div>';
            }
        }
        
        $tablePrefixDisplay = !empty($state['db_config']['table_prefix']) ? $state['db_config']['table_prefix'] : '(none)';
        
        $content = <<<HTML
<div class="step">Step 5 of 5: Review & Install</div>
<h2>Review Installation</h2>
<p>Please review your settings before installing.</p>

{$error}
{$reinstallWarning}

<table>
    <tr><th colspan="2">Database</th></tr>
    <tr><td>Host</td><td>{$state['db_config']['host']}:{$state['db_config']['port']}</td></tr>
    <tr><td>Database</td><td>{$state['db_config']['database']}</td></tr>
    <tr><td>Username</td><td>{$state['db_config']['username']}</td></tr>
    <tr><td>Table Prefix</td><td>{$tablePrefixDisplay}</td></tr>
    
    <tr><th colspan="2">Instance</th></tr>
    <tr><td>Name</td><td>{$state['instance_config']['name']}</td></tr>
    <tr><td>Domain</td><td>{$state['instance_config']['domain']}</td></tr>
    <tr><td>Description</td><td>{$state['instance_config']['description']}</td></tr>
    
    <tr><th colspan="2">Admin Account</th></tr>
    <tr><td>Username</td><td>{$state['admin_config']['username']}</td></tr>
    <tr><td>Email</td><td>{$state['admin_config']['email']}</td></tr>
</table>

<form method="post">
    <input type="hidden" name="action" value="install">
    <button type="submit" class="btn">Install NanoPub</button>
</form>
HTML;
        break;
        
    case 6:
        // Installation complete
        $manualConfig = $state['config_manual'] ?? [];
        $configErrors = $state['config_errors'] ?? [];
        session_destroy();
        
        $configWarningHtml = '';
        if (!empty($manualConfig)) {
            $configWarningHtml = '<div class="alert alert-warning">';
            $configWarningHtml .= '<strong>Manual Configuration Required</strong><br>';
            $configWarningHtml .= '<p>Some configuration files could not be written automatically. Please create them manually:</p>';
            
            foreach ($manualConfig as $filename => $content) {
                $configWarningHtml .= "<h4>config/{$filename}</h4>";
                $configWarningHtml .= '<pre style="background: #f5f5f5; padding: 1rem; overflow-x: auto; font-size: 0.85rem;">' . htmlspecialchars($content) . '</pre>';
            }
            $configWarningHtml .= '</div>';
        }
        
        $content = <<<HTML
<div class="step">Installation Complete!</div>
<h2>Installation Successful</h2>

<div class="alert alert-success">
    <strong>Success!</strong> Your NanoPub instance has been installed.
</div>

{$configWarningHtml}

<p>Your instance is now ready to use. Here are some next steps:</p>

<ul>
    <li>Log in with your admin account</li>
    <li>Configure your instance settings</li>
    <li>Set up cron jobs for background processing</li>
</ul>

<p><strong>Important:</strong> Delete this file (<code>public/install.php</code>) for security.</p>

<a href="/" class="btn">Go to Your Instance</a>
HTML;
        break;
        
    default:
        $content = '<p>Invalid step. <a href="install.php">Start over</a>.</p>';
}

echo render('Installation', $content);