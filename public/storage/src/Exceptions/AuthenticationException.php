<?php

declare(strict_types=1);

namespace NanoPub\Exceptions;

use Exception;
use Throwable;

/**
 * Exception for authentication failures.
 */
final class AuthenticationException extends Exception
{
    /**
     * Guard that failed authentication.
     */
    private readonly ?string $guard;

    /**
     * Create a new AuthenticationException.
     * 
     * @param string $message Error message
     * @param string|null $guard Authentication guard that failed
     * @param int $code HTTP status code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Unauthenticated',
        ?string $guard = null,
        int $code = 401,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->guard = $guard;
    }

    /**
     * Get the guard that failed.
     * 
     * @return string|null
     */
    public function guard(): ?string
    {
        return $this->guard;
    }

    /**
     * Create for invalid credentials.
     * 
     * @return self
     */
    public static function invalidCredentials(): self
    {
        return new self('Invalid credentials');
    }

    /**
     * Create for unauthenticated user.
     * 
     * @return self
     */
    public static function unauthenticated(): self
    {
        return new self('Unauthenticated');
    }

    /**
     * Create for token expired.
     * 
     * @return self
     */
    public static function tokenExpired(): self
    {
        return new self('Token has expired');
    }

    /**
     * Create for invalid token.
     * 
     * @return self
     */
    public static function invalidToken(): self
    {
        return new self('Invalid token');
    }
}