<?php

declare(strict_types=1);

namespace NanoPub\Services;

use Generator;
use NanoPub\Core\Config;
use NanoPub\Core\Database;
use NanoPub\Models\Account;
use NanoPub\Models\Status;

/**
 * Search service for accounts and statuses.
 */
final class SearchService
{
    /**
     * Search accounts.
     * 
     * @param string $query Search query
     * @param int $limit Maximum results
     * @return array<int, array<string, mixed>> Array of accounts
     */
    public function searchAccounts(string $query, int $limit = 40, int $offset = 0): array
    {
        $query = trim($query);
        
        if (empty($query)) {
            return [];
        }
        
        // Handle @username@domain format (Webfinger)
        if (preg_match('/^@?([a-zA-Z0-9_]+)@([a-zA-Z0-9.-]+)$/', $query, $matches)) {
            $username = $matches[1];
            $domain = $matches[2];
            
            // Check if it's a local account
            if ($domain === parse_url(Config::get('app.url'), PHP_URL_HOST)) {
                $account = Account::findByUsername($username);
                return $account !== null ? [$account] : [];
            }
            
            // Try to resolve remote account
            $remoteAccount = $this->resolveAccount($query);
            
            if ($remoteAccount !== null) {
                return [$remoteAccount];
            }
        }
        
        // Handle URL format
        if (filter_var($query, FILTER_VALIDATE_URL)) {
            $account = $this->resolveAccount($query);
            
            if ($account !== null) {
                return [$account];
            }
        }
        
        // Search local accounts
        $accounts = [];
        $searchTerm = "%{$query}%";
        
        $results = Database::fetchAll(
            'SELECT * FROM accounts 
             WHERE (username LIKE ? OR display_name LIKE ?)
             AND is_suspended = 0
             ORDER BY 
                 CASE WHEN username = ? THEN 0 ELSE 1 END,
                 followers_count DESC
             LIMIT ?
             OFFSET ?',
            [$searchTerm, $searchTerm, $query, $limit, $offset]
        );
        
        foreach ($results as $row) {
            $accounts[] = $row;
        }
        
        return $accounts;
    }
    
    /**
     * Search statuses.
     * 
     * @param string $query Search query
     * @param int $accountId Account ID performing the search
     * @param int $limit Maximum results
     * @return array<int, array<string, mixed>> Array of statuses
     */
    public function searchStatuses(string $query, int $accountId, int $limit = 40, int $offset = 0): array
    {
        $query = trim($query);
        
        if (empty($query)) {
            return [];
        }
        
        // Handle URL format
        if (filter_var($query, FILTER_VALIDATE_URL)) {
            $status = $this->resolveStatus($query);
            
            if ($status !== null) {
                return [$status];
            }
        }
        
        // Search statuses
        $statuses = [];
        $searchTerm = "%{$query}%";
        
        // Get account's following list for visibility filtering
        $followingIds = $this->getFollowingIds($accountId);
        $followingIds[] = $accountId;
        $followingPlaceholders = implode(',', array_fill(0, count($followingIds), '?'));
        
        $sql = "SELECT s.*, a.username, a.display_name, a.avatar_url
                FROM statuses s
                INNER JOIN accounts a ON s.account_id = a.id
                WHERE s.content LIKE ?
                AND a.is_suspended = 0
                AND (
                    s.visibility = 'public'
                    OR s.visibility = 'unlisted'
                    OR (s.visibility = 'private' AND s.account_id IN ({$followingPlaceholders}))
                    OR s.account_id = ?
                )
                ORDER BY s.created_at DESC
                LIMIT ?
                OFFSET ?";
        
        $params = array_merge([$searchTerm], $followingIds, [$accountId, $limit, $offset]);
        
        $results = Database::fetchAll($sql, $params);
        
        foreach ($results as $row) {
            $statuses[] = $row;
        }
        
        return $statuses;
    }
    
    /**
     * Search hashtags.
     * 
     * @param string $query Search query
     * @param int $limit Maximum results
     * @return array Array of hashtag info
     */
    public function searchHashtags(string $query, int $limit = 40, int $offset = 0): array
    {
        $query = trim($query);
        
        // Remove # prefix if present
        if (str_starts_with($query, '#')) {
            $query = substr($query, 1);
        }
        
        if (empty($query)) {
            return [];
        }
        
        $searchTerm = "%{$query}%";
        
        // Search for hashtags in statuses
        $results = Database::fetchAll(
            "SELECT 
                LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(s.content, CONCAT('#', ?), -1), ' ', 1)) as tag,
                COUNT(*) as count
             FROM statuses s
             INNER JOIN accounts a ON s.account_id = a.id
             WHERE s.content LIKE CONCAT('%#', ?, '%')
             AND s.visibility IN ('public', 'unlisted')
             AND a.is_suspended = 0
             GROUP BY tag
             HAVING tag LIKE ?
             ORDER BY count DESC
             LIMIT ?
             OFFSET ?",
            [$query, $query, $searchTerm, $limit, $offset]
        );
        
        $hashtags = [];
        $baseUrl = Config::get('app.url');
        
        foreach ($results as $row) {
            $tag = preg_replace('/[^a-zA-Z0-9_]/', '', $row['tag']);
            
            if (!empty($tag)) {
                $hashtags[] = [
                    'name' => '#' . $tag,
                    'url' => "{$baseUrl}/tags/{$tag}",
                    'count' => (int) $row['count'],
                ];
            }
        }
        
        return $hashtags;
    }
    
    /**
     * General search (v2 API).
     * 
     * @param string $query Search query
     * @param int|null $accountId Account ID performing the search (null for unauthenticated)
     * @param string $type Search type: accounts, statuses, hashtags, or all
     * @param int $limit Maximum results per type
     * @param int $offset Offset for pagination
     * @param bool $resolve Whether to resolve remote accounts/statuses
     * @return array{accounts: array, statuses: array, hashtags: array}
     */
    public function search(string $query, ?int $accountId, string $type = 'all', int $limit = 40, int $offset = 0, bool $resolve = false): array
    {
        $results = [
            'accounts' => [],
            'statuses' => [],
            'hashtags' => [],
        ];
        
        $query = trim($query);
        
        if (empty($query)) {
            return $results;
        }
        
        // If resolve is enabled, try to resolve remote accounts/statuses
        if ($resolve) {
            if ($type === 'all' || $type === 'accounts') {
                $resolved = $this->resolveAccount($query);
                if ($resolved !== null) {
                    $results['accounts'][] = $resolved;
                }
            }
            
            if ($type === 'all' || $type === 'statuses') {
                $resolved = $this->resolveStatus($query);
                if ($resolved !== null) {
                    $results['statuses'][] = $resolved;
                }
            }
        }
        
        if ($type === 'all' || $type === 'accounts') {
            $results['accounts'] = array_merge(
                $results['accounts'],
                $this->searchAccounts($query, $limit, $offset)
            );
        }
        
        if (($type === 'all' || $type === 'statuses') && $accountId !== null) {
            $results['statuses'] = array_merge(
                $results['statuses'],
                $this->searchStatuses($query, $accountId, $limit, $offset)
            );
        }
        
        if ($type === 'all' || $type === 'hashtags') {
            $results['hashtags'] = array_merge(
                $results['hashtags'],
                $this->searchHashtags($query, $limit, $offset)
            );
        }
        
        return $results;
    }
    
    /**
     * Resolve remote account.
     * 
     * @param string $query Username@domain or URL
     * @return array|null Account data or null on failure
     */
    public function resolveAccount(string $query): ?array
    {
        $query = trim($query);
        
        // Handle @username@domain format
        if (preg_match('/^@?([a-zA-Z0-9_]+)@([a-zA-Z0-9.-]+)$/', $query, $matches)) {
            $username = $matches[1];
            $domain = $matches[2];
            
            // Check if already exists locally
            $fullUsername = strtolower($username) . '@' . strtolower($domain);
            $existing = Account::findByUsername($fullUsername);
            
            if ($existing !== null) {
                return $existing;
            }
            
            // Try Webfinger discovery
            $actorUrl = $this->webfingerDiscover($username, $domain);
            
            if ($actorUrl !== null) {
                return $this->fetchAndStoreRemoteAccount($actorUrl);
            }
        }
        
        // Handle URL format
        if (filter_var($query, FILTER_VALIDATE_URL)) {
            // Check if it's already a local account
            $existing = Account::findByActorUrl($query);
            
            if ($existing !== null) {
                return $existing;
            }
            
            return $this->fetchAndStoreRemoteAccount($query);
        }
        
        return null;
    }
    
    /**
     * Resolve remote status.
     * 
     * @param string $url Status URL
     * @return array|null Status data or null on failure
     */
    public function resolveStatus(string $url): ?array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        
        // Check if already exists locally
        $existing = Status::findByUri($url);
        
        if ($existing !== null) {
            return $existing;
        }
        
        // Fetch from remote
        $headers = [
            'Accept' => 'application/activity+json',
        ];
        
        $response = http_get($url, $headers);
        
        if ($response['status'] !== 200) {
            return null;
        }
        
        $data = json_decode($response['body'], true);
        
        if ($data === null || ($data['type'] ?? null) !== 'Note') {
            return null;
        }
        
        // Get the author
        $actorUrl = $data['attributedTo'] ?? null;
        
        if ($actorUrl === null) {
            return null;
        }
        
        $account = $this->resolveAccount($actorUrl);
        
        if ($account === null) {
            return null;
        }
        
        // Create the status
        $statusId = Status::create([
            'account_id' => $account['id'],
            'content' => $data['content'] ?? '',
            'content_warning' => $data['summary'] ?? null,
            'visibility' => $this->determineVisibility($data),
            'sensitive' => isset($data['sensitive']) && $data['sensitive'] ? 1 : 0,
            'uri' => $data['id'] ?? $url,
            'url' => $data['url'] ?? $url,
            'local' => 0,
        ]);
        
        return Status::find($statusId);
    }
    
    /**
     * Discover actor URL via Webfinger.
     * 
     * @param string $username Username
     * @param string $domain Domain
     * @return string|null Actor URL or null on failure
     */
    private function webfingerDiscover(string $username, string $domain): ?string
    {
        $webfingerUrl = "https://{$domain}/.well-known/webfinger?resource=acct:{$username}@{$domain}";
        
        $response = http_get($webfingerUrl, ['Accept' => 'application/jrd+json']);
        
        if ($response['status'] !== 200) {
            return null;
        }
        
        $data = json_decode($response['body'], true);
        
        if ($data === null || !isset($data['links'])) {
            return null;
        }
        
        foreach ($data['links'] as $link) {
            if (($link['rel'] ?? null) === 'self' && 
                ($link['type'] ?? null) === 'application/activity+json') {
                return $link['href'] ?? null;
            }
        }
        
        return null;
    }
    
    /**
     * Fetch and store remote account.
     * 
     * @param string $actorUrl Actor URL
     * @return array|null Account data or null on failure
     */
    private function fetchAndStoreRemoteAccount(string $actorUrl): ?array
    {
        $headers = [
            'Accept' => 'application/activity+json',
        ];
        
        $response = http_get($actorUrl, $headers);
        
        if ($response['status'] !== 200) {
            return null;
        }
        
        $data = json_decode($response['body'], true);
        
        if ($data === null) {
            return null;
        }
        
        $username = $data['preferredUsername'] ?? null;
        
        if ($username === null) {
            return null;
        }
        
        // Extract domain from actor URL
        $parsedUrl = parse_url($actorUrl);
        $domain = $parsedUrl['host'] ?? '';
        
        // Generate unique username
        $fullUsername = strtolower($username) . '@' . strtolower($domain);
        
        // Check if already exists
        $existing = Account::findByUsername($fullUsername);
        
        if ($existing !== null) {
            return $existing;
        }
        
        $publicKey = '';
        if (isset($data['publicKey']['publicKeyPem'])) {
            $publicKey = $data['publicKey']['publicKeyPem'];
        }
        
        $accountId = Account::create([
            'username' => $fullUsername,
            'display_name' => $data['name'] ?? $username,
            'bio' => $data['summary'] ?? null,
            'actor_url' => $actorUrl,
            'inbox_url' => $data['inbox'] ?? null,
            'outbox_url' => $data['outbox'] ?? null,
            'followers_url' => $data['followers'] ?? null,
            'following_url' => $data['following'] ?? null,
            'public_key' => $publicKey,
            'is_local' => 0,
            'is_locked' => ($data['manuallyApprovesFollowers'] ?? false) ? 1 : 0,
            'is_bot' => ($data['type'] ?? 'Person') === 'Service' ? 1 : 0,
        ]);
        
        return Account::find($accountId);
    }
    
    /**
     * Get following IDs for an account.
     * 
     * @param int $accountId Account ID
     * @return array<int> Array of account IDs
     */
    private function getFollowingIds(int $accountId): array
    {
        $results = Database::fetchAll(
            'SELECT target_account_id FROM follows WHERE account_id = ?',
            [$accountId]
        );
        
        return array_map('intval', array_column($results, 'target_account_id'));
    }
    
    /**
     * Determine visibility from ActivityPub data.
     * 
     * @param array $data ActivityPub Note data
     * @return string Visibility level
     */
    private function determineVisibility(array $data): string
    {
        $to = $data['to'] ?? [];
        $cc = $data['cc'] ?? [];
        
        if (in_array('https://www.w3.org/ns/activitystreams#Public', $to, true)) {
            return 'public';
        }
        
        if (in_array('https://www.w3.org/ns/activitystreams#Public', $cc, true)) {
            return 'unlisted';
        }
        
        if (is_array($to) && count($to) > 0) {
            return 'private';
        }
        
        return 'direct';
    }
}
