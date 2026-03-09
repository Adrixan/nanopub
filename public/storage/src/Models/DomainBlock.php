<?php

declare(strict_types=1);

namespace NanoPub\Models;

use NanoPub\Core\Database;
use Generator;

/**
 * DomainBlock model for instance-level domain blocking.
 * 
 * Handles blocking/silencing remote instances.
 */
class DomainBlock
{
    /**
     * Find a domain block by ID.
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT * FROM domain_blocks WHERE id = :id";
        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Find a domain block by domain name.
     */
    public static function findByDomain(string $domain): ?array
    {
        $sql = "SELECT * FROM domain_blocks WHERE domain = :domain";
        return Database::fetchOne($sql, ['domain' => $domain]);
    }

    /**
     * Check if a domain is blocked.
     */
    public static function isBlocked(string $domain): bool
    {
        $block = self::findByDomain($domain);
        return $block !== null;
    }

    /**
     * Get the severity level for a domain.
     * Returns 'silence', 'suspend', or null if not blocked.
     */
    public static function getSeverity(string $domain): ?string
    {
        $block = self::findByDomain($domain);
        return $block ? $block['severity'] : null;
    }

    /**
     * Check if a domain is suspended (full block).
     */
    public static function isSuspended(string $domain): bool
    {
        $severity = self::getSeverity($domain);
        return $severity === 'suspend';
    }

    /**
     * Check if a domain is silenced (limited visibility).
     */
    public static function isSilenced(string $domain): bool
    {
        $severity = self::getSeverity($domain);
        return $severity === 'silence';
    }

    /**
     * Create a domain block.
     */
    public static function create(string $domain, string $severity = 'suspend', ?string $reason = null): int
    {
        $sql = "INSERT INTO domain_blocks (domain, severity, reason, created_at)
                VALUES (:domain, :severity, :reason, NOW())
                ON DUPLICATE KEY UPDATE severity = :severity, reason = :reason";

        $params = [
            'domain' => strtolower(trim($domain)),
            'severity' => $severity,
            'reason' => $reason,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Update a domain block.
     */
    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = ['severity', 'reason'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE domain_blocks SET " . implode(', ', $fields) . " WHERE id = :id";
        return Database::execute($sql, $params) > 0;
    }

    /**
     * Delete a domain block.
     */
    public static function delete(int $id): bool
    {
        $sql = "DELETE FROM domain_blocks WHERE id = :id";
        return Database::execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Delete a domain block by domain name.
     */
    public static function deleteByDomain(string $domain): bool
    {
        $sql = "DELETE FROM domain_blocks WHERE domain = :domain";
        return Database::execute($sql, ['domain' => strtolower(trim($domain))]) > 0;
    }

    /**
     * Get all domain blocks (generator for memory efficiency).
     */
    public static function getAll(int $limit = 100): Generator
    {
        $sql = "SELECT * FROM domain_blocks ORDER BY created_at DESC LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Get domain blocks by severity.
     */
    public static function getBySeverity(string $severity, int $limit = 100): Generator
    {
        $sql = "SELECT * FROM domain_blocks WHERE severity = :severity ORDER BY created_at DESC LIMIT :limit";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':severity', $severity, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Get total count of domain blocks.
     */
    public static function getCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM domain_blocks";
        $result = Database::fetchOne($sql);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Check if an actor URL is from a blocked domain.
     */
    public static function isActorBlocked(string $actorUrl): bool
    {
        $domain = parse_url($actorUrl, PHP_URL_HOST);
        
        if (!$domain) {
            return false;
        }

        return self::isBlocked($domain);
    }

    /**
     * Get all blocked domains as an array.
     */
    public static function getBlockedDomains(): array
    {
        $sql = "SELECT domain, severity FROM domain_blocks";
        return Database::fetchAll($sql);
    }

    /**
     * Import domain blocks from an array.
     */
    public static function importBlocks(array $blocks): int
    {
        $count = 0;
        
        foreach ($blocks as $block) {
            $domain = $block['domain'] ?? $block['instance'] ?? null;
            $severity = $block['severity'] ?? 'suspend';
            $reason = $block['reason'] ?? null;
            
            if ($domain) {
                self::create($domain, $severity, $reason);
                $count++;
            }
        }
        
        return $count;
    }
}
