<?php

declare(strict_types=1);

namespace NanoPub\Controllers\ActivityPub;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Services\ActivityPubService;
use NanoPub\Models\Queue\ActivityQueue;

/**
 * Handles incoming ActivityPub activities from federated servers.
 */
final class InboxController
{
    private ActivityPubService $activityPubService; // @phpstan-ignore property.onlyWritten

    public function __construct()
    {
        $this->activityPubService = new ActivityPubService();
    }

    /**
     * Handle POST to shared inbox.
     * 
     * Route: /inbox
     * 
     * @param Request $request The HTTP request
     */
    public function shared(Request $request): void
    {
        // Verify signature (middleware should have done this)
        // Get activity from request body
        $activity = $request->json();
        $actor = $request->getAttribute('actor');

        // Validate activity has required fields
        if (!isset($activity['type'])) {
            Response::json(['error' => 'Missing activity type'], 400);
            return;
        }

        // Queue for processing
        ActivityQueue::enqueue(
            $activity['type'],
            $activity,
            $actor['actor_url'] ?? $activity['actor'] ?? ''
        );

        Response::json(['status' => 'accepted'], 202);
    }

    /**
     * Handle POST to user inbox.
     * 
     * Route: /users/{username}/inbox
     * 
     * @param Request $request The HTTP request
     * @param string $username The target username
     */
    public function user(Request $request, string $username): void
    {
        // Get activity from request body
        $activity = $request->json();
        $actor = $request->getAttribute('actor');

        // Validate activity has required fields
        if (!isset($activity['type'])) {
            Response::json(['error' => 'Missing activity type'], 400);
            return;
        }

        // Queue for processing with target username context
        ActivityQueue::enqueue(
            $activity['type'],
            array_merge($activity, ['_target_username' => $username]),
            $actor['actor_url'] ?? $activity['actor'] ?? ''
        );

        Response::json(['status' => 'accepted'], 202);
    }
}
