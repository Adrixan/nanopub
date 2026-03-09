<?php

declare(strict_types=1);

namespace NanoPub\Controllers\Web;

use NanoPub\Core\Database;
use NanoPub\Core\Request;
use NanoPub\Core\Response;
use NanoPub\Core\View;
use NanoPub\Core\Session;
use NanoPub\Models\Account;
use NanoPub\Models\Instance;
use NanoPub\Models\Report;
use NanoPub\Models\DomainBlock;
use NanoPub\Exceptions\NotFoundException;

/**
 * Handles admin web UI endpoints.
 */
final class AdminController
{
    /**
     * Show admin dashboard.
     * 
     * GET /admin
     */
    public function index(Request $request): void
    {
        $this->requireAdmin();

        $stats = Instance::getStats();
        $reports = iterator_to_array(Report::getAll(10));

        echo View::render('web/admin/index', [
            'stats' => $stats,
            'reports' => $reports,
        ]);
    }

    /**
     * Show reports.
     * 
     * GET /admin/reports
     */
    public function reports(Request $request): void
    {
        $this->requireAdmin();

        $limit = min((int) ($request->query['limit'] ?? 50), 100);
        $reports = iterator_to_array(Report::getAll($limit));

        echo View::render('web/admin/reports', [
            'reports' => $reports,
        ]);
    }

    /**
     * Show domain blocks.
     * 
     * GET /admin/domain_blocks
     */
    public function domainBlocks(Request $request): void
    {
        $this->requireAdmin();

        $limit = min((int) ($request->query['limit'] ?? 100), 200);
        $blocks = iterator_to_array(DomainBlock::getAll($limit));

        echo View::render('web/admin/domain_blocks', [
            'blocks' => $blocks,
        ]);
    }

    /**
     * Show instance settings.
     * 
     * GET /admin/settings
     */
    public function settings(Request $request): void
    {
        $this->requireAdmin();

        $instance = Instance::get();

        echo View::render('web/admin/settings', [
            'instance' => $instance,
        ]);
    }

    /**
     * Update instance settings.
     * 
     * POST /admin/settings
     */
    public function updateSettings(Request $request): void
    {
        $this->requireAdmin();

        $data = $request->json();
        $input = !empty($data) ? $data : $_POST;

        $updateData = [];

        $allowedFields = [
            'title', 'description', 'short_description',
            'contact_email', 'registrations_open', 'approval_required',
            'max_toot_chars', 'max_media_attachments', 'max_image_size', 'max_video_size',
        ];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateData[$field] = $input[$field];
            }
        }

        if (!empty($updateData)) {
            Instance::update($updateData);
        }

        Response::redirect(url('/admin/settings'));
    }

    /**
     * Resolve a report.
     * 
     * POST /admin/reports/{id}/resolve
     */
    public function resolveReport(Request $request, int $id): void
    {
        $this->requireAdmin();

        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            Response::redirect(url('/login'));
            return;
        }

        Report::resolve($id, (int) $accountId);

        Response::redirect(url('/admin/reports'));
    }

    /**
     * Create domain block.
     * 
     * POST /admin/domain_blocks
     */
    public function createDomainBlock(Request $request): void
    {
        $this->requireAdmin();

        $data = $request->json();
        $input = !empty($data) ? $data : $_POST;

        $domain = trim($input['domain'] ?? '');
        $severity = $input['severity'] ?? 'suspend';
        $reason = trim($input['reason'] ?? '');

        if (empty($domain)) {
            Response::redirect(url('/admin/domain_blocks?error=empty'));
            return;
        }

        DomainBlock::create($domain, $severity, $reason);

        Response::redirect(url('/admin/domain_blocks'));
    }

    /**
     * Delete domain block.
     * 
     * DELETE /admin/domain_blocks/{id}
     */
    public function deleteDomainBlock(Request $request, int $id): void
    {
        $this->requireAdmin();

        DomainBlock::delete($id);

        Response::redirect(url('/admin/domain_blocks'));
    }

    /**
     * List users.
     * 
     * GET /admin/users
     */
    public function users(Request $request): void
    {
        $this->requireAdmin();

        $limit = min((int) ($request->query['limit'] ?? 50), 100);
        $offset = (int) ($request->query['offset'] ?? 0);
        $users = iterator_to_array(Account::findAll($limit, $offset));

        echo View::render('web/admin/users', [
            'users' => $users,
        ]);
    }

    /**
     * List remote instances.
     * 
     * GET /admin/instances
     */
    public function instances(Request $request): void
    {
        $this->requireAdmin();

        $limit = min((int) ($request->query['limit'] ?? 50), 100);
        $offset = (int) ($request->query['offset'] ?? 0);

        try {
            $instances = Database::fetchAll(
                'SELECT domain, COUNT(*) as account_count, MAX(created_at) as last_seen_at
                 FROM accounts
                 WHERE is_local = 0 AND domain IS NOT NULL
                 GROUP BY domain
                 ORDER BY account_count DESC
                 LIMIT ? OFFSET ?',
                [$limit, $offset]
            );
        } catch (\RuntimeException $e) {
            $instances = [];
        }

        echo View::render('web/admin/instances', [
            'instances' => $instances,
        ]);
    }

    /**
     * Perform action on a user (suspend/unsuspend).
     * 
     * POST /admin/users/{id}/action
     */
    public function userAction(Request $request, int $id): void
    {
        $this->requireAdmin();

        $data = $request->json();
        $input = !empty($data) ? $data : $_POST;
        $action = $input['action'] ?? '';

        $account = Account::find($id);

        if ($account === null) {
            Response::redirect(url('/admin/users?error=not_found'));
            return;
        }

        try {
            if ($action === 'suspend') {
                Account::update($id, ['is_suspended' => true]);
            } elseif ($action === 'unsuspend') {
                Account::update($id, ['is_suspended' => false]);
            }
        } catch (\RuntimeException $e) {
            Response::redirect(url('/admin/users?error=action_failed'));
            return;
        }

        Response::redirect(url('/admin/users'));
    }

    /**
     * Perform action on a remote instance (block/unblock).
     * 
     * POST /admin/instances/{id}/action
     */
    public function instanceAction(Request $request, int $id): void
    {
        $this->requireAdmin();

        $data = $request->json();
        $input = !empty($data) ? $data : $_POST;
        $action = $input['action'] ?? '';

        try {
            $block = DomainBlock::find($id);

            if ($action === 'block' && $block === null) {
                $domain = trim($input['domain'] ?? '');
                $reason = trim($input['reason'] ?? '');
                if (!empty($domain)) {
                    DomainBlock::create($domain, 'suspend', $reason);
                }
            } elseif ($action === 'unblock' && $block !== null) {
                DomainBlock::delete($id);
            }
        } catch (\RuntimeException $e) {
            Response::redirect(url('/admin/instances?error=action_failed'));
            return;
        }

        Response::redirect(url('/admin/instances'));
    }

    /**
     * Require admin privileges.
     * 
     * @throws NotFoundException If not admin
     */
    private function requireAdmin(): void
    {
        $accountId = Session::get('account_id');
        
        if ($accountId === null) {
            throw new NotFoundException('Page not found');
        }

        $account = Account::find((int) $accountId);

        if ($account === null || (!$account['is_admin'] && !$account['is_moderator'])) {
            throw new NotFoundException('Page not found');
        }
    }
}