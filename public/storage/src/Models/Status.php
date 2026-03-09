<?php

declare(strict_types=1);

namespace NanoPub\Models;

use Generator;
use NanoPub\Core\Config;
use NanoPub\Core\Database;

/**
 * Status model for posts/toots.
 * 
 * Handles CRUD operations and timeline queries for statuses.
 */
final class Status
{
    /**
     * Find a status by ID.
     * 
     * @param int $id Status ID
     * @return array|null Status data or null if not found
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM statuses WHERE id = ?',
            [$id]
        );
    }

    /**
     * Find a status by ActivityPub URI.
     * 
     * @param string $uri ActivityPub URI
     * @return array|null Status data or null if not found
     */
    public static function findByUri(string $uri): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM statuses WHERE uri = ?',
            [$uri]
        );
    }

    /**
     * Get public timeline.
     * 
     * Returns all public statuses from local accounts.
     * 
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return Generator<int, array, mixed, void>
     */
    public static function getPublicTimeline(int $limit = 20, int $offset = 0): Generator
    {
        return Database::fetchGenerator(
            'SELECT s.*, a.username, a.display_name, a.avatar_url 
             FROM statuses s 
             INNER JOIN accounts a ON s.account_id = a.id 
             WHERE s.visibility = "public" 
             AND s.reblog_of_id IS NULL 
             AND a.is_suspended = 0 
             ORDER BY s.created_at DESC 
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    /**
     * Get home timeline for an account.
     * 
     * Returns statuses from followed accounts and own statuses.
     * 
     * @param int $accountId Account ID
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return Generator<int, array, mixed, void>
     */
    public static function getHomeTimeline(int $accountId, int $limit = 20, int $offset = 0): Generator
    {
        return Database::fetchGenerator(
            'SELECT s.*, a.username, a.display_name, a.avatar_url 
             FROM statuses s 
             INNER JOIN accounts a ON s.account_id = a.id 
             LEFT JOIN follows f ON f.target_account_id = s.account_id AND f.account_id = ?
             WHERE (f.id IS NOT NULL OR s.account_id = ?)
             AND s.visibility IN ("public", "unlisted", "private")
             AND s.reblog_of_id IS NULL 
             AND a.is_suspended = 0 
             ORDER BY s.created_at DESC 
             LIMIT ? OFFSET ?',
            [$accountId, $accountId, $limit, $offset]
        );
    }

    /**
     * Get statuses for a specific account.
     * 
     * @param int $accountId Account ID
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return Generator<int, array, mixed, void>
     */
    public static function getAccountStatuses(int $accountId, int $limit = 20, int $offset = 0): Generator
    {
        return Database::fetchGenerator(
            'SELECT * FROM statuses 
             WHERE account_id = ? 
             AND reblog_of_id IS NULL 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?',
            [$accountId, $limit, $offset]
        );
    }

    /**
     * Get replies to a status.
     * 
     * @param int $statusId Status ID
     * @param int $limit Maximum number of results
     * @return Generator<int, array, mixed, void>
     */
    public static function getReplies(int $statusId, int $limit = 50): Generator
    {
        return Database::fetchGenerator(
            'SELECT s.*, a.username, a.display_name, a.avatar_url 
             FROM statuses s 
             INNER JOIN accounts a ON s.account_id = a.id 
             WHERE s.in_reply_to_id = ? 
             ORDER BY s.created_at ASC 
             LIMIT ?',
            [$statusId, $limit]
        );
    }

    /**
     * Get context (ancestors and descendants) for a status.
     * 
     * @param int $statusId Status ID
     * @return array{ancestors: array, descendants: array}
     */
    public static function getContext(int $statusId): array
    {
        $status = self::find($statusId);
        
        if ($status === null) {
            return ['ancestors' => [], 'descendants' => []];
        }

        // Get ancestors (reply chain going up)
        $ancestors = [];
        $currentId = $status['in_reply_to_id'];
        
        while ($currentId !== null) {
            $ancestor = Database::fetchOne(
                'SELECT s.*, a.username, a.display_name, a.avatar_url 
                 FROM statuses s 
                 INNER JOIN accounts a ON s.account_id = a.id 
                 WHERE s.id = ?',
                [(int) $currentId]
            );
            
            if ($ancestor !== null) {
                array_unshift($ancestors, $ancestor);
                $currentId = $ancestor['in_reply_to_id'];
            } else {
                break;
            }
        }

        // Get descendants (replies and their replies)
        $descendants = [];
        self::fetchDescendants($statusId, $descendants);

        return [
            'ancestors' => $ancestors,
            'descendants' => $descendants,
        ];
    }

    /**
     * Recursively fetch descendants.
     * 
     * @param int $statusId Parent status ID
     * @param array $descendants Array to populate with descendants
     */
    private static function fetchDescendants(int $statusId, array &$descendants): void
    {
        $replies = Database::fetchAll(
            'SELECT s.*, a.username, a.display_name, a.avatar_url 
             FROM statuses s 
             INNER JOIN accounts a ON s.account_id = a.id 
             WHERE s.in_reply_to_id = ? 
             ORDER BY s.created_at ASC',
            [$statusId]
        );

        foreach ($replies as $reply) {
            $descendants[] = $reply;
            self::fetchDescendants((int) $reply['id'], $descendants);
        }
    }

    /**
     * Create a new status.
     * 
     * @param array $data Status data
     * @return int New status ID
     */
    public static function create(array $data): int
    {
        $fields = [];
        $placeholders = [];
        $values = [];

        $allowedFields = [
            'account_id', 'in_reply_to_id', 'in_reply_to_account_id', 'reblog_of_id',
            'content', 'content_warning', 'visibility', 'sensitive', 'language',
            'uri', 'url', 'local', 'favourites_count', 'reblogs_count', 'replies_count'
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
            'INSERT INTO statuses (%s) VALUES (%s)',
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        Database::execute($sql, $values);

        $statusId = (int) Database::lastInsertId();

        // Update account statuses count
        if (isset($data['account_id'])) {
            Account::incrementCounter((int) $data['account_id'], 'statuses_count', 1);
        }

        // Update parent replies count
        if (!empty($data['in_reply_to_id'])) {
            self::incrementCounter((int) $data['in_reply_to_id'], 'replies_count', 1);
        }

        return $statusId;
    }

    /**
     * Update a status.
     * 
     * @param int $id Status ID
     * @param array $data Data to update
     * @return int Number of affected rows
     */
    public static function update(int $id, array $data): int
    {
        $sets = [];
        $values = [];

        $allowedFields = [
            'content', 'content_warning', 'visibility', 'sensitive', 'language',
            'favourites_count', 'reblogs_count', 'replies_count'
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
            'UPDATE statuses SET %s WHERE id = ?',
            implode(', ', $sets)
        );

        return Database::execute($sql, $values);
    }

    /**
     * Delete a status.
     * 
     * @param int $id Status ID
     * @return int Number of affected rows
     */
    public static function delete(int $id): int
    {
        $status = self::find($id);
        
        if ($status === null) {
            return 0;
        }

        $affected = Database::execute(
            'DELETE FROM statuses WHERE id = ?',
            [$id]
        );

        if ($affected > 0) {
            // Update account statuses count
            Account::incrementCounter((int) $status['account_id'], 'statuses_count', -1);

            // Update parent replies count
            if ($status['in_reply_to_id'] !== null) {
                self::incrementCounter((int) $status['in_reply_to_id'], 'replies_count', -1);
            }
        }

        return $affected;
    }

    /**
     * Increment a counter field for a status.
     * 
     * @param int $id Status ID
     * @param string $counter Counter name (favourites_count, reblogs_count, replies_count)
     * @param int $amount Amount to increment (negative for decrement)
     * @return int Number of affected rows
     */
    public static function incrementCounter(int $id, string $counter, int $amount = 1): int
    {
        $allowedCounters = ['favourites_count', 'reblogs_count', 'replies_count'];
        
        if (!in_array($counter, $allowedCounters, true)) {
            return 0;
        }

        return Database::execute(
            "UPDATE statuses SET {$counter} = GREATEST(0, {$counter} + ?) WHERE id = ?",
            [$amount, $id]
        );
    }

    /**
     * Check if an account is the author of a status.
     * 
     * @param int $statusId Status ID
     * @param int $accountId Account ID
     * @return bool True if account is the author
     */
    public static function isAuthor(int $statusId, int $accountId): bool
    {
        $result = Database::fetchOne(
            'SELECT account_id FROM statuses WHERE id = ?',
            [$statusId]
        );

        return $result !== null && (int) $result['account_id'] === $accountId;
    }

    /**
     * Get media attachments for a status.
     * 
     * @param int $statusId Status ID
     * @return array<int, array> Media attachments
     */
    public static function getMedia(int $statusId): array
    {
        return Database::fetchAll(
            'SELECT * FROM media_attachments WHERE status_id = ? ORDER BY id ASC',
            [$statusId]
        );
    }

    /**
     * Convert status to ActivityPub format.
     * 
     * @param int $id Status ID
     * @return array|null ActivityPub Note object or null if not found
     */
    public static function toActivityPub(int $id): ?array
    {
        $status = self::find($id);
        
        if ($status === null) {
            return null;
        }

        $account = Account::find((int) $status['account_id']);
        
        if ($account === null) {
            return null;
        }

        $baseUrl = Config::get('app.url');
        
        $note = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id' => $status['uri'] ?? "{$baseUrl}/statuses/{$id}",
            'type' => 'Note',
            'attributedTo' => $account['actor_url'],
            'content' => $status['content'],
            'published' => date('c', strtotime($status['created_at'])),
            'to' => [],
            'cc' => [],
        ];

        // Set visibility
        switch ($status['visibility']) {
            case 'public':
                $note['to'] = ['https://www.w3.org/ns/activitystreams#Public'];
                $note['cc'] = [$account['followers_url']];
                break;
            case 'unlisted':
                $note['to'] = [$account['followers_url']];
                $note['cc'] = ['https://www.w3.org/ns/activitystreams#Public'];
                break;
            case 'private':
                $note['to'] = [$account['followers_url']];
                break;
            case 'direct':
                // Direct messages need specific recipients
                break;
        }

        // Add content warning as summary
        if (!empty($status['content_warning'])) {
            $note['summary'] = $status['content_warning'];
        }

        // Add sensitive flag
        if ($status['sensitive'] === 1) {
            $note['sensitive'] = true;
        }

        // Add inReplyTo if it's a reply
        if ($status['in_reply_to_id'] !== null) {
            $parent = self::find((int) $status['in_reply_to_id']);
            if ($parent !== null) {
                $note['inReplyTo'] = $parent['uri'] ?? "{$baseUrl}/statuses/{$parent['id']}";
            }
        }

        // Add media attachments
        $media = self::getMedia($id);
        if (!empty($media)) {
            $note['attachment'] = array_map(function ($m) use ($baseUrl) {
                return [
                    'type' => 'Document',
                    'mediaType' => $m['mime_type'],
                    'url' => $m['url'],
                    'name' => $m['description'],
                ];
            }, $media);
        }

        return $note;
    }

    /**
     * Search statuses by content.
     * 
     * @param string $query Search query
     * @param int $limit Maximum number of results
     * @return Generator<int, array, mixed, void>
     */
    public static function search(string $query, int $limit = 20): Generator
    {
        $searchTerm = "%{$query}%";

        return Database::fetchGenerator(
            'SELECT s.*, a.username, a.display_name, a.avatar_url 
             FROM statuses s 
             INNER JOIN accounts a ON s.account_id = a.id 
             WHERE s.content LIKE ? 
             AND s.visibility = "public" 
             AND a.is_suspended = 0 
             ORDER BY s.created_at DESC 
             LIMIT ?',
            [$searchTerm, $limit]
        );
    }

    /**
     * Get statuses count for an account.
     * 
     * @param int $accountId Account ID
     * @return int Number of statuses
     */
    public static function getCountForAccount(int $accountId): int
    {
        $result = Database::fetchOne(
            'SELECT COUNT(*) as count FROM statuses WHERE account_id = ?',
            [$accountId]
        );

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get total statuses count.
     * 
     * @return int Total number of statuses
     */
    public static function getTotalCount(): int
    {
        $result = Database::fetchOne(
            'SELECT COUNT(*) as count FROM statuses WHERE local = 1'
        );

        return (int) ($result['count'] ?? 0);
    }
}