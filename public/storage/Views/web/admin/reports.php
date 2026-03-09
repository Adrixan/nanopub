<?php
/**
 * @var iterable $reports Reports list
 */
?>

<section class="admin-section">
    <header class="admin-header">
        <h1 class="page-title">Reports Management</h1>
        <nav class="admin-nav" aria-label="Admin navigation">
            <a href="<?= url('/admin') ?>" class="nav-link">Dashboard</a>
            <a href="<?= url('/admin/reports') ?>" class="nav-link active" aria-current="page">Reports</a>
            <a href="<?= url('/admin/domain_blocks') ?>" class="nav-link">Domain Blocks</a>
            <a href="<?= url('/admin/settings') ?>" class="nav-link">Settings</a>
        </nav>
    </header>
    
    <div class="reports-filters" role="search" aria-label="Filter reports">
        <form method="get" class="filter-form">
            <div class="filter-group">
                <label for="status" class="filter-label">Status</label>
                <select id="status" name="status" class="filter-select">
                    <option value="open" <?= ($status ?? 'open') === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="resolved" <?= ($status ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    <option value="" <?= ($status ?? '') === '' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="category" class="filter-label">Category</label>
                <select id="category" name="category" class="filter-select">
                    <option value="">All Categories</option>
                    <option value="spam" <?= ($category ?? '') === 'spam' ? 'selected' : '' ?>>Spam</option>
                    <option value="harassment" <?= ($category ?? '') === 'harassment' ? 'selected' : '' ?>>Harassment</option>
                    <option value="hate" <?= ($category ?? '') === 'hate' ? 'selected' : '' ?>>Hate Speech</option>
                    <option value="illegal" <?= ($category ?? '') === 'illegal' ? 'selected' : '' ?>>Illegal Content</option>
                    <option value="other" <?= ($category ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-secondary btn-small">Filter</button>
        </form>
    </div>
    
    <section class="reports-section" aria-label="Reports list">
        <?php if (empty($reports) || count($reports) === 0): ?>
            <div class="empty-state">
                <svg aria-hidden="true" width="48" height="48" viewBox="0 0 48 48">
                    <path d="M24 4C12.95 4 4 12.95 4 24s8.95 20 20 20 20-8.95 20-20S35.05 4 24 4zm-2 30h4v4h-4v-4zm0-24h4v20h-4V10z" fill="currentColor"/>
                </svg>
                <p>No reports found</p>
            </div>
        <?php else: ?>
            <ul class="reports-list detailed" role="list">
                <?php foreach ($reports as $report): ?>
                    <li class="report-item" data-report-id="<?= e($report['id'] ?? '') ?>">
                        <div class="report-header">
                            <div class="report-category">
                                <span class="category-badge <?= e($report['category'] ?? 'other') ?>">
                                    <?= e(ucfirst($report['category'] ?? 'other')) ?>
                                </span>
                                <?php if ($report['resolved'] ?? false): ?>
                                    <span class="resolved-badge">Resolved</span>
                                <?php endif; ?>
                                <?php if ($report['forwarded'] ?? false): ?>
                                    <span class="forwarded-badge">Forwarded</span>
                                <?php endif; ?>
                            </div>
                            
                            <time class="report-time" datetime="<?= e($report['created_at'] ?? '') ?>">
                                <?= date('M j, Y \a\t g:i A', strtotime($report['created_at'] ?? 'now')) ?>
                            </time>
                        </div>
                        
                        <div class="report-parties">
                            <div class="report-party target">
                                <h3 class="party-label">Reported Account</h3>
                                <div class="party-info">
                                    <img 
                                        src="<?= e($report['target_account']['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
                                        alt="" 
                                        width="40" 
                                        height="40"
                                        class="party-avatar"
                                        loading="lazy"
                                    >
                                    <div class="party-details">
                                        <a href="<?= url('/@' . e($report['target_account']['username'] ?? 'unknown')) ?>" class="party-name">
                                            <?= e($report['target_account']['display_name'] ?? $report['target_account']['username'] ?? 'Unknown') ?>
                                        </a>
                                        <span class="party-username">@<?= e($report['target_account']['username'] ?? 'unknown') ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="report-party reporter">
                                <h3 class="party-label">Reported By</h3>
                                <div class="party-info">
                                    <img 
                                        src="<?= e($report['account']['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
                                        alt="" 
                                        width="40" 
                                        height="40"
                                        class="party-avatar"
                                        loading="lazy"
                                    >
                                    <div class="party-details">
                                        <a href="<?= url('/@' . e($report['account']['username'] ?? 'unknown')) ?>" class="party-name">
                                            <?= e($report['account']['display_name'] ?? $report['account']['username'] ?? 'Unknown') ?>
                                        </a>
                                        <span class="party-username">@<?= e($report['account']['username'] ?? 'unknown') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($report['comment'])): ?>
                            <div class="report-reason">
                                <h3 class="reason-label">Reason</h3>
                                <p class="reason-text"><?= e($report['comment']) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($report['status'])): ?>
                            <div class="report-content-preview">
                                <h3 class="preview-label">Reported Content</h3>
                                <div class="preview-content">
                                    <?= $report['status']['content'] ?? '' ?>
                                    <time class="preview-time" datetime="<?= e($report['status']['created_at'] ?? '') ?>">
                                        Posted <?= time_ago($report['status']['created_at'] ?? '') ?>
                                    </time>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($report['action_taken'] ?? false): ?>
                            <div class="report-action-taken">
                                <h3 class="action-label">Action Taken</h3>
                                <p><?= e($report['action_taken_reason'] ?? 'No details provided') ?></p>
                                <span class="action-by">By <?= e($report['action_taken_by'] ?? 'Admin') ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="report-actions">
                            <?php if (!($report['resolved'] ?? false)): ?>
                                <button type="button" 
                                        class="btn btn-secondary btn-small"
                                        data-action="view-report"
                                        data-report-id="<?= e($report['id'] ?? '') ?>">
                                    View Details
                                </button>
                                
                                <div class="action-dropdown">
                                    <button type="button" 
                                            class="btn btn-secondary btn-small dropdown-toggle"
                                            aria-haspopup="true"
                                            aria-expanded="false">
                                        Take Action
                                    </button>
                                    <ul class="dropdown-menu" role="menu">
                                        <li role="none">
                                            <button type="button" 
                                                    role="menuitem"
                                                    data-action="warn-user"
                                                    data-account-id="<?= e($report['target_account']['id'] ?? '') ?>"
                                                    data-report-id="<?= e($report['id'] ?? '') ?>"
                                                    data-csrf="<?= e($csrf ?? '') ?>">
                                                Warn User
                                            </button>
                                        </li>
                                        <li role="none">
                                            <button type="button" 
                                                    role="menuitem"
                                                    data-action="disable-account"
                                                    data-account-id="<?= e($report['target_account']['id'] ?? '') ?>"
                                                    data-report-id="<?= e($report['id'] ?? '') ?>"
                                                    data-csrf="<?= e($csrf ?? '') ?>">
                                                Disable Account
                                            </button>
                                        </li>
                                        <li role="none">
                                            <button type="button" 
                                                    role="menuitem"
                                                    data-action="suspend-account"
                                                    data-account-id="<?= e($report['target_account']['id'] ?? '') ?>"
                                                    data-report-id="<?= e($report['id'] ?? '') ?>"
                                                    data-csrf="<?= e($csrf ?? '') ?>">
                                                Suspend Account
                                            </button>
                                        </li>
                                        <li role="none">
                                            <button type="button" 
                                                    role="menuitem"
                                                    data-action="delete-status"
                                                    data-status-id="<?= e($report['status']['id'] ?? '') ?>"
                                                    data-report-id="<?= e($report['id'] ?? '') ?>"
                                                    data-csrf="<?= e($csrf ?? '') ?>"
                                                    <?= empty($report['status']) ? 'disabled' : '' ?>>
                                                Delete Status
                                            </button>
                                        </li>
                                        <li role="separator"></li>
                                        <li role="none">
                                            <button type="button" 
                                                    role="menuitem"
                                                    data-action="resolve-report"
                                                    data-report-id="<?= e($report['id'] ?? '') ?>"
                                                    data-csrf="<?= e($csrf ?? '') ?>">
                                                Mark Resolved
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <span class="resolved-info">
                                    Resolved by <?= e($report['resolved_by'] ?? 'Admin') ?>
                                    <?= time_ago($report['resolved_at'] ?? '') ?>
                                </span>
                                <button type="button" 
                                        class="btn btn-secondary btn-small"
                                        data-action="reopen-report"
                                        data-report-id="<?= e($report['id'] ?? '') ?>"
                                        data-csrf="<?= e($csrf ?? '') ?>">
                                    Reopen
                                </button>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <?php if (!empty($pagination)): ?>
                <nav class="pagination" aria-label="Reports pagination">
                    <?php if (!empty($pagination['prev'])): ?>
                        <a href="<?= e($pagination['prev']) ?>" class="pagination-link prev">Previous</a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Page <?= e($pagination['current'] ?? 1) ?> of <?= e($pagination['total_pages'] ?? 1) ?>
                    </span>
                    
                    <?php if (!empty($pagination['next'])): ?>
                        <a href="<?= e($pagination['next']) ?>" class="pagination-link next">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</section>