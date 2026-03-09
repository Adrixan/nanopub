<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Api\v1;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Services\SearchService;

/**
 * Handles search endpoints for Mastodon API v1 compatibility.
 */
final class SearchController
{
    private SearchService $searchService;

    public function __construct()
    {
        $this->searchService = new SearchService();
    }

    /**
     * Perform a search.
     *
     * GET /api/v1/search
     *
     * @param Request $request The incoming request
     */
    public function index(Request $request): void
    {
        $query = trim($request->query['q'] ?? '');
        $type = $request->query['type'] ?? 'all';
        $limit = min((int) ($request->query['limit'] ?? 20), 40);

        if ($query === '') {
            Response::json([
                'accounts' => [],
                'statuses' => [],
                'hashtags' => [],
            ]);
            return;
        }

        $accountId = $request->getAttribute('account_id');
        $results = $this->searchService->search(
            $query,
            $accountId !== null ? (int) $accountId : 0,
            $type,
            $limit
        );

        Response::json($results);
    }
}
