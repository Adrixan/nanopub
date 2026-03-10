<?php
/**
 * Notifications index view.
 * 
 * @var array $account Current account
 * @var array $notifications Notification list (flat rows from DB)
 */
$activeNav = 'notifications';
?>

<div class="page-layout">
    <main class="page-main" role="main">
        <header class="page-header">
            <h1 class="page-title">Notifications</h1>
        </header>

        <section class="notifications-feed" aria-label="Notifications">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <svg aria-hidden="true" width="48" height="48" viewBox="0 0 24 24" class="empty-icon">
                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" fill="currentColor"/>
                    </svg>
                    <h2 class="empty-title">No notifications yet</h2>
                    <p class="empty-description">When someone interacts with your posts, you'll see it here.</p>
                </div>
            <?php else: ?>
                <div class="notification-list" role="feed" aria-label="Notifications">
                    <?php foreach ($notifications as $n): ?>
                        <?php
                        $type = $n['type'] ?? 'unknown';
                        $fromUsername = $n['from_account_username'] ?? 'unknown';
                        $fromDisplayName = $n['from_account_display_name'] ?? $fromUsername;
                        $fromAvatar = $n['from_account_avatar_url'] ?? url('/assets/images/default-avatar.png');
                        $statusContent = $n['status_content'] ?? null;
                        $createdAt = $n['created_at'] ?? '';
                        $isRead = !empty($n['read_at']);
                        ?>
                        <article class="notification-item notification-<?= e($type) ?><?= $isRead ? '' : ' unread' ?>"
                                 role="article" aria-label="<?= e($type) ?> notification">
                            <div class="notification-icon">
                                <?php if ($type === 'follow'): ?>
                                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="8.5" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/><line x1="20" y1="8" x2="20" y2="14" stroke="currentColor" stroke-width="2"/><line x1="23" y1="11" x2="17" y2="11" stroke="currentColor" stroke-width="2"/></svg>
                                <?php elseif ($type === 'favourite'): ?>
                                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41 1.01 4.5 2.09C13.09 4.01 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="currentColor"/></svg>
                                <?php elseif ($type === 'reblog'): ?>
                                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24"><path d="M7 7l10 10M17 7v10H7" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                <?php elseif ($type === 'mention'): ?>
                                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                <?php else: ?>
                                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                <?php endif; ?>
                            </div>

                            <div class="notification-body">
                                <div class="notification-header">
                                    <a href="<?= url('/@' . e($fromUsername)) ?>" class="notification-author">
                                        <img src="<?= e($fromAvatar) ?>" alt="" width="32" height="32" loading="lazy" class="notification-avatar">
                                        <strong><?= e($fromDisplayName) ?></strong>
                                    </a>
                                    <span class="notification-action">
                                        <?php if ($type === 'follow'): ?>followed you
                                        <?php elseif ($type === 'favourite'): ?>favourited your post
                                        <?php elseif ($type === 'reblog'): ?>boosted your post
                                        <?php elseif ($type === 'mention'): ?>mentioned you
                                        <?php else: ?>sent a notification
                                        <?php endif; ?>
                                    </span>
                                    <time class="notification-time" datetime="<?= e($createdAt) ?>">
                                        <?= time_ago($createdAt) ?>
                                    </time>
                                </div>

                                <?php if ($statusContent): ?>
                                    <div class="notification-excerpt">
                                        <?= mb_strimwidth(strip_tags($statusContent), 0, 200, '…') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
