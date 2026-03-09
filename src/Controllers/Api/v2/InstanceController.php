<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Api\v2;

use NanoPub\Core\Config;
use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Models\Instance;
use NanoPub\Models\Account;

/**
 * Handles instance API endpoints for Mastodon API v2 compatibility.
 */
final class InstanceController
{
    /**
     * Get instance information.
     * 
     * GET /api/v2/instance
     */
    public function show(Request $request): void
    {
        $instance = Instance::get();
        $stats = Instance::getStats();

        Response::json([
            'domain' => parse_url(Config::get('app.url') ?? '', PHP_URL_HOST) ?? '',
            'title' => $instance['title'] ?? Config::get('app.name') ?? '',
            'version' => '4.0.0', // Mastodon API compatibility
            'source_url' => 'https://github.com/nanopub/nanopub',
            'description' => $instance['description'] ?? '',
            'usage' => [
                'users' => [
                    'active_month' => $stats['active_users'] ?? 0,
                ],
            ],
            'thumbnail' => [
                'url' => url('/assets/images/instance-thumbnail.png'),
            ],
            'languages' => ['en'],
            'registrations' => [
                'enabled' => (bool) ($instance['registrations_open'] ?? true),
                'approval_required' => (bool) ($instance['approval_required'] ?? false),
            ],
            'contact' => [
                'email' => $instance['contact_email'] ?? '',
                'account' => $this->getContactAccount($instance),
            ],
            'rules' => $this->getRules(),
            'configuration' => [
                'statuses' => [
                    'max_characters' => (int) ($instance['max_toot_chars'] ?? 500),
                    'max_media_attachments' => (int) ($instance['max_media_attachments'] ?? 4),
                ],
                'media_attachments' => [
                    'image_size_limit' => (int) ($instance['max_image_size'] ?? 8388608),
                    'video_size_limit' => (int) ($instance['max_video_size'] ?? 41943040),
                ],
            ],
        ]);
    }

    /**
     * Get contact account for instance.
     * 
     * @param array|null $instance Instance data
     * @return array|null Contact account or null
     */
    private function getContactAccount(?array $instance): ?array
    {
        if ($instance === null || empty($instance['admin_account_id'])) {
            return null;
        }

        $account = Account::find((int) $instance['admin_account_id']);
        
        return $account !== null ? $this->formatAccount($account) : null;
    }

    /**
     * Get instance rules.
     * 
     * @return array List of rules
     */
    private function getRules(): array
    {
        // Return instance rules if any
        return [];
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
