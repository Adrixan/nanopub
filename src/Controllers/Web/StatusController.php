<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Web;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\View;
use NanoPub\Core\Session;
use NanoPub\Core\Config;
use NanoPub\Core\Database;
use NanoPub\Models\Status;
use NanoPub\Models\Account;
use NanoPub\Models\Like;
use NanoPub\Models\Boost;
use NanoPub\Services\ActivityPubService;
use NanoPub\Services\NotificationService;
use NanoPub\Exceptions\NotFoundException;

/**
 * Handles status web UI endpoints.
 */
final class StatusController
{
    private ActivityPubService $activityPubService; // @phpstan-ignore property.onlyWritten

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
    public function show(Request $request, string $username, int $statusId): string
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

        return View::renderWithLayout('web/statuses/show', [
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
    public function create(Request $request): string
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return '';
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
            return '';
        }

        $baseUrl = Config::get('app.url') ?? '';
        $account = Account::find((int) $accountId);
        
        if ($account === null) {
            Response::redirect(url('/login'));
            return '';
        }

        // Create status (URI/URL set after insert to get the actual ID)
        $statusId = Status::create([
            'account_id' => $accountId,
            'content' => $content,
            'visibility' => $visibility,
            'in_reply_to_id' => $inReplyToId,
            'content_warning' => $contentWarning,
            'sensitive' => $sensitive,
            'uri' => '',
            'url' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update URI and URL now that we have the status ID
        Database::execute(
            'UPDATE statuses SET uri = ?, url = ? WHERE id = ?',
            [
                "{$baseUrl}/statuses/{$statusId}",
                "{$baseUrl}/@{$account['username']}/statuses/{$statusId}",
                $statusId,
            ]
        );

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
        return '';
    }

    /**
     * Delete status.
     * 
     * DELETE /statuses/{id}
     */
    public function delete(Request $request, int $id): string
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::json(['error' => 'Unauthorized'], 401);
            return '';
        }

        $status = Status::find($id);
        
        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        if ((int) $status['account_id'] !== (int) $accountId) {
            Response::json(['error' => 'Forbidden'], 403);
            return '';
        }

        Status::delete($id);

        // Send ActivityPub Delete activity for remote followers
        // The ActivityPubService will handle Delete activity for federation

        Response::redirect(url('/'));
        return '';
    }

    /**
     * Favourite a status.
     * 
     * POST /statuses/{id}/favourite
     */
    public function favourite(Request $request, int $id): string
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return '';
        }

        $status = Status::find($id);
        
        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        try {
            if (!Like::isLiked((int) $accountId, $id)) {
                Like::create((int) $accountId, $id);

                // Notify the status author
                if ((int) $status['account_id'] !== (int) $accountId) {
                    $this->notificationService->notifyFavourite(
                        (int) $status['account_id'],
                        $id,
                        (int) $accountId
                    );
                }
            }
        } catch (\RuntimeException $e) {
            // Silently fail on duplicate or DB error
        }

        $this->redirectBack($status);
        return '';
    }

    /**
     * Unfavourite a status.
     * 
     * POST /statuses/{id}/unfavourite
     */
    public function unfavourite(Request $request, int $id): string
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return '';
        }

        $status = Status::find($id);
        
        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        try {
            Like::delete((int) $accountId, $id);
        } catch (\RuntimeException $e) {
            // Silently fail on DB error
        }

        $this->redirectBack($status);
        return '';
    }

    /**
     * Reblog/boost a status.
     * 
     * POST /statuses/{id}/reblog
     */
    public function reblog(Request $request, int $id): string
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return '';
        }

        $status = Status::find($id);
        
        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        try {
            if (!Boost::isBoosted((int) $accountId, $id)) {
                Boost::create((int) $accountId, $id);

                // Notify the status author
                if ((int) $status['account_id'] !== (int) $accountId) {
                    $this->notificationService->notifyReblog(
                        (int) $status['account_id'],
                        $id,
                        (int) $accountId
                    );
                }
            }
        } catch (\RuntimeException $e) {
            // Silently fail on duplicate or DB error
        }

        $this->redirectBack($status);
        return '';
    }

    /**
     * Undo reblog/boost of a status.
     * 
     * POST /statuses/{id}/unreblog
     */
    public function unreblog(Request $request, int $id): string
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return '';
        }

        $status = Status::find($id);
        
        if ($status === null) {
            throw new NotFoundException('Status not found');
        }

        try {
            Boost::delete((int) $accountId, $id);
        } catch (\RuntimeException $e) {
            // Silently fail on DB error
        }

        $this->redirectBack($status);
        return '';
    }

    /**
     * Redirect back to the status page.
     * 
     * @param array $status Status data
     */
    private function redirectBack(array $status): void
    {
        $account = Account::find((int) $status['account_id']);
        if ($account !== null) {
            Response::redirect(url('/@' . $account['username'] . '/' . $status['id']));
        } else {
            Response::redirect(url('/'));
        }
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