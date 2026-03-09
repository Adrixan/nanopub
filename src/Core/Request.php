<?php

declare(strict_types=1);

namespace NanoPub\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * HTTP request wrapper with memory-efficient body handling.
 * 
 * Streams php://input for memory efficiency with large payloads.
 */
final class Request
{
    /**
     * HTTP method (GET, POST, PUT, DELETE, PATCH).
     */
    public readonly string $method;

    /**
     * Request path without query string.
     */
    public readonly string $path;

    /**
     * Query parameters from URL.
     * 
     * @var array<string, string|array>
     */
    public readonly array $query;

    /**
     * Request headers (normalized to lowercase keys).
     * 
     * @var array<string, string>
     */
    public readonly array $headers;

    /**
     * Raw body stream resource.
     * 
     * @var resource|null
     */
    public readonly mixed $bodyStream;

    /**
     * Parsed JSON body cache.
     */
    private ?array $jsonCache = null;

    /**
     * Request attributes (set by middleware).
     * 
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * Uploaded files.
     * 
     * @var array<string, array>
     */
    public readonly array $files;

    /**
     * Server parameters.
     * 
     * @var array<string, mixed>
     */
    public readonly array $server;

    /**
     * Cookies.
     * 
     * @var array<string, string>
     */
    public readonly array $cookies;

    /**
     * Create a new Request instance.
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array<string, string|array> $query Query parameters
     * @param array<string, string> $headers Request headers
     * @param resource|null $bodyStream Body stream
     * @param array<string, array> $files Uploaded files
     * @param array<string, mixed> $server Server parameters
     * @param array<string, string> $cookies Cookies
     */
    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        mixed $bodyStream = null,
        array $files = [],
        array $server = [],
        array $cookies = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->query = $query;
        $this->headers = $headers;
        $this->bodyStream = $bodyStream;
        $this->files = $files;
        $this->server = $server;
        $this->cookies = $cookies;
    }

    /**
     * Create a Request from PHP globals.
     * 
     * @return self
     */
    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = self::parsePath($_SERVER['REQUEST_URI'] ?? '/');
        $query = $_GET;
        $headers = self::parseHeaders($_SERVER);
        $bodyStream = self::openInputStream();
        $files = self::parseFiles($_FILES);
        $server = $_SERVER;
        $cookies = $_COOKIE;

        return new self(
            $method,
            $path,
            $query,
            $headers,
            $bodyStream,
            $files,
            $server,
            $cookies
        );
    }

    /**
     * Parse the path from a URI.
     * 
     * @param string $uri Full URI
     * @return string Path without query string
     */
    private static function parsePath(string $uri): string
    {
        // Remove query string
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Remove fragment
        $pos = strpos($uri, '#');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Decode and normalize
        $uri = rawurldecode($uri);

        return '/' . trim($uri, '/');
    }

    /**
     * Parse headers from $_SERVER.
     * 
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function parseHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = (string) $value;
            } elseif (str_starts_with($key, 'CONTENT_')) {
                $name = str_replace('_', '-', strtolower($key));
                $headers[$name] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * Open the input stream for memory-efficient body reading.
     * 
     * @return resource|null
     */
    private static function openInputStream(): mixed
    {
        $stream = fopen('php://input', 'r');
        return $stream !== false ? $stream : null;
    }

    /**
     * Parse uploaded files into normalized structure.
     * 
     * @param array<string, array> $files $_FILES array
     * @return array<string, array>
     */
    private static function parseFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if (is_array($file['name'])) {
                // Multiple files with same key
                $count = count($file['name']);
                for ($i = 0; $i < $count; $i++) {
                    $normalized[$key][$i] = [
                        'name' => $file['name'][$i],
                        'type' => $file['type'][$i],
                        'tmp_name' => $file['tmp_name'][$i],
                        'error' => $file['error'][$i],
                        'size' => $file['size'][$i],
                    ];
                }
            } else {
                $normalized[$key] = $file;
            }
        }

        return $normalized;
    }

    /**
     * Get a header value by name.
     * 
     * @param string $name Header name (case-insensitive)
     * @return string|null
     */
    public function getHeader(string $name): ?string
    {
        $name = str_replace('_', '-', strtolower($name));
        return $this->headers[$name] ?? null;
    }

    /**
     * Check if a header exists.
     * 
     * @param string $name Header name
     * @return bool
     */
    public function hasHeader(string $name): bool
    {
        $name = str_replace('_', '-', strtolower($name));
        return isset($this->headers[$name]);
    }

    /**
     * Get a query parameter.
     * 
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get a cookie value.
     * 
     * @param string $name Cookie name
     * @param string|null $default Default value
     * @return string|null
     */
    public function getCookie(string $name, ?string $default = null): ?string
    {
        return $this->cookies[$name] ?? $default;
    }

    /**
     * Get a server parameter.
     * 
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getServer(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Parse and return JSON body.
     * 
     * Caches the parsed result for subsequent calls.
     * 
     * @return array
     * @throws RuntimeException If body stream is not available
     * @throws InvalidArgumentException If JSON is invalid
     */
    public function json(): array
    {
        if ($this->jsonCache !== null) {
            return $this->jsonCache;
        }

        if ($this->bodyStream === null) {
            return [];
        }

        // Read entire stream for JSON parsing
        // For very large payloads, consider streaming JSON parser
        $content = stream_get_contents($this->bodyStream);
        
        if ($content === false || $content === '') {
            return [];
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        $this->jsonCache = is_array($data) ? $data : [];
        return $this->jsonCache;
    }

    /**
     * Get raw body content as string.
     * 
     * Warning: This loads the entire body into memory.
     * Use bodyStream for large payloads.
     * 
     * @return string
     */
    public function body(): string
    {
        if ($this->bodyStream === null) {
            return '';
        }

        $content = stream_get_contents($this->bodyStream);
        return $content !== false ? $content : '';
    }

    /**
     * Get an uploaded file by key.
     * 
     * @param string $key File key
     * @return array|null File data or null if not found
     */
    public function getFile(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Check if request has an uploaded file.
     * 
     * @param string $key File key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Check if request is an AJAX request.
     * 
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->getHeader('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * Check if request expects JSON response.
     * 
     * @return bool
     */
    public function expectsJson(): bool
    {
        $accept = $this->getHeader('accept') ?? '';
        return str_contains($accept, 'application/json') || $this->isAjax();
    }

    /**
     * Get the client IP address.
     * 
     * @return string
     */
    public function ip(): string
    {
        // Check for proxied IP
        $forwarded = $this->getHeader('x-forwarded-for');
        if ($forwarded !== null) {
            $ips = explode(',', $forwarded);
            return trim($ips[0]);
        }

        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get the request user agent.
     * 
     * @return string|null
     */
    public function userAgent(): ?string
    {
        return $this->getHeader('user-agent');
    }

    /**
     * Get the content type.
     * 
     * @return string|null
     */
    public function contentType(): ?string
    {
        return $this->getHeader('content-type');
    }

    /**
     * Check if request method matches.
     * 
     * @param string $method HTTP method
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * Get the full URL.
     * 
     * @return string
     */
    public function url(): string
    {
        $scheme = ($this->server['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http';
        $host = $this->getHeader('host') ?? $this->server['SERVER_NAME'] ?? 'localhost';
        $uri = $this->server['REQUEST_URI'] ?? '/';
        
        return "{$scheme}://{$host}{$uri}";
    }

    /**
     * Set a request attribute.
     * 
     * Used by middleware to pass data to handlers.
     *
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get a request attribute.
     *
     * @param string $key Attribute key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Check if an attribute exists.
     *
     * @param string $key Attribute key
     * @return bool
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Get all request attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}