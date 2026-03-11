<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Web;

use NanoPub\Core\Request;
use NanoPub\Core\View;
use NanoPub\Core\Session;
use NanoPub\Models\Account;
use NanoPub\Models\Follow;

/**
 * Handles the Explore page for user discovery.
 *
 * GET /explore
 */
final class ExploreController
{
    /**
     * Show the explore/user discovery page.
     *
     * Supports searching users via the `q` query parameter.
     */
    public function index(Request $request): string
    {
        $search = trim($request->query['q'] ?? '');
        $currentAccountId = Session::get('account_id');
        $currentAccount = $currentAccountId !== null
            ? Account::find((int) $currentAccountId)
            : null;

        if (!empty($search)) {
            $accounts = iterator_to_array(Account::search($search, 20));
        } else {
            $accounts = iterator_to_array(Account::findAll(20));
        }

        $accountsWithRelationships = [];
        foreach ($accounts as $account) {
            $account['is_following'] = false;
            if (
                $currentAccount !== null
                && (int) $account['id'] !== (int) $currentAccount['id']
            ) {
                $account['is_following'] = Follow::isFollowing(
                    (int) $currentAccountId,
                    (int) $account['id']
                );
            }
            $accountsWithRelationships[] = $account;
        }

        return View::renderWithLayout('web/explore/index', [
            'accounts' => $accountsWithRelationships,
            'currentAccount' => $currentAccount,
            'search' => $search,
            'activeNav' => 'explore',
        ]);
    }
}
