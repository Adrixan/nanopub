<?php

declare(strict_types=1);

namespace NanoPub\Middleware;

use NanoPub\Core\Database;
use NanoPub\Core\Request;
use NanoPub\Core\Session;
use NanoPub\Exceptions\AuthenticationException;

/**
 * Authentication middleware for web sessions and API tokens.
 * 
 * Handles both session-based web authentication and Bearer token
 * authentication for API requests.
 */
final class AuthMiddleware
{
    /**
     * Handle authentication for incoming requests.
     * 
     * Checks for Bearer token (API) first, then falls back to session (Web).
     *
     * @param Request $request The incoming request
     * @param callable $next The next middleware/handler
     * @return mixed
     * @throws AuthenticationException If authentication fails
     */
    public function __invoke(Request $request, callable $next): mixed
    {
        // Check for Bearer token (API)
        $authHeader = $request->getHeader('Authorization');
        if ($authHeader !== null && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $account = $this->validateToken($token);
            
            if ($account !== null) {
                $request->setAttribute('account_id', $account['id']);
                $request->setAttribute('account', $account);
                return $next($request);
            }
            
            throw AuthenticationException::invalidToken();
        }
        
        // Check session (Web)
        $accountId = Session::get('account_id');
        if ($accountId !== null) {
            $account = $this->getAccountById($accountId);
            
            if ($account !== null && ($account['is_suspended'] ?? 0) === 0) {
                $request->setAttribute('account_id', $account['id']);
                $request->setAttribute('account', $account);
                return $next($request);
            }
        }
        
        throw AuthenticationException::unauthenticated();
    }
    
    /**
     * Validate an API Bearer token.
     * 
     * @param string $token The token to validate
     * @return array|null Account data if valid, null otherwise
     */
    private function validateToken(string $token): ?array
    {
        // Query oauth_tokens table with account join
        $result = Database::fetchOne(
            "SELECT a.*, ot.id as token_id 
             FROM oauth_tokens ot 
             INNER JOIN accounts a ON ot.account_id = a.id 
             WHERE ot.token = ? 
               AND ot.revoked = 0 
               AND (ot.expires_at IS NULL OR ot.expires_at > NOW())
               AND a.is_suspended = 0",
            [$token]
        );
        
        if ($result === null) {
            return null;
        }
        
        // Update token's last used time (optional, for tracking)
        Database::execute(
            "UPDATE oauth_tokens SET last_used_at = NOW() WHERE token = ?",
            [$token]
        );
        
        return $result;
    }
    
    /**
     * Get an account by ID.
     * 
     * @param int|string $accountId The account ID
     * @return array|null Account data or null if not found
     */
    private function getAccountById(int|string $accountId): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM accounts WHERE id = ?",
            [(int) $accountId]
        );
    }
}
