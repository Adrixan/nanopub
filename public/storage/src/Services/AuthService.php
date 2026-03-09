<?php

declare(strict_types=1);

namespace NanoPub\Services;

use NanoPub\Core\Config;
use NanoPub\Core\Database;
use NanoPub\Core\Session;
use NanoPub\Models\Account;
use NanoPub\Exceptions\AuthenticationException;
use NanoPub\Exceptions\ValidationException;

/**
 * Authentication service for login, logout, and token management.
 */
final class AuthService
{
    /**
     * Attempt login with credentials.
     * 
     * @param string $login Email address or username
     * @param string $password Plain text password
     * @return array Account data on success
     * @throws AuthenticationException If credentials are invalid
     */
    public function login(string $login, string $password): array
    {
        // Try to find by email first, then by username
        $account = Account::findByEmail($login);
        
        if (!$account) {
            $account = Account::findByUsername($login);
        }
        
        if (!$account || !$account['is_local']) {
            throw new AuthenticationException('Invalid credentials');
        }
        
        if (!verify_password($password, $account['password_hash'])) {
            throw new AuthenticationException('Invalid credentials');
        }
        
        if ($account['is_suspended']) {
            throw new AuthenticationException('Account suspended');
        }
        
        // Create session
        Session::regenerate();
        Session::set('account_id', $account['id']);
        Session::set('login_time', time());
        
        // Update last activity
        Account::update($account['id'], ['last_activity_at' => date('Y-m-d H:i:s')]);
        
        return $account;
    }
    
    /**
     * Logout current user.
     */
    public function logout(): void
    {
        Session::destroy();
    }
    
    /**
     * Register new account.
     * 
     * @param array $data Registration data (username, email, password, display_name)
     * @return int Account ID
     * @throws ValidationException If validation fails
     */
    public function register(array $data): int
    {
        // Validate required fields
        $this->validateRegistration($data);
        
        // Check if username already exists
        if (Account::findByUsername($data['username'])) {
            throw new ValidationException('Username already taken');
        }
        
        // Check if email already exists
        if (Account::findByEmail($data['email'])) {
            throw new ValidationException('Email already registered');
        }
        
        // Generate RSA keys for ActivityPub
        $keyPair = generate_rsa_key_pair();
        
        // Build URLs
        $baseUrl = Config::get('app.url');
        $username = strtolower($data['username']);
        
        // Create account
        $accountId = Account::create([
            'username' => $username,
            'display_name' => $data['display_name'] ?? $username,
            'email' => $data['email'],
            'password_hash' => hash_password($data['password']),
            'private_key' => $keyPair['private'],
            'public_key' => $keyPair['public'],
            'actor_url' => "{$baseUrl}/users/{$username}",
            'inbox_url' => "{$baseUrl}/users/{$username}/inbox",
            'outbox_url' => "{$baseUrl}/users/{$username}/outbox",
            'followers_url' => "{$baseUrl}/users/{$username}/followers",
            'following_url' => "{$baseUrl}/users/{$username}/following",
            'is_local' => 1,
            'is_locked' => 0,
            'is_bot' => 0,
            'is_suspended' => 0,
            'is_admin' => 0,
            'is_moderator' => 0,
            'followers_count' => 0,
            'following_count' => 0,
            'statuses_count' => 0,
            'last_activity_at' => date('Y-m-d H:i:s'),
        ]);
        
        return $accountId;
    }
    
    /**
     * Create OAuth token for API access.
     * 
     * @param int $accountId Account ID
     * @param string $clientId OAuth client ID
     * @param array $scopes Token scopes
     * @param int|null $expiresIn Expiration time in seconds (null for no expiration)
     * @return string Token string
     */
    public function createToken(int $accountId, string $clientId, array $scopes = [], ?int $expiresIn = null): string
    {
        $token = generate_token(32);
        
        $expiresAt = null;
        if ($expiresIn !== null) {
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        }
        
        Database::execute(
            'INSERT INTO oauth_tokens (account_id, token, client_id, scopes, expires_at, created_at)
             VALUES (:account_id, :token, :client_id, :scopes, :expires_at, NOW())',
            [
                'account_id' => $accountId,
                'token' => $token,
                'client_id' => $clientId,
                'scopes' => implode(' ', $scopes),
                'expires_at' => $expiresAt,
            ]
        );
        
        return $token;
    }
    
    /**
     * Validate OAuth token.
     * 
     * @param string $token Token string
     * @return array|null Account data if valid, null otherwise
     */
    public function validateToken(string $token): ?array
    {
        $result = Database::fetchOne(
            'SELECT t.*, a.* FROM oauth_tokens t
             INNER JOIN accounts a ON t.account_id = a.id
             WHERE t.token = :token AND t.revoked = 0
             AND (t.expires_at IS NULL OR t.expires_at > NOW())
             AND a.is_suspended = 0',
            ['token' => $token]
        );
        
        if (!$result) {
            return null;
        }
        
        // Update last used
        Database::execute(
            'UPDATE oauth_tokens SET last_used_at = NOW() WHERE token = :token',
            ['token' => $token]
        );
        
        return $result;
    }
    
    /**
     * Revoke OAuth token.
     * 
     * @param string $token Token string
     * @return bool True if token was revoked
     */
    public function revokeToken(string $token): bool
    {
        return Database::execute(
            'UPDATE oauth_tokens SET revoked = 1 WHERE token = :token',
            ['token' => $token]
        ) > 0;
    }
    
    /**
     * Revoke all tokens for an account.
     * 
     * @param int $accountId Account ID
     * @return int Number of tokens revoked
     */
    public function revokeAllTokens(int $accountId): int
    {
        return Database::execute(
            'UPDATE oauth_tokens SET revoked = 1 WHERE account_id = :account_id',
            ['account_id' => $accountId]
        );
    }
    
    /**
     * Get current authenticated account.
     * 
     * @return array|null Account data or null if not logged in
     */
    public function getCurrentAccount(): ?array
    {
        $accountId = Session::get('account_id');
        if (!$accountId) {
            return null;
        }
        return Account::find((int) $accountId);
    }
    
    /**
     * Check if user is logged in.
     * 
     * @return bool True if logged in
     */
    public function isLoggedIn(): bool
    {
        return Session::get('account_id') !== null;
    }
    
    /**
     * Get account ID of logged in user.
     * 
     * @return int|null Account ID or null if not logged in
     */
    public function getAccountId(): ?int
    {
        $accountId = Session::get('account_id');
        return $accountId ? (int) $accountId : null;
    }
    
    /**
     * Check if current user is admin.
     * 
     * @return bool True if admin
     */
    public function isAdmin(): bool
    {
        $account = $this->getCurrentAccount();
        return $account !== null && ($account['is_admin'] ?? 0) === 1;
    }
    
    /**
     * Check if current user is moderator.
     * 
     * @return bool True if moderator or admin
     */
    public function isModerator(): bool
    {
        $account = $this->getCurrentAccount();
        return $account !== null && (($account['is_moderator'] ?? 0) === 1 || ($account['is_admin'] ?? 0) === 1);
    }
    
    /**
     * Change password for account.
     * 
     * @param int $accountId Account ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @throws AuthenticationException If current password is invalid
     */
    public function changePassword(int $accountId, string $currentPassword, string $newPassword): void
    {
        $account = Account::find($accountId);
        
        if (!$account || !verify_password($currentPassword, $account['password_hash'])) {
            throw new AuthenticationException('Current password is incorrect');
        }
        
        Account::update($accountId, [
            'password_hash' => hash_password($newPassword),
        ]);
        
        // Revoke all tokens except current session
        $this->revokeAllTokens($accountId);
    }
    
    /**
     * Validate registration data.
     * 
     * @param array $data Registration data
     * @throws ValidationException If validation fails
     */
    private function validateRegistration(array $data): void
    {
        // Check required fields
        if (empty($data['username'])) {
            throw new ValidationException('Username is required');
        }
        
        if (empty($data['email'])) {
            throw new ValidationException('Email is required');
        }
        
        if (empty($data['password'])) {
            throw new ValidationException('Password is required');
        }
        
        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]{1,30}$/', $data['username'])) {
            throw new ValidationException('Username must be 1-30 characters and contain only letters, numbers, and underscores');
        }
        
        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email address');
        }
        
        // Validate password strength
        if (strlen($data['password']) < 8) {
            throw new ValidationException('Password must be at least 8 characters');
        }
    }
}
