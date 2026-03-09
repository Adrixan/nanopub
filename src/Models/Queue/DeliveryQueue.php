<?php

declare(strict_types=1);

namespace NanoPub\Models\Queue;

use NanoPub\Core\Database;
use Generator;

/**
 * DeliveryQueue model for outgoing ActivityPub deliveries.
 * 
 * Handles queuing and processing of outgoing federation activities.
 */
class DeliveryQueue
{
    /**
     * Find a queue item by ID.
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT * FROM delivery_queue WHERE id = :id";
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Enqueue a new delivery for processing.
     */
    public static function enqueue(
        string $activityId,
        string $type,
        array $data,
        string $inbox,
        int $signingAccountId,
        int $priority = 0
    ): int {
        $sql = "INSERT INTO delivery_queue (
                    activity_id, activity_type, activity_data, target_inbox,
                    signing_account_id, priority, status, created_at
                ) VALUES (
                    :activity_id, :type, :data, :inbox,
                    :signing_account_id, :priority, 'pending', NOW()
                )";

        $params = [
            'activity_id' => $activityId,
            'type' => $type,
            'data' => json_encode($data),
            'inbox' => $inbox,
            'signing_account_id' => $signingAccountId,
            'priority' => $priority,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Get the next pending delivery for processing.
     * Orders by priority (higher first) then by creation time (older first).
     */
    public static function getNext(): ?array
    {
        $sql = "SELECT * FROM delivery_queue 
                WHERE status = 'pending' 
                ORDER BY priority DESC, created_at ASC 
                LIMIT 1 
                FOR UPDATE SKIP LOCKED";
        
        return Database::fetchOne($sql);
    }

    /**
     * Mark a delivery as processing.
     */
    public static function markProcessing(int $id): bool
    {
        $sql = "UPDATE delivery_queue SET status = 'processing' WHERE id = :id AND status = 'pending'";
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Mark a delivery as completed.
     */
    public static function markCompleted(int $id): bool
    {
        $sql = "UPDATE delivery_queue SET status = 'completed', processed_at = NOW() WHERE id = :id";
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Mark a delivery as failed.
     */
    public static function markFailed(int $id, string $error): bool
    {
        $sql = "UPDATE delivery_queue SET status = 'failed', error = :error, processed_at = NOW() WHERE id = :id";
        return Database::execute($sql, ['id' => $id, 'error' => $error]) > 0;
    }

    /**
     * Increment the attempt count for a delivery.
     */
    public static function incrementAttempts(int $id): int
    {
        $sql = "UPDATE delivery_queue SET attempts = attempts + 1 WHERE id = :id";
        Database::execute($sql, ['id' => $id]);
        
        $item = self::find($id);
        return $item ? (int) $item['attempts'] : 0;
    }

    /**
     * Get count of pending deliveries.
     */
    public static function getPendingCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM delivery_queue WHERE status = 'pending'";
        $result = Database::fetchOne($sql);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get failed deliveries (generator for memory efficiency).
     */
    public static function getFailed(int $limit = 50): Generator
    {
        $sql = "SELECT * FROM delivery_queue 
                WHERE status = 'failed' 
                ORDER BY created_at DESC 
                LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Cleanup old completed/failed deliveries.
     */
    public static function cleanup(int $daysOld = 7): int
    {
        $sql = "DELETE FROM delivery_queue 
                WHERE status IN ('completed', 'failed') 
                  AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        return Database::execute($sql, ['days' => $daysOld]);
    }

    /**
     * Retry a failed delivery.
     */
    public static function retry(int $id): bool
    {
        $sql = "UPDATE delivery_queue 
                SET status = 'pending', attempts = 0, error = NULL, processed_at = NULL 
                WHERE id = :id AND status = 'failed'";
        
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Get deliveries by activity type.
     */
    public static function getByType(string $type, int $limit = 50): Generator
    {
        $sql = "SELECT * FROM delivery_queue 
                WHERE activity_type = :type 
                ORDER BY created_at DESC 
                LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':type', $type, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Get deliveries by target inbox.
     */
    public static function getByInbox(string $inbox, int $limit = 50): Generator
    {
        $sql = "SELECT * FROM delivery_queue 
                WHERE target_inbox = :inbox 
                ORDER BY created_at DESC 
                LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':inbox', $inbox, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Get deliveries by signing account.
     */
    public static function getBySigningAccount(int $accountId, int $limit = 50): Generator
    {
        $sql = "SELECT * FROM delivery_queue 
                WHERE signing_account_id = :account_id 
                ORDER BY created_at DESC 
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
     * Get queue statistics.
     */
    public static function getStats(): array
    {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    AVG(attempts) as avg_attempts
                FROM delivery_queue 
                GROUP BY status";
        
        $results = Database::fetchAll($sql);
        
        $stats = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];
        
        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }
        
        return $stats;
    }

    /**
     * Get activity data as array.
     */
    public static function getActivityData(int $id): ?array
    {
        $item = self::find($id);
        
        if (!$item || empty($item['activity_data'])) {
            return null;
        }
        
        return json_decode($item['activity_data'], true);
    }

    /**
     * Enqueue delivery to multiple inboxes (fan-out).
     */
    public static function enqueueFanOut(
        string $activityId,
        string $type,
        array $data,
        array $inboxes,
        int $signingAccountId,
        int $priority = 0
    ): int {
        $count = 0;
        
        foreach ($inboxes as $inbox) {
            self::enqueue($activityId, $type, $data, $inbox, $signingAccountId, $priority);
            $count++;
        }
        
        return $count;
    }

    /**
     * Get pending deliveries for a specific activity.
     */
    public static function getPendingForActivity(string $activityId): array
    {
        $sql = "SELECT * FROM delivery_queue 
                WHERE activity_id = :activity_id AND status = 'pending'";
        
        return Database::fetchAll($sql, ['activity_id' => $activityId]);
    }

    /**
     * Cancel all pending deliveries for an activity.
     */
    public static function cancelForActivity(string $activityId): int
    {
        $sql = "DELETE FROM delivery_queue WHERE activity_id = :activity_id AND status = 'pending'";
        return Database::execute($sql, ['activity_id' => $activityId]);
    }
}
