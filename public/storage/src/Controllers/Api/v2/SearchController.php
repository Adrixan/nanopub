<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Api\v2;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Services\SearchService;

/**
 * Handles search API endpoints for Mastodon API v2 compatibility.
 */
final class SearchController
{
    private SearchService $searchService;

    public function __construct()
    {
        $this->searchService = new SearchService();
    }

    /**
     * Search accounts, statuses, hashtags.
     * 
     * GET /api/v2/search
     */
    public function index(Request $request): void
    {
        $accountId = $request->getAttribute('account_id');
        $query = $request->query['q'] ?? '';
        $type = $request->query['type'] ?? 'all'; // accounts, statuses, hashtags, all
        $limit = min((int) ($request->query['limit'] ?? 20), 40);
        $offset = (int) ($request->query['offset'] ?? 0);
        $resolve = ($request->query['resolve'] ?? 'false') === 'true';

        if (empty($query)) {
            Response::json([
                'accounts' => [],
                'statuses' => [],
                'hashtags' => [],
            ]);
            return;
        }

        $results = $this->searchService->search(
            $query,
            $accountId !== null ? (int) $accountId : null,
            $type,
            $limit,
            $offset,
            $resolve
        );

        Response::json($results);
    }
}
