<?php
/**
 * @var array $account Profile account
 * @var iterable $following Following list
 * @var array|null $currentAccount Current logged in account
 */
$displayName = $account['display_name'] ?? $account['username'] ?? 'Unknown';
$username = $account['username'] ?? 'unknown';
?>

<section class="profile-section">
    <header class="profile-header-compact">
        <div class="profile-info">
            <img 
                src="<?= e($account['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
                alt="" 
                class="profile-avatar-small"
                width="48"
                height="48"
                loading="lazy"
            >
            
            <div class="profile-details">
                <h1 class="profile-display-name"><?= e($displayName) ?></h1>
                <span class="profile-username">@<?= e($username) ?></span>
            </div>
        </div>
    </header>
    
    <nav class="profile-tabs" aria-label="Profile sections">
        <a href="<?= url('/@' . e($username)) ?>" class="tab">
            Posts
        </a>
        <a href="<?= url('/@' . e($username) . '/following') ?>" class="tab active" aria-current="page">
            Following
        </a>
        <a href="<?= url('/@' . e($username) . '/followers') ?>" class="tab">
            Followers
        </a>
    </nav>
    
    <section class="follow-list-section" aria-label="Following">
        <h2 class="visually-hidden">Following</h2>
        
        <?php if (empty($following) || count($following) === 0): ?>
            <div class="follow-list-empty">
                <p>Not following anyone yet.</p>
            </div>
        <?php else: ?>
            <ul class="follow-list" role="list">
                <?php foreach ($following as $followed): ?>
                    <?php 
                    $account = $followed;
                    $relationship = $followed['relationship'] ?? null;
                    include __DIR__ . '/../../partials/account-card.php'; 
                    ?>
                <?php endforeach; ?>
            </ul>
            
            <?php if (!empty($nextUrl)): ?>
                <div class="follow-list-pagination" data-next-url="<?= e($nextUrl) ?>">
                    <button type="button" class="btn btn-secondary load-more-btn" data-action="load-more">
                        Load more
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</section>