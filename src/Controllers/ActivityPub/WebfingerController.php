<?php

declare(strict_types=1);

namespace NanoPub\Controllers\ActivityPub;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Models\Account;
use NanoPub\Core\Config;
use NanoPub\Exceptions\NotFoundException;

/**
 * Handles Webfinger protocol for account discovery.
 */
final class WebfingerController
{
    /**
     * Handle Webfinger request.
     * 
     * Route: /.well-known/webfinger
     * 
     * @param Request $request The HTTP request
     */
    public function show(Request $request): void
    {
        $resource = $request->query['resource'] ?? '';

        // Parse resource: acct:username@domain
        if (!preg_match('/^acct:(.+)@(.+)$/', $resource, $matches)) {
            throw new NotFoundException('Invalid resource format');
        }

        $username = $matches[1];
        $domain = $matches[2];

        // Verify domain matches
        $appUrl = Config::get('app.url');
        $appDomain = parse_url($appUrl, PHP_URL_HOST);
        
        if ($domain !== $appDomain) {
            throw new NotFoundException('Domain mismatch');
        }

        $account = Account::findByUsername($username);
        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        $response = new Response();
        $response->status(200)
            ->header('Content-Type', 'application/jrd+json')
            ->content(json_encode([
                'subject' => $resource,
                'aliases' => [
                    url("/users/{$username}"),
                    url("/@{$username}"),
                ],
                'links' => [
                    [
                        'rel' => 'self',
                        'type' => 'application/activity+json',
                        'href' => url("/users/{$username}"),
                    ],
                    [
                        'rel' => 'http://webfinger.net/rel/profile-page',
                        'type' => 'text/html',
                        'href' => url("/@{$username}"),
                    ],
                    [
                        'rel' => 'http://ostatus.org/schema/1.0/subscribe',
                        'template' => url('/authorize_interaction?uri={uri}'),
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->send();
    }
}
