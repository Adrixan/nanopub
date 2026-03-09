<?php

declare(strict_types=1);

namespace NanoPub\Models;

use NanoPub\Core\Database;
use Generator;

/**
 * Bookmark model for saving statuses.
 * 
 * Handles bookmarking statuses for later reading.
 */
class Bookmark
{
    /**
     * Find a bookmark by ID.
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT b.*, 
                       a.username as account_username,
                       s.content as status_content
                FROM bookmarks b
                JOIN accounts a ON b.account_id = a.id
                JOIN statuses s ON b.status_id = s.id
                WHERE b.id = :id";
        
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Check if an account has bookmarked a status.
     */
    public static function isBookmarked(int $accountId, int $statusId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM bookmarks WHERE account_id = :account_id AND status_id = :status_id";
        
        $result = Database::fetchOne($sql, [
            'account_id' => $accountId,
            'status_id' => $statusId,
        ]);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Create a bookmark.
     */
    public static function create(int $accountId, int $statusId): int
    {
        $sql = "INSERT INTO bookmarks (account_id, status_id, created_at)
                VALUES (:account_id, :status_id, NOW())";

        $params = [
            'account_id' => $accountId,
            'status_id' => $statusId,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Delete a bookmark (unbookmark).
     */
    public static function delete(int $accountId, int $statusId): bool
    {
        $sql = "DELETE FROM bookmarks WHERE account_id = :account_id AND status_id = :status_id";
        return Database::execute($sql, [
            'account_id' => $accountId,
            'status_id' => $statusId,
        ]) > 0;
    }

    /**
     * Get bookmarks for an account (generator for memory efficiency).
     */
    public static function getForAccount(int $accountId, int $limit = 40, int $offset = 0): Generator
    {
        $sql = "SELECT b.*, 
                       s.id as status_id, s.content, s.content_warning, s.visibility, 
                       s.created_at as status_created_at, s.favourites_count, s.reblogs_count, s.replies_count,
                       a.id as author_id, a.username as author_username, 
                       a.display_name as author_display_name, a.avatar_url as author_avatar_url
                FROM bookmarks b
                JOIN statuses s ON b.status_id = s.id
                JOIN accounts a ON s.account_id = a.id
                WHERE b.account_id = :account_id
                ORDER BY b.created_at DESC
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
     * Get bookmark count for an account.
     */
    public static function getCountForAccount(int $accountId): int
    {
        $sql = "SELECT COUNT(*) as count FROM bookmarks WHERE account_id = :account_id";
        $result = Database::fetchOne($sql, ['account_id' => $accountId]);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Find bookmark by account and status.
     */
    public static function findByAccountAndStatus(int $accountId, int $statusId): ?array
    {
        $sql = "SELECT * FROM bookmarks WHERE account_id = :account_id AND status_id = :status_id";
        return Database::fetchOne($sql, [
            'account_id' => $accountId,
            'status_id' => $statusId,
        ]);
    }

    /**
     * Delete all bookmarks for a status (when status is deleted).
     */
    public static function deleteForStatus(int $statusId): int
    {
        $sql = "DELETE FROM bookmarks WHERE status_id = :status_id";
        return Database::execute($sql, ['status_id' => $statusId]);
    }

    /**
     * Delete all bookmarks for an account (when account is deleted).
     */
    public static function deleteForAccount(int $accountId): int
    {
        $sql = "DELETE FROM bookmarks WHERE account_id = :account_id";
        return Database::execute($sql, ['account_id' => $accountId]);
    }
}
