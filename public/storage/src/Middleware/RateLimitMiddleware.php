<?php

declare(strict_types=1);

namespace NanoPub\Middleware;

use NanoPub\Core\Database;
use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Exceptions\RateLimitException;

/**
 * Rate limiting middleware using database for tracking.
 * 
 * Tracks request counts per IP or account within a time window.
 * Adds rate limit headers to responses.
 */
final class RateLimitMiddleware
{
    /**
     * Maximum requests per window.
     */
    private readonly int $limit;

    /**
     * Time window in seconds.
     */
    private readonly int $window;

    /**
     * Create a new rate limit middleware.
     *
     * @param int $limit Maximum requests per window (default: 60)
     * @param int $window Time window in seconds (default: 3600 = 1 hour)
     */
    public function __construct(int $limit = 60, int $window = 3600)
    {
        $this->limit = $limit;
        $this->window = $window;
    }

    /**
     * Handle rate limiting for incoming requests.
     *
     * @param Request $request The incoming request
     * @param callable $next The next middleware/handler
     * @return mixed
     * @throws RateLimitException If rate limit is exceeded
     */
    public function __invoke(Request $request, callable $next): mixed
    {
        $key = $this->getKey($request);
        $count = $this->getCount($key);

        if ($count >= $this->limit) {
            $retryAfter = $this->getRetryAfter($key);
            throw new RateLimitException(
                "Rate limit exceeded. Maximum {$this->limit} requests per {$this->window} seconds.",
                $retryAfter,
                $key
            );
        }

        $this->increment($key);

        $response = $next($request);

        // Add rate limit headers if response is a Response object
        if ($response instanceof Response) {
            $response->header('X-RateLimit-Limit', (string) $this->limit);
            $response->header('X-RateLimit-Remaining', (string) max(0, $this->limit - $count - 1));
            $response->header('X-RateLimit-Reset', (string) $this->getWindowEnd());
        }

        return $response;
    }

    /**
     * Get the rate limit key for the request.
     * 
     * Uses account ID if authenticated, IP address otherwise.
     *
     * @param Request $request The incoming request
     * @return string Rate limit key
     */
    private function getKey(Request $request): string
    {
        $accountId = $request->getAttribute('account_id');
        
        if ($accountId !== null) {
            return "account:{$accountId}";
        }
        
        $ip = $request->ip();
        return "ip:{$ip}";
    }

    /**
     * Get the current request count for a key.
     *
     * @param string $key Rate limit key
     * @return int Current count
     */
    private function getCount(string $key): int
    {
        $windowStart = time() - $this->window;
        
        $result = Database::fetchOne(
            "SELECT request_count FROM rate_limits 
             WHERE rate_limit_key = ? AND window_start > ?",
            [$key, date('Y-m-d H:i:s', $windowStart)]
        );

        return $result !== null ? (int) $result['request_count'] : 0;
    }

    /**
     * Increment the request count for a key.
     *
     * @param string $key Rate limit key
     */
    private function increment(string $key): void
    {
        $now = time();
        $windowStart = date('Y-m-d H:i:s', $now - $this->window);
        
        // Try to update existing record
        $updated = Database::execute(
            "UPDATE rate_limits 
             SET request_count = request_count + 1, last_request_at = NOW() 
             WHERE rate_limit_key = ? AND window_start > ?",
            [$key, $windowStart]
        );

        if ($updated === 0) {
            // No existing record in current window, create new one
            Database::execute(
                "INSERT INTO rate_limits (rate_limit_key, request_count, window_start, last_request_at) 
                 VALUES (?, 1, NOW(), NOW())",
                [$key]
            );
        }

        // Clean up old entries periodically (1% chance)
        if (random_int(1, 100) === 1) {
            $this->cleanup();
        }
    }

    /**
     * Get seconds until the rate limit window resets.
     *
     * @param string $key Rate limit key
     * @return int Seconds until reset
     */
    private function getRetryAfter(string $key): int
    {
        $result = Database::fetchOne(
            "SELECT window_start FROM rate_limits WHERE rate_limit_key = ?",
            [$key]
        );

        if ($result === null) {
            return $this->window;
        }

        $windowStart = strtotime($result['window_start']);
        $windowEnd = $windowStart + $this->window;
        $remaining = $windowEnd - time();

        return max(1, $remaining);
    }

    /**
     * Get the timestamp when the current window ends.
     *
     * @return int Unix timestamp
     */
    private function getWindowEnd(): int
    {
        return time() + $this->window;
    }

    /**
     * Clean up expired rate limit entries.
     */
    private function cleanup(): void
    {
        $cutoff = time() - ($this->window * 2);
        
        Database::execute(
            "DELETE FROM rate_limits WHERE window_start < ?",
            [date('Y-m-d H:i:s', $cutoff)]
        );
    }
}
