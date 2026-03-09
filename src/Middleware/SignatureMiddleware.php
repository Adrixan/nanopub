<?php

declare(strict_types=1);

namespace NanoPub\Middleware;

use NanoPub\Core\Config;
use NanoPub\Core\Database;
use NanoPub\Core\Request;
use NanoPub\Exceptions\AuthenticationException;
use NanoPub\Models\Account;

/**
 * HTTP Signature verification middleware for ActivityPub federation.
 * 
 * Verifies HTTP Signatures on incoming federated requests.
 * Used for ActivityPub inbox endpoints to authenticate remote actors.
 * 
 * @see https://tools.ietf.org/html/draft-cavage-http-signatures-12
 */
final class SignatureMiddleware
{
    /**
     * Handle HTTP Signature verification for incoming requests.
     *
     * @param Request $request The incoming request
     * @param callable $next The next middleware/handler
     * @return mixed
     * @throws AuthenticationException If signature verification fails
     */
    public function __invoke(Request $request, callable $next): mixed
    {
        $signatureHeader = $request->getHeader('signature');

        if ($signatureHeader === null) {
            throw new AuthenticationException('Missing signature header');
        }

        $signature = $this->parseSignature($signatureHeader);

        if (!isset($signature['keyId'], $signature['signature'], $signature['headers'])) {
            throw new AuthenticationException('Invalid signature header format');
        }

        // Get actor's public key
        $account = $this->getAccount($signature['keyId']);

        if ($account === null || empty($account['public_key'])) {
            throw new AuthenticationException('Unable to retrieve actor public key');
        }

        // Build the signed string
        $signedString = $this->buildSignedString($request, $signature['headers']);

        // Verify signature
        if (!rsa_verify($signedString, $signature['signature'], $account['public_key'])) {
            throw new AuthenticationException('Invalid signature');
        }

        // Set authenticated actor on request
        $request->setAttribute('account_id', $account['id']);
        $request->setAttribute('account', $account);
        $request->setAttribute('authenticated_via', 'signature');

        return $next($request);
    }

    /**
     * Parse the Signature header into components.
     *
     * @param string $header The Signature header value
     * @return array{keyId?: string, headers?: array<string>, signature?: string} Parsed components
     */
    private function parseSignature(string $header): array
    {
        $result = [];

        // Parse key-value pairs from header
        // Format: keyId="...",headers="...",signature="..."
        $pattern = '/(\w+)="([^"]*)"/';
        
        if (preg_match_all($pattern, $header, $matches, PREG_SET_ORDER) === false) {
            return $result;
        }

        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[2];

            switch ($key) {
                case 'keyId':
                    $result['keyId'] = $value;
                    break;
                case 'headers':
                    // Headers are space-separated
                    $result['headers'] = explode(' ', $value);
                    break;
                case 'signature':
                    $result['signature'] = $value;
                    break;
            }
        }

        return $result;
    }

    /**
     * Build the signed string from request and signed headers.
     *
     * @param Request $request The incoming request
     * @param array<string> $headers Headers that were signed
     * @return string The string that should match the signature
     */
    private function buildSignedString(Request $request, array $headers): string
    {
        $lines = [];

        foreach ($headers as $header) {
            $header = strtolower($header);

            if ($header === '(request-target)') {
                // Special pseudo-header for request method and path
                $method = strtolower($request->method);
                $path = $request->path;
                $lines[] = "(request-target): {$method} {$path}";
            } else {
                $value = $request->getHeader($header);
                
                if ($value !== null) {
                    $lines[] = "{$header}: {$value}";
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get account by keyId (actor URL).
     * 
     * First checks local database, then fetches from remote instance.
     *
     * @param string $keyId The keyId from the signature (actor URL with #main-key)
     * @return array|null Account data or null if not found
     */
    private function getAccount(string $keyId): ?array
    {
        // Extract actor URL from keyId (usually ends with #main-key)
        $actorUrl = $this->extractActorUrl($keyId);

        // Check local database first
        $account = Account::findByActorUrl($actorUrl);

        if ($account !== null) {
            return $account;
        }

        // Fetch actor from remote instance
        return $this->fetchRemoteActor($actorUrl);
    }

    /**
     * Extract actor URL from keyId.
     * 
     * keyId is typically: https://example.com/users/username#main-key
     *
     * @param string $keyId The keyId from signature
     * @return string Actor URL
     */
    private function extractActorUrl(string $keyId): string
    {
        // Remove fragment (#main-key)
        $pos = strpos($keyId, '#');
        
        if ($pos !== false) {
            return substr($keyId, 0, $pos);
        }

        // If no fragment, the keyId might be the actor URL itself
        return $keyId;
    }

    /**
     * Fetch a remote actor and store in database.
     *
     * @param string $actorUrl The actor URL to fetch
     * @return array|null Account data or null if fetch fails
     */
    private function fetchRemoteActor(string $actorUrl): ?array
    {
        // Make HTTP request to fetch actor
        $response = http_get($actorUrl, [
            'Accept' => 'application/activity+json, application/ld+json',
        ]);

        if ($response['status'] !== 200) {
            return null;
        }

        $actor = json_decode($response['body'], true);

        if (!is_array($actor)) {
            return null;
        }

        // Validate required fields
        if (!isset($actor['id'], $actor['type'], $actor['preferredUsername'])) {
            return null;
        }

        // Extract public key
        $publicKey = null;
        if (isset($actor['publicKey']['publicKeyPem'])) {
            $publicKey = $actor['publicKey']['publicKeyPem'];
        }

        if ($publicKey === null) {
            return null;
        }

        // Extract inbox/outbox URLs
        $inboxUrl = $actor['inbox'] ?? null;
        $outboxUrl = $actor['outbox'] ?? null;
        $followersUrl = $actor['followers'] ?? null;
        $followingUrl = $actor['following'] ?? null;

        // Extract display name
        $displayName = $actor['name'] ?? $actor['preferredUsername'];

        // Extract avatar
        $avatarUrl = null;
        if (isset($actor['icon']['url'])) {
            $avatarUrl = $actor['icon']['url'];
        }

        // Extract bio
        $bio = null;
        if (isset($actor['summary'])) {
            $bio = strip_tags($actor['summary']);
        }

        // Check if account already exists (by username)
        $existingAccount = Account::findByUsername($actor['preferredUsername']);
        
        if ($existingAccount !== null) {
            // Update existing account with new data
            Database::execute(
                "UPDATE accounts SET 
                    actor_url = ?,
                    inbox_url = ?,
                    outbox_url = ?,
                    followers_url = ?,
                    following_url = ?,
                    public_key = ?,
                    display_name = ?,
                    avatar_url = ?,
                    bio = ?,
                    is_local = 0,
                    updated_at = NOW()
                 WHERE id = ?",
                [
                    $actorUrl,
                    $inboxUrl,
                    $outboxUrl,
                    $followersUrl,
                    $followingUrl,
                    $publicKey,
                    $displayName,
                    $avatarUrl,
                    $bio,
                    $existingAccount['id'],
                ]
            );
            
            return Account::find($existingAccount['id']);
        }

        // Create new remote account
        $accountId = $this->createRemoteAccount([
            'username' => $actor['preferredUsername'],
            'display_name' => $displayName,
            'actor_url' => $actorUrl,
            'inbox_url' => $inboxUrl,
            'outbox_url' => $outboxUrl,
            'followers_url' => $followersUrl,
            'following_url' => $followingUrl,
            'public_key' => $publicKey,
            'avatar_url' => $avatarUrl,
            'bio' => $bio,
            'is_local' => 0,
            'is_locked' => ($actor['manuallyApprovesFollowers'] ?? false) ? 1 : 0,
            'is_bot' => in_array($actor['type'], ['Service', 'Application'], true) ? 1 : 0,
        ]);

        return $accountId !== null ? Account::find($accountId) : null;
    }

    /**
     * Create a remote account in the database.
     *
     * @param array<string, mixed> $data Account data
     * @return int|null Account ID or null on failure
     */
    private function createRemoteAccount(array $data): ?int
    {
        try {
            Database::execute(
                "INSERT INTO accounts (
                    username, display_name, actor_url, inbox_url, outbox_url,
                    followers_url, following_url, public_key, avatar_url, bio,
                    is_local, is_locked, is_bot, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $data['username'],
                    $data['display_name'],
                    $data['actor_url'],
                    $data['inbox_url'],
                    $data['outbox_url'],
                    $data['followers_url'],
                    $data['following_url'],
                    $data['public_key'],
                    $data['avatar_url'],
                    $data['bio'],
                    $data['is_local'],
                    $data['is_locked'],
                    $data['is_bot'],
                ]
            );

            return (int) Database::lastInsertId();
        } catch (\Throwable) {
            return null;
        }
    }
}
