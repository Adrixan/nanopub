<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Api\v1;

use NanoPub\Core\Config;
use NanoPub\Core\Database;
use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Models\Account;
use NanoPub\Models\Status;
use NanoPub\Models\Like;
use NanoPub\Models\Boost;
use NanoPub\Models\Bookmark;
use NanoPub\Exceptions\NotFoundException;

/**
 * Handles timeline API endpoints for Mastodon API v1 compatibility.
 */
final class TimelinesController
{
    /**
     * Get home timeline.
     * 
     * GET /api/v1/timelines/home
     */
    public function home(Request $request): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $limit = (int) ($request->query['limit'] ?? 20);
        $limit = min(max($limit, 1), 40);

        // Get max_id for pagination
        $maxId = isset($request->query['max_id']) ? (int) $request->query['max_id'] : null;
        $sinceId = isset($request->query['since_id']) ? (int) $request->query['since_id'] : null;

        // Build query with pagination
        $offset = 0;
        $whereClause = '';
        $params = [];

        if ($maxId !== null) {
            $whereClause = 'AND s.id < ?';
            $params[] = $maxId;
        } elseif ($sinceId !== null) {
            $whereClause = 'AND s.id > ?';
            $params[] = $sinceId;
        }

        $sql = "SELECT s.*, a.username, a.display_name, a.avatar_url, a.is_local, a.is_locked, a.is_bot,
                       a.followers_count, a.following_count, a.statuses_count, a.bio, a.actor_url, a.header_url
                FROM statuses s 
                INNER JOIN accounts a ON s.account_id = a.id 
                LEFT JOIN follows f ON f.target_account_id = s.account_id AND f.account_id = ?
                WHERE (f.id IS NOT NULL OR s.account_id = ?)
                AND s.visibility IN ('public', 'unlisted', 'private')
                AND s.reblog_of_id IS NULL 
                AND a.is_suspended = 0 
                {$whereClause}
                ORDER BY s.created_at DESC 
                LIMIT ?";

        $params = array_merge([$accountId, $accountId], $params, [$limit]);

        $statuses = Database::fetchAll($sql, $params);

        $result = [];
        foreach ($statuses as $status) {
            $result[] = $this->formatStatus($status, (int) $accountId);
        }

        // Build Link header for pagination
        $this->sendWithPagination($result, $request, 'home');
    }

    /**
     * Get public timeline.
     * 
     * GET /api/v1/timelines/public
     */
    public function public(Request $request): void
    {
        $accountId = $request->getAttribute('account_id');

        $limit = (int) ($request->query['limit'] ?? 20);
        $limit = min(max($limit, 1), 40);

        // Get max_id for pagination
        $maxId = isset($request->query['max_id']) ? (int) $request->query['max_id'] : null;
        $sinceId = isset($request->query['since_id']) ? (int) $request->query['since_id'] : null;

        // Check for local only
        $local = isset($request->query['local']) && $request->query['local'] === 'true';

        // Build query with pagination
        $whereClause = '';
        $params = [];

        if ($maxId !== null) {
            $whereClause .= ' AND s.id < ?';
            $params[] = $maxId;
        } elseif ($sinceId !== null) {
            $whereClause .= ' AND s.id > ?';
            $params[] = $sinceId;
        }

        if ($local) {
            $whereClause .= ' AND s.local = 1';
        }

        $sql = "SELECT s.*, a.username, a.display_name, a.avatar_url, a.is_local, a.is_locked, a.is_bot,
                       a.followers_count, a.following_count, a.statuses_count, a.bio, a.actor_url, a.header_url
                FROM statuses s 
                INNER JOIN accounts a ON s.account_id = a.id 
                WHERE s.visibility = 'public' 
                AND s.reblog_of_id IS NULL 
                AND a.is_suspended = 0 
                {$whereClause}
                ORDER BY s.created_at DESC 
                LIMIT ?";

        $params[] = $limit;

        $statuses = Database::fetchAll($sql, $params);

        $result = [];
        foreach ($statuses as $status) {
            $result[] = $this->formatStatus($status, $accountId);
        }

        // Build Link header for pagination
        $this->sendWithPagination($result, $request, 'public');
    }

    /**
     * Get hashtag timeline.
     * 
     * GET /api/v1/timelines/tag/:hashtag
     */
    public function hashtag(Request $request, string $hashtag): void
    {
        $accountId = $request->getAttribute('account_id');

        $limit = (int) ($request->query['limit'] ?? 20);
        $limit = min(max($limit, 1), 40);

        // Get max_id for pagination
        $maxId = isset($request->query['max_id']) ? (int) $request->query['max_id'] : null;
        $sinceId = isset($request->query['since_id']) ? (int) $request->query['since_id'] : null;

        // Check for local only
        $local = isset($request->query['local']) && $request->query['local'] === 'true';

        // Normalize hashtag (remove # if present)
        $hashtag = ltrim($hashtag, '#');

        // Build query with pagination
        $whereClause = '';
        $params = [$hashtag];

        if ($maxId !== null) {
            $whereClause .= ' AND s.id < ?';
            $params[] = $maxId;
        } elseif ($sinceId !== null) {
            $whereClause .= ' AND s.id > ?';
            $params[] = $sinceId;
        }

        if ($local) {
            $whereClause .= ' AND s.local = 1';
        }

        $sql = "SELECT s.*, a.username, a.display_name, a.avatar_url, a.is_local, a.is_locked, a.is_bot,
                       a.followers_count, a.following_count, a.statuses_count, a.bio, a.actor_url, a.header_url
                FROM statuses s 
                INNER JOIN accounts a ON s.account_id = a.id 
                INNER JOIN tags t ON t.status_id = s.id
                WHERE t.name = ?
                AND s.visibility = 'public' 
                AND s.reblog_of_id IS NULL 
                AND a.is_suspended = 0 
                {$whereClause}
                ORDER BY s.created_at DESC 
                LIMIT ?";

        $params[] = $limit;

        $statuses = Database::fetchAll($sql, $params);

        $result = [];
        foreach ($statuses as $status) {
            $result[] = $this->formatStatus($status, $accountId);
        }

        // Build Link header for pagination
        $this->sendWithPagination($result, $request, 'tag/' . $hashtag);
    }

    /**
     * Get list timeline.
     * 
     * GET /api/v1/timelines/list/:id
     */
    public function list(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        // Verify list ownership
        $list = Database::fetchOne(
            'SELECT * FROM lists WHERE id = ? AND account_id = ?',
            [$id, $accountId]
        );

        if ($list === null) {
            throw new NotFoundException('List not found');
        }

        $limit = (int) ($request->query['limit'] ?? 20);
        $limit = min(max($limit, 1), 40);

        // Get max_id for pagination
        $maxId = isset($request->query['max_id']) ? (int) $request->query['max_id'] : null;
        $sinceId = isset($request->query['since_id']) ? (int) $request->query['since_id'] : null;

        // Build query with pagination
        $whereClause = '';
        $params = [$id, $accountId];

        if ($maxId !== null) {
            $whereClause .= ' AND s.id < ?';
            $params[] = $maxId;
        } elseif ($sinceId !== null) {
            $whereClause .= ' AND s.id > ?';
            $params[] = $sinceId;
        }

        $sql = "SELECT s.*, a.username, a.display_name, a.avatar_url, a.is_local, a.is_locked, a.is_bot,
                       a.followers_count, a.following_count, a.statuses_count, a.bio, a.actor_url, a.header_url
                FROM statuses s 
                INNER JOIN accounts a ON s.account_id = a.id 
                INNER JOIN list_accounts la ON la.account_id = s.account_id
                INNER JOIN lists l ON l.id = la.list_id
                WHERE l.id = ?
                AND l.account_id = ?
                AND s.visibility IN ('public', 'unlisted', 'private')
                AND s.reblog_of_id IS NULL 
                AND a.is_suspended = 0 
                {$whereClause}
                ORDER BY s.created_at DESC 
                LIMIT ?";

        $params[] = $limit;

        $statuses = Database::fetchAll($sql, $params);

        $result = [];
        foreach ($statuses as $status) {
            $result[] = $this->formatStatus($status, (int) $accountId);
        }

        // Build Link header for pagination
        $this->sendWithPagination($result, $request, 'list/' . $id);
    }

    /**
     * Send response with Link header for pagination.
     * 
     * @param array $data Response data
     * @param Request $request Request object
     * @param string $endpoint Endpoint name for Link header
     */
    private function sendWithPagination(array $data, Request $request, string $endpoint): void
    {
        $baseUrl = Config::get('app.url') ?? '';
        $apiUrl = "{$baseUrl}/api/v1/timelines/{$endpoint}";

        $linkHeader = '';

        if (!empty($data)) {
            $lastId = (int) $data[count($data) - 1]['id'];
            $firstId = (int) $data[0]['id'];

            $queryParams = $request->query;

            // Next link (older)
            $nextParams = array_merge($queryParams, ['max_id' => $lastId]);
            $nextLink = $apiUrl . '?' . http_build_query($nextParams);

            // Prev link (newer)
            $prevParams = array_merge($queryParams, ['since_id' => $firstId]);
            $prevLink = $apiUrl . '?' . http_build_query($prevParams);

            $linkHeader = "<{$nextLink}>; rel=\"next\", <{$prevLink}>; rel=\"prev\"";
        }

        $response = new \NanoPub\Core\Response();
        $response->status(200);
        $response->header('Content-Type', 'application/json; charset=utf-8');

        if (!empty($linkHeader)) {
            $response->header('Link', $linkHeader);
        }

        $response->content(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $response->send();
    }

    /**
     * Format status for Mastodon API response.
     * 
     * @param array $status Status data
     * @param int|null $accountId Current account ID for interaction state
     * @return array Formatted status
     */
    private function formatStatus(array $status, ?int $accountId = null): array
    {
        $baseUrl = Config::get('app.url') ?? '';

        // Build account from joined data
        $account = [
            'id' => $status['account_id'],
            'username' => $status['username'],
            'display_name' => $status['display_name'],
            'avatar_url' => $status['avatar_url'],
            'is_local' => $status['is_local'],
            'is_locked' => $status['is_locked'],
            'is_bot' => $status['is_bot'],
            'followers_count' => $status['followers_count'],
            'following_count' => $status['following_count'],
            'statuses_count' => $status['statuses_count'],
            'bio' => $status['bio'],
            'actor_url' => $status['actor_url'],
            'header_url' => $status['header_url'],
            'created_at' => $status['created_at'] ?? date('c'),
        ];

        // Get media attachments
        $media = Status::getMedia((int) $status['id']);
        $mediaAttachments = [];
        foreach ($media as $m) {
            $mediaAttachments[] = [
                'id' => (string) $m['id'],
                'type' => $m['type'] ?? 'image',
                'url' => $m['url'],
                'preview_url' => $m['preview_url'] ?? $m['url'],
                'remote_url' => $m['remote_url'] ?? null,
                'text_url' => $m['url'],
                'meta' => $m['width'] && $m['height'] ? [
                    'original' => [
                        'width' => (int) $m['width'],
                        'height' => (int) $m['height'],
                        'size' => "{$m['width']}x{$m['height']}",
                        'aspect' => (float) $m['width'] / max(1, (int) $m['height']),
                    ],
                ] : null,
                'description' => $m['description'] ?? '',
            ];
        }

        // Check interaction state
        $favourited = false;
        $reblogged = false;
        $bookmarked = false;
        $muted = false;

        if ($accountId !== null) {
            $favourited = Like::isLiked($accountId, (int) $status['id']);
            $reblogged = Boost::isBoosted($accountId, (int) $status['id']);
            $bookmarked = Bookmark::isBookmarked($accountId, (int) $status['id']);

            $muteCheck = Database::fetchOne(
                'SELECT 1 FROM mutes WHERE account_id = ? AND target_account_id = ?',
                [$accountId, (int) $status['account_id']]
            );
            $muted = $muteCheck !== null;
        }

        return [
            'id' => (string) $status['id'],
            'created_at' => $status['created_at'],
            'in_reply_to_id' => $status['in_reply_to_id'] !== null ? (string) $status['in_reply_to_id'] : null,
            'in_reply_to_account_id' => $status['in_reply_to_account_id'] !== null ? (string) $status['in_reply_to_account_id'] : null,
            'sensitive' => (bool) ($status['sensitive'] ?? false),
            'spoiler_text' => $status['content_warning'] ?? '',
            'visibility' => $status['visibility'] ?? 'public',
            'language' => $status['language'] ?? 'en',
            'uri' => $status['uri'] ?? "{$baseUrl}/statuses/{$status['id']}",
            'url' => $status['url'] ?? "{$baseUrl}/statuses/{$status['id']}",
            'replies_count' => (int) ($status['replies_count'] ?? 0),
            'reblogs_count' => (int) ($status['reblogs_count'] ?? 0),
            'favourites_count' => (int) ($status['favourites_count'] ?? 0),
            'favourited' => $favourited,
            'reblogged' => $reblogged,
            'muted' => $muted,
            'bookmarked' => $bookmarked,
            'pinned' => false,
            'content' => $status['content'] ?? '',
            'reblog' => null,
            'account' => $this->formatAccount($account),
            'media_attachments' => $mediaAttachments,
            'mentions' => [],
            'tags' => [],
            'emojis' => [],
            'card' => null,
            'poll' => null,
        ];
    }

    /**
     * Format account for Mastodon API response.
     * 
     * @param array $account Account data
     * @return array Formatted account
     */
    private function formatAccount(array $account): array
    {
        $baseUrl = Config::get('app.url') ?? '';
        $username = $account['username'] ?? '';

        return [
            'id' => (string) $account['id'],
            'username' => $username,
            'acct' => $account['is_local'] ? $username : $username . '@' . parse_url($account['actor_url'] ?? '', PHP_URL_HOST),
            'display_name' => $account['display_name'] ?? $username,
            'locked' => (bool) ($account['is_locked'] ?? false),
            'bot' => (bool) ($account['is_bot'] ?? false),
            'created_at' => $account['created_at'] ?? date('c'),
            'note' => $account['bio'] ?? '',
            'url' => "{$baseUrl}/@{$username}",
            'avatar' => $account['avatar_url'] ?? "{$baseUrl}/assets/images/default-avatar.png",
            'avatar_static' => $account['avatar_url'] ?? "{$baseUrl}/assets/images/default-avatar.png",
            'header' => $account['header_url'] ?? "{$baseUrl}/assets/images/default-header.png",
            'header_static' => $account['header_url'] ?? "{$baseUrl}/assets/images/default-header.png",
            'followers_count' => (int) ($account['followers_count'] ?? 0),
            'following_count' => (int) ($account['following_count'] ?? 0),
            'statuses_count' => (int) ($account['statuses_count'] ?? 0),
            'last_status_at' => $account['last_activity_at'] ?? null,
            'emojis' => [],
            'fields' => [],
        ];
    }
}