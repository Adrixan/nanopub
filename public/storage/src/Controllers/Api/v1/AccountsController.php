<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Api\v1;

use NanoPub\Core\Config;
use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Models\Account;
use NanoPub\Models\Follow;
use NanoPub\Models\Status;
use NanoPub\Services\ActivityPubService;
use NanoPub\Services\NotificationService;
use NanoPub\Exceptions\NotFoundException;

/**
 * Handles account-related API endpoints for Mastodon API v1 compatibility.
 */
final class AccountsController
{
    private ActivityPubService $activityPubService;

    private NotificationService $notificationService;

    public function __construct()
    {
        $this->activityPubService = new ActivityPubService();
        $this->notificationService = new NotificationService();
    }

    /**
     * Get account by ID.
     * 
     * GET /api/v1/accounts/:id
     */
    public function show(Request $request, int $id): void
    {
        $account = Account::find($id);

        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        Response::json($this->formatAccount($account));
    }

    /**
     * Verify credentials (get current authenticated account).
     * 
     * GET /api/v1/accounts/verify_credentials
     */
    public function verifyCredentials(Request $request): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $account = Account::find((int) $accountId);

        if ($account === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        Response::json($this->formatAccount($account, true));
    }

    /**
     * Update credentials (update current account).
     * 
     * PATCH /api/v1/accounts/update_credentials
     */
    public function updateCredentials(Request $request): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $data = $request->json();
        $updateData = [];

        // Handle display_name
        if (isset($data['display_name'])) {
            $updateData['display_name'] = trim($data['display_name']);
        }

        // Handle bio/note
        if (isset($data['note'])) {
            $updateData['bio'] = trim($data['note']);
        }

        // Handle locked status
        if (isset($data['locked'])) {
            $updateData['is_locked'] = (bool) $data['locked'] ? 1 : 0;
        }

        // Handle bot flag
        if (isset($data['bot'])) {
            $updateData['is_bot'] = (bool) $data['bot'] ? 1 : 0;
        }

        // Handle avatar (would need file upload handling)
        // Handle header (would need file upload handling)

        if (!empty($updateData)) {
            Account::update((int) $accountId, $updateData);
        }

        $account = Account::find((int) $accountId);
        Response::json($this->formatAccount($account, true));
    }

    /**
     * Get statuses for an account.
     * 
     * GET /api/v1/accounts/:id/statuses
     */
    public function statuses(Request $request, int $id): void
    {
        $account = Account::find($id);

        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        $limit = (int) ($request->query['limit'] ?? 20);
        $limit = min(max($limit, 1), 40);
        $offset = (int) ($request->query['offset'] ?? 0);

        // Only media parameter
        $onlyMedia = isset($request->query['only_media']) && $request->query['only_media'] === 'true';
        $excludeReplies = isset($request->query['exclude_replies']) && $request->query['exclude_replies'] === 'true';

        $statuses = Status::getAccountStatuses($id, $limit, $offset);

        $result = [];
        foreach ($statuses as $status) {
            // Filter by only_media if requested
            if ($onlyMedia) {
                $media = Status::getMedia((int) $status['id']);
                if (empty($media)) {
                    continue;
                }
            }

            // Filter replies if requested
            if ($excludeReplies && $status['in_reply_to_id'] !== null) {
                continue;
            }

            $result[] = $this->formatStatus($status);
        }

        Response::json($result);
    }

    /**
     * Get followers for an account.
     * 
     * GET /api/v1/accounts/:id/followers
     */
    public function followers(Request $request, int $id): void
    {
        $account = Account::find($id);

        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        $limit = (int) ($request->query['limit'] ?? 40);
        $limit = min(max($limit, 1), 80);

        $followers = Follow::getFollowers($id, $limit);

        $result = [];
        foreach ($followers as $follow) {
            $followerAccount = Account::find((int) $follow['account_id']);
            if ($followerAccount !== null) {
                $result[] = $this->formatAccount($followerAccount);
            }
        }

        Response::json($result);
    }

    /**
     * Get following for an account.
     * 
     * GET /api/v1/accounts/:id/following
     */
    public function following(Request $request, int $id): void
    {
        $account = Account::find($id);

        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        $limit = (int) ($request->query['limit'] ?? 40);
        $limit = min(max($limit, 1), 80);

        $following = Follow::getFollowing($id, $limit);

        $result = [];
        foreach ($following as $follow) {
            $followingAccount = Account::find((int) $follow['target_account_id']);
            if ($followingAccount !== null) {
                $result[] = $this->formatAccount($followingAccount);
            }
        }

        Response::json($result);
    }

    /**
     * Follow an account.
     * 
     * POST /api/v1/accounts/:id/follow
     */
    public function follow(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $targetAccount = Account::find($id);

        if ($targetAccount === null) {
            throw new NotFoundException('Account not found');
        }

        // Check if already following
        if (Follow::isFollowing((int) $accountId, $id)) {
            Response::json($this->formatRelationship((int) $accountId, $id));
            return;
        }

        // Check if target is locked
        if ($targetAccount['is_locked']) {
            // Create follow request instead
            \NanoPub\Models\FollowRequest::create((int) $accountId, $id);
            
            Response::json($this->formatRelationship((int) $accountId, $id, true));
            return;
        }

        // Create follow
        Follow::create((int) $accountId, $id);

        // Notify target account
        $this->notificationService->notifyFollow($id, (int) $accountId);

        // Send ActivityPub Follow activity for remote accounts
        if (!$targetAccount['is_local']) {
            $actor = Account::find((int) $accountId);
            $activity = $this->activityPubService->buildActivity(
                'Follow',
                $targetAccount['actor_url'],
                (int) $accountId
            );
            $this->activityPubService->sendActivity(
                $activity,
                (int) $accountId,
                $targetAccount['inbox_url']
            );
        }

        Response::json($this->formatRelationship((int) $accountId, $id));
    }

    /**
     * Unfollow an account.
     * 
     * POST /api/v1/accounts/:id/unfollow
     */
    public function unfollow(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $targetAccount = Account::find($id);

        if ($targetAccount === null) {
            throw new NotFoundException('Account not found');
        }

        // Delete follow relationship
        Follow::delete((int) $accountId, $id);

        // Send ActivityPub Undo Follow activity for remote accounts
        if (!$targetAccount['is_local']) {
            $followActivity = $this->activityPubService->buildActivity(
                'Follow',
                $targetAccount['actor_url'],
                (int) $accountId
            );
            $undoActivity = $this->activityPubService->buildActivity(
                'Undo',
                $followActivity,
                (int) $accountId
            );
            $this->activityPubService->sendActivity(
                $undoActivity,
                (int) $accountId,
                $targetAccount['inbox_url']
            );
        }

        Response::json($this->formatRelationship((int) $accountId, $id));
    }

    /**
     * Block an account.
     * 
     * POST /api/v1/accounts/:id/block
     */
    public function block(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $targetAccount = Account::find($id);

        if ($targetAccount === null) {
            throw new NotFoundException('Account not found');
        }

        // Create block relationship
        \NanoPub\Core\Database::execute(
            'INSERT INTO blocks (account_id, target_account_id, created_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE created_at = NOW()',
            [(int) $accountId, $id]
        );

        // Remove follow relationship if exists
        Follow::delete((int) $accountId, $id);
        Follow::delete($id, (int) $accountId);

        Response::json($this->formatRelationship((int) $accountId, $id));
    }

    /**
     * Unblock an account.
     * 
     * POST /api/v1/accounts/:id/unblock
     */
    public function unblock(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $targetAccount = Account::find($id);

        if ($targetAccount === null) {
            throw new NotFoundException('Account not found');
        }

        // Delete block relationship
        \NanoPub\Core\Database::execute(
            'DELETE FROM blocks WHERE account_id = ? AND target_account_id = ?',
            [(int) $accountId, $id]
        );

        Response::json($this->formatRelationship((int) $accountId, $id));
    }

    /**
     * Mute an account.
     * 
     * POST /api/v1/accounts/:id/mute
     */
    public function mute(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $targetAccount = Account::find($id);

        if ($targetAccount === null) {
            throw new NotFoundException('Account not found');
        }

        $data = $request->json();
        $notifications = isset($data['notifications']) ? (bool) $data['notifications'] : true;

        // Create mute relationship
        \NanoPub\Core\Database::execute(
            'INSERT INTO mutes (account_id, target_account_id, hide_notifications, created_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE hide_notifications = ?, created_at = NOW()',
            [(int) $accountId, $id, $notifications ? 1 : 0, $notifications ? 1 : 0]
        );

        Response::json($this->formatRelationship((int) $accountId, $id));
    }

    /**
     * Unmute an account.
     * 
     * POST /api/v1/accounts/:id/unmute
     */
    public function unmute(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $targetAccount = Account::find($id);

        if ($targetAccount === null) {
            throw new NotFoundException('Account not found');
        }

        // Delete mute relationship
        \NanoPub\Core\Database::execute(
            'DELETE FROM mutes WHERE account_id = ? AND target_account_id = ?',
            [(int) $accountId, $id]
        );

        Response::json($this->formatRelationship((int) $accountId, $id));
    }

    /**
     * Get relationships for current account.
     * 
     * GET /api/v1/accounts/relationships
     */
    public function relationships(Request $request): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $ids = $request->query['id'] ?? [];

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $result = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $result[] = $this->formatRelationship((int) $accountId, $id);
        }

        Response::json($result);
    }

    /**
     * Search for accounts.
     * 
     * GET /api/v1/accounts/search
     */
    public function search(Request $request): void
    {
        $query = trim($request->query['q'] ?? '');

        if (empty($query)) {
            Response::json([]);
            return;
        }

        $limit = (int) ($request->query['limit'] ?? 40);
        $limit = min(max($limit, 1), 80);

        $offset = (int) ($request->query['offset'] ?? 0);

        $accounts = Account::search($query, $limit);

        $result = [];
        foreach ($accounts as $account) {
            $result[] = $this->formatAccount($account);
        }

        Response::json($result);
    }

    /**
     * Format account for Mastodon API response.
     * 
     * @param array $account Account data
     * @param bool $includeSource Include source data for credentials
     * @return array Formatted account
     */
    private function formatAccount(array $account, bool $includeSource = false): array
    {
        $baseUrl = Config::get('app.url') ?? '';
        $username = $account['username'] ?? '';

        $formatted = [
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

        if ($includeSource) {
            $formatted['source'] = [
                'privacy' => 'public',
                'sensitive' => false,
                'language' => 'en',
                'note' => $account['bio'] ?? '',
                'fields' => [],
            ];
        }

        return $formatted;
    }

    /**
     * Format status for Mastodon API response.
     * 
     * @param array $status Status data
     * @return array Formatted status
     */
    private function formatStatus(array $status): array
    {
        $baseUrl = Config::get('app.url') ?? '';
        $account = Account::find((int) $status['account_id']);

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
            'favourited' => false,
            'reblogged' => false,
            'muted' => false,
            'bookmarked' => false,
            'content' => $status['content'] ?? '',
            'reblog' => null,
            'account' => $account !== null ? $this->formatAccount($account) : null,
            'media_attachments' => [],
            'mentions' => [],
            'tags' => [],
            'emojis' => [],
            'card' => null,
            'poll' => null,
        ];
    }

    /**
     * Format relationship for Mastodon API response.
     * 
     * @param int $accountId Current account ID
     * @param int $targetId Target account ID
     * @param bool $requested Follow requested
     * @return array Formatted relationship
     */
    private function formatRelationship(int $accountId, int $targetId, bool $requested = false): array
    {
        $relationship = Follow::getRelationship($accountId, $targetId);

        // Check for blocking
        $blocking = \NanoPub\Core\Database::fetchOne(
            'SELECT 1 FROM blocks WHERE account_id = ? AND target_account_id = ?',
            [$accountId, $targetId]
        );

        // Check for muting
        $muting = \NanoPub\Core\Database::fetchOne(
            'SELECT hide_notifications FROM mutes WHERE account_id = ? AND target_account_id = ?',
            [$accountId, $targetId]
        );

        // Check for domain blocking (if target is remote)
        $domainBlocking = false;

        return [
            'id' => (string) $targetId,
            'following' => $relationship['following'],
            'followed_by' => $relationship['followed_by'],
            'blocking' => $blocking !== null,
            'muting' => $muting !== null,
            'muting_notifications' => $muting !== null && (bool) $muting['hide_notifications'],
            'requested' => $requested,
            'domain_blocking' => $domainBlocking,
            'showing_reblogs' => true,
            'endorsed' => false,
            'note' => '',
        ];
    }
}
