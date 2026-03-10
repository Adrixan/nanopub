<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Web;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\View;
use NanoPub\Core\Session;
use NanoPub\Models\Account;
use NanoPub\Models\Notification;

/**
 * Handles notification web UI endpoints.
 */
final class NotificationController
{
    /**
     * Show notifications list.
     * 
     * GET /notifications
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

        $notifications = iterator_to_array(
            Notification::getForAccount((int) $accountId, 40)
        );

        Notification::markAllAsRead((int) $accountId);

        return View::render('web/notifications/index', [
            'account' => $account,
            'notifications' => $notifications,
            'activeNav' => 'notifications',
        ]);
    }
}
