<?php
/**
 * Bookmarks index view.
 * 
 * @var array $account Current account
 * @var array $bookmarks Bookmarked status rows (flat, with author data from JOIN)
 */
$activeNav = 'bookmarks';
?>

<div class="page-layout">
    <main class="page-main" role="main">
        <header class="page-header">
            <h1 class="page-title">Bookmarks</h1>
        </header>

        <section class="bookmarks-feed" aria-label="Bookmarked statuses">
            <?php if (empty($bookmarks)): ?>
                <div class="empty-state">
                    <svg aria-hidden="true" width="48" height="48" viewBox="0 0 24 24" class="empty-icon">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <h2 class="empty-title">No bookmarks yet</h2>
                    <p class="empty-description">Save posts for later by bookmarking them.</p>
                </div>
            <?php else: ?>
                <div class="status-list" role="feed" aria-label="Bookmarked statuses">
                    <?php foreach ($bookmarks as $bm): ?>
                        <?php
                        $authorUsername = $bm['author_username'] ?? 'unknown';
                        $authorDisplayName = $bm['author_display_name'] ?? $authorUsername;
                        $authorAvatar = $bm['author_avatar_url'] ?? url('/assets/images/default-avatar.png');
                        $statusId = $bm['status_id'] ?? '';
                        $content = $bm['content'] ?? '';
                        $cw = $bm['content_warning'] ?? '';
                        $createdAt = $bm['status_created_at'] ?? $bm['created_at'] ?? '';
                        ?>
                        <article class="status" data-status-id="<?= e((string) $statusId) ?>" role="article">
                            <div class="status-content-wrapper">
                                <header class="status-header">
                                    <a href="<?= url('/@' . e($authorUsername)) ?>" class="status-author-avatar">
                                        <img src="<?= e($authorAvatar) ?>" alt="" width="48" height="48" loading="lazy">
                                    </a>
                                    <div class="status-author-info">
                                        <a href="<?= url('/@' . e($authorUsername)) ?>" class="status-author-name">
                                            <span class="display-name"><?= e($authorDisplayName) ?></span>
                                            <span class="username">@<?= e($authorUsername) ?></span>
                                        </a>
                                        <a href="<?= url('/@' . e($authorUsername) . '/statuses/' . e((string) $statusId)) ?>" class="status-time">
                                            <time datetime="<?= e($createdAt) ?>"><?= time_ago($createdAt) ?></time>
                                        </a>
                                    </div>
                                </header>

                                <div class="status-content">
                                    <?php if (!empty($cw)): ?>
                                        <div class="content-warning">
                                            <p><?= e($cw) ?></p>
                                            <button type="button" class="cw-toggle" aria-expanded="false">Show more</button>
                                        </div>
                                    <?php endif; ?>

                                    <div class="status-text<?= !empty($cw) ? ' hidden' : '' ?>">
                                        <?= $content ?>
                                    </div>
                                </div>

                                <footer class="status-footer">
                                    <nav class="status-interactions" aria-label="Status interactions">
                                        <span class="interaction-btn">
                                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                                <path d="M10 18l-1.45-1.32C4.4 12.36 2 10.28 2 7.5 2 5.42 3.42 4 5.5 4c1.74 0 3.41 1.01 4.5 2.09C11.09 5.01 12.76 4 14.5 4 16.58 4 18 5.42 18 7.5c0 2.78-2.4 4.86-6.55 8.18L10 18z" fill="none" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                            <?php if (($bm['favourites_count'] ?? 0) > 0): ?>
                                                <span class="count"><?= e((string) $bm['favourites_count']) ?></span>
                                            <?php endif; ?>
                                        </span>

                                        <span class="interaction-btn">
                                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                                <path d="M4 4l8 8M12 4v8h-8" fill="none" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                            <?php if (($bm['reblogs_count'] ?? 0) > 0): ?>
                                                <span class="count"><?= e((string) $bm['reblogs_count']) ?></span>
                                            <?php endif; ?>
                                        </span>

                                        <span class="interaction-btn bookmark-btn active" aria-label="Bookmarked">
                                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                                <path d="M16 18l-6-5-6 5V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v14z" fill="currentColor"/>
                                            </svg>
                                        </span>
                                    </nav>
                                </footer>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
