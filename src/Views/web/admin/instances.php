<?php
/**
 * @var array $instances List of remote instances
 */
?>

<section class="admin-section">
    <header class="admin-header">
        <h1 class="page-title">Remote Instances</h1>
        <nav class="admin-nav" aria-label="Admin navigation">
            <a href="<?= url('/admin') ?>" class="nav-link">Dashboard</a>
            <a href="<?= url('/admin/reports') ?>" class="nav-link">Reports</a>
            <a href="<?= url('/admin/users') ?>" class="nav-link">Users</a>
            <a href="<?= url('/admin/instances') ?>" class="nav-link active" aria-current="page">Instances</a>
            <a href="<?= url('/admin/domain_blocks') ?>" class="nav-link">Domain Blocks</a>
            <a href="<?= url('/admin/settings') ?>" class="nav-link">Settings</a>
        </nav>
    </header>
    
    <?php if (empty($instances)): ?>
        <div class="empty-state">
            <p>No remote instances found.</p>
        </div>
    <?php else: ?>
        <table class="admin-table" role="table">
            <thead>
                <tr>
                    <th scope="col">Domain</th>
                    <th scope="col">Known Accounts</th>
                    <th scope="col">Last Seen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($instances as $instance): ?>
                    <tr>
                        <td><?= e($instance['domain'] ?? '') ?></td>
                        <td><?= e(number_format((int) ($instance['account_count'] ?? 0))) ?></td>
                        <td>
                            <time datetime="<?= e($instance['last_seen_at'] ?? '') ?>">
                                <?= e($instance['last_seen_at'] ?? '') ?>
                            </time>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
