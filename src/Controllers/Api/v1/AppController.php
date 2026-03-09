<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Api\v1;

use NanoPub\Core\Database;
use NanoPub\Core\Request;
use NanoPub\Core\Response;

/**
 * Handles OAuth application registration for Mastodon API v1 compatibility.
 */
final class AppController
{
    /**
     * Register a new OAuth application.
     *
     * POST /api/v1/apps
     */
    public function create(Request $request): void
    {
        $data = $request->json();

        $clientName = trim($data['client_name'] ?? '');
        $redirectUris = trim($data['redirect_uris'] ?? 'urn:ietf:wg:oauth:2.0:oob');
        $scopes = trim($data['scopes'] ?? 'read');
        $website = trim($data['website'] ?? '');

        $clientId = generate_token(32);
        $clientSecret = generate_token(32);
        $appId = (string) time();

        try {
            Database::execute(
                'INSERT INTO oauth_applications (client_id, client_secret, name, redirect_uri, scopes, website, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'))',
                [$clientId, $clientSecret, $clientName, $redirectUris, $scopes, $website]
            );
            $appId = Database::lastInsertId();
        } catch (\Throwable $e) {
            // Table may not exist yet; continue with generated values
        }

        Response::json([
            'id' => $appId,
            'name' => $clientName,
            'website' => $website,
            'redirect_uri' => $redirectUris,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'vapid_key' => '',
        ]);
    }
}
