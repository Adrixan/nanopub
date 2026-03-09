<?php

declare(strict_types=1);

namespace NanoPub\Exceptions;

use Exception;
use Throwable;

/**
 * Exception for input validation errors.
 */
final class ValidationException extends Exception
{
    /**
     * Validation errors by field.
     * 
     * @var array<string, string>
     */
    private readonly array $errors;

    /**
     * Create a new ValidationException.
     * 
     * @param string $message Error message
     * @param array<string, string> $errors Field-specific errors
     * @param int $code HTTP status code
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Validation failed',
        array $errors = [],
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get the validation errors.
     * 
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if a specific field has an error.
     * 
     * @param string $field Field name
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get the error for a specific field.
     * 
     * @param string $field Field name
     * @return string|null
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Create from a single field error.
     * 
     * @param string $field Field name
     * @param string $message Error message
     * @return self
     */
    public static function field(string $field, string $message): self
    {
        return new self($message, [$field => $message]);
    }

    /**
     * Create from multiple field errors.
     * 
     * @param array<string, string> $errors Field errors
     * @param string $message General error message
     * @return self
     */
    public static function fields(array $errors, string $message = 'Validation failed'): self
    {
        return new self($message, $errors);
    }
}