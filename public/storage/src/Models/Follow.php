<?php

declare(strict_types=1);

namespace NanoPub\Models;

use NanoPub\Core\Database;
use Generator;

/**
 * Follow model for account relationships.
 * 
 * Handles follower/following relationships between accounts.
 */
class Follow
{
    /**
     * Find a follow relationship by ID.
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT f.*, 
                       follower.username as follower_username,
                       follower.display_name as follower_display_name,
                       following.username as following_username,
                       following.display_name as following_display_name
                FROM follows f
                JOIN accounts follower ON f.account_id = follower.id
                JOIN accounts following ON f.target_account_id = following.id
                WHERE f.id = :id";
        
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Check if an account is following another account.
     */
    public static function isFollowing(int $accountId, int $targetAccountId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM follows 
                WHERE account_id = :account_id AND target_account_id = :target_account_id";
        
        $result = Database::fetchOne($sql, [
            'account_id' => $accountId,
            'target_account_id' => $targetAccountId,
        ]);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get the relationship between two accounts.
     * Returns array with 'following' and 'followed_by' booleans.
     */
    public static function getRelationship(int $accountId, int $targetAccountId): array
    {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM follows WHERE account_id = :account_id AND target_account_id = :target_id) as following,
                    (SELECT COUNT(*) FROM follows WHERE account_id = :target_id AND target_account_id = :account_id) as followed_by";
        
        $result = Database::fetchOne($sql, [
            'account_id' => $accountId,
            'target_id' => $targetAccountId,
        ]);

        return [
            'following' => ($result['following'] ?? 0) > 0,
            'followed_by' => ($result['followed_by'] ?? 0) > 0,
        ];
    }

    /**
     * Create a follow relationship.
     */
    public static function create(int $accountId, int $targetAccountId, ?string $uri = null): int
    {
        $sql = "INSERT INTO follows (account_id, target_account_id, uri, created_at)
                VALUES (:account_id, :target_account_id, :uri, NOW())";

        $params = [
            'account_id' => $accountId,
            'target_account_id' => $targetAccountId,
            'uri' => $uri,
        ];

        Database::execute($sql, $params);
        
        // Update follower count for target account
        Database::execute(
            "UPDATE accounts SET followers_count = followers_count + 1 WHERE id = :id",
            ['id' => $targetAccountId]
        );
        
        // Update following count for follower account
        Database::execute(
            "UPDATE accounts SET following_count = following_count + 1 WHERE id = :id",
            ['id' => $accountId]
        );
        
        return (int) Database::lastInsertId();
    }

    /**
     * Delete a follow relationship.
     */
    public static function delete(int $accountId, int $targetAccountId): bool
    {
        $sql = "DELETE FROM follows WHERE account_id = :account_id AND target_account_id = :target_account_id";
        $deleted = Database::execute($sql, [
            'account_id' => $accountId,
            'target_account_id' => $targetAccountId,
        ]) > 0;
        
        if ($deleted) {
            // Update follower count for target account
            Database::execute(
                "UPDATE accounts SET followers_count = GREATEST(0, followers_count - 1) WHERE id = :id",
                ['id' => $targetAccountId]
            );
            
            // Update following count for follower account
            Database::execute(
                "UPDATE accounts SET following_count = GREATEST(0, following_count - 1) WHERE id = :id",
                ['id' => $accountId]
            );
        }
        
        return $deleted;
    }

    /**
     * Get followers for an account (generator for memory efficiency).
     */
    public static function getFollowers(int $accountId, int $limit = 40): Generator
    {
        $sql = "SELECT f.*, a.id as account_id, a.username, a.display_name, a.avatar_url, a.bio,
                       a.is_local, a.is_locked, a.is_bot, a.followers_count, a.following_count, a.statuses_count
                FROM follows f
                JOIN accounts a ON f.account_id = a.id
                WHERE f.target_account_id = :account_id
                ORDER BY f.created_at DESC
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
     * Get accounts that an account is following (generator for memory efficiency).
     */
    public static function getFollowing(int $accountId, int $limit = 40): Generator
    {
        $sql = "SELECT f.*, a.id as account_id, a.username, a.display_name, a.avatar_url, a.bio,
                       a.is_local, a.is_locked, a.is_bot, a.followers_count, a.following_count, a.statuses_count
                FROM follows f
                JOIN accounts a ON f.target_account_id = a.id
                WHERE f.account_id = :account_id
                ORDER BY f.created_at DESC
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
     * Get followers count for an account.
     */
    public static function getFollowersCount(int $accountId): int
    {
        $sql = "SELECT followers_count FROM accounts WHERE id = :id";
        $result = Database::fetchOne($sql, ['id' => $accountId]);
        return (int) ($result['followers_count'] ?? 0);
    }

    /**
     * Get following count for an account.
     */
    public static function getFollowingCount(int $accountId): int
    {
        $sql = "SELECT following_count FROM accounts WHERE id = :id";
        $result = Database::fetchOne($sql, ['id' => $accountId]);
        return (int) ($result['following_count'] ?? 0);
    }

    /**
     * Get follow by URI (for ActivityPub).
     */
    public static function findByUri(string $uri): ?array
    {
        $sql = "SELECT * FROM follows WHERE uri = :uri";
        return Database::fetchOne($sql, ['uri' => $uri]);
    }

    /**
     * Get mutual followers (accounts that both accounts follow).
     */
    public static function getMutualFollows(int $accountId1, int $accountId2, int $limit = 20): Generator
    {
        $sql = "SELECT a.id, a.username, a.display_name, a.avatar_url
                FROM follows f1
                JOIN follows f2 ON f1.target_account_id = f2.target_account_id
                JOIN accounts a ON f1.target_account_id = a.id
                WHERE f1.account_id = :account1 AND f2.account_id = :account2
                LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':account1', $accountId1, \PDO::PARAM_INT);
        $stmt->bindValue(':account2', $accountId2, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Get follower IDs for an account (for delivery).
     */
    public static function getFollowerIds(int $accountId): array
    {
        $sql = "SELECT account_id FROM follows WHERE target_account_id = :account_id";
        $result = Database::fetchAll($sql, ['account_id' => $accountId]);
        return array_column($result, 'account_id');
    }
}
