<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Api\v1;

use NanoPub\Core\Config;
use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Models\Account;
use NanoPub\Models\Status;
use NanoPub\Models\Notification;
use NanoPub\Services\NotificationService;
use NanoPub\Exceptions\NotFoundException;

/**
 * Handles notification API endpoints for Mastodon API v1 compatibility.
 */
final class NotificationsController
{
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
    }

    /**
     * Get all notifications.
     * 
     * GET /api/v1/notifications
     */
    public function index(Request $request): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $limit = (int) ($request->query['limit'] ?? 20);
        $limit = min(max($limit, 1), 40);

        $offset = (int) ($request->query['offset'] ?? 0);

        // Get max_id for pagination
        $maxId = isset($request->query['max_id']) ? (int) $request->query['max_id'] : null;
        $sinceId = isset($request->query['since_id']) ? (int) $request->query['since_id'] : null;

        // Get type filter
        $types = null;
        if (isset($request->query['types'])) {
            $types = is_array($request->query['types']) 
                ? $request->query['types'] 
                : [$request->query['types']];
        }

        // Get exclude_types filter
        $excludeTypes = null;
        if (isset($request->query['exclude_types'])) {
            $excludeTypes = is_array($request->query['exclude_types'])
                ? $request->query['exclude_types']
                : [$request->query['exclude_types']];
        }

        // Build query
        $whereClause = 'WHERE n.account_id = ?';
        $params = [$accountId];

        if ($maxId !== null) {
            $whereClause .= ' AND n.id < ?';
            $params[] = $maxId;
        } elseif ($sinceId !== null) {
            $whereClause .= ' AND n.id > ?';
            $params[] = $sinceId;
        }

        if ($types !== null) {
            $placeholders = implode(',', array_fill(0, count($types), '?'));
            $whereClause .= " AND n.type IN ({$placeholders})";
            $params = array_merge($params, $types);
        }

        if ($excludeTypes !== null) {
            $placeholders = implode(',', array_fill(0, count($excludeTypes), '?'));
            $whereClause .= " AND n.type NOT IN ({$placeholders})";
            $params = array_merge($params, $excludeTypes);
        }

        $sql = "SELECT n.*, 
                       a.id as from_id, a.username as from_username, a.display_name as from_display_name, 
                       a.avatar_url as from_avatar_url, a.is_local as from_is_local, a.is_locked as from_is_locked,
                       a.is_bot as from_is_bot, a.followers_count as from_followers_count,
                       a.following_count as from_following_count, a.statuses_count as from_statuses_count,
                       a.bio as from_bio, a.actor_url as from_actor_url, a.header_url as from_header_url,
                       s.id as status_id, s.content as status_content, s.visibility as status_visibility,
                       s.created_at as status_created_at, s.uri as status_uri, s.url as status_url,
                       s.sensitive as status_sensitive, s.content_warning as status_content_warning,
                       s.favourites_count as status_favourites_count, s.reblogs_count as status_reblogs_count,
                       s.replies_count as status_replies_count, s.account_id as status_account_id,
                       sa.username as status_account_username, sa.display_name as status_account_display_name,
                       sa.avatar_url as status_account_avatar_url
                FROM notifications n
                LEFT JOIN accounts a ON n.from_account_id = a.id
                LEFT JOIN statuses s ON n.status_id = s.id
                LEFT JOIN accounts sa ON s.account_id = sa.id
                {$whereClause}
                ORDER BY n.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $notifications = \NanoPub\Core\Database::fetchAll($sql, $params);

        $result = [];
        foreach ($notifications as $notification) {
            $result[] = $this->formatNotification($notification);
        }

        Response::json($result);
    }

    /**
     * Get a single notification.
     * 
     * GET /api/v1/notifications/:id
     */
    public function show(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $notification = Notification::find($id);

        if ($notification === null) {
            throw new NotFoundException('Notification not found');
        }

        // Verify ownership
        if ((int) $notification['account_id'] !== (int) $accountId) {
            throw new NotFoundException('Notification not found');
        }

        // Get full notification data with joins
        $sql = "SELECT n.*, 
                       a.id as from_id, a.username as from_username, a.display_name as from_display_name, 
                       a.avatar_url as from_avatar_url, a.is_local as from_is_local, a.is_locked as from_is_locked,
                       a.is_bot as from_is_bot, a.followers_count as from_followers_count,
                       a.following_count as from_following_count, a.statuses_count as from_statuses_count,
                       a.bio as from_bio, a.actor_url as from_actor_url, a.header_url as from_header_url,
                       s.id as status_id, s.content as status_content, s.visibility as status_visibility,
                       s.created_at as status_created_at, s.uri as status_uri, s.url as status_url,
                       s.sensitive as status_sensitive, s.content_warning as status_content_warning,
                       s.favourites_count as status_favourites_count, s.reblogs_count as status_reblogs_count,
                       s.replies_count as status_replies_count, s.account_id as status_account_id,
                       sa.username as status_account_username, sa.display_name as status_account_display_name,
                       sa.avatar_url as status_account_avatar_url
                FROM notifications n
                LEFT JOIN accounts a ON n.from_account_id = a.id
                LEFT JOIN statuses s ON n.status_id = s.id
                LEFT JOIN accounts sa ON s.account_id = sa.id
                WHERE n.id = ?";

        $notification = \NanoPub\Core\Database::fetchOne($sql, [$id]);

        if ($notification === null) {
            throw new NotFoundException('Notification not found');
        }

        Response::json($this->formatNotification($notification));
    }

    /**
     * Clear all notifications.
     * 
     * POST /api/v1/notifications/clear
     */
    public function clear(Request $request): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $count = $this->notificationService->clearAll((int) $accountId);

        Response::json(['cleared' => $count]);
    }

    /**
     * Dismiss a single notification.
     * 
     * POST /api/v1/notifications/:id/dismiss
     */
    public function dismiss(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $notification = Notification::find($id);

        if ($notification === null) {
            throw new NotFoundException('Notification not found');
        }

        // Verify ownership
        if ((int) $notification['account_id'] !== (int) $accountId) {
            throw new NotFoundException('Notification not found');
        }

        $deleted = $this->notificationService->delete($id, (int) $accountId);

        Response::json(['dismissed' => $deleted]);
    }

    /**
     * Format notification for Mastodon API response.
     * 
     * @param array $notification Notification data with joined fields
     * @return array Formatted notification
     */
    private function formatNotification(array $notification): array
    {
        $baseUrl = Config::get('app.url') ?? '';

        $formatted = [
            'id' => (string) $notification['id'],
            'type' => $notification['type'],
            'created_at' => $notification['created_at'],
            'read' => $notification['read_at'] !== null,
        ];

        // Add account (who triggered the notification)
        if (!empty($notification['from_account_id'])) {
            $formatted['account'] = [
                'id' => (string) $notification['from_id'],
                'username' => $notification['from_username'],
                'acct' => $notification['from_is_local'] 
                    ? $notification['from_username'] 
                    : $notification['from_username'] . '@' . parse_url($notification['from_actor_url'] ?? '', PHP_URL_HOST),
                'display_name' => $notification['from_display_name'] ?? $notification['from_username'],
                'locked' => (bool) ($notification['from_is_locked'] ?? false),
                'bot' => (bool) ($notification['from_is_bot'] ?? false),
                'created_at' => $notification['created_at'] ?? date('c'),
                'note' => $notification['from_bio'] ?? '',
                'url' => "{$baseUrl}/@{$notification['from_username']}",
                'avatar' => $notification['from_avatar_url'] ?? "{$baseUrl}/assets/images/default-avatar.png",
                'avatar_static' => $notification['from_avatar_url'] ?? "{$baseUrl}/assets/images/default-avatar.png",
                'header' => $notification['from_header_url'] ?? "{$baseUrl}/assets/images/default-header.png",
                'header_static' => $notification['from_header_url'] ?? "{$baseUrl}/assets/images/default-header.png",
                'followers_count' => (int) ($notification['from_followers_count'] ?? 0),
                'following_count' => (int) ($notification['from_following_count'] ?? 0),
                'statuses_count' => (int) ($notification['from_statuses_count'] ?? 0),
                'emojis' => [],
                'fields' => [],
            ];
        } else {
            $formatted['account'] = null;
        }

        // Add status if present
        if (!empty($notification['status_id'])) {
            $statusAccount = [
                'id' => (string) $notification['status_account_id'],
                'username' => $notification['status_account_username'],
                'display_name' => $notification['status_account_display_name'] ?? $notification['status_account_username'],
                'avatar' => $notification['status_account_avatar_url'] ?? "{$baseUrl}/assets/images/default-avatar.png",
            ];

            $formatted['status'] = [
                'id' => (string) $notification['status_id'],
                'created_at' => $notification['status_created_at'],
                'in_reply_to_id' => null,
                'in_reply_to_account_id' => null,
                'sensitive' => (bool) ($notification['status_sensitive'] ?? false),
                'spoiler_text' => $notification['status_content_warning'] ?? '',
                'visibility' => $notification['status_visibility'] ?? 'public',
                'language' => 'en',
                'uri' => $notification['status_uri'] ?? "{$baseUrl}/statuses/{$notification['status_id']}",
                'url' => $notification['status_url'] ?? "{$baseUrl}/statuses/{$notification['status_id']}",
                'replies_count' => (int) ($notification['status_replies_count'] ?? 0),
                'reblogs_count' => (int) ($notification['status_reblogs_count'] ?? 0),
                'favourites_count' => (int) ($notification['status_favourites_count'] ?? 0),
                'favourited' => false,
                'reblogged' => false,
                'muted' => false,
                'bookmarked' => false,
                'content' => $notification['status_content'] ?? '',
                'reblog' => null,
                'account' => $statusAccount,
                'media_attachments' => [],
                'mentions' => [],
                'tags' => [],
                'emojis' => [],
                'card' => null,
                'poll' => null,
            ];
        } else {
            $formatted['status'] = null;
        }

        // Add report for moderation notifications (if applicable)
        $formatted['report'] = null;

        return $formatted;
    }
}