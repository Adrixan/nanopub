<?php

declare(strict_types=1);

namespace NanoPub\Exceptions;

use Exception;
use Throwable;

/**
 * Exception for rate limiting errors.
 */
final class RateLimitException extends Exception
{
    /**
     * Seconds until rate limit resets.
     */
    private readonly int $retryAfter;

    /**
     * Rate limit key that was exceeded.
     */
    private readonly ?string $key;

    /**
     * Create a new RateLimitException.
     * 
     * @param string $message Error message
     * @param int $retryAfter Seconds until retry
     * @param string|null $key Rate limit key
     * @param int $code HTTP status code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Too many requests',
        int $retryAfter = 60,
        ?string $key = null,
        int $code = 429,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->retryAfter = $retryAfter;
        $this->key = $key;
    }

    /**
     * Get the retry-after value in seconds.
     * 
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Get the rate limit key.
     * 
     * @return string|null
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Create for a specific limit.
     * 
     * @param int $limit Maximum requests allowed
     * @param int $retryAfter Seconds until retry
     * @return self
     */
    public static function limitExceeded(int $limit, int $retryAfter = 60): self
    {
        return new self(
            "Rate limit exceeded. Maximum {$limit} requests allowed.",
            $retryAfter
        );
    }

    /**
     * Create for IP-based rate limiting.
     * 
     * @param string $ip IP address
     * @param int $retryAfter Seconds until retry
     * @return self
     */
    public static function ipLimited(string $ip, int $retryAfter = 60): self
    {
        return new self(
            'Too many requests from your IP address',
            $retryAfter,
            "ip:{$ip}"
        );
    }

    /**
     * Create for user-based rate limiting.
     * 
     * @param int|string $userId User ID
     * @param int $retryAfter Seconds until retry
     * @return self
     */
    public static function userLimited(int|string $userId, int $retryAfter = 60): self
    {
        return new self(
            'Too many requests from your account',
            $retryAfter,
            "user:{$userId}"
        );
    }
}