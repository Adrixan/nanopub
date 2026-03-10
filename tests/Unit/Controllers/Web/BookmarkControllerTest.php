<?php

declare(strict_types=1);

namespace NanoPub\Tests\Unit\Controllers\Web;

use NanoPub\Controllers\Web\BookmarkController;
use NanoPub\Core\Database;
use NanoPub\Core\Request;
use NanoPub\Core\Session;
use NanoPub\Core\View;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test cases for BookmarkController.
 */
final class BookmarkControllerTest extends TestCase
{
    private static PDO $testPdo;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->setUpSession();
    }

    protected function tearDown(): void
    {
        $this->tearDownSession();
        $this->tearDownDatabase();

        View::setViewsPath('');
    }

    public function testIndexRedirectsUnauthenticatedUser(): void
    {
        unset($_SESSION['account_id']);

        $request = new Request('GET', '/bookmarks');
        $controller = new BookmarkController();
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

        View::setViewsPath(BASE_PATH . '/src/Views');

        // Load time helper required by the bookmark template
        require_once BASE_PATH . '/src/Helpers/time.php';

        $request = new Request('GET', '/bookmarks');
        $controller = new BookmarkController();
        $result = $controller->index($request);

        self::assertNotEmpty($result);
        self::assertStringContainsString('Bookmarks', $result);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function setUpDatabase(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE accounts (
            id INTEGER PRIMARY KEY,
            username TEXT,
            display_name TEXT,
            avatar_url TEXT
        )');
        $pdo->exec('CREATE TABLE bookmarks (
            id INTEGER PRIMARY KEY,
            account_id INTEGER,
            status_id INTEGER,
            created_at TEXT
        )');
        $pdo->exec('CREATE TABLE statuses (
            id INTEGER PRIMARY KEY,
            account_id INTEGER,
            content TEXT,
            visibility TEXT,
            content_warning TEXT,
            created_at TEXT,
            favourites_count INTEGER DEFAULT 0,
            reblogs_count INTEGER DEFAULT 0,
            replies_count INTEGER DEFAULT 0
        )');

        self::$testPdo = $pdo;

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
