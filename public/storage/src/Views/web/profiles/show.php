<?php
/**
 * @var array $account Profile account
 * @var iterable $statuses Account statuses
 * @var array|null $currentAccount Current logged in account
 * @var array|null $relationship Relationship data
 */
$displayName = $account['display_name'] ?? $account['username'] ?? 'Unknown';
$username = $account['username'] ?? 'unknown';
$domain = $account['domain'] ?? parse_url($account['url'] ?? '', PHP_URL_HOST) ?? '';
$fullUsername = $domain ? $username . '@' . $domain : $username;
$isOwnProfile = isset($currentAccount['id']) && $currentAccount['id'] === ($account['id'] ?? null);
?>

<section class="profile-section">
    <header class="profile-header">
        <div class="profile-banner"<?= !empty($account['header_url']) ? ' style="background-image: url(' . e($account['header_url']) . ')"' : '' ?>>
            <img 
                src="<?= e($account['header_url'] ?? url('/assets/images/default-header.png')) ?>" 
                alt="" 
                class="profile-banner-image"
                loading="lazy"
            >
        </div>
        
        <div class="profile-info">
            <div class="profile-avatar-wrapper">
                <img 
                    src="<?= e($account['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
                    alt="" 
                    class="profile-avatar"
                    width="120"
                    height="120"
                    loading="lazy"
                >
            </div>
            
            <div class="profile-details">
                <div class="profile-name">
                    <h1 class="profile-display-name"><?= e($displayName) ?></h1>
                    <span class="profile-username">@<?= e($fullUsername) ?></span>
                </div>
                
                <?php if (!empty($account['locked'])): ?>
                    <span class="profile-badge" title="Locked account">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16">
                            <rect x="3" y="7" width="10" height="8" rx="1" fill="none" stroke="currentColor" stroke-width="2"/>
                            <path d="M5 7V5a3 3 0 0 1 6 0v2" fill="none" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <span class="visually-hidden">Locked account</span>
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($account['bot'])): ?>
                    <span class="profile-badge" title="Bot account">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16">
                            <rect x="2" y="6" width="12" height="8" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                            <circle cx="5" cy="10" r="1" fill="currentColor"/>
                            <circle cx="11" cy="10" r="1" fill="currentColor"/>
                            <line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <span class="visually-hidden">Bot account</span>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="profile-actions">
                <?php if ($isOwnProfile): ?>
                    <a href="<?= url('/settings') ?>" class="btn btn-secondary">
                        Edit Profile
                    </a>
                <?php elseif (isset($currentAccount) && $currentAccount): ?>
                    <?php if ($relationship['blocked_by'] ?? false): ?>
                        <span class="blocked-label">Blocks you</span>
                    <?php elseif ($relationship['blocking'] ?? false): ?>
                        <button type="button" 
                                class="btn btn-danger"
                                data-action="unblock"
                                data-account-id="<?= e($account['id'] ?? '') ?>"
                                data-csrf="<?= e($csrf ?? '') ?>">
                            Unblock
                        </button>
                    <?php elseif ($relationship['requested'] ?? false): ?>
                        <button type="button" 
                                class="btn btn-secondary"
                                data-action="cancel-follow-request"
                                data-account-id="<?= e($account['id'] ?? '') ?>"
                                data-csrf="<?= e($csrf ?? '') ?>">
                            Cancel Request
                        </button>
                    <?php elseif ($relationship['following'] ?? false): ?>
                        <button type="button" 
                                class="btn btn-secondary following-btn"
                                data-action="unfollow"
                                data-account-id="<?= e($account['id'] ?? '') ?>"
                                data-csrf="<?= e($csrf ?? '') ?>">
                            <span class="following-text">Following</span>
                            <span class="unfollow-text">Unfollow</span>
                        </button>
                    <?php else: ?>
                        <button type="button" 
                                class="btn btn-primary"
                                data-action="follow"
                                data-account-id="<?= e($account['id'] ?? '') ?>"
                                data-csrf="<?= e($csrf ?? '') ?>">
                            <?= ($relationship['followed_by'] ?? false) ? 'Follow back' : 'Follow' ?>
                        </button>
                    <?php endif; ?>
                    
                    <div class="profile-menu">
                        <button type="button" class="profile-menu-toggle" aria-label="Profile options" aria-haspopup="true">
                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                <circle cx="10" cy="4" r="1.5" fill="currentColor"/>
                                <circle cx="10" cy="10" r="1.5" fill="currentColor"/>
                                <circle cx="10" cy="16" r="1.5" fill="currentColor"/>
                            </svg>
                        </button>
                        <ul class="profile-dropdown" role="menu" aria-hidden="true">
                            <li role="none">
                                <a href="<?= url('/messages/new?to=' . e($username)) ?>" role="menuitem">Send Message</a>
                            </li>
                            <li role="none">
                                <button type="button" 
                                        role="menuitem"
                                        data-action="<?= ($relationship['muting'] ?? false) ? 'unmute' : 'mute' ?>"
                                        data-account-id="<?= e($account['id'] ?? '') ?>"
                                        data-csrf="<?= e($csrf ?? '') ?>">
                                    <?= ($relationship['muting'] ?? false) ? 'Unmute' : 'Mute' ?>
                                </button>
                            </li>
                            <li role="none">
                                <button type="button" 
                                        role="menuitem"
                                        data-action="<?= ($relationship['blocking'] ?? false) ? 'unblock' : 'block' ?>"
                                        data-account-id="<?= e($account['id'] ?? '') ?>"
                                        data-csrf="<?= e($csrf ?? '') ?>">
                                    <?= ($relationship['blocking'] ?? false) ? 'Unblock' : 'Block' ?>
                                </button>
                            </li>
                            <li role="none">
                                <a href="<?= url('/report?account=' . e($account['id'] ?? '')) ?>" role="menuitem">Report</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($account['note'])): ?>
            <div class="profile-bio">
                <?= $account['note'] ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-meta">
            <?php if (!empty($account['fields'])): ?>
                <dl class="profile-fields">
                    <?php foreach ($account['fields'] as $field): ?>
                        <div class="field-item">
                            <dt class="field-name"><?= e($field['name'] ?? '') ?></dt>
                            <dd class="field-value">
                                <?= $field['value'] ?? '' ?>
                                <?php if ($field['verified_at'] ?? null): ?>
                                    <span class="verified-badge" title="Verified">
                                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16">
                                            <path d="M8 1l2 2 3-1-1 3 2 2-2 1 1 3-3-1-2 2-2-2-3 1 1-3-2-1 2-2-1-3 3 1z" fill="currentColor"/>
                                        </svg>
                                    </span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
            
            <div class="profile-stats">
                <a href="<?= url('/@' . e($username) . '/following') ?>" class="stat-link">
                    <strong><?= e(number_format($account['following_count'] ?? 0)) ?></strong> following
                </a>
                <a href="<?= url('/@' . e($username) . '/followers') ?>" class="stat-link">
                    <strong><?= e(number_format($account['followers_count'] ?? 0)) ?></strong> followers
                </a>
                <span class="stat">
                    <strong><?= e(number_format($account['statuses_count'] ?? 0)) ?></strong> posts
                </span>
            </div>
            
            <div class="profile-dates">
                <?php if (!empty($account['created_at'])): ?>
                    <span class="profile-joined">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16">
                            <rect x="2" y="3" width="12" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                            <line x1="2" y1="6" x2="14" y2="6" stroke="currentColor" stroke-width="2"/>
                            <line x1="5" y1="1" x2="5" y2="3" stroke="currentColor" stroke-width="2"/>
                            <line x1="11" y1="1" x2="11" y2="3" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Joined <?= date('F Y', strtotime($account['created_at'])) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <nav class="profile-tabs" aria-label="Profile sections">
        <a href="<?= url('/@' . e($username)) ?>" class="tab active" aria-current="page">
            Posts
        </a>
        <a href="<?= url('/@' . e($username) . '/replies') ?>" class="tab">
            Replies
        </a>
        <a href="<?= url('/@' . e($username) . '/media') ?>" class="tab">
            Media
        </a>
    </nav>
    
    <section class="profile-statuses" aria-label="User posts">
        <?php if (empty($statuses) || count($statuses) === 0): ?>
            <div class="profile-empty">
                <p>No posts yet.</p>
            </div>
        <?php else: ?>
            <div class="status-list" role="feed">
                <?php foreach ($statuses as $status): ?>
                    <?php 
                    $currentAccount = $currentAccount ?? null;
                    include __DIR__ . '/../../partials/status.php'; 
                    ?>
                <?php endforeach; ?>
            </div>
            
            <div class="profile-load-more" data-next-url="<?= e($nextUrl ?? '') ?>">
                <button type="button" class="btn btn-secondary load-more-btn" data-action="load-more">
                    Load more
                </button>
            </div>
        <?php endif; ?>
    </section>
</section>