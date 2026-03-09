<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Api\v1;

use NanoPub\Core\Config;
use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Models\MediaAttachment;
use NanoPub\Services\MediaService;
use NanoPub\Exceptions\NotFoundException;
use NanoPub\Exceptions\ValidationException;

/**
 * Handles media upload API endpoints for Mastodon API v1 compatibility.
 */
final class MediaController
{
    private MediaService $mediaService;

    public function __construct()
    {
        $this->mediaService = new MediaService();
    }

    /**
     * Upload a new media file.
     * 
     * POST /api/v1/media
     */
    public function create(Request $request): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        // Check if file was uploaded
        if (empty($request->files['file'])) {
            Response::badRequest('No file uploaded');
            return;
        }

        $file = $request->files['file'];

        try {
            $media = $this->mediaService->upload($file, (int) $accountId);
        } catch (ValidationException $e) {
            Response::badRequest($e->getMessage());
            return;
        }

        Response::json($this->formatMedia($media), 200);
    }

    /**
     * Get media attachment by ID.
     * 
     * GET /api/v1/media/:id
     */
    public function show(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $media = MediaAttachment::find($id);

        if ($media === null) {
            throw new NotFoundException('Media not found');
        }

        // Verify ownership
        if ((int) $media['account_id'] !== (int) $accountId) {
            throw new NotFoundException('Media not found');
        }

        Response::json($this->formatMedia($media));
    }

    /**
     * Update media attachment description.
     * 
     * PUT /api/v1/media/:id
     */
    public function update(Request $request, int $id): void
    {
        $accountId = $request->getAttribute('account_id');

        if ($accountId === null) {
            Response::unauthorized('Unauthorized');
            return;
        }

        $media = MediaAttachment::find($id);

        if ($media === null) {
            throw new NotFoundException('Media not found');
        }

        // Verify ownership
        if ((int) $media['account_id'] !== (int) $accountId) {
            throw new NotFoundException('Media not found');
        }

        // Check if already attached to a status
        if ($media['status_id'] !== null) {
            Response::badRequest('Cannot update media that is already attached to a status');
            return;
        }

        $data = $request->json();

        // Update description (alt text)
        if (isset($data['description'])) {
            $this->mediaService->updateDescription($id, trim($data['description']));
        }

        // Refresh media data
        $media = MediaAttachment::find($id);

        Response::json($this->formatMedia($media));
    }

    /**
     * Format media attachment for Mastodon API response.
     * 
     * @param array $media Media attachment data
     * @return array Formatted media attachment
     */
    private function formatMedia(array $media): array
    {
        $baseUrl = Config::get('app.url') ?? '';

        $formatted = [
            'id' => (string) $media['id'],
            'type' => $media['type'] ?? $this->getTypeFromMime($media['mime_type'] ?? ''),
            'url' => $media['url'],
            'preview_url' => $media['preview_url'] ?? $media['url'],
            'remote_url' => $media['remote_url'] ?? null,
            'text_url' => $media['url'],
            'meta' => null,
            'description' => $media['description'] ?? '',
            'blurhash' => null,
        ];

        // Add meta information if dimensions are available
        if (!empty($media['width']) && !empty($media['height'])) {
            $width = (int) $media['width'];
            $height = (int) $media['height'];

            $formatted['meta'] = [
                'original' => [
                    'width' => $width,
                    'height' => $height,
                    'size' => "{$width}x{$height}",
                    'aspect' => (float) $width / max(1, $height),
                ],
                'small' => [
                    'width' => min(400, $width),
                    'height' => (int) round(min(400, $width) * $height / max(1, $width)),
                    'size' => min(400, $width) . 'x' . (int) round(min(400, $width) * $height / max(1, $width)),
                    'aspect' => (float) $width / max(1, $height),
                ],
            ];

            // Add focus point if available (for thumbnail positioning)
            $formatted['meta']['focus'] = [
                'x' => 0.0,
                'y' => 0.0,
            ];
        }

        // Add file size info
        if (!empty($media['file_size'])) {
            $formatted['meta']['original']['size'] = $media['file_size'];
        }

        return $formatted;
    }

    /**
     * Get media type from MIME type.
     * 
     * @param string $mimeType MIME type
     * @return string Media type (image, video, audio, unknown)
     */
    private function getTypeFromMime(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'unknown',
        };
    }
}