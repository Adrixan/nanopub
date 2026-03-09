<?php
/**
 * @var array $account Profile account
 * @var iterable $followers Followers list
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
        <a href="<?= url('/@' . e($username) . '/following') ?>" class="tab">
            Following
        </a>
        <a href="<?= url('/@' . e($username) . '/followers') ?>" class="tab active" aria-current="page">
            Followers
        </a>
    </nav>
    
    <section class="follow-list-section" aria-label="Followers">
        <h2 class="visually-hidden">Followers</h2>
        
        <?php if (empty($followers) || count($followers) === 0): ?>
            <div class="follow-list-empty">
                <p>No followers yet.</p>
            </div>
        <?php else: ?>
            <ul class="follow-list" role="list">
                <?php foreach ($followers as $follower): ?>
                    <?php 
                    $account = $follower;
                    $relationship = $follower['relationship'] ?? null;
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