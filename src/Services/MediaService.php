<?php

declare(strict_types=1);

namespace NanoPub\Services;

use NanoPub\Core\Config;
use NanoPub\Core\Database;
use NanoPub\Models\MediaAttachment;
use NanoPub\Exceptions\ValidationException;

/**
 * Media processing service for uploads.
 */
final class MediaService
{
    /**
     * Allowed image MIME types.
     */
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];
    
    /**
     * Allowed video MIME types.
     */
    private const ALLOWED_VIDEO_TYPES = [
        'video/mp4',
        'video/webm',
    ];
    
    /**
     * Maximum image size (8MB).
     */
    private const MAX_IMAGE_SIZE = 8388608;
    
    /**
     * Maximum video size (40MB).
     */
    private const MAX_VIDEO_SIZE = 41943040;
    
    /**
     * Thumbnail maximum width.
     */
    private const THUMBNAIL_MAX_WIDTH = 400;
    
    /**
     * Upload media file.
     * 
     * @param array $file Uploaded file data ($_FILES entry)
     * @param int $accountId Account ID uploading the file
     * @return array Media attachment data
     * @throws ValidationException If validation fails
     */
    public function upload(array $file, int $accountId): array
    {
        // Validate file
        $this->validate($file);
        
        // Generate unique filename
        $filename = $this->generateFilename($file['name']);
        
        // Determine storage path
        $mimeType = $this->getMimeType($file['tmp_name']);
        $type = MediaAttachment::getTypeFromMime($mimeType);
        
        $subdir = match ($type) {
            'image', 'gifv' => 'images',
            'video' => 'videos',
            'audio' => 'audio',
            default => 'media',
        };
        
        $storageDir = storage_path('uploads/media/' . $subdir);
        $destPath = $storageDir . '/' . $filename;
        
        // Ensure directory exists
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        
        // Process based on type
        $width = null;
        $height = null;
        $thumbnailPath = null;
        
        if (in_array($type, ['image', 'gifv'], true)) {
            $imageData = $this->processImage($file['tmp_name'], $destPath);
            $width = $imageData['width'];
            $height = $imageData['height'];
            $thumbnailPath = $imageData['thumbnail_path'];
        } elseif ($type === 'video') {
            $videoData = $this->processVideo($file['tmp_name'], $destPath);
            $width = $videoData['width'];
            $height = $videoData['height'];
            $thumbnailPath = $videoData['thumbnail_path'];
        } else {
            // Just move the file
            move_uploaded_file($file['tmp_name'], $destPath);
        }
        
        // Get file size
        $fileSize = filesize($destPath);
        
        // Build URLs
        $baseUrl = Config::get('app.url');
        $url = "{$baseUrl}/media/{$subdir}/{$filename}";
        $previewUrl = null;
        
        if ($thumbnailPath !== null) {
            $thumbnailName = basename($thumbnailPath);
            $previewUrl = "{$baseUrl}/media/cache/{$thumbnailName}";
        }
        
        // Save to database
        $mediaId = MediaAttachment::create([
            'account_id' => $accountId,
            'type' => $type,
            'url' => $url,
            'preview_url' => $previewUrl,
            'width' => $width,
            'height' => $height,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
        ]);
        
        return MediaAttachment::find($mediaId);
    }
    
    /**
     * Process image upload.
     * 
     * @param string $sourcePath Source file path
     * @param string $destPath Destination file path
     * @return array{width: int|null, height: int|null, thumbnail_path: string|null}
     */
    private function processImage(string $sourcePath, string $destPath): array
    {
        // Get image dimensions
        $imageInfo = getimagesize($sourcePath);
        $width = $imageInfo[0] ?? null;
        $height = $imageInfo[1] ?? null;
        
        // Move the file
        move_uploaded_file($sourcePath, $destPath);
        
        // Create thumbnail
        $thumbnailPath = $this->createThumbnail($destPath);
        
        return [
            'width' => $width,
            'height' => $height,
            'thumbnail_path' => $thumbnailPath,
        ];
    }
    
    /**
     * Process video upload.
     * 
     * @param string $sourcePath Source file path
     * @param string $destPath Destination file path
     * @return array{width: int|null, height: int|null, thumbnail_path: string|null}
     */
    private function processVideo(string $sourcePath, string $destPath): array
    {
        $width = null;
        $height = null;
        $thumbnailPath = null;
        
        // Move the file first
        move_uploaded_file($sourcePath, $destPath);
        
        // Try to get video info with ffprobe
        $ffprobeOutput = [];
        exec(
            sprintf('ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 %s 2>/dev/null', escapeshellarg($destPath)),
            $ffprobeOutput
        );
        
        if (!empty($ffprobeOutput[0])) {
            $parts = explode(',', $ffprobeOutput[0]);
            if (count($parts) === 2) {
                $width = (int) $parts[0];
                $height = (int) $parts[1];
            }
        }
        
        // Try to create thumbnail with ffmpeg
        $cacheDir = storage_path('uploads/cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $thumbnailName = generate_token(16) . '.jpg';
        $thumbnailFullPath = $cacheDir . '/' . $thumbnailName;
        
        exec(
            sprintf(
                'ffmpeg -y -i %s -ss 00:00:01 -vframes 1 -vf scale=%d:-1 %s 2>/dev/null',
                escapeshellarg($destPath),
                self::THUMBNAIL_MAX_WIDTH,
                escapeshellarg($thumbnailFullPath)
            ),
            $output,
            $returnCode
        );
        
        if ($returnCode === 0 && file_exists($thumbnailFullPath)) {
            $thumbnailPath = $thumbnailFullPath;
        }
        
        return [
            'width' => $width,
            'height' => $height,
            'thumbnail_path' => $thumbnailPath,
        ];
    }
    
    /**
     * Validate uploaded file.
     * 
     * @param array $file Uploaded file data
     * @throws ValidationException If validation fails
     */
    private function validate(array $file): void
    {
        // Check for upload errors
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        
        match ($error) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => throw new ValidationException('File is too large'),
            UPLOAD_ERR_PARTIAL => throw new ValidationException('File was only partially uploaded'),
            UPLOAD_ERR_NO_FILE => throw new ValidationException('No file was uploaded'),
            UPLOAD_ERR_NO_TMP_DIR => throw new ValidationException('Missing temporary folder'),
            UPLOAD_ERR_CANT_WRITE => throw new ValidationException('Failed to write file to disk'),
            default => throw new ValidationException('Unknown upload error'),
        };
        
        // Check file size
        $fileSize = $file['size'] ?? 0;
        $mimeType = $this->getMimeType($file['tmp_name']);
        
        if (in_array($mimeType, self::ALLOWED_IMAGE_TYPES, true)) {
            if ($fileSize > self::MAX_IMAGE_SIZE) {
                throw new ValidationException('Image file is too large (max 8MB)');
            }
        } elseif (in_array($mimeType, self::ALLOWED_VIDEO_TYPES, true)) {
            if ($fileSize > self::MAX_VIDEO_SIZE) {
                throw new ValidationException('Video file is too large (max 40MB)');
            }
        } else {
            throw new ValidationException('Unsupported file type');
        }
        
        // Verify file content matches extension
        $allowedTypes = array_merge(self::ALLOWED_IMAGE_TYPES, self::ALLOWED_VIDEO_TYPES);
        if (!in_array($mimeType, $allowedTypes, true)) {
            throw new ValidationException('Invalid file type');
        }
    }
    
    /**
     * Generate unique filename.
     * 
     * @param string $originalName Original filename
     * @return string Unique filename
     */
    private function generateFilename(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        if (empty($extension)) {
            $extension = 'bin';
        }
        
        $extension = strtolower($extension);
        
        // Validate extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];
        if (!in_array($extension, $allowedExtensions, true)) {
            $extension = 'bin';
        }
        
        return generate_token(32) . '.' . $extension;
    }
    
    /**
     * Get MIME type from file.
     * 
     * @param string $filePath File path
     * @return string MIME type
     */
    private function getMimeType(string $filePath): string
    {
        // Use finfo for accurate MIME detection
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        return $mimeType ?: 'application/octet-stream';
    }
    
    /**
     * Get storage path for media.
     * 
     * @param string $filename Filename
     * @param string $type Media type (image, video, audio)
     * @return string Full storage path
     */
    public function getStoragePath(string $filename, string $type = 'media'): string
    {
        $subdir = match ($type) {
            'image', 'gifv' => 'images',
            'video' => 'videos',
            'audio' => 'audio',
            default => 'media',
        };
        
        return storage_path('uploads/media/' . $subdir . '/' . $filename);
    }
    
    /**
     * Get URL for media.
     * 
     * @param string $filename Filename
     * @param string $type Media type
     * @return string Public URL
     */
    public function getUrl(string $filename, string $type = 'media'): string
    {
        $subdir = match ($type) {
            'image', 'gifv' => 'images',
            'video' => 'videos',
            'audio' => 'audio',
            default => 'media',
        };
        
        $baseUrl = Config::get('app.url');
        return "{$baseUrl}/media/{$subdir}/{$filename}";
    }
    
    /**
     * Delete media file.
     * 
     * @param int $mediaId Media attachment ID
     * @return bool True on success
     */
    public function delete(int $mediaId): bool
    {
        return MediaAttachment::delete($mediaId) > 0;
    }
    
    /**
     * Create thumbnail from image.
     * 
     * @param string $sourcePath Source image path
     * @param int $maxWidth Maximum thumbnail width
     * @return string|null Thumbnail path or null on failure
     */
    private function createThumbnail(string $sourcePath, int $maxWidth = self::THUMBNAIL_MAX_WIDTH): ?string
    {
        // Get image info
        $imageInfo = getimagesize($sourcePath);
        
        if ($imageInfo === false) {
            return null;
        }
        
        $mimeType = $imageInfo['mime'];
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // Calculate new dimensions
        $ratio = $width / $height;
        $newWidth = min($maxWidth, $width);
        $newHeight = (int) ($newWidth / $ratio);
        
        // Create source image
        $sourceImage = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/gif' => imagecreatefromgif($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => null,
        };
        
        if ($sourceImage === false) {
            return null;
        }
        
        // Create thumbnail
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        
        if ($thumbnail === false) {
            imagedestroy($sourceImage);
            return null;
        }
        
        // Preserve transparency for PNG/GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
            imagefill($thumbnail, 0, 0, $transparent);
        }
        
        // Resize
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save thumbnail
        $cacheDir = storage_path('uploads/cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $thumbnailName = generate_token(16) . '.jpg';
        $thumbnailPath = $cacheDir . '/' . $thumbnailName;
        
        $saved = imagejpeg($thumbnail, $thumbnailPath, 85);
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
        
        return $saved ? $thumbnailPath : null;
    }
    
    /**
     * Attach media to a status.
     * 
     * @param int $mediaId Media attachment ID
     * @param int $statusId Status ID
     * @return bool True on success
     */
    public function attachToStatus(int $mediaId, int $statusId): bool
    {
        return MediaAttachment::attachToStatus($mediaId, $statusId) > 0;
    }
    
    /**
     * Get unattached media for an account.
     * 
     * @param int $accountId Account ID
     * @return array Media attachments
     */
    public function getUnattachedForAccount(int $accountId): array
    {
        return MediaAttachment::getUnattachedForAccount($accountId);
    }
    
    /**
     * Update media description.
     * 
     * @param int $mediaId Media attachment ID
     * @param string $description Alt text description
     * @return bool True on success
     */
    public function updateDescription(int $mediaId, string $description): bool
    {
        return MediaAttachment::update($mediaId, ['description' => $description]) > 0;
    }
    
    /**
     * Get storage usage for an account.
     * 
     * @param int $accountId Account ID
     * @return int Bytes used
     */
    public function getStorageUsage(int $accountId): int
    {
        return MediaAttachment::getStorageForAccount($accountId);
    }
}
