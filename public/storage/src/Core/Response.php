<?php

declare(strict_types=1);

namespace NanoPub\Core;

use Closure;
use JsonException;
use RuntimeException;

/**
 * HTTP response builder with streaming support.
 * 
 * Supports memory-efficient streaming for large responses.
 */
final class Response
{
    /**
     * HTTP status code.
     */
    private int $statusCode = 200;

    /**
     * Response headers.
     * 
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Response body content.
     */
    private string $content = '';

    /**
     * Streaming callback for large responses.
     */
    private ?Closure $streamCallback = null;

    /**
     * Whether response has been sent.
     */
    private bool $sent = false;

    /**
     * Create a new Response instance.
     */
    public function __construct() {}

    /**
     * Set the HTTP status code.
     * 
     * @param int $code HTTP status code
     * @return self
     */
    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Set a response header.
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return self
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set multiple headers.
     * 
     * @param array<string, string> $headers Headers to set
     * @return self
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    /**
     * Set the response content.
     * 
     * @param string $content Response body
     * @return self
     */
    public function content(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Set a streaming callback for the response.
     * 
     * @param callable $callback Callback that receives output stream
     * @return self
     */
    public function stream(callable $callback): self
    {
        $this->streamCallback = $callback;
        return $this;
    }

    /**
     * Send a JSON response.
     * 
     * @param mixed $data Data to encode as JSON
     * @param int $status HTTP status code
     */
    public static function json(mixed $data, int $status = 200): void
    {
        $response = new self();
        $response->status($status);
        $response->header('Content-Type', 'application/json; charset=utf-8');
        
        try {
            $json = json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $e) {
            throw new RuntimeException(
                'JSON encoding failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
        
        $response->content($json);
        $response->send();
    }

    /**
     * Send an HTML response.
     * 
     * @param string $content HTML content
     * @param int $status HTTP status code
     */
    public static function html(string $content, int $status = 200): void
    {
        $response = new self();
        $response->status($status);
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->content($content);
        $response->send();
    }

    /**
     * Send a streaming response.
     * 
     * Memory-efficient for large responses.
     * 
     * @param callable $callback Callback that receives output stream
     * @param int $status HTTP status code
     */
    public static function streamResponse(callable $callback, int $status = 200): void
    {
        $response = new self();
        $response->status($status);
        $response->stream($callback);
        $response->send();
    }

    /**
     * Send a redirect response.
     * 
     * @param string $url Redirect URL
     * @param int $status HTTP status code (default 302)
     */
    public static function redirect(string $url, int $status = 302): void
    {
        $response = new self();
        $response->status($status);
        $response->header('Location', $url);
        $response->send();
    }

    /**
     * Send a text response.
     * 
     * @param string $content Text content
     * @param int $status HTTP status code
     */
    public static function text(string $content, int $status = 200): void
    {
        $response = new self();
        $response->status($status);
        $response->header('Content-Type', 'text/plain; charset=utf-8');
        $response->content($content);
        $response->send();
    }

    /**
     * Send a 404 Not Found response.
     * 
     * @param string $message Error message
     */
    public static function notFound(string $message = 'Not Found'): void
    {
        self::text($message, 404);
    }

    /**
     * Send a 403 Forbidden response.
     * 
     * @param string $message Error message
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::text($message, 403);
    }

    /**
     * Send a 401 Unauthorized response.
     * 
     * @param string $message Error message
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::text($message, 401);
    }

    /**
     * Send a 400 Bad Request response.
     * 
     * @param string $message Error message
     */
    public static function badRequest(string $message = 'Bad Request'): void
    {
        self::text($message, 400);
    }

    /**
     * Send a 500 Internal Server Error response.
     * 
     * @param string $message Error message
     */
    public static function serverError(string $message = 'Internal Server Error'): void
    {
        self::text($message, 500);
    }

    /**
     * Send a 204 No Content response.
     */
    public static function noContent(): void
    {
        $response = new self();
        $response->status(204);
        $response->send();
    }

    /**
     * Set cache control headers.
     * 
     * @param int $seconds Cache duration in seconds
     * @return self
     */
    public function cache(int $seconds): self
    {
        $this->header('Cache-Control', "public, max-age={$seconds}");
        $this->header('Expires', gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
        return $this;
    }

    /**
     * Set no-cache headers.
     * 
     * @return self
     */
    public function noCache(): self
    {
        $this->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $this->header('Pragma', 'no-cache');
        $this->header('Expires', '0');
        return $this;
    }

    /**
     * Set Content-Disposition header for file downloads.
     * 
     * @param string $filename Filename for download
     * @param bool $attachment Force download vs inline display
     * @return self
     */
    public function attachment(string $filename, bool $attachment = true): self
    {
        $disposition = $attachment ? 'attachment' : 'inline';
        $this->header('Content-Disposition', "{$disposition}; filename=\"{$filename}\"");
        return $this;
    }

    /**
     * Send the response to the client.
     */
    public function send(): void
    {
        if ($this->sent) {
            throw new RuntimeException('Response already sent');
        }

        $this->sent = true;

        // Send status code
        http_response_code($this->statusCode);

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Send content or stream
        if ($this->streamCallback !== null) {
            // Flush output buffer for streaming
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            
            // Call streaming callback
            ($this->streamCallback)();
        } else {
            echo $this->content;
        }
    }

    /**
     * Get the status code.
     * 
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get all headers.
     * 
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the response content.
     * 
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Check if response has been sent.
     * 
     * @return bool
     */
    public function isSent(): bool
    {
        return $this->sent;
    }
}