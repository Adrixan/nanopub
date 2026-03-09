<?php

declare(strict_types=1);

namespace NanoPub\Middleware;

use NanoPub\Core\Config;
use NanoPub\Core\Request;
use NanoPub\Core\Response;

/**
 * CORS handling middleware for API requests.
 * 
 * Handles preflight OPTIONS requests and adds CORS headers to responses.
 * Configurable allowed origins for security.
 */
final class CorsMiddleware
{
    /**
     * Default allowed methods.
     */
    private const DEFAULT_METHODS = 'GET, POST, PUT, DELETE, PATCH, OPTIONS';

    /**
     * Default allowed headers.
     */
    private const DEFAULT_HEADERS = 'Authorization, Content-Type, Accept, X-Requested-With';

    /**
     * Max age for preflight cache (24 hours).
     */
    private const MAX_AGE = 86400;

    /**
     * Handle CORS for incoming requests.
     *
     * @param Request $request The incoming request
     * @param callable $next The next middleware/handler
     * @return mixed
     */
    public function __invoke(Request $request, callable $next): mixed
    {
        // Handle preflight OPTIONS request
        if ($request->method === 'OPTIONS') {
            return $this->preflightResponse($request);
        }

        $response = $next($request);

        // Add CORS headers to response
        if ($response instanceof Response) {
            $this->addCorsHeaders($request, $response);
        }

        return $response;
    }

    /**
     * Create a preflight response for OPTIONS requests.
     *
     * @param Request $request The incoming request
     * @return Response
     */
    private function preflightResponse(Request $request): Response
    {
        $response = new Response();
        $response->status(204);
        
        $this->addCorsHeaders($request, $response);
        
        // Add preflight-specific headers
        $requestHeaders = $request->getHeader('Access-Control-Request-Headers');
        if ($requestHeaders !== null) {
            $response->header('Access-Control-Allow-Headers', $requestHeaders);
        } else {
            $response->header('Access-Control-Allow-Headers', self::DEFAULT_HEADERS);
        }
        
        $requestMethod = $request->getHeader('Access-Control-Request-Method');
        if ($requestMethod !== null) {
            $response->header('Access-Control-Allow-Methods', $requestMethod);
        } else {
            $response->header('Access-Control-Allow-Methods', self::DEFAULT_METHODS);
        }
        
        $response->header('Access-Control-Max-Age', (string) self::MAX_AGE);

        return $response;
    }

    /**
     * Add CORS headers to a response.
     *
     * @param Request $request The incoming request
     * @param Response $response The response to modify
     */
    private function addCorsHeaders(Request $request, Response $response): void
    {
        $origin = $request->getHeader('origin');
        
        if ($origin === null) {
            // No origin header, likely same-origin request
            return;
        }

        $allowedOrigin = $this->allowedOrigin($origin);
        
        if ($allowedOrigin === '') {
            // Origin not allowed, don't add CORS headers
            return;
        }

        $response->header('Access-Control-Allow-Origin', $allowedOrigin);
        $response->header('Access-Control-Allow-Methods', self::DEFAULT_METHODS);
        $response->header('Access-Control-Allow-Headers', self::DEFAULT_HEADERS);
        $response->header('Access-Control-Allow-Credentials', 'true');
        $response->header('Access-Control-Max-Age', (string) self::MAX_AGE);

        // Expose useful headers to the client
        $response->header(
            'Access-Control-Expose-Headers',
            'X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Link'
        );
    }

    /**
     * Check if an origin is allowed and return it.
     *
     * @param string $origin The origin to check
     * @return string The allowed origin, or empty string if not allowed
     */
    private function allowedOrigin(string $origin): string
    {
        // Get allowed origins from config
        $allowedOrigins = Config::get('app.cors.origins', []);
        
        // If no config, allow same-origin or use app URL
        if (empty($allowedOrigins)) {
            $appUrl = Config::get('app.url', '');
            
            // Allow the app's own URL
            if ($appUrl !== '' && $this->originsMatch($origin, $appUrl)) {
                return $origin;
            }
            
            // In development, allow localhost origins
            $env = Config::get('app.env', 'production');
            if ($env === 'development' || $env === 'local') {
                if ($this->isLocalhost($origin)) {
                    return $origin;
                }
            }
            
            // Strict mode: return empty to deny
            return '';
        }
        
        // Check against configured allowed origins
        foreach ($allowedOrigins as $allowed) {
            if ($allowed === '*') {
                // Wildcard - allow any origin (not recommended for production)
                return '*';
            }
            
            if ($this->originsMatch($origin, $allowed)) {
                return $origin;
            }
        }
        
        return '';
    }

    /**
     * Check if two origins match.
     *
     * @param string $origin The request origin
     * @param string $allowed The allowed origin pattern
     * @return bool
     */
    private function originsMatch(string $origin, string $allowed): bool
    {
        // Normalize both origins
        $origin = strtolower(rtrim($origin, '/'));
        $allowed = strtolower(rtrim($allowed, '/'));
        
        return $origin === $allowed;
    }

    /**
     * Check if an origin is a localhost origin.
     *
     * @param string $origin The origin to check
     * @return bool
     */
    private function isLocalhost(string $origin): bool
    {
        $parsed = parse_url($origin);
        
        if ($parsed === false || !isset($parsed['host'])) {
            return false;
        }
        
        $host = $parsed['host'];
        
        return $host === 'localhost' 
            || $host === '127.0.0.1' 
            || $host === '::1'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local');
    }
}
