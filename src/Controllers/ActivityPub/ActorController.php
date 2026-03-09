<?php

declare(strict_types=1);

namespace NanoPub\Controllers\ActivityPub;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Models\Account;
use NanoPub\Services\ActivityPubService;
use NanoPub\Exceptions\NotFoundException;

/**
 * Returns ActivityPub actor objects.
 */
final class ActorController
{
    private ActivityPubService $activityPubService;

    public function __construct()
    {
        $this->activityPubService = new ActivityPubService();
    }

    /**
     * Get actor by username.
     * 
     * Route: /users/{username}
     * 
     * @param Request $request The HTTP request
     * @param string $username The username
     */
    public function show(Request $request, string $username): void
    {
        $account = Account::findByUsername($username);
        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        $actor = $this->activityPubService->getActor($account);

        $response = new Response();
        $response->status(200)
            ->header('Content-Type', 'application/activity+json')
            ->content(json_encode($actor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->send();
    }
}
