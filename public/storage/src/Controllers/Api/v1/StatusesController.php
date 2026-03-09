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
use NanoPub\Models\MediaAttachment;
use NanoPub\Services\ActivityPubService;
use NanoPub\Services\NotificationService;
use NanoPub\Exceptions\NotFoundException;

/**
 * Handles status-related API endpoints for Mastodon API v1 compatibility.
 */
final class StatusesController
{
    private ActivityPubService $activityPubService;

    private NotificationService $notificationService;

    public function __construct()
    {
        $this->activityPubService = new ActivityPubService();
        $this->notificationService = new NotificationService();
    }

    /**
     * Get status by ID.
     * 
     * GET /api/v1/statuses/:id
     */
    public function show(Request $request, int $id): void
    {
        $status = Status::find($id);

        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        // Check visibility
        $accountId = $request->getAttribute('account_id');
        if (!$this->canViewStatus($status, $accountId)) {
            throw new NotFoundException('Status not found');
        }

        Response::json($this->formatStatus($status, $accountId));
    }

    /**
     * Create a new status.
     * 
     * POST /api/v1/statuses
     */
    public function create(Request $request): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $data = $request->json();

        // Validate required fields
        $content = trim($data['status'] ?? '');
        if (empty($content) && empty($data['media_ids'])) {
            Response::badRequest('Status is empty');
            return;
        }

        // Validate content length
        if (mb_strlen($content) > 5000) {
            Response::badRequest('Status is too long (max 5000 characters)');
            return;
        }

        // Get visibility
        $visibility = $data['visibility'] ?? 'public';
        $allowedVisibility = ['public', 'unlisted', 'private', 'direct'];
        if (!in_array($visibility, $allowedVisibility, true)) {
            $visibility = 'public';
        }

        // Handle in_reply_to
        $inReplyToId = null;
        $inReplyToAccountId = null;
        if (!empty($data['in_reply_to_id'])) {
            $parentStatus = Status::find((int) $data['in_reply_to_id']);
            if ($parentStatus !== null) {
                $inReplyToId = $parentStatus['id'];
                $inReplyToAccountId = $parentStatus['account_id'];
            }
        }

        // Build URLs
        $baseUrl = Config::get('app.url') ?? '';
        $statusId = 0; // Will be set after creation

        // Create status
        $statusId = Status::create([
            'account_id' => (int) $accountId,
            'content' => $content,
            'content_warning' => $data['spoiler_text'] ?? null,
            'visibility' => $visibility,
            'sensitive' => isset($data['sensitive']) && $data['sensitive'] ? 1 : 0,
            'language' => $data['language'] ?? 'en',
            'in_reply_to_id' => $inReplyToId,
            'in_reply_to_account_id' => $inReplyToAccountId,
            'local' => 1,
            'uri' => "{$baseUrl}/statuses/{$statusId}",
            'url' => "{$baseUrl}/statuses/{$statusId}",
        ]);

        // Attach media
        if (!empty($data['media_ids']) && is_array($data['media_ids'])) {
            foreach ($data['media_ids'] as $mediaId) {
                $media = MediaAttachment::find((int) $mediaId);
                if ($media !== null && $media['account_id'] == $accountId) {
                    MediaAttachment::attachToStatus((int) $mediaId, $statusId);
                }
            }
        }

        // Update URI/URL with actual ID
        Status::update($statusId, [
            'uri' => "{$baseUrl}/statuses/{$statusId}",
            'url' => "{$baseUrl}/statuses/{$statusId}",
        ]);

        // Get the created status
        $status = Status::find($statusId);

        // Send to ActivityPub followers
        if ($visibility !== 'direct') {
            $this->deliverStatusToFollowers($status, (int) $accountId);
        }

        Response::json($this->formatStatus($status, (int) $accountId), 201);
    }

    /**
     * Delete a status.
     * 
     * DELETE /api/v1/statuses/:id
     */
    public function delete(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $status = Status::find($id);

        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        // Check ownership
        if ((int) $status['account_id'] !== (int) $accountId) {
            Response::forbidden('You can only delete your own statuses');
            return;
        }

        // Delete the status
        Status::delete($id);

        // Send Delete activity to followers for remote distribution
        $this->deliverDeleteToFollowers($status, (int) $accountId);

        Response::json(['deleted' => true]);
    }

    /**
     * Get context (ancestors and descendants) for a status.
     * 
     * GET /api/v1/statuses/:id/context
     */
    public function context(Request $request, int $id): void
    {
        $status = Status::find($id);

        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        $accountId = $request->getAttribute('account_id');
        if (!$this->canViewStatus($status, $accountId)) {
            throw new NotFoundException('Status not found');
        }

        $context = Status::getContext($id);

        $result = [
            'ancestors' => [],
            'descendants' => [],
        ];

        foreach ($context['ancestors'] as $ancestor) {
            if ($this->canViewStatus($ancestor, $accountId)) {
                $result['ancestors'][] = $this->formatStatus($ancestor, $accountId);
            }
        }

        foreach ($context['descendants'] as $descendant) {
            if ($this->canViewStatus($descendant, $accountId)) {
                $result['descendants'][] = $this->formatStatus($descendant, $accountId);
            }
        }

        Response::json($result);
    }

    /**
     * Favourite a status.
     * 
     * POST /api/v1/statuses/:id/favourite
     */
    public function favourite(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $status = Status::find($id);

        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        if (!$this->canViewStatus($status, $accountId)) {
            throw new NotFoundException('Status not found');
        }

        // Check if already favourited
        if (Like::isLiked((int) $accountId, $id)) {
            Response::json($this->formatStatus($status, (int) $accountId));
            return;
        }

        // Create like
        $baseUrl = Config::get('app.url') ?? '';
        $likeUri = "{$baseUrl}/likes/" . generate_token(16);
        Like::create((int) $accountId, $id, $likeUri);

        // Notify status author
        $this->notificationService->notifyFavourite(
            (int) $status['account_id'],
            $id,
            (int) $accountId
        );

        // Send Like activity for remote statuses
        $statusAccount = Account::find((int) $status['account_id']);
        if ($statusAccount !== null && !$statusAccount['is_local']) {
            $activity = $this->activityPubService->buildActivity(
                'Like',
                $status['uri'] ?? "{$baseUrl}/statuses/{$id}",
                (int) $accountId
            );
            $this->activityPubService->sendActivity(
                $activity,
                (int) $accountId,
                $statusAccount['inbox_url']
            );
        }

        // Refresh status
        $status = Status::find($id);
        Response::json($this->formatStatus($status, (int) $accountId));
    }

    /**
     * Unfavourite a status.
     * 
     * POST /api/v1/statuses/:id/unfavourite
     */
    public function unfavourite(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $status = Status::find($id);

        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        // Delete like
        Like::delete((int) $accountId, $id);

        // Send Undo Like activity for remote statuses
        $statusAccount = Account::find((int) $status['account_id']);
        if ($statusAccount !== null && !$statusAccount['is_local']) {
            $baseUrl = Config::get('app.url') ?? '';
            $likeActivity = $this->activityPubService->buildActivity(
                'Like',
                $status['uri'] ?? "{$baseUrl}/statuses/{$id}",
                (int) $accountId
            );
            $undoActivity = $this->activityPubService->buildActivity(
                'Undo',
                $likeActivity,
                (int) $accountId
            );
            $this->activityPubService->sendActivity(
                $undoActivity,
                (int) $accountId,
                $statusAccount['inbox_url']
            );
        }

        // Refresh status
        $status = Status::find($id);
        Response::json($this->formatStatus($status, (int) $accountId));
    }

    /**
     * Reblog a status.
     * 
     * POST /api/v1/statuses/:id/reblog
     */
    public function reblog(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $status = Status::find($id);

        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        if (!$this->canViewStatus($status, $accountId)) {
            throw new NotFoundException('Status not found');
        }

        // Check if already reblogged
        if (Boost::isBoosted((int) $accountId, $id)) {
            Response::json($this->formatStatus($status, (int) $accountId));
            return;
        }

        // Create boost
        $baseUrl = Config::get('app.url') ?? '';
        $boostUri = "{$baseUrl}/boosts/" . generate_token(16);
        Boost::create((int) $accountId, $id, $boostUri);

        // Notify status author
        $this->notificationService->notifyReblog(
            (int) $status['account_id'],
            $id,
            (int) $accountId
        );

        // Send Announce activity for remote statuses
        $statusAccount = Account::find((int) $status['account_id']);
        if ($statusAccount !== null && !$statusAccount['is_local']) {
            $activity = $this->activityPubService->buildActivity(
                'Announce',
                $status['uri'] ?? "{$baseUrl}/statuses/{$id}",
                (int) $accountId
            );
            $this->activityPubService->sendActivity(
                $activity,
                (int) $accountId,
                $statusAccount['inbox_url']
            );
        }

        // Refresh status
        $status = Status::find($id);
        Response::json($this->formatStatus($status, (int) $accountId));
    }

    /**
     * Unreblog a status.
     * 
     * POST /api/v1/statuses/:id/unreblog
     */
    public function unreblog(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $status = Status::find($id);

        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        // Delete boost
        Boost::delete((int) $accountId, $id);

        // Send Undo Announce activity for remote statuses
        $statusAccount = Account::find((int) $status['account_id']);
        if ($statusAccount !== null && !$statusAccount['is_local']) {
            $baseUrl = Config::get('app.url') ?? '';
            $announceActivity = $this->activityPubService->buildActivity(
                'Announce',
                $status['uri'] ?? "{$baseUrl}/statuses/{$id}",
                (int) $accountId
            );
            $undoActivity = $this->activityPubService->buildActivity(
                'Undo',
                $announceActivity,
                (int) $accountId
            );
            $this->activityPubService->sendActivity(
                $undoActivity,
                (int) $accountId,
                $statusAccount['inbox_url']
            );
        }

        // Refresh status
        $status = Status::find($id);
        Response::json($this->formatStatus($status, (int) $accountId));
    }

    /**
     * Bookmark a status.
     * 
     * POST /api/v1/statuses/:id/bookmark
     */
    public function bookmark(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $status = Status::find($id);

        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        if (!$this->canViewStatus($status, $accountId)) {
            throw new NotFoundException('Status not found');
        }

        // Create bookmark if not exists
        if (!Bookmark::isBookmarked((int) $accountId, $id)) {
            Bookmark::create((int) $accountId, $id);
        }

        // Refresh status
        Response::json($this->formatStatus($status, (int) $accountId));
    }

    /**
     * Unbookmark a status.
     * 
     * POST /api/v1/statuses/:id/unbookmark
     */
    public function unbookmark(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $status = Status::find($id);

        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        // Delete bookmark
        Bookmark::delete((int) $accountId, $id);

        // Refresh status
        Response::json($this->formatStatus($status, (int) $accountId));
    }

    /**
     * Pin a status.
     * 
     * POST /api/v1/statuses/:id/pin
     */
    public function pin(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $status = Status::find($id);

        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        // Check ownership
        if ((int) $status['account_id'] !== (int) $accountId) {
            Response::forbidden('You can only pin your own statuses');
            return;
        }

        // Create pin
        Database::execute(
            'INSERT INTO status_pins (account_id, status_id, created_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE created_at = NOW()',
            [(int) $accountId, $id]
        );

        Response::json($this->formatStatus($status, (int) $accountId));
    }

    /**
     * Unpin a status.
     * 
     * POST /api/v1/statuses/:id/unpin
     */
    public function unpin(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $status = Status::find($id);

        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        // Delete pin
        Database::execute(
            'DELETE FROM status_pins WHERE account_id = ? AND status_id = ?',
            [(int) $accountId, $id]
        );

        Response::json($this->formatStatus($status, (int) $accountId));
    }

    /**
     * Check if a status can be viewed by an account.
     * 
     * @param array $status Status data
     * @param int|null $accountId Account ID (null for anonymous)
     * @return bool True if can view
     */
    private function canViewStatus(array $status, ?int $accountId): bool
    {
        $visibility = $status['visibility'] ?? 'public';

        // Public and unlisted are visible to all
        if (in_array($visibility, ['public', 'unlisted'], true)) {
            return true;
        }

        // Private and direct require authentication
        if ($accountId === null) {
            return false;
        }

        // Author can always view
        if ((int) $status['account_id'] === $accountId) {
            return true;
        }

        // For private, check if follower
        if ($visibility === 'private') {
            return \NanoPub\Models\Follow::isFollowing($accountId, (int) $status['account_id']);
        }

        // For direct, check if mentioned
        if ($visibility === 'direct') {
            // Check mentions table or in_reply_to_account_id
            if ($status['in_reply_to_account_id'] === $accountId) {
                return true;
            }

            // Check mentions
            $mention = Database::fetchOne(
                'SELECT 1 FROM mentions WHERE status_id = ? AND account_id = ?',
                [(int) $status['id'], $accountId]
            );
            return $mention !== null;
        }

        return false;
    }

    /**
     * Deliver status to followers via ActivityPub.
     * 
     * @param array $status Status data
     * @param int $accountId Author account ID
     */
    private function deliverStatusToFollowers(array $status, int $accountId): void
    {
        $baseUrl = Config::get('app.url') ?? '';
        $account = Account::find($accountId);

        if ($account === null) {
            return;
        }

        // Build Create activity
        $note = Status::toActivityPub((int) $status['id']);
        if ($note === null) {
            return;
        }

        $activity = $this->activityPubService->buildActivity('Create', $note, $accountId);

        // Get follower inboxes
        $followerIds = \NanoPub\Models\Follow::getFollowerIds($accountId);

        foreach ($followerIds as $followerId) {
            $follower = Account::find($followerId);
            if ($follower !== null && !$follower['is_local'] && !empty($follower['inbox_url'])) {
                $this->activityPubService->sendActivity(
                    $activity,
                    $accountId,
                    $follower['inbox_url']
                );
            }
        }

        // Also send to shared inbox
        $sharedInbox = "{$baseUrl}/inbox";
        // Note: In production, you'd batch deliver to shared inboxes
    }

    /**
     * Deliver Delete activity to followers.
     * 
     * @param array $status Status data
     * @param int $accountId Author account ID
     */
    private function deliverDeleteToFollowers(array $status, int $accountId): void
    {
        $baseUrl = Config::get('app.url') ?? '';
        $account = Account::find($accountId);

        if ($account === null) {
            return;
        }

        // Build Delete activity
        $activity = $this->activityPubService->buildActivity(
            'Delete',
            [
                'id' => $status['uri'] ?? "{$baseUrl}/statuses/{$status['id']}",
                'type' => 'Tombstone',
            ],
            $accountId
        );

        // Get follower inboxes
        $followerIds = \NanoPub\Models\Follow::getFollowerIds($accountId);

        foreach ($followerIds as $followerId) {
            $follower = Account::find($followerId);
            if ($follower !== null && !$follower['is_local'] && !empty($follower['inbox_url'])) {
                $this->activityPubService->sendActivity(
                    $activity,
                    $accountId,
                    $follower['inbox_url']
                );
            }
        }
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
        $account = Account::find((int) $status['account_id']);

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
        $pinned = false;

        if ($accountId !== null) {
            $favourited = Like::isLiked($accountId, (int) $status['id']);
            $reblogged = Boost::isBoosted($accountId, (int) $status['id']);
            $bookmarked = Bookmark::isBookmarked($accountId, (int) $status['id']);

            $muteCheck = Database::fetchOne(
                'SELECT 1 FROM mutes WHERE account_id = ? AND target_account_id = ?',
                [$accountId, (int) $status['account_id']]
            );
            $muted = $muteCheck !== null;

            $pinCheck = Database::fetchOne(
                'SELECT 1 FROM status_pins WHERE account_id = ? AND status_id = ?',
                [$accountId, (int) $status['id']]
            );
            $pinned = $pinCheck !== null;
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
            'pinned' => $pinned,
            'content' => $status['content'] ?? '',
            'reblog' => null,
            'account' => $account !== null ? $this->formatAccount($account) : null,
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