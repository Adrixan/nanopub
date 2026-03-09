<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Web;

use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\View;
use NanoPub\Core\Session;
use NanoPub\Models\Account;
use NanoPub\Services\AuthService;
use NanoPub\Services\MediaService;
use NanoPub\Exceptions\ValidationException;

/**
 * Handles settings web UI endpoints.
 */
final class SettingsController
{
    private AuthService $authService;

    private MediaService $mediaService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->mediaService = new MediaService();
    }

    /**
     * Show settings page.
     * 
     * GET /settings
     */
    public function index(Request $request): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $account = Account::find((int) $accountId);
        
        if ($account === null) {
            Response::redirect(url('/login'));
            return;
        }

        echo View::render('web/settings/index', [
            'account' => $account,
        ]);
    }

    /**
     * Update profile.
     * 
     * POST /settings/profile
     */
    public function updateProfile(Request $request): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $data = $request->json();
        $input = !empty($data) ? $data : $_POST;

        $updateData = [];

        // Handle display_name
        if (isset($input['display_name'])) {
            $updateData['display_name'] = trim($input['display_name']);
        }

        // Handle bio/note
        if (isset($input['note'])) {
            $updateData['bio'] = trim($input['note']);
        }

        // Handle locked status
        if (isset($input['locked'])) {
            $updateData['is_locked'] = (bool) $input['locked'];
        }

        // Handle avatar upload
        if (isset($request->files['avatar']) && $request->files['avatar']['error'] === UPLOAD_ERR_OK) {
            try {
                $media = $this->mediaService->upload(
                    $request->files['avatar'],
                    (int) $accountId
                );
                $updateData['avatar_url'] = $media['url'];
            } catch (ValidationException $e) {
                $account = Account::find((int) $accountId);
                echo View::render('web/settings/index', [
                    'account' => $account,
                    'error' => $e->getMessage(),
                ]);
                return;
            }
        }

        // Handle header upload
        if (isset($request->files['header']) && $request->files['header']['error'] === UPLOAD_ERR_OK) {
            try {
                $media = $this->mediaService->upload(
                    $request->files['header'],
                    (int) $accountId
                );
                $updateData['header_url'] = $media['url'];
            } catch (ValidationException $e) {
                $account = Account::find((int) $accountId);
                echo View::render('web/settings/index', [
                    'account' => $account,
                    'error' => $e->getMessage(),
                ]);
                return;
            }
        }

        // Update account
        if (!empty($updateData)) {
            Account::update((int) $accountId, $updateData);
        }

        Response::redirect(url('/settings'));
    }

    /**
     * Update password.
     * 
     * POST /settings/password
     */
    public function updatePassword(Request $request): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $data = $request->json();
        $input = !empty($data) ? $data : $_POST;

        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        $account = Account::find((int) $accountId);
        
        if ($account === null) {
            Response::redirect(url('/login'));
            return;
        }

        // Verify current password
        if (!verify_password($currentPassword, $account['password_hash'])) {
            echo View::render('web/settings/index', [
                'account' => $account,
                'error' => 'Current password is incorrect',
            ]);
            return;
        }

        // Validate new password
        if (strlen($newPassword) < 8) {
            echo View::render('web/settings/index', [
                'account' => $account,
                'error' => 'Password must be at least 8 characters',
            ]);
            return;
        }

        // Confirm passwords match
        if ($newPassword !== $confirmPassword) {
            echo View::render('web/settings/index', [
                'account' => $account,
                'error' => 'Passwords do not match',
            ]);
            return;
        }

        // Update password
        Account::update((int) $accountId, [
            'password_hash' => hash_password($newPassword),
        ]);

        Response::redirect(url('/settings'));
    }

    /**
     * Show import/export page.
     * 
     * GET /settings/export
     */
    public function export(Request $request): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $account = Account::find((int) $accountId);
        
        if ($account === null) {
            Response::redirect(url('/login'));
            return;
        }

        echo View::render('web/settings/export', [
            'account' => $account,
        ]);
    }

    /**
     * Redirect to main settings page (profile is shown at index).
     * 
     * GET /settings/profile
     */
    public function profile(Request $request): void
    {
        Response::redirect(url('/settings'));
    }

    /**
     * Show account settings page.
     * 
     * GET /settings/account
     */
    public function account(Request $request): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $account = Account::find((int) $accountId);
        
        if ($account === null) {
            Response::redirect(url('/login'));
            return;
        }

        echo View::render('web/settings/account', [
            'account' => $account,
        ]);
    }

    /**
     * Show privacy settings page.
     * 
     * GET /settings/privacy
     */
    public function privacy(Request $request): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $account = Account::find((int) $accountId);
        
        if ($account === null) {
            Response::redirect(url('/login'));
            return;
        }

        echo View::render('web/settings/privacy', [
            'account' => $account,
        ]);
    }

    /**
     * Show notification settings page.
     * 
     * GET /settings/notifications
     */
    public function notifications(Request $request): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $account = Account::find((int) $accountId);
        
        if ($account === null) {
            Response::redirect(url('/login'));
            return;
        }

        echo View::render('web/settings/notifications', [
            'account' => $account,
        ]);
    }

    /**
     * Update account settings.
     * 
     * POST /settings/account
     */
    public function updateAccount(Request $request): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $data = $request->json();
        $input = !empty($data) ? $data : $_POST;

        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        $account = Account::find((int) $accountId);
        
        if ($account === null) {
            Response::redirect(url('/login'));
            return;
        }

        if (!empty($newPassword)) {
            if (!verify_password($currentPassword, $account['password_hash'])) {
                echo View::render('web/settings/account', [
                    'account' => $account,
                    'error' => 'Current password is incorrect',
                ]);
                return;
            }

            if (strlen($newPassword) < 8) {
                echo View::render('web/settings/account', [
                    'account' => $account,
                    'error' => 'Password must be at least 8 characters',
                ]);
                return;
            }

            if ($newPassword !== $confirmPassword) {
                echo View::render('web/settings/account', [
                    'account' => $account,
                    'error' => 'Passwords do not match',
                ]);
                return;
            }

            Account::update((int) $accountId, [
                'password_hash' => hash_password($newPassword),
            ]);
        }

        // Handle email update
        $newEmail = trim($input['email'] ?? '');
        if (!empty($newEmail) && $newEmail !== ($account['email'] ?? '')) {
            Account::update((int) $accountId, [
                'email' => $newEmail,
            ]);
        }

        Response::redirect(url('/settings/account'));
    }

    /**
     * Update privacy settings.
     * 
     * POST /settings/privacy
     */
    public function updatePrivacy(Request $request): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $data = $request->json();
        $input = !empty($data) ? $data : $_POST;

        $updateData = [];

        if (isset($input['locked'])) {
            $updateData['is_locked'] = (bool) $input['locked'];
        }

        if (isset($input['discoverable'])) {
            $updateData['discoverable'] = (bool) $input['discoverable'];
        }

        if (!empty($updateData)) {
            Account::update((int) $accountId, $updateData);
        }

        Response::redirect(url('/settings/privacy'));
    }

    /**
     * Update notification preferences.
     * 
     * POST /settings/notifications
     */
    public function updateNotifications(Request $request): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        $data = $request->json();
        $input = !empty($data) ? $data : $_POST;

        $updateData = [];

        if (isset($input['notify_mentions'])) {
            $updateData['notify_mentions'] = (bool) $input['notify_mentions'];
        }

        if (isset($input['notify_follows'])) {
            $updateData['notify_follows'] = (bool) $input['notify_follows'];
        }

        if (isset($input['notify_favourites'])) {
            $updateData['notify_favourites'] = (bool) $input['notify_favourites'];
        }

        if (isset($input['notify_reblogs'])) {
            $updateData['notify_reblogs'] = (bool) $input['notify_reblogs'];
        }

        if (!empty($updateData)) {
            Account::update((int) $accountId, $updateData);
        }

        Response::redirect(url('/settings/notifications'));
    }
}