<?php

declare(strict_types=1);

namespace NanoPub\Models;

use NanoPub\Core\Database;

/**
 * Instance model for instance configuration.
 * 
 * Handles instance-level settings and statistics.
 */
class Instance
{
    /**
     * Get instance configuration.
     * Returns the first (and should be only) instance record.
     */
    public static function get(): ?array
    {
        $sql = "SELECT * FROM instance LIMIT 1";
        return Database::fetchOne($sql);
    }

    /**
     * Update instance configuration.
     */
    public static function update(array $data): bool
    {
        $instance = self::get();
        
        if (!$instance) {
            return false;
        }

        $fields = [];
        $params = ['id' => $instance['id']];

        $allowedFields = [
            'domain', 'title', 'description', 'short_description',
            'contact_email', 'admin_account_id', 'registrations_open',
            'approval_required', 'max_toot_chars', 'max_media_attachments',
            'max_image_size', 'max_video_size'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE instance SET " . implode(', ', $fields) . " WHERE id = :id";
        
        return Database::execute($sql, $params) > 0;
    }

    /**
     * Initialize instance with default values.
     */
    public static function initialize(string $domain, string $title, string $email): int
    {
        // Check if instance already exists
        $existing = self::get();
        if ($existing) {
            return (int) $existing['id'];
        }

        $sql = "INSERT INTO instance (
                    domain, title, description, short_description,
                    contact_email, registrations_open, approval_required,
                    max_toot_chars, max_media_attachments, max_image_size, max_video_size,
                    created_at, updated_at
                ) VALUES (
                    :domain, :title, '', '',
                    :email, 1, 0,
                    500, 4, 8388608, 41943040,
                    NOW(), NOW()
                )";

        $params = [
            'domain' => $domain,
            'title' => $title,
            'email' => $email,
        ];

        Database::execute($sql, $params);
        return (int) Database::lastInsertId();
    }

    /**
     * Get instance statistics.
     */
    public static function getStats(): array
    {
        $stats = [
            'user_count' => 0,
            'status_count' => 0,
            'domain_count' => 0,
        ];

        // User count (local accounts only)
        $sql = "SELECT COUNT(*) as count FROM accounts WHERE is_local = 1";
        $result = Database::fetchOne($sql);
        $stats['user_count'] = (int) ($result['count'] ?? 0);

        // Status count (local statuses only)
        $sql = "SELECT COUNT(*) as count FROM statuses WHERE local = 1";
        $result = Database::fetchOne($sql);
        $stats['status_count'] = (int) ($result['count'] ?? 0);

        // Known domains (unique domains from remote accounts)
        $sql = "SELECT COUNT(DISTINCT SUBSTRING_INDEX(actor_url, '/', 3)) as count 
                FROM accounts 
                WHERE is_local = 0 AND actor_url IS NOT NULL";
        $result = Database::fetchOne($sql);
        $stats['domain_count'] = (int) ($result['count'] ?? 0);

        return $stats;
    }

    /**
     * Get instance configuration for API responses.
     */
    public static function getConfig(): array
    {
        $instance = self::get();
        
        if (!$instance) {
            return [
                'max_toot_chars' => 500,
                'max_media_attachments' => 4,
                'max_image_size' => 8388608,
                'max_video_size' => 41943040,
                'registrations_open' => true,
                'approval_required' => false,
            ];
        }

        return [
            'max_toot_chars' => (int) $instance['max_toot_chars'],
            'max_media_attachments' => (int) $instance['max_media_attachments'],
            'max_image_size' => (int) $instance['max_image_size'],
            'max_video_size' => (int) $instance['max_video_size'],
            'registrations_open' => (bool) $instance['registrations_open'],
            'approval_required' => (bool) $instance['approval_required'],
        ];
    }

    /**
     * Get instance info for API v1 instance endpoint.
     */
    public static function getApiV1Info(): array
    {
        $instance = self::get();
        $stats = self::getStats();
        
        if (!$instance) {
            return [
                'uri' => '',
                'title' => 'NanoPub',
                'description' => '',
                'version' => '1.0.0',
                'stats' => $stats,
                'registrations' => true,
                'approval_required' => false,
            ];
        }

        return [
            'uri' => $instance['domain'],
            'title' => $instance['title'],
            'description' => $instance['description'] ?? '',
            'short_description' => $instance['short_description'] ?? '',
            'email' => $instance['contact_email'] ?? '',
            'version' => '1.0.0',
            'stats' => $stats,
            'thumbnail' => null,
            'registrations' => (bool) $instance['registrations_open'],
            'approval_required' => (bool) $instance['approval_required'],
            'configuration' => [
                'statuses' => [
                    'max_characters' => (int) $instance['max_toot_chars'],
                    'max_media_attachments' => (int) $instance['max_media_attachments'],
                ],
                'media_attachments' => [
                    'image_size_limit' => (int) $instance['max_image_size'],
                    'video_size_limit' => (int) $instance['max_video_size'],
                ],
            ],
        ];
    }

    /**
     * Set admin account.
     */
    public static function setAdminAccount(int $accountId): bool
    {
        return self::update(['admin_account_id' => $accountId]);
    }

    /**
     * Check if registrations are open.
     */
    public static function registrationsOpen(): bool
    {
        $instance = self::get();
        return $instance ? (bool) $instance['registrations_open'] : false;
    }

    /**
     * Check if approval is required for registration.
     */
    public static function approvalRequired(): bool
    {
        $instance = self::get();
        return $instance ? (bool) $instance['approval_required'] : false;
    }
}
