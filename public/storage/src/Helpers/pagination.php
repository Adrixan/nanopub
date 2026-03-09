<?php

declare(strict_types=1);

/**
 * NanoPub Pagination Utilities
 * 
 * Pagination utilities for API and display.
 */

/**
 * Calculate pagination metadata.
 * 
 * @param int $page Current page number (1-indexed)
 * @param int $perPage Items per page
 * @param int $total Total number of items
 * @return array{page: int, per_page: int, total: int, total_pages: int, has_next: bool, has_prev: bool, next: int|null, prev: int|null} Pagination metadata
 */
function paginate(int $page, int $perPage, int $total): array
{
    // Ensure valid values
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $total = max(0, $total);
    
    // Calculate total pages
    $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
    
    // Clamp page to valid range
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    
    // Calculate navigation
    $hasNext = $page < $totalPages;
    $hasPrev = $page > 1;
    
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'has_next' => $hasNext,
        'has_prev' => $hasPrev,
        'next' => $hasNext ? $page + 1 : null,
        'prev' => $hasPrev ? $page - 1 : null,
    ];
}

/**
 * Get offset from page number.
 * 
 * @param int $page Page number (1-indexed)
 * @param int $perPage Items per page
 * @return int Offset for database query
 */
function page_to_offset(int $page, int $perPage): int
{
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    
    return ($page - 1) * $perPage;
}

/**
 * Build pagination links for API responses.
 * 
 * @param int $page Current page
 * @param int $totalPages Total pages
 * @param string $baseUrl Base URL for links
 * @return array{first: string, last: string, next: string|null, prev: string|null} Pagination links
 */
function pagination_links(int $page, int $totalPages, string $baseUrl): array
{
    $page = max(1, $page);
    $totalPages = max(1, $totalPages);
    
    // Parse base URL to handle existing query string
    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    
    $links = [
        'first' => $baseUrl . $separator . 'page=1',
        'last' => $baseUrl . $separator . 'page=' . $totalPages,
        'next' => null,
        'prev' => null,
    ];
    
    if ($page < $totalPages) {
        $links['next'] = $baseUrl . $separator . 'page=' . ($page + 1);
    }
    
    if ($page > 1) {
        $links['prev'] = $baseUrl . $separator . 'page=' . ($page - 1);
    }
    
    return $links;
}

/**
 * Parse Link header for pagination.
 * Parses RFC 8288 Web Linking format.
 * 
 * @param string $header Link header value
 * @return array<string, string> Array of rel => URL pairs
 */
function parse_link_header(string $header): array
{
    $links = [];
    
    // Split by comma, but be careful of URLs containing commas
    $parts = preg_split('/,\s*(?=<)/', $header);
    
    if ($parts === false) {
        return $links;
    }
    
    foreach ($parts as $part) {
        // Match URL and rel
        if (preg_match('/<([^>]+)>/', $part, $urlMatch) &&
            preg_match('/rel="([^"]+)"/', $part, $relMatch)) {
            $url = $urlMatch[1];
            $rels = explode(' ', $relMatch[1]);
            
            foreach ($rels as $rel) {
                $links[$rel] = $url;
            }
        }
    }
    
    return $links;
}
