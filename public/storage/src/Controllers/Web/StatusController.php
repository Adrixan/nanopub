<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Web;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\View;
use NanoPub\Core\Session;
use NanoPub\Core\Config;
use NanoPub\Models\Status;
use NanoPub\Models\Account;
use NanoPub\Services\ActivityPubService;
use NanoPub\Services\NotificationService;
use NanoPub\Exceptions\NotFoundException;

/**
 * Handles status web UI endpoints.
 */
final class StatusController
{
    private ActivityPubService $activityPubService;

    private NotificationService $notificationService;

    public function __construct()
    {
        $this->activityPubService = new ActivityPubService();
        $this->notificationService = new NotificationService();
    }

    /**
     * Show single status.
     * 
     * GET /@{username}/{statusId}
     */
    public function show(Request $request, string $username, int $statusId): void
    {
        $account = Account::findByUsername($username);
        
        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        $status = Status::find($statusId);
        
        if ($status === null || (int) $status['account_id'] !== $account['id']) {
            throw new NotFoundException('Status not found');
        }

        $context = Status::getContext($statusId);
        $currentAccount = $this->getCurrentAccount();

        echo View::render('web/statuses/show', [
            'status' => $status,
            'account' => $account,
            'context' => $context,
            'currentAccount' => $currentAccount,
        ]);
    }

    /**
     * Create new status.
     * 
     * POST /statuses
     */
    public function create(Request $request): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $data = $request->json();
        $input = !empty($data) ? $data : $_POST;

        $content = trim($input['status'] ?? $input['content'] ?? '');
        $visibility = $input['visibility'] ?? 'public';
        $inReplyToId = isset($input['in_reply_to_id']) ? (int) $input['in_reply_to_id'] : null;
        $contentWarning = trim($input['spoiler_text'] ?? $input['content_warning'] ?? '');
        $sensitive = isset($input['sensitive']) && (bool) $input['sensitive'];

        if (empty($content)) {
            Response::redirect(url('/?error=empty'));
            return;
        }

        $baseUrl = Config::get('app.url') ?? '';
        $account = Account::find((int) $accountId);
        
        if ($account === null) {
            Response::redirect(url('/login'));
            return;
        }

        // Create status
        $statusId = Status::create([
            'account_id' => $accountId,
            'content' => $content,
            'visibility' => $visibility,
            'in_reply_to_id' => $inReplyToId,
            'content_warning' => $contentWarning,
            'sensitive' => $sensitive,
            'uri' => "{$baseUrl}/statuses/{$accountId}",
            'url' => "{$baseUrl}/@{$account['username']}/statuses/",
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Handle ActivityPub for public/unlisted posts
        if (in_array($visibility, ['public', 'unlisted'])) {
            // Queue for federated delivery via ActivityPub
            // The ActivityPubService will handle Create activity
        }

        // Handle reply notifications
        if ($inReplyToId !== null) {
            $parentStatus = Status::find($inReplyToId);
            if ($parentStatus !== null && (int) $parentStatus['account_id'] !== (int) $accountId) {
                $this->notificationService->notifyMention(
                    (int) $parentStatus['account_id'],
                    $statusId,
                    (int) $accountId
                );
            }
        }

        Response::redirect(url('/'));
    }

    /**
     * Delete status.
     * 
     * DELETE /statuses/{id}
     */
    public function delete(Request $request, int $id): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::json(['error' => 'Unauthorized'], 401);
            return;
        }

        $status = Status::find($id);
        
        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        if ((int) $status['account_id'] !== (int) $accountId) {
            Response::json(['error' => 'Forbidden'], 403);
            return;
        }

        Status::delete($id);

        // Send ActivityPub Delete activity for remote followers
        // The ActivityPubService will handle Delete activity for federation

        Response::redirect(url('/'));
    }

    /**
     * Get current authenticated account.
     * 
     * @return array|null Account data or null
     */
    private function getCurrentAccount(): ?array
    {
        $accountId = Session::get('account_id');
        
        return $accountId !== null ? Account::find((int) $accountId) : null;
    }
}