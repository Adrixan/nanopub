<?php

declare(strict_types=1);

/**
 * Middleware pipeline configuration.
 * 
 * Middleware is executed in order listed. Each middleware can:
 * - Return null to continue to next middleware/controller
 * - Return a Response to short-circuit the pipeline
 * 
 * Middleware classes must implement:
 *   public function handle(Request $request): ?Response
 * 
 * Note: All middleware have been disabled pending proper implementation.
 * The existing middleware (RateLimitMiddleware, AuthMiddleware) use a different
 * interface (__invoke with callable) that is incompatible with the App's
 * handle(Request $request): ?Response requirement.
 */

return [];
