<?php

declare(strict_types=1);

namespace NanoPub\Models;

use NanoPub\Core\Config;
use NanoPub\Core\Database;

/**
 * MediaAttachment model for file attachments.
 * 
 * Handles CRUD operations for media files attached to statuses.
 */
final class MediaAttachment
{
    /**
     * Find a media attachment by ID.
     * 
     * @param int $id Media attachment ID
     * @return array|null Media attachment data or null if not found
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM media_attachments WHERE id = ?',
            [$id]
        );
    }

    /**
     * Find all media attachments for a status.
     * 
     * @param int $statusId Status ID
     * @return array<int, array> Media attachments
     */
    public static function findByStatusId(int $statusId): array
    {
        return Database::fetchAll(
            'SELECT * FROM media_attachments WHERE status_id = ? ORDER BY id ASC',
            [$statusId]
        );
    }

    /**
     * Create a new media attachment.
     * 
     * @param array $data Media attachment data
     * @return int New media attachment ID
     */
    public static function create(array $data): int
    {
        $fields = [];
        $placeholders = [];
        $values = [];

        $allowedFields = [
            'status_id', 'account_id', 'type', 'url', 'remote_url',
            'preview_url', 'description', 'width', 'height',
            'file_size', 'mime_type'
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
            'INSERT INTO media_attachments (%s) VALUES (%s)',
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        Database::execute($sql, $values);

        return (int) Database::lastInsertId();
    }

    /**
     * Update a media attachment.
     * 
     * @param int $id Media attachment ID
     * @param array $data Data to update
     * @return int Number of affected rows
     */
    public static function update(int $id, array $data): int
    {
        $sets = [];
        $values = [];

        $allowedFields = [
            'status_id', 'type', 'url', 'remote_url',
            'preview_url', 'description', 'width', 'height',
            'file_size', 'mime_type'
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
            'UPDATE media_attachments SET %s WHERE id = ?',
            implode(', ', $sets)
        );

        return Database::execute($sql, $values);
    }

    /**
     * Delete a media attachment.
     * 
     * @param int $id Media attachment ID
     * @return int Number of affected rows
     */
    public static function delete(int $id): int
    {
        $media = self::find($id);
        
        if ($media === null) {
            return 0;
        }

        // Delete the physical file
        $filePath = self::getFilePath($id);
        if ($filePath !== null && file_exists($filePath)) {
            @unlink($filePath);
        }

        // Delete preview file if exists
        if (!empty($media['preview_url'])) {
            $previewPath = storage_path('uploads/cache/' . basename($media['preview_url']));
            if (file_exists($previewPath)) {
                @unlink($previewPath);
            }
        }

        return Database::execute(
            'DELETE FROM media_attachments WHERE id = ?',
            [$id]
        );
    }

    /**
     * Delete all media attachments for a status.
     * 
     * @param int $statusId Status ID
     * @return int Number of deleted attachments
     */
    public static function deleteByStatusId(int $statusId): int
    {
        $media = self::findByStatusId($statusId);
        $count = 0;

        foreach ($media as $item) {
            if (self::delete((int) $item['id']) > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get the file path for a media attachment.
     * 
     * @param int $id Media attachment ID
     * @return string|null Full file path or null if not found
     */
    public static function getFilePath(int $id): ?string
    {
        $media = self::find($id);
        
        if ($media === null || empty($media['url'])) {
            return null;
        }

        // Extract filename from URL
        $filename = basename(parse_url($media['url'], PHP_URL_PATH));
        
        if (empty($filename)) {
            return null;
        }

        // Determine subdirectory based on type
        $subdir = match ($media['type']) {
            'image' => 'images',
            'video', 'gifv' => 'videos',
            'audio' => 'audio',
            default => 'media',
        };

        return storage_path('uploads/media/' . $subdir . '/' . $filename);
    }

    /**
     * Get media attachments for an account (not attached to any status).
     * 
     * @param int $accountId Account ID
     * @return array<int, array> Media attachments
     */
    public static function getUnattachedForAccount(int $accountId): array
    {
        return Database::fetchAll(
            'SELECT * FROM media_attachments WHERE account_id = ? AND status_id IS NULL ORDER BY created_at DESC',
            [$accountId]
        );
    }

    /**
     * Attach media to a status.
     * 
     * @param int $mediaId Media attachment ID
     * @param int $statusId Status ID
     * @return int Number of affected rows
     */
    public static function attachToStatus(int $mediaId, int $statusId): int
    {
        return Database::execute(
            'UPDATE media_attachments SET status_id = ? WHERE id = ?',
            [$statusId, $mediaId]
        );
    }

    /**
     * Get media type from MIME type.
     * 
     * @param string $mimeType MIME type
     * @return string Media type (image, video, gifv, audio, unknown)
     */
    public static function getTypeFromMime(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/gif') => 'gifv',
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'unknown',
        };
    }

    /**
     * Convert media attachment to ActivityPub format.
     * 
     * @param int $id Media attachment ID
     * @return array|null ActivityPub Document object or null if not found
     */
    public static function toActivityPub(int $id): ?array
    {
        $media = self::find($id);
        
        if ($media === null) {
            return null;
        }

        $document = [
            'type' => 'Document',
            'mediaType' => $media['mime_type'],
            'url' => $media['url'],
        ];

        if (!empty($media['description'])) {
            $document['name'] = $media['description'];
        }

        if (!empty($media['width']) && !empty($media['height'])) {
            $document['width'] = (int) $media['width'];
            $document['height'] = (int) $media['height'];
        }

        return $document;
    }

    /**
     * Get total storage used by media attachments.
     * 
     * @return int Total bytes used
     */
    public static function getTotalStorage(): int
    {
        $result = Database::fetchOne(
            'SELECT SUM(file_size) as total FROM media_attachments'
        );

        return (int) ($result['total'] ?? 0);
    }

    /**
     * Get storage used by an account.
     * 
     * @param int $accountId Account ID
     * @return int Total bytes used
     */
    public static function getStorageForAccount(int $accountId): int
    {
        $result = Database::fetchOne(
            'SELECT SUM(file_size) as total FROM media_attachments WHERE account_id = ?',
            [$accountId]
        );

        return (int) ($result['total'] ?? 0);
    }
}