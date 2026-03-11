<?php
/**
 * @var array $accounts Accounts to display
 * @var array|null $currentAccount Current logged-in account
 * @var string $search Current search query
 */
$activeNav = 'explore';
?>
<div class="timeline-layout">
    <?php
    $notificationCount = $notificationCount ?? 0;
    $trendingTags = $trendingTags ?? [];
    include __DIR__ . '/../../partials/sidebar.php';
    ?>

    <main class="timeline-main" role="main">
        <header class="page-header">
            <h1 class="page-title">Explore</h1>
        </header>

        <section class="explore-search">
            <form action="<?= url('/explore') ?>" method="get" class="search-form">
                <input
                    type="search"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Search users..."
                    class="search-input"
                    aria-label="Search users"
                >
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </section>

        <section class="explore-users">
            <?php if (empty($accounts)): ?>
                <p class="empty-state">
                    <?= !empty($search) ? 'No users found matching your search.' : 'No users to show yet.' ?>
                </p>
            <?php endif; ?>

            <?php foreach ($accounts as $account):
                $displayName = $account['display_name'] ?? $account['username'] ?? 'Unknown';
                $username = $account['username'] ?? 'unknown';
                $domain = $account['domain'] ?? '';
                $fullUsername = $domain ? $username . '@' . $domain : $username;
                $isCurrentUser = $currentAccount !== null
                    && (int) ($currentAccount['id'] ?? 0) === (int) ($account['id'] ?? 0);
            ?>
                <article class="account-card" data-account-id="<?= e((string) ($account['id'] ?? '')) ?>">
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
                                    <strong><?= e(number_format($account['followers_count'] ?? 0)) ?></strong> followers
                                </span>
                                <span class="stat">
                                    <strong><?= e(number_format($account['following_count'] ?? 0)) ?></strong> following
                                </span>
                            </div>
                        </div>
                    </a>

                    <?php if ($currentAccount !== null && !$isCurrentUser): ?>
                        <div class="account-actions">
                            <?php if ($account['is_following']): ?>
                                <form method="post" action="<?= url('/@' . e($username) . '/unfollow') ?>">
                                    <button type="submit" class="btn btn-secondary btn-small following-btn">
                                        <span class="following-text">Following</span>
                                        <span class="unfollow-text">Unfollow</span>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= url('/@' . e($username) . '/follow') ?>">
                                    <button type="submit" class="btn btn-primary btn-small">Follow</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>
