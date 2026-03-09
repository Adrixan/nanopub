<?php

declare(strict_types=1);

namespace NanoPub\Models\Queue;

use NanoPub\Core\Database;
use Generator;

/**
 * ActivityQueue model for incoming ActivityPub activities.
 * 
 * Handles queuing and processing of incoming federation activities.
 */
class ActivityQueue
{
    /**
     * Find a queue item by ID.
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT * FROM activity_queue WHERE id = :id";
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Enqueue a new activity for processing.
     */
    public static function enqueue(string $type, array $data, string $actor, int $priority = 0): int
    {
        $sql = "INSERT INTO activity_queue (activity_type, activity_data, actor, priority, status, created_at)
                VALUES (:type, :data, :actor, :priority, 'pending', NOW())";

        $params = [
            'type' => $type,
            'data' => json_encode($data),
            'actor' => $actor,
            'priority' => $priority,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Get the next pending activity for processing.
     * Orders by priority (higher first) then by creation time (older first).
     */
    public static function getNext(): ?array
    {
        $sql = "SELECT * FROM activity_queue 
                WHERE status = 'pending' 
                ORDER BY priority DESC, created_at ASC 
                LIMIT 1 
                FOR UPDATE SKIP LOCKED";
        
        return Database::fetchOne($sql);
    }

    /**
     * Mark an activity as processing.
     */
    public static function markProcessing(int $id): bool
    {
        $sql = "UPDATE activity_queue SET status = 'processing' WHERE id = :id AND status = 'pending'";
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Mark an activity as completed.
     */
    public static function markCompleted(int $id): bool
    {
        $sql = "UPDATE activity_queue SET status = 'completed', processed_at = NOW() WHERE id = :id";
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Mark an activity as failed.
     */
    public static function markFailed(int $id, string $error): bool
    {
        $sql = "UPDATE activity_queue SET status = 'failed', error = :error, processed_at = NOW() WHERE id = :id";
        return Database::execute($sql, ['id' => $id, 'error' => $error]) > 0;
    }

    /**
     * Increment the attempt count for an activity.
     */
    public static function incrementAttempts(int $id): int
    {
        $sql = "UPDATE activity_queue SET attempts = attempts + 1 WHERE id = :id";
        Database::execute($sql, ['id' => $id]);
        
        $item = self::find($id);
        return $item ? (int) $item['attempts'] : 0;
    }

    /**
     * Get count of pending activities.
     */
    public static function getPendingCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM activity_queue WHERE status = 'pending'";
        $result = Database::fetchOne($sql);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get failed activities (generator for memory efficiency).
     */
    public static function getFailed(int $limit = 50): Generator
    {
        $sql = "SELECT * FROM activity_queue 
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
     * Cleanup old completed/failed activities.
     */
    public static function cleanup(int $daysOld = 7): int
    {
        $sql = "DELETE FROM activity_queue 
                WHERE status IN ('completed', 'failed') 
                  AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        return Database::execute($sql, ['days' => $daysOld]);
    }

    /**
     * Retry a failed activity.
     */
    public static function retry(int $id): bool
    {
        $sql = "UPDATE activity_queue 
                SET status = 'pending', attempts = 0, error = NULL, processed_at = NULL 
                WHERE id = :id AND status = 'failed'";
        
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Get activities by type.
     */
    public static function getByType(string $type, int $limit = 50): Generator
    {
        $sql = "SELECT * FROM activity_queue 
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
     * Get activities by actor.
     */
    public static function getByActor(string $actor, int $limit = 50): Generator
    {
        $sql = "SELECT * FROM activity_queue 
                WHERE actor = :actor 
                ORDER BY created_at DESC 
                LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':actor', $actor, \PDO::PARAM_STR);
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
                FROM activity_queue 
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
}
