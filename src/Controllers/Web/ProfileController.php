<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Web;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\View;
use NanoPub\Core\Session;
use NanoPub\Models\Account;
use NanoPub\Models\Status;
use NanoPub\Models\Follow;
use NanoPub\Exceptions\NotFoundException;

/**
 * Handles profile web UI endpoints.
 */
final class ProfileController
{
    /**
     * Show profile.
     * 
     * GET /@{username}
     */
    public function show(Request $request, string $username): void
    {
        $account = Account::findByUsername($username);
        
        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        $statuses = Status::getAccountStatuses((int) $account['id'], 20);
        $currentAccount = $this->getCurrentAccount();
        $relationship = $currentAccount !== null
            ? Follow::getRelationship((int) $currentAccount['id'], (int) $account['id'])
            : null;

        echo View::render('web/profiles/show', [
            'account' => $account,
            'statuses' => $statuses,
            'currentAccount' => $currentAccount,
            'relationship' => $relationship,
        ]);
    }

    /**
     * Show followers.
     * 
     * GET /@{username}/followers
     */
    public function followers(Request $request, string $username): void
    {
        $account = Account::findByUsername($username);
        
        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        $limit = min((int) ($request->query['limit'] ?? 40), 80);
        $offset = (int) ($request->query['offset'] ?? 0);

        $followers = Follow::getFollowers((int) $account['id'], $limit, $offset);
        $currentAccount = $this->getCurrentAccount();

        echo View::render('web/profiles/followers', [
            'account' => $account,
            'followers' => $followers,
            'currentAccount' => $currentAccount,
        ]);
    }

    /**
     * Show following.
     * 
     * GET /@{username}/following
     */
    public function following(Request $request, string $username): void
    {
        $account = Account::findByUsername($username);
        
        if ($account === null) {
            throw new NotFoundException('Account not found');
        }

        $limit = min((int) ($request->query['limit'] ?? 40), 80);
        $offset = (int) ($request->query['offset'] ?? 0);

        $following = Follow::getFollowing((int) $account['id'], $limit, $offset);
        $currentAccount = $this->getCurrentAccount();

        echo View::render('web/profiles/following', [
            'account' => $account,
            'following' => $following,
            'currentAccount' => $currentAccount,
        ]);
    }

    /**
     * Follow a user.
     * 
     * POST /@{username}/follow
     */
    public function follow(Request $request, string $username): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $targetAccount = Account::findByUsername($username);
        
        if ($targetAccount === null) {
            throw new NotFoundException('Account not found');
        }

        // Cannot follow yourself
        if ((int) $targetAccount['id'] === (int) $accountId) {
            Response::redirect(url('/@' . $username));
            return;
        }

        try {
            if (!Follow::isFollowing((int) $accountId, (int) $targetAccount['id'])) {
                Follow::create((int) $accountId, (int) $targetAccount['id']);
            }
        } catch (\RuntimeException $e) {
            // Silently fail on duplicate or DB error
        }

        Response::redirect(url('/@' . $username));
    }

    /**
     * Unfollow a user.
     * 
     * POST /@{username}/unfollow
     */
    public function unfollow(Request $request, string $username): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $targetAccount = Account::findByUsername($username);
        
        if ($targetAccount === null) {
            throw new NotFoundException('Account not found');
        }

        try {
            Follow::delete((int) $accountId, (int) $targetAccount['id']);
        } catch (\RuntimeException $e) {
            // Silently fail on DB error
        }

        Response::redirect(url('/@' . $username));
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