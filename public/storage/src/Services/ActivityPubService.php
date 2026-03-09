<?php

declare(strict_types=1);

namespace NanoPub\Services;

use NanoPub\Core\Config;
use NanoPub\Core\Database;
use NanoPub\Models\Account;
use NanoPub\Models\Status;
use NanoPub\Models\Follow;
use NanoPub\Models\FollowRequest;
use NanoPub\Models\Like;
use NanoPub\Models\Boost;
use NanoPub\Models\Queue\ActivityQueue;
use NanoPub\Models\Queue\DeliveryQueue;
use NanoPub\Exceptions\ValidationException;

/**
 * ActivityPub federation service for handling activities.
 */
final class ActivityPubService
{
    /**
     * Get actor object for account.
     * 
     * @param array $account Account data
     * @return array ActivityPub actor object
     */
    public function getActor(array $account): array
    {
        $baseUrl = Config::get('app.url');
        
        return [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
                [
                    'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
                    'sensitive' => 'as:sensitive',
                    'movedTo' => 'as:movedTo',
                    'alsoKnownAs' => 'as:alsoKnownAs',
                ],
            ],
            'id' => $account['actor_url'],
            'type' => $account['is_bot'] ? 'Service' : 'Person',
            'preferredUsername' => $account['username'],
            'name' => $account['display_name'] ?? $account['username'],
            'summary' => $account['bio'] ?? '',
            'url' => "{$baseUrl}/@{$account['username']}",
            'inbox' => $account['inbox_url'],
            'outbox' => $account['outbox_url'],
            'followers' => $account['followers_url'],
            'following' => $account['following_url'],
            'manuallyApprovesFollowers' => (bool) $account['is_locked'],
            'publicKey' => [
                'id' => "{$account['actor_url']}#main-key",
                'owner' => $account['actor_url'],
                'publicKeyPem' => $account['public_key'],
            ],
            'endpoints' => [
                'sharedInbox' => "{$baseUrl}/inbox",
            ],
        ];
    }
    
    /**
     * Handle incoming activity.
     * 
     * @param array $activity Activity data
     * @param string $actor Actor URL
     */
    public function handleActivity(array $activity, string $actor): void
    {
        $type = $activity['type'] ?? null;
        
        if ($type === null) {
            return;
        }
        
        match ($type) {
            'Follow' => $this->handleFollow($activity, $actor),
            'Accept' => $this->handleAccept($activity, $actor),
            'Reject' => $this->handleReject($activity, $actor),
            'Create' => $this->handleCreate($activity, $actor),
            'Announce' => $this->handleAnnounce($activity, $actor),
            'Like' => $this->handleLike($activity, $actor),
            'Undo' => $this->handleUndo($activity, $actor),
            'Delete' => $this->handleDelete($activity, $actor),
            'Update' => $this->handleUpdate($activity, $actor),
            default => null,
        };
    }
    
    /**
     * Handle Follow activity.
     * 
     * @param array $activity Activity data
     * @param string $actor Actor URL
     */
    public function handleFollow(array $activity, string $actor): void
    {
        $targetActorUrl = $activity['object'] ?? null;
        
        if ($targetActorUrl === null) {
            return;
        }
        
        // Find target account
        $targetAccount = Account::findByActorUrl($targetActorUrl);
        
        if ($targetAccount === null || !$targetAccount['is_local']) {
            return;
        }
        
        // Get or fetch the follower account
        $followerAccount = $this->getOrCreateRemoteAccount($actor);
        
        if ($followerAccount === null) {
            return;
        }
        
        // Check if already following
        if (Follow::isFollowing($followerAccount['id'], $targetAccount['id'])) {
            return;
        }
        
        $followUri = $activity['id'] ?? null;
        
        if ($targetAccount['is_locked']) {
            // Create follow request
            FollowRequest::create(
                $followerAccount['id'],
                $targetAccount['id'],
                $followUri
            );
            
            // Notify account of follow request
            $notificationService = new NotificationService();
            $notificationService->notifyFollowRequest($targetAccount['id'], $followerAccount['id']);
        } else {
            // Create follow directly
            Follow::create($followerAccount['id'], $targetAccount['id'], $followUri);
            
            // Send Accept back
            $this->sendAcceptFollow($targetAccount, $followerAccount, $activity);
            
            // Notify account of new follower
            $notificationService = new NotificationService();
            $notificationService->notifyFollow($targetAccount['id'], $followerAccount['id']);
        }
    }
    
    /**
     * Handle Accept activity.
     * 
     * @param array $activity Activity data
     * @param string $actor Actor URL
     */
    public function handleAccept(array $activity, string $actor): void
    {
        $object = $activity['object'] ?? null;
        
        if ($object === null) {
            return;
        }
        
        // Check if this is accepting a follow
        $objectType = $object['type'] ?? null;
        
        if ($objectType === 'Follow') {
            $followUri = $object['id'] ?? null;
            
            if ($followUri === null) {
                return;
            }
            
            // Find the follow request
            $followRequest = FollowRequest::findByUri($followUri);
            
            if ($followRequest === null) {
                return;
            }
            
            // Get the remote account
            $remoteAccount = $this->getOrCreateRemoteAccount($actor);
            
            if ($remoteAccount === null) {
                return;
            }
            
            // Create the follow relationship
            Follow::create(
                $followRequest['account_id'],
                $followRequest['target_account_id'],
                $followUri
            );
            
            // Delete the follow request
            FollowRequest::reject($followRequest['id']);
        }
    }
    
    /**
     * Handle Reject activity.
     * 
     * @param array $activity Activity data
     * @param string $actor Actor URL
     */
    public function handleReject(array $activity, string $actor): void
    {
        $object = $activity['object'] ?? null;
        
        if ($object === null) {
            return;
        }
        
        $objectType = $object['type'] ?? null;
        
        if ($objectType === 'Follow') {
            $followUri = $object['id'] ?? null;
            
            if ($followUri === null) {
                return;
            }
            
            // Delete the follow request
            $followRequest = FollowRequest::findByUri($followUri);
            
            if ($followRequest !== null) {
                FollowRequest::reject($followRequest['id']);
            }
        }
    }
    
    /**
     * Handle Create activity (new post).
     * 
     * @param array $activity Activity data
     * @param string $actor Actor URL
     */
    public function handleCreate(array $activity, string $actor): void
    {
        $object = $activity['object'] ?? null;
        
        if ($object === null) {
            return;
        }
        
        $objectType = $object['type'] ?? null;
        
        if ($objectType === 'Note') {
            $this->createStatusFromNote($object, $actor);
        }
    }
    
    /**
     * Handle Announce activity (boost).
     * 
     * @param array $activity Activity data
     * @param string $actor Actor URL
     */
    public function handleAnnounce(array $activity, string $actor): void
    {
        $objectUri = $activity['object'] ?? null;
        
        if ($objectUri === null) {
            return;
        }
        
        // Get the remote account
        $account = $this->getOrCreateRemoteAccount($actor);
        
        if ($account === null) {
            return;
        }
        
        // Get or fetch the status
        $status = $this->getOrCreateRemoteStatus($objectUri);
        
        if ($status === null) {
            return;
        }
        
        // Create boost
        $boostUri = $activity['id'] ?? null;
        
        Boost::create($account['id'], $status['id'], $boostUri);
    }
    
    /**
     * Handle Like activity.
     * 
     * @param array $activity Activity data
     * @param string $actor Actor URL
     */
    public function handleLike(array $activity, string $actor): void
    {
        $objectUri = $activity['object'] ?? null;
        
        if ($objectUri === null) {
            return;
        }
        
        // Get the remote account
        $account = $this->getOrCreateRemoteAccount($actor);
        
        if ($account === null) {
            return;
        }
        
        // Get or fetch the status
        $status = $this->getOrCreateRemoteStatus($objectUri);
        
        if ($status === null) {
            return;
        }
        
        // Create like
        $likeUri = $activity['id'] ?? null;
        
        Like::create($account['id'], $status['id'], $likeUri);
        
        // Notify status author
        $notificationService = new NotificationService();
        $notificationService->notifyFavourite(
            $status['account_id'],
            $status['id'],
            $account['id']
        );
    }
    
    /**
     * Handle Undo activity.
     * 
     * @param array $activity Activity data
     * @param string $actor Actor URL
     */
    public function handleUndo(array $activity, string $actor): void
    {
        $object = $activity['object'] ?? null;
        
        if ($object === null) {
            return;
        }
        
        $objectType = $object['type'] ?? null;
        
        // Get the remote account
        $account = $this->getOrCreateRemoteAccount($actor);
        
        if ($account === null) {
            return;
        }
        
        match ($objectType) {
            'Follow' => $this->undoFollow($object, $account),
            'Like' => $this->undoLike($object, $account),
            'Announce' => $this->undoAnnounce($object, $account),
            default => null,
        };
    }
    
    /**
     * Handle Delete activity.
     * 
     * @param array $activity Activity data
     * @param string $actor Actor URL
     */
    public function handleDelete(array $activity, string $actor): void
    {
        $objectUri = $activity['object'] ?? null;
        
        if ($objectUri === null) {
            return;
        }
        
        // Check if it's a status
        $status = Status::findByUri($objectUri);
        
        if ($status !== null) {
            // Verify ownership
            $account = $this->getOrCreateRemoteAccount($actor);
            
            if ($account !== null && $status['account_id'] === $account['id']) {
                Status::delete($status['id']);
            }
            
            return;
        }
        
        // Check if it's an account
        $account = Account::findByActorUrl($objectUri);
        
        if ($account !== null && !$account['is_local']) {
            // Mark account as deleted (or actually delete)
            Account::update($account['id'], [
                'is_suspended' => 1,
                'bio' => '[Deleted]',
            ]);
        }
    }
    
    /**
     * Handle Update activity.
     * 
     * @param array $activity Activity data
     * @param string $actor Actor URL
     */
    public function handleUpdate(array $activity, string $actor): void
    {
        $object = $activity['object'] ?? null;
        
        if ($object === null) {
            return;
        }
        
        $objectType = $object['type'] ?? null;
        
        if ($objectType === 'Person' || $objectType === 'Service') {
            $this->updateRemoteAccount($object, $actor);
        } elseif ($objectType === 'Note') {
            $this->updateRemoteStatus($object, $actor);
        }
    }
    
    /**
     * Send activity to remote inbox.
     * 
     * @param array $activity Activity data
     * @param int $actorAccountId Actor account ID
     * @param string $targetInbox Target inbox URL
     */
    public function sendActivity(array $activity, int $actorAccountId, string $targetInbox): void
    {
        DeliveryQueue::enqueue(
            $activity['id'] ?? generate_token(16),
            $activity['type'] ?? 'Activity',
            $activity,
            $targetInbox,
            $actorAccountId
        );
    }
    
    /**
     * Deliver activity (called by queue worker).
     * 
     * @param array $queueItem Queue item data
     * @return bool True on success
     */
    public function deliverActivity(array $queueItem): bool
    {
        $account = Account::find((int) $queueItem['signing_account_id']);
        
        if ($account === null || empty($account['private_key'])) {
            return false;
        }
        
        $activityData = json_decode($queueItem['activity_data'], true);
        
        if ($activityData === null) {
            return false;
        }
        
        // Build headers
        $headers = [
            'Content-Type' => 'application/activity+json',
            'Accept' => 'application/activity+json',
        ];
        
        // Sign the request
        $headers = $this->signRequest(
            'POST',
            $queueItem['target_inbox'],
            $headers,
            (int) $account['id'],
            json_encode($activityData)
        );
        
        // Send the request
        $response = http_post_json(
            $queueItem['target_inbox'],
            $activityData,
            $headers
        );
        
        return $response['status'] >= 200 && $response['status'] < 300;
    }
    
    /**
     * Fetch remote actor.
     * 
     * @param string $uri Actor URI
     * @return array|null Actor data or null on failure
     */
    public function fetchRemoteActor(string $uri): ?array
    {
        $headers = [
            'Accept' => 'application/activity+json',
        ];
        
        $response = http_get($uri, $headers);
        
        if ($response['status'] !== 200) {
            return null;
        }
        
        $data = json_decode($response['body'], true);
        
        if ($data === null) {
            return null;
        }
        
        return $data;
    }
    
    /**
     * Fetch remote status.
     * 
     * @param string $uri Status URI
     * @return array|null Status data or null on failure
     */
    public function fetchRemoteStatus(string $uri): ?array
    {
        $headers = [
            'Accept' => 'application/activity+json',
        ];
        
        $response = http_get($uri, $headers);
        
        if ($response['status'] !== 200) {
            return null;
        }
        
        $data = json_decode($response['body'], true);
        
        if ($data === null) {
            return null;
        }
        
        return $data;
    }
    
    /**
     * Build activity object.
     * 
     * @param string $type Activity type
     * @param array $object Activity object
     * @param int $actorAccountId Actor account ID
     * @return array Activity object
     */
    public function buildActivity(string $type, array $object, int $actorAccountId): array
    {
        $account = Account::find($actorAccountId);
        
        if ($account === null) {
            return [];
        }
        
        $baseUrl = Config::get('app.url');
        $activityId = "{$baseUrl}/activities/" . generate_token(16);
        
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => $type,
            'actor' => $account['actor_url'],
            'object' => $object,
            'published' => date('c'),
        ];
    }
    
    /**
     * Sign HTTP request.
     * 
     * @param string $method HTTP method
     * @param string $url Target URL
     * @param array $headers Request headers
     * @param int $accountId Account ID for signing
     * @param string|null $body Request body
     * @return array Headers with signature
     */
    public function signRequest(string $method, string $url, array $headers, int $accountId, ?string $body = null): array
    {
        $account = Account::find($accountId);
        
        if ($account === null || empty($account['private_key'])) {
            return $headers;
        }
        
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';
        $path = ($parsedUrl['path'] ?? '/') . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '');
        
        // Build signing string
        $date = gmdate('D, d M Y H:i:s T');
        $digest = $body !== null ? 'SHA-256=' . base64_encode(hash('sha256', $body, true)) : '';
        
        $headers['Host'] = $host;
        $headers['Date'] = $date;
        
        if ($digest !== '') {
            $headers['Digest'] = $digest;
        }
        
        // Build signature string
        $signedHeaders = ['(request-target)', 'host', 'date'];
        $signatureString = "(request-target): {$method} {$path}\nhost: {$host}\ndate: {$date}";
        
        if ($digest !== '') {
            $signedHeaders[] = 'digest';
            $signatureString .= "\ndigest: {$digest}";
        }
        
        // Sign with private key
        $signature = rsa_sign($signatureString, $account['private_key']);
        
        $keyId = "{$account['actor_url']}#main-key";
        $headersString = implode(' ', $signedHeaders);
        
        $headers['Signature'] = "keyId=\"{$keyId}\",algorithm=\"rsa-sha256\",headers=\"{$headersString}\",signature=\"{$signature}\"";
        
        return $headers;
    }
    
    /**
     * Get or create remote account from actor URL.
     * 
     * @param string $actorUrl Actor URL
     * @return array|null Account data or null on failure
     */
    private function getOrCreateRemoteAccount(string $actorUrl): ?array
    {
        // Check if already exists
        $account = Account::findByActorUrl($actorUrl);
        
        if ($account !== null) {
            return $account;
        }
        
        // Fetch from remote
        $actorData = $this->fetchRemoteActor($actorUrl);
        
        if ($actorData === null) {
            return null;
        }
        
        return $this->createRemoteAccount($actorData);
    }
    
    /**
     * Create remote account from actor data.
     * 
     * @param array $actorData Actor data
     * @return array|null Account data or null on failure
     */
    private function createRemoteAccount(array $actorData): ?array
    {
        $actorUrl = $actorData['id'] ?? null;
        
        if ($actorUrl === null) {
            return null;
        }
        
        $username = $actorData['preferredUsername'] ?? null;
        
        if ($username === null) {
            return null;
        }
        
        // Extract domain from actor URL
        $parsedUrl = parse_url($actorUrl);
        $domain = $parsedUrl['host'] ?? '';
        
        // Generate unique username
        $fullUsername = strtolower($username) . '@' . strtolower($domain);
        
        // Check if username already exists with different actor
        $existing = Account::findByUsername($fullUsername);
        
        if ($existing !== null) {
            return $existing;
        }
        
        $publicKey = '';
        if (isset($actorData['publicKey']['publicKeyPem'])) {
            $publicKey = $actorData['publicKey']['publicKeyPem'];
        }
        
        $accountId = Account::create([
            'username' => $fullUsername,
            'display_name' => $actorData['name'] ?? $username,
            'bio' => $actorData['summary'] ?? null,
            'actor_url' => $actorUrl,
            'inbox_url' => $actorData['inbox'] ?? null,
            'outbox_url' => $actorData['outbox'] ?? null,
            'followers_url' => $actorData['followers'] ?? null,
            'following_url' => $actorData['following'] ?? null,
            'public_key' => $publicKey,
            'is_local' => 0,
            'is_locked' => ($actorData['manuallyApprovesFollowers'] ?? false) ? 1 : 0,
            'is_bot' => ($actorData['type'] ?? 'Person') === 'Service' ? 1 : 0,
        ]);
        
        return Account::find($accountId);
    }
    
    /**
     * Get or create remote status from URI.
     * 
     * @param string $uri Status URI
     * @return array|null Status data or null on failure
     */
    private function getOrCreateRemoteStatus(string $uri): ?array
    {
        // Check if already exists
        $status = Status::findByUri($uri);
        
        if ($status !== null) {
            return $status;
        }
        
        // Fetch from remote
        $statusData = $this->fetchRemoteStatus($uri);
        
        if ($statusData === null) {
            return null;
        }
        
        return $this->createStatusFromNote($statusData, $statusData['attributedTo'] ?? '');
    }
    
    /**
     * Create status from Note object.
     * 
     * @param array $note Note data
     * @param string $actor Actor URL
     * @return array|null Status data or null on failure
     */
    private function createStatusFromNote(array $note, string $actor): ?array
    {
        $uri = $note['id'] ?? null;
        
        if ($uri === null) {
            return null;
        }
        
        // Check if already exists
        $existing = Status::findByUri($uri);
        
        if ($existing !== null) {
            return $existing;
        }
        
        // Get or create the account
        $account = $this->getOrCreateRemoteAccount($actor);
        
        if ($account === null) {
            return null;
        }
        
        // Determine visibility
        $visibility = 'public';
        $to = $note['to'] ?? [];
        $cc = $note['cc'] ?? [];
        
        if (in_array('https://www.w3.org/ns/activitystreams#Public', $to, true)) {
            $visibility = 'public';
        } elseif (in_array('https://www.w3.org/ns/activitystreams#Public', $cc, true)) {
            $visibility = 'unlisted';
        } elseif (is_array($to) && count($to) > 0 && !in_array('https://www.w3.org/ns/activitystreams#Public', $to, true)) {
            $visibility = 'private';
        }
        
        // Get in-reply-to if present
        $inReplyToUri = $note['inReplyTo'] ?? null;
        $inReplyToId = null;
        $inReplyToAccountId = null;
        
        if ($inReplyToUri !== null) {
            $parentStatus = $this->getOrCreateRemoteStatus($inReplyToUri);
            
            if ($parentStatus !== null) {
                $inReplyToId = $parentStatus['id'];
                $inReplyToAccountId = $parentStatus['account_id'];
            }
        }
        
        $statusId = Status::create([
            'account_id' => $account['id'],
            'in_reply_to_id' => $inReplyToId,
            'in_reply_to_account_id' => $inReplyToAccountId,
            'content' => $note['content'] ?? '',
            'content_warning' => $note['summary'] ?? null,
            'visibility' => $visibility,
            'sensitive' => isset($note['sensitive']) && $note['sensitive'] ? 1 : 0,
            'uri' => $uri,
            'url' => $note['url'] ?? $uri,
            'local' => 0,
        ]);
        
        return Status::find($statusId);
    }
    
    /**
     * Send Accept for Follow activity.
     * 
     * @param array $target Target account (being followed)
     * @param array $follower Follower account
     * @param array $followActivity Original follow activity
     */
    private function sendAcceptFollow(array $target, array $follower, array $followActivity): void
    {
        $baseUrl = Config::get('app.url');
        
        $acceptActivity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => "{$baseUrl}/activities/" . generate_token(16),
            'type' => 'Accept',
            'actor' => $target['actor_url'],
            'object' => $followActivity,
        ];
        
        $this->sendActivity(
            $acceptActivity,
            (int) $target['id'],
            $follower['inbox_url']
        );
    }
    
    /**
     * Undo a follow.
     * 
     * @param array $object Follow object
     * @param array $account Account performing undo
     */
    private function undoFollow(array $object, array $account): void
    {
        $targetActorUrl = $object['object'] ?? null;
        
        if ($targetActorUrl === null) {
            return;
        }
        
        $targetAccount = Account::findByActorUrl($targetActorUrl);
        
        if ($targetAccount === null) {
            return;
        }
        
        Follow::delete($account['id'], $targetAccount['id']);
    }
    
    /**
     * Undo a like.
     * 
     * @param array $object Like object
     * @param array $account Account performing undo
     */
    private function undoLike(array $object, array $account): void
    {
        $objectUri = $object['object'] ?? null;
        
        if ($objectUri === null) {
            return;
        }
        
        $status = Status::findByUri($objectUri);
        
        if ($status === null) {
            return;
        }
        
        Like::delete($account['id'], $status['id']);
    }
    
    /**
     * Undo an announce.
     * 
     * @param array $object Announce object
     * @param array $account Account performing undo
     */
    private function undoAnnounce(array $object, array $account): void
    {
        $objectUri = $object['object'] ?? null;
        
        if ($objectUri === null) {
            return;
        }
        
        $status = Status::findByUri($objectUri);
        
        if ($status === null) {
            return;
        }
        
        Boost::delete($account['id'], $status['id']);
    }
    
    /**
     * Update remote account from actor data.
     * 
     * @param array $object Actor object
     * @param string $actor Actor URL
     */
    private function updateRemoteAccount(array $object, string $actor): void
    {
        $account = Account::findByActorUrl($actor);
        
        if ($account === null || $account['is_local']) {
            return;
        }
        
        Account::update($account['id'], [
            'display_name' => $object['name'] ?? $account['display_name'],
            'bio' => $object['summary'] ?? $account['bio'],
            'is_locked' => ($object['manuallyApprovesFollowers'] ?? false) ? 1 : 0,
        ]);
    }
    
    /**
     * Update remote status from Note data.
     * 
     * @param array $object Note object
     * @param string $actor Actor URL
     */
    private function updateRemoteStatus(array $object, string $actor): void
    {
        $uri = $object['id'] ?? null;
        
        if ($uri === null) {
            return;
        }
        
        $status = Status::findByUri($uri);
        
        if ($status === null || $status['local']) {
            return;
        }
        
        // Verify ownership
        $account = Account::findByActorUrl($actor);
        
        if ($account === null || $status['account_id'] !== $account['id']) {
            return;
        }
        
        Status::update($status['id'], [
            'content' => $object['content'] ?? $status['content'],
            'content_warning' => $object['summary'] ?? $status['content_warning'],
            'sensitive' => isset($object['sensitive']) && $object['sensitive'] ? 1 : 0,
        ]);
    }
}
