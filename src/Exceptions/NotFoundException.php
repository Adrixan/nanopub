<?php

declare(strict_types=1);

namespace NanoPub\Exceptions;

use Exception;
use Throwable;

/**
 * Exception for 404 Not Found errors.
 */
final class NotFoundException extends Exception
{
    /**
     * Create a new NotFoundException.
     * 
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Resource not found',
        int $code = 404,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}