<?php

declare(strict_types=1);

namespace NanoPub\Controllers\ActivityPub;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\Database;
use NanoPub\Models\Account;
use NanoPub\Exceptions\NotFoundException;

/**
 * Returns an ActivityPub OrderedCollection of an account's followers.
 */
final class FollowersController
{
    /**
     * Get user's followers collection.
     *
     * Route: /users/{username}/followers
     *
     * @param Request $request  The HTTP request
     * @param string  $username The username
     */
    public function index(Request $request, string $username): void
    {
        $account = Account::findByUsername($username);
        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        $baseUrl = url("/users/{$username}/followers");
        $followersCount = (int) ($account['followers_count'] ?? 0);
        $page = (int) ($request->query['page'] ?? 0);

        if ($page > 0) {
            $perPage = 40;
            $offset = ($page - 1) * $perPage;

            $rows = Database::fetchAll(
                'SELECT a.actor_url FROM follows f '
                . 'JOIN accounts a ON f.account_id = a.id '
                . 'WHERE f.target_account_id = ? '
                . 'ORDER BY f.created_at DESC LIMIT ? OFFSET ?',
                [(int) $account['id'], $perPage, $offset]
            );

            $items = array_map(
                fn(array $row): string => $row['actor_url'],
                $rows
            );

            $collection = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'type' => 'OrderedCollectionPage',
                'id' => $baseUrl . "?page={$page}",
                'partOf' => $baseUrl,
                'orderedItems' => $items,
            ];

            if (count($items) === $perPage) {
                $collection['next'] = $baseUrl . '?page=' . ($page + 1);
            }

            $response = new Response();
            $response->status(200)
                ->header('Content-Type', 'application/activity+json')
                ->content(json_encode($collection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->send();
        } else {
            $response = new Response();
            $response->status(200)
                ->header('Content-Type', 'application/activity+json')
                ->content(json_encode([
                    '@context' => 'https://www.w3.org/ns/activitystreams',
                    'type' => 'OrderedCollection',
                    'id' => $baseUrl,
                    'totalItems' => $followersCount,
                    'first' => $baseUrl . '?page=1',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->send();
        }
    }
}
