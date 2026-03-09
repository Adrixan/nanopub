<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Api\v1;

use NanoPub\Core\Request;
use NanoPub\Core\Response;

/**
 * Handles custom emoji endpoints for Mastodon API v1 compatibility.
 */
final class EmojiController
{
    /**
     * List all custom emojis.
     *
     * GET /api/v1/custom_emojis
     */
    public function index(Request $request): void
    {
        Response::json([]);
    }
}
