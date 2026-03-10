<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Web;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\View;
use NanoPub\Core\Session;
use NanoPub\Models\Account;
use NanoPub\Models\Bookmark;

/**
 * Handles bookmark web UI endpoints.
 */
final class BookmarkController
{
    /**
     * Show bookmarked statuses.
     * 
     * GET /bookmarks
     */
    public function index(Request $request): string
    {
        $accountId = Session::get('account_id');

        if ($accountId === null) {
            Response::redirect(url('/login'));
            return '';
        }

        $account = Account::find((int) $accountId);

        if ($account === null) {
            Session::destroy();
            Response::redirect(url('/login'));
            return '';
        }

        $bookmarks = iterator_to_array(
            Bookmark::getForAccount((int) $accountId, 40)
        );

        return View::render('web/bookmarks/index', [
            'account' => $account,
            'bookmarks' => $bookmarks,
            'activeNav' => 'bookmarks',
        ]);
    }
}
