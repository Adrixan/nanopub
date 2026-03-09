<?php

declare(strict_types=1);

namespace NanoPub\Models;

use NanoPub\Core\Database;
use Generator;

/**
 * Notification model for user notifications.
 * 
 * Handles notifications for follows, mentions, likes, boosts, etc.
 */
class Notification
{
    /**
     * Find a notification by ID.
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT n.*, 
                       a.username as from_account_username,
                       a.display_name as from_account_display_name,
                       a.avatar_url as from_account_avatar_url,
                       s.content as status_content
                FROM notifications n
                LEFT JOIN accounts a ON n.from_account_id = a.id
                LEFT JOIN statuses s ON n.status_id = s.id
                WHERE n.id = :id";
        
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Get notifications for an account (generator for memory efficiency).
     */
    public static function getForAccount(int $accountId, int $limit = 20, int $offset = 0): Generator
    {
        $sql = "SELECT n.*, 
                       a.username as from_account_username,
                       a.display_name as from_account_display_name,
                       a.avatar_url as from_account_avatar_url,
                       s.content as status_content,
                       s.visibility as status_visibility
                FROM notifications n
                LEFT JOIN accounts a ON n.from_account_id = a.id
                LEFT JOIN statuses s ON n.status_id = s.id
                WHERE n.account_id = :account_id
                ORDER BY n.created_at DESC
                LIMIT :limit OFFSET :offset";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Get unread notification count for an account.
     */
    public static function getUnreadCount(int $accountId): int
    {
        $sql = "SELECT COUNT(*) as count 
                FROM notifications 
                WHERE account_id = :account_id AND read_at IS NULL";
        
        $result = Database::fetchOne($sql, ['account_id' => $accountId]);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Create a new notification.
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO notifications (account_id, type, from_account_id, status_id, read_at, created_at)
                VALUES (:account_id, :type, :from_account_id, :status_id, :read_at, NOW())";

        $params = [
            'account_id' => $data['account_id'],
            'type' => $data['type'],
            'from_account_id' => $data['from_account_id'] ?? null,
            'status_id' => $data['status_id'] ?? null,
            'read_at' => $data['read_at'] ?? null,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Mark a notification as read.
     */
    public static function markAsRead(int $id): bool
    {
        $sql = "UPDATE notifications SET read_at = NOW() WHERE id = :id AND read_at IS NULL";
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Mark all notifications as read for an account.
     */
    public static function markAllAsRead(int $accountId): int
    {
        $sql = "UPDATE notifications SET read_at = NOW() WHERE account_id = :account_id AND read_at IS NULL";
        return Database::execute($sql, ['account_id' => $accountId]);
    }

    /**
     * Delete a notification.
     */
    public static function delete(int $id): bool
    {
        $sql = "DELETE FROM notifications WHERE id = :id";
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Clear all notifications for an account.
     */
    public static function clearAll(int $accountId): int
    {
        $sql = "DELETE FROM notifications WHERE account_id = :account_id";
        return Database::execute($sql, ['account_id' => $accountId]);
    }

    /**
     * Get notifications by type for an account.
     */
    public static function getByType(int $accountId, string $type, int $limit = 20): Generator
    {
        $sql = "SELECT n.*, 
                       a.username as from_account_username,
                       a.display_name as from_account_display_name,
                       a.avatar_url as from_account_avatar_url,
                       s.content as status_content
                FROM notifications n
                LEFT JOIN accounts a ON n.from_account_id = a.id
                LEFT JOIN statuses s ON n.status_id = s.id
                WHERE n.account_id = :account_id AND n.type = :type
                ORDER BY n.created_at DESC
                LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Check if a similar notification exists (to prevent duplicates).
     */
    public static function exists(int $accountId, string $type, ?int $fromAccountId = null, ?int $statusId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM notifications 
                WHERE account_id = :account_id 
                  AND type = :type 
                  AND from_account_id <=> :from_account_id
                  AND status_id <=> :status_id
                  AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $result = Database::fetchOne($sql, [
            'account_id' => $accountId,
            'type' => $type,
            'from_account_id' => $fromAccountId,
            'status_id' => $statusId,
        ]);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Delete old notifications (cleanup).
     */
    public static function deleteOld(int $daysOld = 90): int
    {
        $sql = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        return Database::execute($sql, ['days' => $daysOld]);
    }
}
