<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Api\v1;

use NanoPub\Core\Config;
use NanoPub\Core\Database;
use NanoPub\Core\Request;
use NanoPub\Core\Response;

/**
 * Handles instance information endpoints for Mastodon API v1 compatibility.
 */
final class InstanceController
{
    /**
     * Get instance information.
     *
     * GET /api/v1/instance
     */
    public function show(Request $request): void
    {
        $stats = [
            'user_count' => 0,
            'status_count' => 0,
            'domain_count' => 0,
        ];

        try {
            $row = Database::fetchOne(
                'SELECT COUNT(*) AS cnt FROM accounts WHERE is_local = 1',
                []
            );
            $stats['user_count'] = (int) ($row['cnt'] ?? 0);

            $row = Database::fetchOne(
                'SELECT COUNT(*) AS cnt FROM statuses WHERE local = 1',
                []
            );
            $stats['status_count'] = (int) ($row['cnt'] ?? 0);

            $row = Database::fetchOne(
                'SELECT COUNT(DISTINCT domain) AS cnt FROM accounts WHERE is_local = 0',
                []
            );
            $stats['domain_count'] = (int) ($row['cnt'] ?? 0);
        } catch (\Throwable $e) {
            // Fall back to zero stats if database is unavailable
        }

        Response::json([
            'uri' => Config::get('app.url', ''),
            'title' => Config::get('app.name', 'NanoPub'),
            'description' => '',
            'short_description' => '',
            'email' => '',
            'version' => '4.0.0 (compatible; NanoPub 1.0)',
            'urls' => [
                'streaming_api' => '',
            ],
            'stats' => $stats,
            'languages' => ['en'],
            'registrations' => true,
            'approval_required' => false,
            'configuration' => [
                'statuses' => [
                    'max_characters' => 5000,
                    'max_media_attachments' => 4,
                ],
                'media_attachments' => [
                    'supported_mime_types' => [
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp',
                    ],
                    'image_size_limit' => 8388608,
                ],
            ],
        ]);
    }

    /**
     * Get list of known peers (federated domains).
     *
     * GET /api/v1/instance/peers
     */
    public function peers(Request $request): void
    {
        $domains = [];

        try {
            $rows = Database::fetchAll(
                'SELECT DISTINCT domain FROM accounts WHERE is_local = 0 AND is_suspended = 0',
                []
            );

            foreach ($rows as $row) {
                $domains[] = $row['domain'];
            }
        } catch (\Throwable $e) {
            // Fall back to empty array if database is unavailable
        }

        Response::json($domains);
    }

    /**
     * Get weekly activity statistics.
     *
     * GET /api/v1/instance/activity
     */
    public function activity(Request $request): void
    {
        Response::json([]);
    }
}
