<?php

declare(strict_types=1);

namespace NanoPub\Middleware;

use NanoPub\Core\Request;
use NanoPub\Exceptions\ValidationException;

/**
 * JSON parsing middleware for request body.
 * 
 * Parses JSON request bodies and makes the data available via request attributes.
 * Only processes requests with JSON content type.
 */
final class JsonMiddleware
{
    /**
     * Handle JSON parsing for incoming requests.
     *
     * @param Request $request The incoming request
     * @param callable $next The next middleware/handler
     * @return mixed
     * @throws ValidationException If JSON is invalid
     */
    public function __invoke(Request $request, callable $next): mixed
    {
        // Only process requests with JSON content type
        $contentType = $request->getHeader('content-type');
        
        if ($contentType === null || !str_contains($contentType, 'application/json')) {
            // Not a JSON request, continue without parsing
            return $next($request);
        }

        $body = $request->body();
        
        if ($body === '') {
            // Empty body, nothing to parse
            $request->setAttribute('json', []);
            return $next($request);
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException(
                'Invalid JSON: ' . $this->getJsonErrorMessage(json_last_error()),
                ['body' => 'Invalid JSON format']
            );
        }

        // Ensure we have an array (JSON could be a scalar value)
        if (!is_array($data)) {
            $data = ['value' => $data];
        }

        $request->setAttribute('json', $data);

        return $next($request);
    }

    /**
     * Get a human-readable JSON error message.
     *
     * @param int $errorCode JSON error code
     * @return string Error message
     */
    private function getJsonErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
            default => json_last_error_msg(),
        };
    }
}
