<?php

declare(strict_types=1);

namespace NanoPub\Tests\Unit\Controllers\Web;

use NanoPub\Controllers\Web\StatusController;
use NanoPub\Core\Database;
use NanoPub\Core\Request;
use NanoPub\Core\Session;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test cases for StatusController.
 */
final class StatusControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->setUpSession();

        // Reset response code to a known state between tests
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        $this->tearDownSession();
        $this->tearDownDatabase();
    }

    public function testCreateRedirectsUnauthenticatedUser(): void
    {
        unset($_SESSION['account_id']);

        $request = new Request('POST', '/statuses');
        $controller = new StatusController();
        $controller->create($request);

        self::assertSame(302, http_response_code());
    }

    public function testCreateRedirectsOnEmptyContent(): void
    {
        $_SESSION['account_id'] = 1;

        $body = fopen('php://memory', 'r+');
        self::assertNotFalse($body);
        fwrite($body, json_encode(['status' => '']));
        rewind($body);

        $request = new Request(
            'POST',
            '/statuses',
            [],
            ['content-type' => 'application/json'],
            $body,
        );

        $controller = new StatusController();
        $controller->create($request);

        fclose($body);

        // The controller sets a 302 redirect to /?error=empty
        self::assertSame(302, http_response_code());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function setUpDatabase(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // StatusController doesn't touch the DB for the two tested paths
        // but the constructor instantiates services that may query it later.
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
