<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Api\v1;

use NanoPub\Core\Config;
use NanoPub\Core\Database;
use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Models\Account;
use NanoPub\Services\AuthService;
use NanoPub\Exceptions\ValidationException;
use NanoPub\Exceptions\AuthenticationException;

/**
 * Handles authentication API endpoints for Mastodon API v1 compatibility.
 */
final class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Register a new account.
     * 
     * POST /api/v1/accounts
     */
    public function register(Request $request): void
    {
        $data = $request->json();

        // Validate required fields
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $displayName = trim($data['display_name'] ?? $username);
        $agreement = $data['agreement'] ?? false;

        // Check terms agreement
        if (!$agreement) {
            Response::badRequest('You must agree to the terms of service');
            return;
        }

        try {
            $accountId = $this->authService->register([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'display_name' => $displayName,
            ]);
        } catch (ValidationException $e) {
            Response::badRequest($e->getMessage());
            return;
        }

        // Get the created account
        $account = Account::find($accountId);

        // Generate token for immediate login
        $token = $this->authService->createToken(
            $accountId,
            'web',
            ['read', 'write', 'follow', 'push'],
            null // No expiration
        );

        Response::json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'scope' => 'read write follow push',
            'created_at' => time(),
            'account' => $this->formatAccount($account),
        ], 201);
    }

    /**
     * Get OAuth token.
     * 
     * POST /oauth/token
     */
    public function token(Request $request): void
    {
        $data = $request->json();

        // Support form data as well
        if (empty($data)) {
            $data = $request->query;
        }

        $grantType = $data['grant_type'] ?? 'password';
        $clientId = $data['client_id'] ?? '';
        $clientSecret = $data['client_secret'] ?? '';
        $scope = $data['scope'] ?? 'read';

        // Validate client
        $client = $this->validateClient($clientId, $clientSecret);
        if ($client === null) {
            Response::json([
                'error' => 'invalid_client',
                'error_description' => 'Invalid client credentials',
            ], 401);
            return;
        }

        try {
            switch ($grantType) {
                case 'password':
                    $tokenData = $this->handlePasswordGrant($data, $clientId, $scope);
                    break;

                case 'authorization_code':
                    $tokenData = $this->handleAuthorizationCodeGrant($data, $clientId, $scope);
                    break;

                case 'refresh_token':
                    $tokenData = $this->handleRefreshTokenGrant($data, $clientId, $scope);
                    break;

                case 'client_credentials':
                    $tokenData = $this->handleClientCredentialsGrant($clientId, $scope);
                    break;

                default:
                    Response::json([
                        'error' => 'unsupported_grant_type',
                        'error_description' => 'Unsupported grant type',
                    ], 400);
                    return;
            }
        } catch (AuthenticationException $e) {
            Response::json([
                'error' => 'invalid_grant',
                'error_description' => $e->getMessage(),
            ], 401);
            return;
        } catch (ValidationException $e) {
            Response::json([
                'error' => 'invalid_request',
                'error_description' => $e->getMessage(),
            ], 400);
            return;
        }

        Response::json($tokenData);
    }

    /**
     * Revoke OAuth token.
     * 
     * POST /oauth/revoke
     */
    public function revoke(Request $request): void
    {
        $data = $request->json();

        // Support form data as well
        if (empty($data)) {
            $data = $request->query;
        }

        $token = $data['token'] ?? '';
        $clientId = $data['client_id'] ?? '';
        $clientSecret = $data['client_secret'] ?? '';

        // Validate client
        $client = $this->validateClient($clientId, $clientSecret);
        if ($client === null) {
            Response::json([
                'error' => 'invalid_client',
                'error_description' => 'Invalid client credentials',
            ], 401);
            return;
        }

        // Revoke the token
        $revoked = $this->authService->revokeToken($token);

        if ($revoked) {
            Response::json(['revoked' => true]);
        } else {
            Response::json(['revoked' => false]);
        }
    }

    /**
     * Create a new OAuth application.
     * 
     * POST /api/v1/apps
     */
    public function createApp(Request $request): void
    {
        $data = $request->json();

        $clientName = trim($data['client_name'] ?? '');
        $redirectUris = $data['redirect_uris'] ?? '';
        $scopes = $data['scopes'] ?? 'read';
        $website = trim($data['website'] ?? '');

        if (empty($clientName)) {
            Response::badRequest('client_name is required');
            return;
        }

        if (empty($redirectUris)) {
            Response::badRequest('redirect_uris is required');
            return;
        }

        // Generate client ID and secret
        $clientId = generate_token(16);
        $clientSecret = generate_token(32);

        // Store the application
        Database::execute(
            'INSERT INTO oauth_applications (client_id, client_secret, name, redirect_uri, scopes, website, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$clientId, $clientSecret, $clientName, $redirectUris, $scopes, $website]
        );

        $baseUrl = Config::get('app.url') ?? '';

        Response::json([
            'id' => (string) Database::lastInsertId(),
            'name' => $clientName,
            'website' => $website ?: null,
            'redirect_uri' => $redirectUris,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'vapid_key' => null, // Would need to generate VAPID keys for push notifications
        ], 201);
    }

    /**
     * Handle password grant type.
     * 
     * @param array $data Request data
     * @param string $clientId Client ID
     * @param string $scope Requested scope
     * @return array Token data
     * @throws AuthenticationException If credentials are invalid
     */
    private function handlePasswordGrant(array $data, string $clientId, string $scope): array
    {
        $email = $data['username'] ?? ''; // Mastodon uses 'username' for email
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            throw new ValidationException('Username and password are required');
        }

        // Attempt login
        $account = $this->authService->login($email, $password);

        // Create token
        $scopes = explode(' ', $scope);
        $token = $this->authService->createToken(
            (int) $account['id'],
            $clientId,
            $scopes,
            7 * 24 * 60 * 60 // 7 days
        );

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'scope' => $scope,
            'created_at' => time(),
            'expires_in' => 7 * 24 * 60 * 60,
            'refresh_token' => null, // Could implement refresh tokens
        ];
    }

    /**
     * Handle authorization code grant type.
     * 
     * @param array $data Request data
     * @param string $clientId Client ID
     * @param string $scope Requested scope
     * @return array Token data
     * @throws AuthenticationException If code is invalid
     */
    private function handleAuthorizationCodeGrant(array $data, string $clientId, string $scope): array
    {
        $code = $data['code'] ?? '';
        $redirectUri = $data['redirect_uri'] ?? '';

        if (empty($code)) {
            throw new ValidationException('Authorization code is required');
        }

        // Validate the authorization code
        $authCode = Database::fetchOne(
            'SELECT * FROM oauth_authorization_codes WHERE code = ? AND client_id = ? AND redirect_uri = ? AND expires_at > NOW()',
            [$code, $clientId, $redirectUri]
        );

        if ($authCode === null) {
            throw new AuthenticationException('Invalid or expired authorization code');
        }

        // Delete the used authorization code
        Database::execute(
            'DELETE FROM oauth_authorization_codes WHERE code = ?',
            [$code]
        );

        // Create token
        $scopes = explode(' ', $scope);
        $token = $this->authService->createToken(
            (int) $authCode['account_id'],
            $clientId,
            $scopes,
            7 * 24 * 60 * 60 // 7 days
        );

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'scope' => $scope,
            'created_at' => time(),
            'expires_in' => 7 * 24 * 60 * 60,
        ];
    }

    /**
     * Handle refresh token grant type.
     * 
     * @param array $data Request data
     * @param string $clientId Client ID
     * @param string $scope Requested scope
     * @return array Token data
     * @throws AuthenticationException If refresh token is invalid
     */
    private function handleRefreshTokenGrant(array $data, string $clientId, string $scope): array
    {
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            throw new ValidationException('Refresh token is required');
        }

        // Validate refresh token (would need a separate table for refresh tokens)
        // For now, we'll just return an error
        throw new AuthenticationException('Refresh tokens are not supported');
    }

    /**
     * Handle client credentials grant type.
     * 
     * @param string $clientId Client ID
     * @param string $scope Requested scope
     * @return array Token data
     */
    private function handleClientCredentialsGrant(string $clientId, string $scope): array
    {
        // Create a token for the client (not associated with any account)
        $token = generate_token(32);

        Database::execute(
            'INSERT INTO oauth_tokens (token, client_id, scopes, created_at) VALUES (?, ?, ?, NOW())',
            [$token, $clientId, $scope]
        );

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'scope' => $scope,
            'created_at' => time(),
        ];
    }

    /**
     * Validate OAuth client credentials.
     * 
     * @param string $clientId Client ID
     * @param string $clientSecret Client secret
     * @return array|null Client data or null if invalid
     */
    private function validateClient(string $clientId, string $clientSecret): ?array
    {
        // Allow empty client for public clients
        if (empty($clientId)) {
            return ['client_id' => '', 'public' => true];
        }

        $client = Database::fetchOne(
            'SELECT * FROM oauth_applications WHERE client_id = ?',
            [$clientId]
        );

        if ($client === null) {
            return null;
        }

        // Check secret if provided (public clients may not have a secret)
        if (!empty($client['client_secret']) && !empty($clientSecret)) {
            if ($client['client_secret'] !== $clientSecret) {
                return null;
            }
        }

        return $client;
    }

    /**
     * Format account for Mastodon API response.
     * 
     * @param array $account Account data
     * @return array Formatted account
     */
    private function formatAccount(array $account): array
    {
        $baseUrl = Config::get('app.url') ?? '';
        $username = $account['username'] ?? '';

        return [
            'id' => (string) $account['id'],
            'username' => $username,
            'acct' => $account['is_local'] ? $username : $username . '@' . parse_url($account['actor_url'] ?? '', PHP_URL_HOST),
            'display_name' => $account['display_name'] ?? $username,
            'locked' => (bool) ($account['is_locked'] ?? false),
            'bot' => (bool) ($account['is_bot'] ?? false),
            'created_at' => $account['created_at'] ?? date('c'),
            'note' => $account['bio'] ?? '',
            'url' => "{$baseUrl}/@{$username}",
            'avatar' => $account['avatar_url'] ?? "{$baseUrl}/assets/images/default-avatar.png",
            'avatar_static' => $account['avatar_url'] ?? "{$baseUrl}/assets/images/default-avatar.png",
            'header' => $account['header_url'] ?? "{$baseUrl}/assets/images/default-header.png",
            'header_static' => $account['header_url'] ?? "{$baseUrl}/assets/images/default-header.png",
            'followers_count' => (int) ($account['followers_count'] ?? 0),
            'following_count' => (int) ($account['following_count'] ?? 0),
            'statuses_count' => (int) ($account['statuses_count'] ?? 0),
            'last_status_at' => $account['last_activity_at'] ?? null,
            'emojis' => [],
            'fields' => [],
        ];
    }
}