<?php

declare(strict_types=1);

namespace NanoPub\Models;

use NanoPub\Core\Database;
use Generator;

/**
 * Report model for user reports.
 * 
 * Handles reports of accounts and statuses.
 */
class Report
{
    /**
     * Find a report by ID.
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT r.*, 
                       reporter.username as reporter_username,
                       reporter.display_name as reporter_display_name,
                       target.username as target_username,
                       target.display_name as target_display_name,
                       action_taker.username as action_taken_by_username
                FROM reports r
                JOIN accounts reporter ON r.account_id = reporter.id
                JOIN accounts target ON r.target_account_id = target.id
                LEFT JOIN accounts action_taker ON r.action_taken_by_account_id = action_taker.id
                WHERE r.id = :id";
        
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Create a new report.
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO reports (
                    account_id, target_account_id, status_ids, comment, 
                    category, forwarded, created_at
                ) VALUES (
                    :account_id, :target_account_id, :status_ids, :comment,
                    :category, :forwarded, NOW()
                )";

        $params = [
            'account_id' => $data['account_id'],
            'target_account_id' => $data['target_account_id'],
            'status_ids' => $data['status_ids'] ?? null,
            'comment' => $data['comment'] ?? null,
            'category' => $data['category'] ?? 'other',
            'forwarded' => $data['forwarded'] ?? 0,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Update a report.
     */
    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = ['status_ids', 'comment', 'category', 'forwarded', 'action_taken_at', 'action_taken_by_account_id'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE reports SET " . implode(', ', $fields) . " WHERE id = :id";
        return Database::execute($sql, $params) > 0;
    }

    /**
     * Resolve a report (mark as action taken).
     */
    public static function resolve(int $id, int $actionTakenBy): bool
    {
        $sql = "UPDATE reports SET action_taken_at = NOW(), action_taken_by_account_id = :action_taken_by WHERE id = :id";
        return Database::execute($sql, [
            'id' => $id,
            'action_taken_by' => $actionTakenBy,
        ]) > 0;
    }

    /**
     * Reopen a report (remove action taken).
     */
    public static function reopen(int $id): bool
    {
        $sql = "UPDATE reports SET action_taken_at = NULL, action_taken_by_account_id = NULL WHERE id = :id";
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Get all reports (generator for memory efficiency).
     */
    public static function getAll(int $limit = 20, int $offset = 0): Generator
    {
        $sql = "SELECT r.*, 
                       reporter.username as reporter_username,
                       reporter.display_name as reporter_display_name,
                       target.username as target_username,
                       target.display_name as target_display_name,
                       action_taker.username as action_taken_by_username
                FROM reports r
                JOIN accounts reporter ON r.account_id = reporter.id
                JOIN accounts target ON r.target_account_id = target.id
                LEFT JOIN accounts action_taker ON r.action_taken_by_account_id = action_taker.id
                ORDER BY r.created_at DESC
                LIMIT :limit OFFSET :offset";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Get reports by reporter (generator for memory efficiency).
     */
    public static function getByAccount(int $accountId, int $limit = 20): Generator
    {
        $sql = "SELECT r.*, 
                       target.username as target_username,
                       target.display_name as target_display_name
                FROM reports r
                JOIN accounts target ON r.target_account_id = target.id
                WHERE r.account_id = :account_id
                ORDER BY r.created_at DESC
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
     * Get reports about a specific account (generator for memory efficiency).
     */
    public static function getReportsAboutAccount(int $targetAccountId, int $limit = 20): Generator
    {
        $sql = "SELECT r.*, 
                       reporter.username as reporter_username,
                       reporter.display_name as reporter_display_name
                FROM reports r
                JOIN accounts reporter ON r.account_id = reporter.id
                WHERE r.target_account_id = :target_account_id
                ORDER BY r.created_at DESC
                LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':target_account_id', $targetAccountId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Get unresolved reports (generator for memory efficiency).
     */
    public static function getUnresolved(int $limit = 20): Generator
    {
        $sql = "SELECT r.*, 
                       reporter.username as reporter_username,
                       target.username as target_username
                FROM reports r
                JOIN accounts reporter ON r.account_id = reporter.id
                JOIN accounts target ON r.target_account_id = target.id
                WHERE r.action_taken_at IS NULL
                ORDER BY r.created_at DESC
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
     * Get count of unresolved reports.
     */
    public static function getUnresolvedCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM reports WHERE action_taken_at IS NULL";
        $result = Database::fetchOne($sql);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get reports by category.
     */
    public static function getByCategory(string $category, int $limit = 20): Generator
    {
        $sql = "SELECT r.*, 
                       reporter.username as reporter_username,
                       target.username as target_username
                FROM reports r
                JOIN accounts reporter ON r.account_id = reporter.id
                JOIN accounts target ON r.target_account_id = target.id
                WHERE r.category = :category
                ORDER BY r.created_at DESC
                LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':category', $category, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Delete a report.
     */
    public static function delete(int $id): bool
    {
        $sql = "DELETE FROM reports WHERE id = :id";
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Get status IDs from a report.
     */
    public static function getStatusIds(int $reportId): array
    {
        $report = self::find($reportId);
        
        if (!$report || empty($report['status_ids'])) {
            return [];
        }

        $decoded = json_decode($report['status_ids'], true);
        return is_array($decoded) ? $decoded : [];
    }
}
