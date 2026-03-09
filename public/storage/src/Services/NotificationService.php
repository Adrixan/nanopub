<?php

declare(strict_types=1);

namespace NanoPub\Services;

use Generator;
use NanoPub\Core\Config;
use NanoPub\Models\Notification;
use NanoPub\Models\Account;

/**
 * Notification service for creating and managing notifications.
 */
final class NotificationService
{
    /**
     * Create notification.
     * 
     * @param int $accountId Account to notify
     * @param string $type Notification type
     * @param int|null $fromAccountId Account that triggered the notification
     * @param int|null $statusId Related status ID
     * @return int Notification ID (0 if not created)
     */
    public function create(int $accountId, string $type, ?int $fromAccountId = null, ?int $statusId = null): int
    {
        // Don't notify self
        if ($fromAccountId !== null && $accountId === $fromAccountId) {
            return 0;
        }
        
        // Check for duplicate notification within the last hour
        if (Notification::exists($accountId, $type, $fromAccountId, $statusId)) {
            return 0;
        }
        
        return Notification::create([
            'account_id' => $accountId,
            'type' => $type,
            'from_account_id' => $fromAccountId,
            'status_id' => $statusId,
        ]);
    }
    
    /**
     * Notify of new follower.
     * 
     * @param int $accountId Account being followed
     * @param int $followerId Follower account ID
     * @return int Notification ID
     */
    public function notifyFollow(int $accountId, int $followerId): int
    {
        return $this->create($accountId, 'follow', $followerId);
    }
    
    /**
     * Notify of follow request.
     * 
     * @param int $accountId Account that received the request
     * @param int $requesterId Requester account ID
     * @return int Notification ID
     */
    public function notifyFollowRequest(int $accountId, int $requesterId): int
    {
        return $this->create($accountId, 'follow_request', $requesterId);
    }
    
    /**
     * Notify of mention.
     * 
     * @param int $accountId Account mentioned
     * @param int $statusId Status containing the mention
     * @param int $fromAccountId Account that mentioned
     * @return int Notification ID
     */
    public function notifyMention(int $accountId, int $statusId, int $fromAccountId): int
    {
        return $this->create($accountId, 'mention', $fromAccountId, $statusId);
    }
    
    /**
     * Notify of boost (reblog).
     * 
     * @param int $accountId Account whose status was boosted
     * @param int $statusId Status that was boosted
     * @param int $fromAccountId Account that boosted
     * @return int Notification ID
     */
    public function notifyReblog(int $accountId, int $statusId, int $fromAccountId): int
    {
        return $this->create($accountId, 'reblog', $fromAccountId, $statusId);
    }
    
    /**
     * Notify of favourite.
     * 
     * @param int $accountId Account whose status was favourited
     * @param int $statusId Status that was favourited
     * @param int $fromAccountId Account that favourited
     * @return int Notification ID
     */
    public function notifyFavourite(int $accountId, int $statusId, int $fromAccountId): int
    {
        return $this->create($accountId, 'favourite', $fromAccountId, $statusId);
    }
    
    /**
     * Notify of poll ending.
     * 
     * @param int $accountId Account that created the poll
     * @param int $statusId Status containing the poll
     * @return int Notification ID
     */
    public function notifyPoll(int $accountId, int $statusId): int
    {
        return $this->create($accountId, 'poll', null, $statusId);
    }
    
    /**
     * Notify of status update (edited).
     * 
     * @param int $accountId Account to notify
     * @param int $statusId Status that was updated
     * @param int $fromAccountId Account that updated the status
     * @return int Notification ID
     */
    public function notifyStatus(int $accountId, int $statusId, int $fromAccountId): int
    {
        return $this->create($accountId, 'status', $fromAccountId, $statusId);
    }
    
    /**
     * Notify of status update (edited).
     * 
     * @param int $accountId Account to notify
     * @param int $statusId Status that was updated
     * @param int $fromAccountId Account that updated the status
     * @return int Notification ID
     */
    public function notifyUpdate(int $accountId, int $statusId, int $fromAccountId): int
    {
        return $this->create($accountId, 'update', $fromAccountId, $statusId);
    }
    
    /**
     * Get notifications for account.
     * 
     * @param int $accountId Account ID
     * @param int $limit Maximum results
     * @param int $offset Offset for pagination
     * @return Generator<int, array, mixed, void>
     */
    public function getForAccount(int $accountId, int $limit = 40, int $offset = 0): Generator
    {
        return Notification::getForAccount($accountId, $limit, $offset);
    }
    
    /**
     * Get unread notification count.
     * 
     * @param int $accountId Account ID
     * @return int Unread count
     */
    public function getUnreadCount(int $accountId): int
    {
        return Notification::getUnreadCount($accountId);
    }
    
    /**
     * Mark notification as read.
     * 
     * @param int $notificationId Notification ID
     * @param int $accountId Account ID (for ownership verification)
     * @return bool True on success
     */
    public function markAsRead(int $notificationId, int $accountId): bool
    {
        // Verify ownership first
        $notification = Notification::find($notificationId);
        
        if ($notification === null || $notification['account_id'] !== $accountId) {
            return false;
        }
        
        return Notification::markAsRead($notificationId);
    }
    
    /**
     * Mark all notifications as read for an account.
     * 
     * @param int $accountId Account ID
     * @return int Number of notifications marked as read
     */
    public function markAllAsRead(int $accountId): int
    {
        return Notification::markAllAsRead($accountId);
    }
    
    /**
     * Delete a notification.
     * 
     * @param int $notificationId Notification ID
     * @param int $accountId Account ID (for ownership verification)
     * @return bool True on success
     */
    public function delete(int $notificationId, int $accountId): bool
    {
        // Verify ownership first
        $notification = Notification::find($notificationId);
        
        if ($notification === null || $notification['account_id'] !== $accountId) {
            return false;
        }
        
        return Notification::delete($notificationId);
    }
    
    /**
     * Clear all notifications for an account.
     * 
     * @param int $accountId Account ID
     * @return int Number of notifications deleted
     */
    public function clearAll(int $accountId): int
    {
        return Notification::clearAll($accountId);
    }
    
    /**
     * Get notifications by type.
     * 
     * @param int $accountId Account ID
     * @param string $type Notification type
     * @param int $limit Maximum results
     * @return Generator<int, array, mixed, void>
     */
    public function getByType(int $accountId, string $type, int $limit = 40): Generator
    {
        return Notification::getByType($accountId, $type, $limit);
    }
    
    /**
     * Cleanup old notifications.
     * 
     * @param int $daysOld Delete notifications older than this many days
     * @return int Number of notifications deleted
     */
    public function cleanup(int $daysOld = 90): int
    {
        return Notification::deleteOld($daysOld);
    }
    
    /**
     * Format notification for API response.
     * 
     * @param array $notification Notification data
     * @return array Formatted notification
     */
    public function formatForApi(array $notification): array
    {
        $baseUrl = Config::get('app.url') ?? '';
        
        $formatted = [
            'id' => (string) $notification['id'],
            'type' => $notification['type'],
            'created_at' => $notification['created_at'],
            'read' => $notification['read_at'] !== null,
        ];
        
        // Add account info
        if (!empty($notification['from_account_id'])) {
            $account = Account::find((int) $notification['from_account_id']);
            
            if ($account !== null) {
                $formatted['account'] = [
                    'id' => (string) $account['id'],
                    'username' => $account['username'],
                    'display_name' => $account['display_name'] ?? $account['username'],
                    'avatar_url' => $account['avatar_url'],
                    'url' => "{$baseUrl}/@{$account['username']}",
                ];
            }
        }
        
        // Add status info
        if (!empty($notification['status_id'])) {
            $status = \NanoPub\Models\Status::find((int) $notification['status_id']);
            
            if ($status !== null) {
                $formatted['status'] = [
                    'id' => (string) $status['id'],
                    'content' => $status['content'],
                    'visibility' => $status['visibility'],
                    'created_at' => $status['created_at'],
                    'url' => $status['url'] ?? "{$baseUrl}/statuses/{$status['id']}",
                ];
            }
        }
        
        return $formatted;
    }
}
