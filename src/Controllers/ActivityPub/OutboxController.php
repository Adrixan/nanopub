<?php

declare(strict_types=1);

namespace NanoPub\Controllers\ActivityPub;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Models\Account;
use NanoPub\Models\Status;
use NanoPub\Services\ActivityPubService;
use NanoPub\Exceptions\NotFoundException;

/**
 * Handles outgoing ActivityPub activities.
 */
final class OutboxController
{
    private ActivityPubService $activityPubService;

    public function __construct()
    {
        $this->activityPubService = new ActivityPubService();
    }

    /**
     * Get user's outbox (GET).
     * 
     * Route: /users/{username}/outbox
     * 
     * @param Request $request The HTTP request
     * @param string $username The username
     */
    public function index(Request $request, string $username): void
    {
        $account = Account::findByUsername($username);
        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        // Return OrderedCollection with first page
        $page = (int) ($request->query['page'] ?? 0);
        $perPage = 20;

        if ($page > 0) {
            // Return items
            $statuses = Status::getAccountStatuses(
                (int) $account['id'],
                $perPage,
                ($page - 1) * $perPage
            );

            $items = [];
            foreach ($statuses as $status) {
                $items[] = $this->activityPubService->buildActivity(
                    'Create',
                    $status,
                    (int) $account['id']
                );
            }

            $response = new Response();
            $response->status(200)
                ->header('Content-Type', 'application/activity+json')
                ->content(json_encode([
                    '@context' => 'https://www.w3.org/ns/activitystreams',
                    'type' => 'OrderedCollectionPage',
                    'id' => url("/users/{$username}/outbox?page={$page}"),
                    'partOf' => url("/users/{$username}/outbox"),
                    'orderedItems' => $items,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->send();
        } else {
            // Return collection
            $response = new Response();
            $response->status(200)
                ->header('Content-Type', 'application/activity+json')
                ->content(json_encode([
                    '@context' => 'https://www.w3.org/ns/activitystreams',
                    'type' => 'OrderedCollection',
                    'id' => url("/users/{$username}/outbox"),
                    'totalItems' => (int) ($account['statuses_count'] ?? 0),
                    'first' => url("/users/{$username}/outbox?page=1"),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->send();
        }
    }

    /**
     * Post to outbox (POST) - for authenticated users.
     * 
     * Route: /users/{username}/outbox
     * 
     * @param Request $request The HTTP request
     * @param string $username The username
     */
    public function post(Request $request, string $username): void
    {
        // Verify authentication
        $authenticatedUser = $request->getAttribute('user');
        
        if ($authenticatedUser === null) {
            Response::json(['error' => 'Unauthorized'], 401);
            return;
        }

        $account = Account::findByUsername($username);
        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        // Verify the authenticated user owns this outbox
        if ((int) $account['id'] !== (int) $authenticatedUser['id']) {
            Response::json(['error' => 'Forbidden'], 403);
            return;
        }

        // Get activity from request
        $activity = $request->json();

        // Validate activity
        if (!isset($activity['type'])) {
            Response::json(['error' => 'Missing activity type'], 400);
            return;
        }

        // Build the activity with proper context
        $result = $this->activityPubService->buildActivity(
            $activity['type'],
            $activity['object'] ?? $activity,
            (int) $account['id']
        );

        if (empty($result)) {
            Response::json(['error' => 'Failed to build activity'], 400);
            return;
        }

        $response = new Response();
        $response->status(201)
            ->header('Content-Type', 'application/activity+json')
            ->content(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->send();
    }
}
