<?php

declare(strict_types=1);

namespace NanoPub\Models;

use Generator;
use NanoPub\Core\Database;

/**
 * Account model for user management.
 * 
 * Handles CRUD operations and relationships for accounts.
 */
final class Account
{
    /**
     * Find an account by ID.
     * 
     * @param int $id Account ID
     * @return array|null Account data or null if not found
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM accounts WHERE id = ?',
            [$id]
        );
    }

    /**
     * Find an account by username.
     * 
     * @param string $username Username to search for
     * @return array|null Account data or null if not found
     */
    public static function findByUsername(string $username): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM accounts WHERE username = ?',
            [$username]
        );
    }

    /**
     * Find an account by email address.
     * 
     * @param string $email Email address to search for
     * @return array|null Account data or null if not found
     */
    public static function findByEmail(string $email): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM accounts WHERE email = ?',
            [$email]
        );
    }

    /**
     * Find an account by ActivityPub actor URL.
     * 
     * @param string $url Actor URL to search for
     * @return array|null Account data or null if not found
     */
    public static function findByActorUrl(string $url): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM accounts WHERE actor_url = ?',
            [$url]
        );
    }

    /**
     * Get all accounts with pagination.
     * 
     * Uses generator for memory efficiency.
     * 
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return Generator<int, array, mixed, void>
     */
    public static function findAll(int $limit = 50, int $offset = 0): Generator
    {
        return Database::fetchGenerator(
            'SELECT * FROM accounts ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    /**
     * Create a new account.
     * 
     * @param array $data Account data
     * @return int New account ID
     */
    public static function create(array $data): int
    {
        $fields = [];
        $placeholders = [];
        $values = [];

        $allowedFields = [
            'username', 'display_name', 'email', 'password_hash', 'bio',
            'avatar_url', 'header_url', 'private_key', 'public_key',
            'actor_url', 'inbox_url', 'outbox_url', 'followers_url', 'following_url',
            'is_local', 'is_locked', 'is_bot', 'is_suspended', 'is_admin', 'is_moderator',
            'followers_count', 'following_count', 'statuses_count', 'last_activity_at'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return 0;
        }

        $sql = sprintf(
            'INSERT INTO accounts (%s) VALUES (%s)',
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        Database::execute($sql, $values);

        return (int) Database::lastInsertId();
    }

    /**
     * Update an account.
     * 
     * @param int $id Account ID
     * @param array $data Data to update
     * @return int Number of affected rows
     */
    public static function update(int $id, array $data): int
    {
        $sets = [];
        $values = [];

        $allowedFields = [
            'username', 'display_name', 'email', 'password_hash', 'bio',
            'avatar_url', 'header_url', 'private_key', 'public_key',
            'actor_url', 'inbox_url', 'outbox_url', 'followers_url', 'following_url',
            'is_local', 'is_locked', 'is_bot', 'is_suspended', 'is_admin', 'is_moderator',
            'followers_count', 'following_count', 'statuses_count', 'last_activity_at'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $sets[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($sets)) {
            return 0;
        }

        $values[] = $id;

        $sql = sprintf(
            'UPDATE accounts SET %s WHERE id = ?',
            implode(', ', $sets)
        );

        return Database::execute($sql, $values);
    }

    /**
     * Delete an account.
     * 
     * @param int $id Account ID
     * @return int Number of affected rows
     */
    public static function delete(int $id): int
    {
        return Database::execute(
            'DELETE FROM accounts WHERE id = ?',
            [$id]
        );
    }

    /**
     * Get followers for an account.
     * 
     * @param int $id Account ID
     * @param int $limit Maximum number of results
     * @return Generator<int, array, mixed, void>
     */
    public static function getFollowers(int $id, int $limit = 50): Generator
    {
        return Database::fetchGenerator(
            'SELECT a.* FROM accounts a 
             INNER JOIN follows f ON a.id = f.account_id 
             WHERE f.target_account_id = ? 
             ORDER BY f.created_at DESC 
             LIMIT ?',
            [$id, $limit]
        );
    }

    /**
     * Get accounts that the given account is following.
     * 
     * @param int $id Account ID
     * @param int $limit Maximum number of results
     * @return Generator<int, array, mixed, void>
     */
    public static function getFollowing(int $id, int $limit = 50): Generator
    {
        return Database::fetchGenerator(
            'SELECT a.* FROM accounts a 
             INNER JOIN follows f ON a.id = f.target_account_id 
             WHERE f.account_id = ? 
             ORDER BY f.created_at DESC 
             LIMIT ?',
            [$id, $limit]
        );
    }

    /**
     * Get statuses for an account.
     * 
     * @param int $id Account ID
     * @param int $limit Maximum number of results
     * @return Generator<int, array, mixed, void>
     */
    public static function getStatuses(int $id, int $limit = 50): Generator
    {
        return Database::fetchGenerator(
            'SELECT * FROM statuses 
             WHERE account_id = ? AND visibility IN ("public", "unlisted") 
             ORDER BY created_at DESC 
             LIMIT ?',
            [$id, $limit]
        );
    }

    /**
     * Increment a counter field for an account.
     * 
     * @param int $id Account ID
     * @param string $counter Counter name (followers_count, following_count, statuses_count)
     * @param int $amount Amount to increment (negative for decrement)
     * @return int Number of affected rows
     */
    public static function incrementCounter(int $id, string $counter, int $amount = 1): int
    {
        $allowedCounters = ['followers_count', 'following_count', 'statuses_count'];
        
        if (!in_array($counter, $allowedCounters, true)) {
            return 0;
        }

        $amount = (int) $amount;

        return Database::execute(
            "UPDATE accounts SET {$counter} = GREATEST(0, {$counter} + ?) WHERE id = ?",
            [$amount, $id]
        );
    }

    /**
     * Verify a password for an account.
     * 
     * @param int $id Account ID
     * @param string $password Password to verify
     * @return bool True if password matches
     */
    public static function verifyPassword(int $id, string $password): bool
    {
        $account = self::find($id);
        
        if ($account === null || empty($account['password_hash'])) {
            return false;
        }

        return verify_password($password, $account['password_hash']);
    }

    /**
     * Generate RSA key pair for ActivityPub.
     * 
     * @param int $id Account ID
     * @return bool True if keys were generated and stored
     */
    public static function generateKeys(int $id): bool
    {
        $keyPair = generate_rsa_key_pair();

        $affected = self::update($id, [
            'private_key' => $keyPair['private'],
            'public_key' => $keyPair['public'],
        ]);

        return $affected > 0;
    }

    /**
     * Get public key for an account.
     * 
     * @param int $id Account ID
     * @return string|null Public key in PEM format or null if not found
     */
    public static function getPublicKey(int $id): ?string
    {
        $result = Database::fetchOne(
            'SELECT public_key FROM accounts WHERE id = ?',
            [$id]
        );

        return $result['public_key'] ?? null;
    }

    /**
     * Check if an account is local.
     * 
     * @param int $id Account ID
     * @return bool True if account is local
     */
    public static function isLocal(int $id): bool
    {
        $result = Database::fetchOne(
            'SELECT is_local FROM accounts WHERE id = ?',
            [$id]
        );

        return ($result['is_local'] ?? 0) === 1;
    }

    /**
     * Check if an account is suspended.
     * 
     * @param int $id Account ID
     * @return bool True if account is suspended
     */
    public static function isSuspended(int $id): bool
    {
        $result = Database::fetchOne(
            'SELECT is_suspended FROM accounts WHERE id = ?',
            [$id]
        );

        return ($result['is_suspended'] ?? 0) === 1;
    }

    /**
     * Check if an account is locked (requires follow approval).
     * 
     * @param int $id Account ID
     * @return bool True if account is locked
     */
    public static function isLocked(int $id): bool
    {
        $result = Database::fetchOne(
            'SELECT is_locked FROM accounts WHERE id = ?',
            [$id]
        );

        return ($result['is_locked'] ?? 0) === 1;
    }

    /**
     * Update last activity timestamp.
     * 
     * @param int $id Account ID
     * @return int Number of affected rows
     */
    public static function updateLastActivity(int $id): int
    {
        return Database::execute(
            'UPDATE accounts SET last_activity_at = NOW() WHERE id = ?',
            [$id]
        );
    }

    /**
     * Search accounts by username or display name.
     * 
     * @param string $query Search query
     * @param int $limit Maximum number of results
     * @return Generator<int, array, mixed, void>
     */
    public static function search(string $query, int $limit = 20): Generator
    {
        $searchTerm = "%{$query}%";

        return Database::fetchGenerator(
            'SELECT * FROM accounts 
             WHERE (username LIKE ? OR display_name LIKE ?) 
             AND is_suspended = 0 
             ORDER BY 
                 CASE WHEN username = ? THEN 0 ELSE 1 END,
                 followers_count DESC 
             LIMIT ?',
            [$searchTerm, $searchTerm, $query, $limit]
        );
    }

    /**
     * Get local account count.
     * 
     * @return int Number of local accounts
     */
    public static function getLocalCount(): int
    {
        $result = Database::fetchOne(
            'SELECT COUNT(*) as count FROM accounts WHERE is_local = 1'
        );

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get total account count.
     * 
     * @return int Total number of accounts
     */
    public static function getTotalCount(): int
    {
        $result = Database::fetchOne(
            'SELECT COUNT(*) as count FROM accounts'
        );

        return (int) ($result['count'] ?? 0);
    }
}