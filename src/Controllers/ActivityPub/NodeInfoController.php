<?php

declare(strict_types=1);

namespace NanoPub\Controllers\ActivityPub;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\Database;

/**
 * Returns NodeInfo discovery and schema documents.
 */
final class NodeInfoController
{
    /**
     * Well-known nodeinfo discovery endpoint.
     *
     * Route: /.well-known/nodeinfo
     *
     * @param Request $request The HTTP request
     */
    public function index(Request $request): void
    {
        $response = new Response();
        $response->status(200)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->content(json_encode([
                'links' => [
                    [
                        'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
                        'href' => url('/nodeinfo/2.0'),
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->send();
    }

    /**
     * NodeInfo 2.0 schema endpoint.
     *
     * Route: /nodeinfo/2.0
     *
     * @param Request $request The HTTP request
     */
    public function show(Request $request): void
    {
        $userCount = 0;
        $statusCount = 0;

        try {
            $userRow = Database::fetchOne(
                'SELECT COUNT(*) as count FROM accounts WHERE is_local = 1'
            );
            $userCount = (int) ($userRow['count'] ?? 0);

            $statusRow = Database::fetchOne(
                'SELECT COUNT(*) as count FROM statuses WHERE local = 1'
            );
            $statusCount = (int) ($statusRow['count'] ?? 0);
        } catch (\Throwable $e) {
            // Fallback to 0s on database error
        }

        $response = new Response();
        $response->status(200)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->content(json_encode([
                'version' => '2.0',
                'software' => [
                    'name' => 'nanopub',
                    'version' => '1.0.0',
                ],
                'protocols' => ['activitypub'],
                'usage' => [
                    'users' => [
                        'total' => $userCount,
                        'activeMonth' => $userCount,
                        'activeHalfyear' => $userCount,
                    ],
                    'localPosts' => $statusCount,
                ],
                'openRegistrations' => true,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->send();
    }
}
