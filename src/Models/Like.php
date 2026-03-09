<?php

declare(strict_types=1);

namespace NanoPub\Models;

use NanoPub\Core\Database;
use Generator;

/**
 * Like model for status favourites.
 * 
 * Handles liking/favouriting statuses.
 */
class Like
{
    /**
     * Find a like by ID.
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT l.*, 
                       a.username as account_username,
                       a.display_name as account_display_name,
                       s.content as status_content
                FROM likes l
                JOIN accounts a ON l.account_id = a.id
                JOIN statuses s ON l.status_id = s.id
                WHERE l.id = :id";
        
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Check if an account has liked a status.
     */
    public static function isLiked(int $accountId, int $statusId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM likes WHERE account_id = :account_id AND status_id = :status_id";
        
        $result = Database::fetchOne($sql, [
            'account_id' => $accountId,
            'status_id' => $statusId,
        ]);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Create a like.
     */
    public static function create(int $accountId, int $statusId, ?string $uri = null): int
    {
        $sql = "INSERT INTO likes (account_id, status_id, uri, created_at)
                VALUES (:account_id, :status_id, :uri, NOW())";

        $params = [
            'account_id' => $accountId,
            'status_id' => $statusId,
            'uri' => $uri,
        ];

        Database::execute($sql, $params);
        
        // Update favourites count on status
        Database::execute(
            "UPDATE statuses SET favourites_count = favourites_count + 1 WHERE id = :id",
            ['id' => $statusId]
        );
        
        return (int) Database::lastInsertId();
    }

    /**
     * Delete a like (unlike).
     */
    public static function delete(int $accountId, int $statusId): bool
    {
        $sql = "DELETE FROM likes WHERE account_id = :account_id AND status_id = :status_id";
        $deleted = Database::execute($sql, [
            'account_id' => $accountId,
            'status_id' => $statusId,
        ]) > 0;
        
        if ($deleted) {
            // Update favourites count on status
            Database::execute(
                "UPDATE statuses SET favourites_count = GREATEST(0, favourites_count - 1) WHERE id = :id",
                ['id' => $statusId]
            );
        }
        
        return $deleted;
    }

    /**
     * Get likes for a status (generator for memory efficiency).
     */
    public static function getForStatus(int $statusId, int $limit = 40): Generator
    {
        $sql = "SELECT l.*, a.id as account_id, a.username, a.display_name, a.avatar_url, a.is_local
                FROM likes l
                JOIN accounts a ON l.account_id = a.id
                WHERE l.status_id = :status_id
                ORDER BY l.created_at DESC
                LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':status_id', $statusId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Get like count for a status.
     */
    public static function getCountForStatus(int $statusId): int
    {
        $sql = "SELECT favourites_count FROM statuses WHERE id = :id";
        $result = Database::fetchOne($sql, ['id' => $statusId]);
        return (int) ($result['favourites_count'] ?? 0);
    }

    /**
     * Get likes by an account (generator for memory efficiency).
     */
    public static function getForAccount(int $accountId, int $limit = 40): Generator
    {
        $sql = "SELECT l.*, s.id as status_id, s.content, s.created_at as status_created_at,
                       a.username as author_username, a.display_name as author_display_name
                FROM likes l
                JOIN statuses s ON l.status_id = s.id
                JOIN accounts a ON s.account_id = a.id
                WHERE l.account_id = :account_id
                ORDER BY l.created_at DESC
                LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Find like by URI (for ActivityPub).
     */
    public static function findByUri(string $uri): ?array
    {
        $sql = "SELECT * FROM likes WHERE uri = :uri";
        return Database::fetchOne($sql, ['uri' => $uri]);
    }

    /**
     * Find like by account and status.
     */
    public static function findByAccountAndStatus(int $accountId, int $statusId): ?array
    {
        $sql = "SELECT * FROM likes WHERE account_id = :account_id AND status_id = :status_id";
        return Database::fetchOne($sql, [
            'account_id' => $accountId,
            'status_id' => $statusId,
        ]);
    }

    /**
     * Delete all likes for a status (when status is deleted).
     */
    public static function deleteForStatus(int $statusId): int
    {
        $sql = "DELETE FROM likes WHERE status_id = :status_id";
        return Database::execute($sql, ['status_id' => $statusId]);
    }

    /**
     * Delete all likes by an account (when account is deleted).
     */
    public static function deleteForAccount(int $accountId): int
    {
        // First, decrement favourites_count on all liked statuses
        $sql = "SELECT status_id FROM likes WHERE account_id = :account_id";
        $likes = Database::fetchAll($sql, ['account_id' => $accountId]);
        
        foreach ($likes as $like) {
            Database::execute(
                "UPDATE statuses SET favourites_count = GREATEST(0, favourites_count - 1) WHERE id = :id",
                ['id' => $like['status_id']]
            );
        }
        
        // Then delete the likes
        $sql = "DELETE FROM likes WHERE account_id = :account_id";
        return Database::execute($sql, ['account_id' => $accountId]);
    }
}
