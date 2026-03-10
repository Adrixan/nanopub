<?php

declare(strict_types=1);

namespace NanoPub\Tests\Unit\Controllers\Web;

use NanoPub\Controllers\Web\NotificationController;
use NanoPub\Core\Database;
use NanoPub\Core\Request;
use NanoPub\Core\Session;
use NanoPub\Core\View;
use Pdo\Sqlite as PdoSqlite;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test cases for NotificationController.
 */
final class NotificationControllerTest extends TestCase
{
    private static PdoSqlite $testPdo;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->setUpSession();
    }

    protected function tearDown(): void
    {
        $this->tearDownSession();
        $this->tearDownDatabase();

        // Reset View path so other tests aren't affected
        View::setViewsPath('');
    }

    public function testIndexRedirectsUnauthenticatedUser(): void
    {
        unset($_SESSION['account_id']);

        $request = new Request('GET', '/notifications');
        $controller = new NotificationController();
        $result = $controller->index($request);

        self::assertSame('', $result);
    }

    public function testIndexRendersForAuthenticatedUser(): void
    {
        self::$testPdo->exec(
            "INSERT INTO accounts (id, username, display_name, avatar_url)
             VALUES (1, 'testuser', 'Test User', '/img/avatar.png')"
        );

        $_SESSION['account_id'] = 1;

        // Point View at the real templates shipped with the project
        View::setViewsPath(BASE_PATH . '/src/Views');

        // Load time helper required by the notification template
        require_once BASE_PATH . '/src/Helpers/time.php';

        $request = new Request('GET', '/notifications');
        $controller = new NotificationController();
        $result = $controller->index($request);

        self::assertNotEmpty($result);
        self::assertStringContainsString('Notifications', $result);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function setUpDatabase(): void
    {
        $pdo = new PdoSqlite('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Notification::markAllAsRead() uses MySQL NOW(); provide a SQLite equivalent
        $pdo->createFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);

        $pdo->exec('CREATE TABLE accounts (
            id INTEGER PRIMARY KEY,
            username TEXT,
            display_name TEXT,
            avatar_url TEXT
        )');
        $pdo->exec('CREATE TABLE notifications (
            id INTEGER PRIMARY KEY,
            account_id INTEGER,
            type TEXT,
            from_account_id INTEGER,
            status_id INTEGER,
            read_at TEXT,
            created_at TEXT
        )');
        $pdo->exec('CREATE TABLE statuses (
            id INTEGER PRIMARY KEY,
            account_id INTEGER,
            content TEXT,
            visibility TEXT
        )');

        self::$testPdo = $pdo;

        // Inject into Database singleton via reflection
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('connection');
        $prop->setValue(null, $pdo);
    }

    private function tearDownDatabase(): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('connection');
        $prop->setValue(null, null);
    }

    private function setUpSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];

        // Reset Session's internal started flag
        $ref = new ReflectionClass(Session::class);
        $prop = $ref->getProperty('started');
        $prop->setValue(null, false);
    }

    private function tearDownSession(): void
    {
        $_SESSION = [];

        $ref = new ReflectionClass(Session::class);
        $prop = $ref->getProperty('started');
        $prop->setValue(null, false);
    }
}
