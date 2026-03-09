<?php

declare(strict_types=1);

namespace NanoPub\Core;

use NanoPub\Exceptions\AuthenticationException;
use NanoPub\Exceptions\NotFoundException;
use NanoPub\Exceptions\RateLimitException;
use NanoPub\Exceptions\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Application container with lazy service loading.
 * 
 * Main entry point for the application.
 */
final class App
{
    /**
     * Service container for lazy loading.
     * 
     * @var array<string, object>
     */
    private static array $services = [];

    /**
     * Service factory callbacks.
     * 
     * @var array<string, callable(): object>
     */
    private static array $factories = [];

    /**
     * Application bootstrapped flag.
     */
    private static bool $bootstrapped = false;

    /**
     * Middleware pipeline.
     * 
     * @var array<int, class-string>
     */
    private static array $middleware = [];

    /**
     * Bootstrap the application.
     */
    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        // Set error handling based on environment
        $debug = Config::get('app.debug', false);
        
        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '0');
        }

        // Set timezone
        $timezone = Config::get('app.timezone', 'UTC');
        date_default_timezone_set($timezone);

        // Set locale
        $locale = Config::get('app.locale', 'en');
        setlocale(LC_ALL, $locale);

        // Load routes
        $routesFile = dirname(__DIR__, 2) . '/config/routes.php';
        if (file_exists($routesFile)) {
            Router::load($routesFile);
        }

        // Load middleware
        $middlewareFile = dirname(__DIR__, 2) . '/config/middleware.php';
        if (file_exists($middlewareFile)) {
            self::$middleware = require $middlewareFile;
        }

        // Set views path
        View::setViewsPath(dirname(__DIR__) . '/Views');

        self::$bootstrapped = true;
    }

    /**
     * Run the application.
     * 
     * Main entry point that handles the request lifecycle.
     */
    public static function run(): void
    {
        try {
            // Bootstrap if not already done
            self::bootstrap();

            // Create request from globals
            $request = Request::createFromGlobals();

            // Start session
            Session::start();

            // Run middleware pipeline
            $response = self::runMiddleware($request);

            // If middleware returned a response, send it
            if ($response !== null) {
                if ($response instanceof Response) {
                    $response->send();
                }
                return;
            }

            // Dispatch to controller
            $handler = Router::dispatch($request);
            $result = $handler($request);

            // Send response
            self::sendResponse($result);

        } catch (NotFoundException $e) {
            self::handleNotFound($e);
        } catch (AuthenticationException $e) {
            self::handleAuthenticationError($e);
        } catch (ValidationException $e) {
            self::handleValidationError($e);
        } catch (RateLimitException $e) {
            self::handleRateLimitError($e);
        } catch (Throwable $e) {
            self::handleError($e);
        }
    }

    /**
     * Run the middleware pipeline.
     * 
     * @param Request $request HTTP request
     * @return Response|null Returns Response if middleware handles request
     */
    private static function runMiddleware(Request $request): ?Response
    {
        foreach (self::$middleware as $middlewareClass) {
            if (!class_exists($middlewareClass)) {
                throw new RuntimeException("Middleware not found: {$middlewareClass}");
            }

            $middleware = new $middlewareClass();
            $response = $middleware->handle($request);

            if ($response !== null) {
                return $response;
            }
        }

        return null;
    }

    /**
     * Send the response based on controller result.
     * 
     * @param mixed $result Controller result
     */
    private static function sendResponse(mixed $result): void
    {
        if ($result instanceof Response) {
            $result->send();
        } elseif (is_string($result)) {
            Response::html($result);
        } elseif (is_array($result)) {
            Response::json($result);
        } elseif ($result === null) {
            Response::noContent();
        } else {
            throw new RuntimeException('Invalid controller response type');
        }
    }

    /**
     * Handle 404 Not Found errors.
     */
    private static function handleNotFound(NotFoundException $e): void
    {
        if (self::isApiRequest()) {
            Response::json(['error' => 'Not Found', 'message' => $e->getMessage()], 404);
        } else {
            $content = View::render('error/404', ['message' => $e->getMessage()]);
            Response::html($content, 404);
        }
    }

    /**
     * Handle authentication errors.
     */
    private static function handleAuthenticationError(AuthenticationException $e): void
    {
        if (self::isApiRequest()) {
            Response::json(['error' => 'Unauthorized', 'message' => $e->getMessage()], 401);
        } else {
            Session::flash('error', $e->getMessage());
            Response::redirect('/login');
        }
    }

    /**
     * Handle validation errors.
     */
    private static function handleValidationError(ValidationException $e): void
    {
        if (self::isApiRequest()) {
            Response::json([
                'error' => 'Validation Error',
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
            ], 422);
        } else {
            Session::flash('errors', $e->getErrors());
            Session::flash('old', $_POST ?? []);
            Session::flash('error', $e->getMessage());
            Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    /**
     * Handle rate limit errors.
     */
    private static function handleRateLimitError(RateLimitException $e): void
    {
        if (self::isApiRequest()) {
            Response::json([
                'error' => 'Too Many Requests',
                'message' => $e->getMessage(),
                'retry_after' => $e->getRetryAfter(),
            ], 429);
        } else {
            $content = View::render('error/429', [
                'message' => $e->getMessage(),
                'retryAfter' => $e->getRetryAfter(),
            ]);
            Response::html($content, 429);
        }
    }

    /**
     * Handle general errors.
     */
    private static function handleError(Throwable $e): void
    {
        $debug = Config::get('app.debug', false);

        // Log error
        error_log(sprintf(
            "[%s] %s in %s:%d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        if (self::isApiRequest()) {
            Response::json([
                'error' => 'Internal Server Error',
                'message' => $debug ? $e->getMessage() : 'An error occurred',
            ], 500);
        } else {
            if ($debug) {
                $content = View::render('error/debug', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            } else {
                $content = View::render('error/500', [
                    'message' => 'An error occurred',
                ]);
            }
            Response::html($content, 500);
        }
    }

    /**
     * Check if current request is an API request.
     * 
     * @return bool
     */
    private static function isApiRequest(): bool
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        return str_starts_with($path, '/api/') || str_starts_with($path, '/activitypub/');
    }

    /**
     * Get a service instance (lazy loaded).
     * 
     * @template T
     * @param class-string<T> $service Service class name
     * @return T
     */
    public static function get(string $service): object
    {
        if (isset(self::$services[$service])) {
            return self::$services[$service];
        }

        // Check for registered factory
        if (isset(self::$factories[$service])) {
            self::$services[$service] = (self::$factories[$service])();
            return self::$services[$service];
        }

        // Create new instance
        if (!class_exists($service)) {
            throw new RuntimeException("Service not found: {$service}");
        }

        self::$services[$service] = new $service();
        return self::$services[$service];
    }

    /**
     * Register a service factory.
     * 
     * @param string $service Service name
     * @param callable(): object $factory Factory callback
     */
    public static function register(string $service, callable $factory): void
    {
        self::$factories[$service] = $factory;
    }

    /**
     * Bind a service instance.
     * 
     * @param string $service Service name
     * @param object $instance Service instance
     */
    public static function bind(string $service, object $instance): void
    {
        self::$services[$service] = $instance;
    }

    /**
     * Check if a service is registered.
     * 
     * @param string $service Service name
     * @return bool
     */
    public static function has(string $service): bool
    {
        return isset(self::$services[$service]) || isset(self::$factories[$service]);
    }

    /**
     * Remove a service from the container.
     * 
     * @param string $service Service name
     */
    public static function forget(string $service): void
    {
        unset(self::$services[$service], self::$factories[$service]);
    }

    /**
     * Clear all services.
     */
    public static function clear(): void
    {
        self::$services = [];
        self::$factories = [];
    }

    /**
     * Get the application environment.
     * 
     * @return string
     */
    public static function environment(): string
    {
        return Config::get('app.env', 'production');
    }

    /**
     * Check if application is in debug mode.
     * 
     * @return bool
     */
    public static function isDebug(): bool
    {
        return Config::get('app.debug', false);
    }

    /**
     * Check if application is in production.
     * 
     * @return bool
     */
    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    /**
     * Check if application is in local development.
     * 
     * @return bool
     */
    public static function isLocal(): bool
    {
        return self::environment() === 'local';
    }

    /**
     * Get the application URL.
     * 
     * @return string
     */
    public static function url(): string
    {
        return Config::get('app.url', '');
    }

    /**
     * Get the application name.
     * 
     * @return string
     */
    public static function name(): string
    {
        return Config::get('app.name', 'NanoPub');
    }
}