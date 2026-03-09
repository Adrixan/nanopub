<?php
/**
 * @var array $users List of user accounts
 */
?>

<section class="admin-section">
    <header class="admin-header">
        <h1 class="page-title">User Management</h1>
        <nav class="admin-nav" aria-label="Admin navigation">
            <a href="<?= url('/admin') ?>" class="nav-link">Dashboard</a>
            <a href="<?= url('/admin/reports') ?>" class="nav-link">Reports</a>
            <a href="<?= url('/admin/users') ?>" class="nav-link active" aria-current="page">Users</a>
            <a href="<?= url('/admin/instances') ?>" class="nav-link">Instances</a>
            <a href="<?= url('/admin/domain_blocks') ?>" class="nav-link">Domain Blocks</a>
            <a href="<?= url('/admin/settings') ?>" class="nav-link">Settings</a>
        </nav>
    </header>
    
    <?php if (empty($users)): ?>
        <div class="empty-state">
            <p>No users found.</p>
        </div>
    <?php else: ?>
        <table class="admin-table" role="table">
            <thead>
                <tr>
                    <th scope="col">Username</th>
                    <th scope="col">Display Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Status</th>
                    <th scope="col">Created</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr data-user-id="<?= e($user['id'] ?? '') ?>">
                        <td>
                            <a href="<?= url('/@' . e($user['username'] ?? '')) ?>">
                                @<?= e($user['username'] ?? '') ?>
                            </a>
                        </td>
                        <td><?= e($user['display_name'] ?? '') ?></td>
                        <td><?= e($user['email'] ?? '') ?></td>
                        <td>
                            <?php if ($user['is_suspended'] ?? false): ?>
                                <span class="badge badge-danger">Suspended</span>
                            <?php elseif ($user['is_admin'] ?? false): ?>
                                <span class="badge badge-primary">Admin</span>
                            <?php elseif ($user['is_moderator'] ?? false): ?>
                                <span class="badge badge-secondary">Moderator</span>
                            <?php else: ?>
                                <span class="badge badge-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <time datetime="<?= e($user['created_at'] ?? '') ?>">
                                <?= e($user['created_at'] ?? '') ?>
                            </time>
                        </td>
                        <td class="actions-cell">
                            <?php if ($user['is_suspended'] ?? false): ?>
                                <form action="<?= url('/admin/users/' . e($user['id']) . '/action') ?>" 
                                      method="post" 
                                      class="inline-form">
                                    <input type="hidden" name="action" value="unsuspend">
                                    <button type="submit" class="btn btn-secondary btn-small">Unsuspend</button>
                                </form>
                            <?php else: ?>
                                <form action="<?= url('/admin/users/' . e($user['id']) . '/action') ?>" 
                                      method="post" 
                                      class="inline-form">
                                    <input type="hidden" name="action" value="suspend">
                                    <button type="submit" class="btn btn-danger btn-small">Suspend</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
