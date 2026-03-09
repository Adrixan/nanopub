<?php
/**
 * @var array $stats Instance statistics
 * @var iterable $reports Recent reports
 */
?>

<section class="admin-section">
    <header class="admin-header">
        <h1 class="page-title">Admin Dashboard</h1>
        <nav class="admin-nav" aria-label="Admin navigation">
            <a href="<?= url('/admin') ?>" class="nav-link active" aria-current="page">Dashboard</a>
            <a href="<?= url('/admin/reports') ?>" class="nav-link">Reports</a>
            <a href="<?= url('/admin/domain_blocks') ?>" class="nav-link">Domain Blocks</a>
            <a href="<?= url('/admin/settings') ?>" class="nav-link">Settings</a>
        </nav>
    </header>
    
    <section class="admin-stats" aria-label="Instance Statistics">
        <h2 class="visually-hidden">Statistics</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" fill="currentColor"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= e(number_format($stats['user_count'] ?? 0)) ?></span>
                    <span class="stat-label">Users</span>
                </div>
                <?php if (isset($stats['user_count_change'])): ?>
                    <span class="stat-change <?= $stats['user_count_change'] >= 0 ? 'positive' : 'negative' ?>">
                        <?= $stats['user_count_change'] >= 0 ? '+' : '' ?><?= e($stats['user_count_change']) ?>%
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z" fill="currentColor"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= e(number_format($stats['status_count'] ?? 0)) ?></span>
                    <span class="stat-label">Posts</span>
                </div>
                <?php if (isset($stats['status_count_change'])): ?>
                    <span class="stat-change <?= $stats['status_count_change'] >= 0 ? 'positive' : 'negative' ?>">
                        <?= $stats['status_count_change'] >= 0 ? '+' : '' ?><?= e($stats['status_count_change']) ?>%
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" fill="currentColor"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= e(number_format($stats['instance_count'] ?? 0)) ?></span>
                    <span class="stat-label">Connected Instances</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= e(number_format($stats['active_monthly'] ?? 0)) ?></span>
                    <span class="stat-label">Active (Monthly)</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" fill="currentColor"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= e(number_format($stats['active_weekly'] ?? 0)) ?></span>
                    <span class="stat-label">Active (Weekly)</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z" fill="currentColor"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= e(number_format($stats['pending_follow_requests'] ?? 0)) ?></span>
                    <span class="stat-label">Pending Requests</span>
                </div>
            </div>
        </div>
    </section>
    
    <section class="admin-reports" aria-label="Recent Reports">
        <header class="section-header">
            <h2 class="section-title">Recent Reports</h2>
            <a href="<?= url('/admin/reports') ?>" class="view-all-link">View all</a>
        </header>
        
        <?php if (empty($reports) || count($reports) === 0): ?>
            <div class="empty-state">
                <svg aria-hidden="true" width="48" height="48" viewBox="0 0 48 48">
                    <path d="M24 4C12.95 4 4 12.95 4 24s8.95 20 20 20 20-8.95 20-20S35.05 4 24 4zm-2 30h4v4h-4v-4zm0-24h4v20h-4V10z" fill="currentColor"/>
                </svg>
                <p>No pending reports</p>
            </div>
        <?php else: ?>
            <ul class="reports-list" role="list">
                <?php foreach ($reports as $report): ?>
                    <li class="report-item" data-report-id="<?= e($report['id'] ?? '') ?>">
                        <div class="report-info">
                            <div class="report-category">
                                <span class="category-badge"><?= e($report['category'] ?? 'other') ?></span>
                                <?php if ($report['forwarded'] ?? false): ?>
                                    <span class="forwarded-badge">Forwarded</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="report-content">
                                <p class="report-reason"><?= e($report['comment'] ?? 'No reason provided') ?></p>
                                <div class="report-meta">
                                    <span class="report-target">
                                        Reported: 
                                        <a href="<?= url('/@' . e($report['target_account']['username'] ?? 'unknown')) ?>">
                                            @<?= e($report['target_account']['username'] ?? 'unknown') ?>
                                        </a>
                                    </span>
                                    <span class="report-reporter">
                                        By: 
                                        <a href="<?= url('/@' . e($report['account']['username'] ?? 'unknown')) ?>">
                                            @<?= e($report['account']['username'] ?? 'unknown') ?>
                                        </a>
                                    </span>
                                    <time class="report-time" datetime="<?= e($report['created_at'] ?? '') ?>">
                                        <?= time_ago($report['created_at'] ?? '') ?>
                                    </time>
                                </div>
                            </div>
                        </div>
                        
                        <div class="report-actions">
                            <button type="button" 
                                    class="btn btn-secondary btn-small"
                                    data-action="view-report"
                                    data-report-id="<?= e($report['id'] ?? '') ?>">
                                View
                            </button>
                            <button type="button" 
                                    class="btn btn-primary btn-small"
                                    data-action="resolve-report"
                                    data-report-id="<?= e($report['id'] ?? '') ?>"
                                    data-csrf="<?= e($csrf ?? '') ?>">
                                Resolve
                            </button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    
    <section class="admin-quick-actions" aria-label="Quick Actions">
        <h2 class="section-title">Quick Actions</h2>
        
        <div class="quick-actions-grid">
            <a href="<?= url('/admin/reports') ?>" class="quick-action-card">
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor"/>
                </svg>
                <span>View Reports</span>
                <?php if (($stats['pending_reports'] ?? 0) > 0): ?>
                    <span class="badge"><?= e($stats['pending_reports']) ?></span>
                <?php endif; ?>
            </a>
            
            <a href="<?= url('/admin/domain_blocks') ?>" class="quick-action-card">
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L18.31 7.1C19.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z" fill="currentColor"/>
                </svg>
                <span>Domain Blocks</span>
            </a>
            
            <a href="<?= url('/admin/settings') ?>" class="quick-action-card">
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z" fill="currentColor"/>
                </svg>
                <span>Instance Settings</span>
            </a>
            
            <a href="<?= url('/admin/users') ?>" class="quick-action-card">
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" fill="currentColor"/>
                </svg>
                <span>User Management</span>
            </a>
        </div>
    </section>
</section>