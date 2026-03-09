<?php

declare(strict_types=1);

namespace NanoPub\Models;

use NanoPub\Core\Database;
use Generator;

/**
 * Boost model for status reblogs.
 * 
 * Handles boosting/reblogging statuses.
 */
class Boost
{
    /**
     * Find a boost by ID.
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT b.*, 
                       a.username as account_username,
                       a.display_name as account_display_name,
                       s.content as status_content
                FROM boosts b
                JOIN accounts a ON b.account_id = a.id
                JOIN statuses s ON b.status_id = s.id
                WHERE b.id = :id";
        
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Check if an account has boosted a status.
     */
    public static function isBoosted(int $accountId, int $statusId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM boosts WHERE account_id = :account_id AND status_id = :status_id";
        
        $result = Database::fetchOne($sql, [
            'account_id' => $accountId,
            'status_id' => $statusId,
        ]);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Create a boost.
     */
    public static function create(int $accountId, int $statusId, ?string $uri = null): int
    {
        $sql = "INSERT INTO boosts (account_id, status_id, uri, created_at)
                VALUES (:account_id, :status_id, :uri, NOW())";

        $params = [
            'account_id' => $accountId,
            'status_id' => $statusId,
            'uri' => $uri,
        ];

        Database::execute($sql, $params);
        
        // Update reblogs count on status
        Database::execute(
            "UPDATE statuses SET reblogs_count = reblogs_count + 1 WHERE id = :id",
            ['id' => $statusId]
        );
        
        return (int) Database::lastInsertId();
    }

    /**
     * Delete a boost (unboost).
     */
    public static function delete(int $accountId, int $statusId): bool
    {
        $sql = "DELETE FROM boosts WHERE account_id = :account_id AND status_id = :status_id";
        $deleted = Database::execute($sql, [
            'account_id' => $accountId,
            'status_id' => $statusId,
        ]) > 0;
        
        if ($deleted) {
            // Update reblogs count on status
            Database::execute(
                "UPDATE statuses SET reblogs_count = GREATEST(0, reblogs_count - 1) WHERE id = :id",
                ['id' => $statusId]
            );
        }
        
        return $deleted;
    }

    /**
     * Get boosts for a status (generator for memory efficiency).
     */
    public static function getForStatus(int $statusId, int $limit = 40): Generator
    {
        $sql = "SELECT b.*, a.id as account_id, a.username, a.display_name, a.avatar_url, a.is_local
                FROM boosts b
                JOIN accounts a ON b.account_id = a.id
                WHERE b.status_id = :status_id
                ORDER BY b.created_at DESC
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
     * Get boost count for a status.
     */
    public static function getCountForStatus(int $statusId): int
    {
        $sql = "SELECT reblogs_count FROM statuses WHERE id = :id";
        $result = Database::fetchOne($sql, ['id' => $statusId]);
        return (int) ($result['reblogs_count'] ?? 0);
    }

    /**
     * Get boosts by an account (generator for memory efficiency).
     */
    public static function getForAccount(int $accountId, int $limit = 40): Generator
    {
        $sql = "SELECT b.*, s.id as status_id, s.content, s.created_at as status_created_at,
                       a.username as author_username, a.display_name as author_display_name
                FROM boosts b
                JOIN statuses s ON b.status_id = s.id
                JOIN accounts a ON s.account_id = a.id
                WHERE b.account_id = :account_id
                ORDER BY b.created_at DESC
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
     * Find boost by URI (for ActivityPub).
     */
    public static function findByUri(string $uri): ?array
    {
        $sql = "SELECT * FROM boosts WHERE uri = :uri";
        return Database::fetchOne($sql, ['uri' => $uri]);
    }

    /**
     * Find boost by account and status.
     */
    public static function findByAccountAndStatus(int $accountId, int $statusId): ?array
    {
        $sql = "SELECT * FROM boosts WHERE account_id = :account_id AND status_id = :status_id";
        return Database::fetchOne($sql, [
            'account_id' => $accountId,
            'status_id' => $statusId,
        ]);
    }

    /**
     * Delete all boosts for a status (when status is deleted).
     */
    public static function deleteForStatus(int $statusId): int
    {
        $sql = "DELETE FROM boosts WHERE status_id = :status_id";
        return Database::execute($sql, ['status_id' => $statusId]);
    }

    /**
     * Delete all boosts by an account (when account is deleted).
     */
    public static function deleteForAccount(int $accountId): int
    {
        // First, decrement reblogs_count on all boosted statuses
        $sql = "SELECT status_id FROM boosts WHERE account_id = :account_id";
        $boosts = Database::fetchAll($sql, ['account_id' => $accountId]);
        
        foreach ($boosts as $boost) {
            Database::execute(
                "UPDATE statuses SET reblogs_count = GREATEST(0, reblogs_count - 1) WHERE id = :id",
                ['id' => $boost['status_id']]
            );
        }
        
        // Then delete the boosts
        $sql = "DELETE FROM boosts WHERE account_id = :account_id";
        return Database::execute($sql, ['account_id' => $accountId]);
    }

    /**
     * Get the original status for a boost.
     */
    public static function getOriginalStatus(int $boostId): ?array
    {
        $sql = "SELECT s.*, a.username as author_username, a.display_name as author_display_name,
                       a.avatar_url as author_avatar_url
                FROM boosts b
                JOIN statuses s ON b.status_id = s.id
                JOIN accounts a ON s.account_id = a.id
                WHERE b.id = :boost_id";
        
        return Database::fetchOne($sql, ['boost_id' => $boostId]);
    }
}
