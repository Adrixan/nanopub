<?php
/**
 * @var array $account Account data
 * @var array|null $relationship Relationship data (following, followed_by, etc.)
 */
$displayName = $account['display_name'] ?? $account['username'] ?? 'Unknown';
$username = $account['username'] ?? 'unknown';
$domain = $account['domain'] ?? parse_url($account['url'] ?? '', PHP_URL_HOST) ?? '';
$fullUsername = $domain ? $username . '@' . $domain : $username;
?>

<article class="account-card" data-account-id="<?= e($account['id'] ?? '') ?>">
    <a href="<?= url('/@' . e($username)) ?>" class="account-card-link">
        <img 
            src="<?= e($account['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
            alt="" 
            class="account-avatar"
            width="48"
            height="48"
            loading="lazy"
        >
        
        <div class="account-info">
            <h3 class="account-display-name"><?= e($displayName) ?></h3>
            <p class="account-username">@<?= e($fullUsername) ?></p>
            
            <?php if (!empty($account['note'])): ?>
                <p class="account-bio">
                    <?= e(mb_strimwidth(strip_tags($account['note']), 0, 100, '...')) ?>
                </p>
            <?php endif; ?>
            
            <div class="account-stats">
                <span class="stat">
                    <strong><?= e(number_format($account['statuses_count'] ?? 0)) ?></strong> posts
                </span>
                <span class="stat">
                    <strong><?= e(number_format($account['followers_count'] ?? 0)) ?></strong> followers
                </span>
                <span class="stat">
                    <strong><?= e(number_format($account['following_count'] ?? 0)) ?></strong> following
                </span>
            </div>
        </div>
    </a>
    
    <?php if (isset($currentAccount) && $currentAccount && ($currentAccount['id'] ?? null) !== ($account['id'] ?? null)): ?>
        <div class="account-actions">
            <?php if ($relationship['blocked_by'] ?? false): ?>
                <span class="blocked-label">Blocks you</span>
            <?php elseif ($relationship['blocking'] ?? false): ?>
                <button type="button" 
                        class="btn btn-danger btn-small"
                        data-action="unblock"
                        data-account-id="<?= e($account['id'] ?? '') ?>"
                        data-csrf="<?= e($csrf ?? '') ?>">
                    Unblock
                </button>
            <?php elseif ($relationship['requested'] ?? false): ?>
                <button type="button" 
                        class="btn btn-secondary btn-small"
                        data-action="cancel-follow-request"
                        data-account-id="<?= e($account['id'] ?? '') ?>"
                        data-csrf="<?= e($csrf ?? '') ?>">
                    Cancel Request
                </button>
            <?php elseif ($relationship['following'] ?? false): ?>
                <button type="button" 
                        class="btn btn-secondary btn-small following-btn"
                        data-action="unfollow"
                        data-account-id="<?= e($account['id'] ?? '') ?>"
                        data-csrf="<?= e($csrf ?? '') ?>">
                    <span class="following-text">Following</span>
                    <span class="unfollow-text">Unfollow</span>
                </button>
            <?php else: ?>
                <button type="button" 
                        class="btn btn-primary btn-small"
                        data-action="follow"
                        data-account-id="<?= e($account['id'] ?? '') ?>"
                        data-csrf="<?= e($csrf ?? '') ?>">
                    <?= ($relationship['followed_by'] ?? false) ? 'Follow back' : 'Follow' ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</article>