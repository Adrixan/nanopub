<?php
/**
 * @var iterable $statuses Public timeline statuses
 */
?>

<div class="timeline-layout">
    <?php 
    $activeNav = 'public';
    $notificationCount = $notificationCount ?? 0;
    $trendingTags = $trendingTags ?? [];
    include __DIR__ . '/../../partials/sidebar.php'; 
    ?>
    
    <main class="timeline-main" role="main">
        <header class="timeline-header">
            <h1 class="timeline-title">Public Timeline</h1>
            <nav class="timeline-tabs" aria-label="Timeline filters">
                <a href="<?= url('/') ?>" class="tab">
                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="none" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Following
                </a>
                <a href="<?= url('/public') ?>" class="tab active" aria-current="page">
                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                        <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                        <line x1="2" y1="10" x2="18" y2="10" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Federated
                </a>
            </nav>
        </header>
        
        <section class="timeline-feed" aria-label="Public Timeline">
            <?php if (empty($statuses) || count($statuses) === 0): ?>
                <div class="timeline-empty">
                    <h2 class="empty-title">No posts yet</h2>
                    <p class="empty-description">
                        Be the first to post something!
                    </p>
                    <?php if (empty($isLoggedIn)): ?>
                        <a href="<?= url('/login') ?>" class="btn btn-primary">
                            Sign in to post
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="status-list" role="feed" aria-label="Statuses">
                    <?php foreach ($statuses as $status): ?>
                        <?php 
                        $currentAccount = $account ?? null;
                        include __DIR__ . '/../../partials/status.php'; 
                        ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="timeline-load-more" data-next-url="<?= e($nextUrl ?? '') ?>">
                    <button type="button" class="btn btn-secondary load-more-btn" data-action="load-more">
                        Load more
                    </button>
                </div>
            <?php endif; ?>
        </section>
    </main>
    
    <aside class="timeline-right-sidebar" role="complementary">
        <?php if (isset($trendingTags) && !empty($trendingTags)): ?> <?php // @phpstan-ignore isset.variable ?>
            <section class="trending-section" aria-labelledby="trending-heading">
                <h2 id="trending-heading" class="section-heading">Trending hashtags</h2>
                <ul class="trending-list">
                    <?php foreach (array_slice($trendingTags, 0, 5) as $tag): ?>
                        <li>
                            <a href="<?= url('/tags/' . e($tag['name'])) ?>" class="trending-tag">
                                <span class="tag-name">#<?= e($tag['name']) ?></span>
                                <?php if (isset($tag['count'])): ?>
                                    <span class="tag-count"><?= e(number_format($tag['count'])) ?> posts</span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </aside>
</div>
