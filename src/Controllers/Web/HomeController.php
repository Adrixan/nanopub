<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Web;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\View;
use NanoPub\Core\Session;
use NanoPub\Models\Status;
use NanoPub\Models\Account;
use NanoPub\Models\Instance;
use NanoPub\Services\AuthService;
use RuntimeException;

/**
 * Handles home page web UI endpoints.
 */
final class HomeController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Show home page / landing page.
     * 
     * GET /
     */
    public function index(Request $request): string
    {
        if ($this->authService->isLoggedIn()) {
            return $this->timeline($request);
        } else {
            return $this->showLandingPage();
        }
    }

    /**
     * Show landing page with fallback for database failures.
     */
    private function showLandingPage(): string
    {
        try {
            $instance = Instance::get();
        } catch (RuntimeException $e) {
            $instance = null;
        }

        $fallbackInstance = $instance ?? [
            'title' => 'NanoPub',
            'description' => 'A federated social network powered by NanoPub',
            'short_description' => 'A federated social network',
            'registrations_open' => true,
            'approval_required' => false,
            'user_count' => 0,
            'status_count' => 0,
            'peer_count' => 0,
        ];

        return View::render('web/home/landing', [
            'instance' => $fallbackInstance,
        ]);
    }

    /**
     * Show home timeline for authenticated users.
     * 
     * @param Request $request Request object
     */
    private function timeline(Request $request): string
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return '';
        }

        try {
            $account = Account::find((int) $accountId);
        } catch (RuntimeException $e) {
            return $this->showTimelineError('Unable to load your account. Please try again later.');
        }

        if ($account === null) {
            Session::destroy();
            Response::redirect(url('/login'));
            return '';
        }

        try {
            $statuses = iterator_to_array(Status::getHomeTimeline((int) $accountId, 20));
        } catch (RuntimeException $e) {
            return $this->showTimelineError('Unable to load your timeline. The database may be temporarily unavailable.');
        }

        return View::render('web/home/timeline', [
            'account' => $account,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Show timeline error page for logged-in users.
     * 
     * @param string $message Error message to display
     */
    private function showTimelineError(string $message): string
    {
        return View::render('error/500', [
            'message' => $message,
        ]);
    }

    /**
     * Show public timeline (accessible to all users).
     * 
     * GET /public
     */
    public function publicTimeline(Request $request): string
    {
        try {
            $statuses = iterator_to_array(Status::getPublicTimeline(20));
        } catch (RuntimeException $e) {
            return View::render('error/500', [
                'message' => 'Unable to load the public timeline. The database may be temporarily unavailable.',
            ]);
        }

        return View::render('web/home/public', [
            'statuses' => $statuses,
        ]);
    }

    /**
     * Show about page.
     * 
     * GET /about
     */
    public function about(Request $request): string
    {
        try {
            $instance = Instance::get();
        } catch (RuntimeException $e) {
            $instance = null;
        }

        $fallbackInstance = $instance ?? [
            'title' => 'NanoPub',
            'description' => 'A federated social network powered by NanoPub',
        ];

        return View::render('web/home/about', [
            'instance' => $fallbackInstance,
        ]);
    }
}
