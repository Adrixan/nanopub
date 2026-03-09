<?php

declare(strict_types=1);

namespace NanoPub\Core;

use NanoPub\Exceptions\NotFoundException;
use RuntimeException;

/**
 * URL to controller mapping with route parameters support.
 * 
 * Routes are loaded from configuration and dispatched to controllers.
 */
final class Router
{
    /**
     * Route table organized by HTTP method.
     * 
     * @var array<string, array<string, callable|array>>
     */
    private static array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
    ];

    /**
     * Route patterns with parameter placeholders.
     * 
     * @var array<string, array<string, array{pattern: string, handler: callable|array, params: array<string>}>>
     */
    private static array $patterns = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
    ];

    /**
     * Load routes from a configuration file.
     * 
     * @param string $file Path to routes configuration file
     * @throws RuntimeException If file cannot be loaded
     */
    public static function load(string $file): void
    {
        if (!file_exists($file)) {
            throw new RuntimeException("Routes file not found: {$file}");
        }

        $routes = require $file;

        if (!is_array($routes)) {
            throw new RuntimeException("Routes file must return an array: {$file}");
        }

        foreach ($routes as $method => $methodRoutes) {
            $method = strtoupper($method);
            
            if (!isset(self::$routes[$method])) {
                throw new RuntimeException("Unsupported HTTP method: {$method}");
            }

            foreach ($methodRoutes as $path => $handler) {
                self::addRoute($method, $path, $handler);
            }
        }
    }

    /**
     * Add a route to the routing table.
     * 
     * @param string $method HTTP method
     * @param string $path URL path (may contain {param} placeholders)
     * @param callable|array $handler Controller callable or [class, method] array
     */
    public static function addRoute(string $method, string $path, callable|array $handler): void
    {
        $method = strtoupper($method);

        if (!isset(self::$routes[$method])) {
            throw new RuntimeException("Unsupported HTTP method: {$method}");
        }

        // Check if path contains parameters
        if (preg_match('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $path)) {
            // Convert to regex pattern
            $pattern = preg_replace(
                '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
                '(?P<$1>[^/]+)',
                $path
            );
            $pattern = '#^' . $pattern . '$#';

            // Extract parameter names
            preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $path, $matches);
            $params = $matches[1];

            self::$patterns[$method][$path] = [
                'pattern' => $pattern,
                'handler' => $handler,
                'params' => $params,
            ];
        } else {
            // Static route
            self::$routes[$method][$path] = $handler;
        }
    }

    /**
     * Dispatch a request to the appropriate controller.
     * 
     * @param Request $request HTTP request
     * @return callable Controller callable
     * @throws NotFoundException If no route matches
     */
    public static function dispatch(Request $request): callable
    {
        $method = $request->method;
        $path = $request->path;

        // Check static routes first
        if (isset(self::$routes[$method][$path])) {
            return self::resolveHandler(self::$routes[$method][$path], []);
        }

        // Check pattern routes
        foreach (self::$patterns[$method] as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                // Extract named captures
                $params = [];
                foreach ($route['params'] as $param) {
                    $params[$param] = $matches[$param];
                }
                return self::resolveHandler($route['handler'], $params);
            }
        }

        throw new NotFoundException("No route found for {$method} {$path}");
    }

    /**
     * Resolve a handler to a callable.
     * 
     * @param callable|array $handler Handler from route table
     * @param array<string, string> $params Route parameters
     * @return callable
     */
    private static function resolveHandler(callable|array $handler, array $params): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        // [ClassName, methodName] format
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (!class_exists($class)) {
                throw new RuntimeException("Controller class not found: {$class}");
            }

            return function (Request $request) use ($class, $method, $params) {
                $controller = new $class();
                return $controller->{$method}($request, ...$params);
            };
        }

        throw new RuntimeException('Invalid route handler');
    }

    /**
     * Get all registered routes.
     * 
     * @return array<string, array<string, callable|array>>
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Get all registered patterns.
     * 
     * @return array<string, array<string, array{pattern: string, handler: callable|array, params: array<string>}>>
     */
    public static function getPatterns(): array
    {
        return self::$patterns;
    }

    /**
     * Clear all routes.
     */
    public static function clear(): void
    {
        self::$routes = [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'DELETE' => [],
            'PATCH' => [],
        ];
        self::$patterns = [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'DELETE' => [],
            'PATCH' => [],
        ];
    }

    /**
     * Generate a URL for a named route.
     * 
     * @param string $name Route name
     * @param array<string, string> $params Route parameters
     * @return string
     * @throws RuntimeException If route not found
     */
    public static function url(string $name, array $params = []): string
    {
        // Search for route by name (stored as path)
        foreach (self::$routes as $methodRoutes) {
            if (isset($methodRoutes[$name])) {
                $path = $name;
                foreach ($params as $key => $value) {
                    $path = str_replace("{{$key}}", $value, $path);
                }
                return $path;
            }
        }

        foreach (self::$patterns as $methodPatterns) {
            if (isset($methodPatterns[$name])) {
                $path = $name;
                foreach ($params as $key => $value) {
                    $path = str_replace("{{$key}}", $value, $path);
                }
                return $path;
            }
        }

        throw new RuntimeException("Route not found: {$name}");
    }
}