<?php

declare(strict_types=1);

namespace NanoPub\Models;

use NanoPub\Core\Database;
use Generator;

/**
 * FollowRequest model for locked account follow requests.
 * 
 * Handles pending follow requests for accounts that require approval.
 */
class FollowRequest
{
    /**
     * Find a follow request by ID.
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT fr.*, 
                       requester.username as requester_username,
                       requester.display_name as requester_display_name,
                       requester.avatar_url as requester_avatar_url,
                       target.username as target_username,
                       target.display_name as target_display_name
                FROM follow_requests fr
                JOIN accounts requester ON fr.account_id = requester.id
                JOIN accounts target ON fr.target_account_id = target.id
                WHERE fr.id = :id";
        
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Check if there's a pending follow request.
     */
    public static function hasPending(int $accountId, int $targetAccountId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM follow_requests 
                WHERE account_id = :account_id AND target_account_id = :target_account_id";
        
        $result = Database::fetchOne($sql, [
            'account_id' => $accountId,
            'target_account_id' => $targetAccountId,
        ]);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Create a follow request.
     */
    public static function create(int $accountId, int $targetAccountId, ?string $uri = null): int
    {
        $sql = "INSERT INTO follow_requests (account_id, target_account_id, uri, created_at)
                VALUES (:account_id, :target_account_id, :uri, NOW())";

        $params = [
            'account_id' => $accountId,
            'target_account_id' => $targetAccountId,
            'uri' => $uri,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Accept a follow request.
     * Creates the follow relationship and removes the request.
     */
    public static function accept(int $id): bool
    {
        $request = self::find($id);
        
        if (!$request) {
            return false;
        }

        // Use transaction to ensure atomicity
        $pdo = Database::getConnection();
        
        try {
            $pdo->beginTransaction();
            
            // Create the follow relationship
            Follow::create(
                $request['account_id'],
                $request['target_account_id'],
                $request['uri']
            );
            
            // Delete the follow request
            $sql = "DELETE FROM follow_requests WHERE id = :id";
            Database::execute($sql, ['id' => $id]);
            
            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reject a follow request.
     */
    public static function reject(int $id): bool
    {
        $sql = "DELETE FROM follow_requests WHERE id = :id";
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Get pending follow requests for an account (generator for memory efficiency).
     */
    public static function getPending(int $accountId, int $limit = 40): Generator
    {
        $sql = "SELECT fr.*, 
                       a.username, a.display_name, a.avatar_url, a.bio,
                       a.is_local, a.is_locked, a.is_bot
                FROM follow_requests fr
                JOIN accounts a ON fr.account_id = a.id
                WHERE fr.target_account_id = :account_id
                ORDER BY fr.created_at DESC
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
     * Get pending follow request count for an account.
     */
    public static function getPendingCount(int $accountId): int
    {
        $sql = "SELECT COUNT(*) as count FROM follow_requests WHERE target_account_id = :account_id";
        $result = Database::fetchOne($sql, ['account_id' => $accountId]);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get follow requests sent by an account.
     */
    public static function getSentRequests(int $accountId, int $limit = 40): Generator
    {
        $sql = "SELECT fr.*, 
                       a.username, a.display_name, a.avatar_url, a.is_locked
                FROM follow_requests fr
                JOIN accounts a ON fr.target_account_id = a.id
                WHERE fr.account_id = :account_id
                ORDER BY fr.created_at DESC
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
     * Find follow request by account IDs.
     */
    public static function findByAccounts(int $accountId, int $targetAccountId): ?array
    {
        $sql = "SELECT * FROM follow_requests 
                WHERE account_id = :account_id AND target_account_id = :target_account_id";
        
        return Database::fetchOne($sql, [
            'account_id' => $accountId,
            'target_account_id' => $targetAccountId,
        ]);
    }

    /**
     * Cancel a follow request (by requester).
     */
    public static function cancel(int $accountId, int $targetAccountId): bool
    {
        $sql = "DELETE FROM follow_requests WHERE account_id = :account_id AND target_account_id = :target_account_id";
        return Database::execute($sql, [
            'account_id' => $accountId,
            'target_account_id' => $targetAccountId,
        ]) > 0;
    }

    /**
     * Find follow request by URI (for ActivityPub).
     */
    public static function findByUri(string $uri): ?array
    {
        $sql = "SELECT * FROM follow_requests WHERE uri = :uri";
        return Database::fetchOne($sql, ['uri' => $uri]);
    }
}
