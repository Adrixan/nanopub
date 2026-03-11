<aside class="sidebar" role="complementary" aria-label="Sidebar navigation">
    <nav class="sidebar-nav" aria-label="Main navigation">
        <ul class="nav-list">
            <li>
                <a href="<?= url('/') ?>" class="nav-link<?= isset($activeNav) && $activeNav === 'home' ? ' active' : '' ?>" aria-current="<?= isset($activeNav) && $activeNav === 'home' ? 'page' : 'false' ?>">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="none" stroke="currentColor" stroke-width="2"/>
                        <polyline points="9 22 9 12 15 12 15 22" fill="none" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <span>Home</span>
                </a>
            </li>
            <li>
                <a href="<?= url('/public') ?>" class="nav-link<?= isset($activeNav) && $activeNav === 'public' ? ' active' : '' ?>">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
                        <line x1="2" y1="12" x2="22" y2="12" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" fill="none" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <span>Federated</span>
                </a>
            </li>
            <li>
                <a href="<?= url('/notifications') ?>" class="nav-link<?= isset($activeNav) && $activeNav === 'notifications' ? ' active' : '' ?>">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" fill="currentColor"/>
                    </svg>
                    <span>Notifications</span>
                    <?php if (isset($notificationCount) && $notificationCount > 0): ?>
                        <span class="nav-badge"><?= $notificationCount > 99 ? '99+' : $notificationCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="<?= url('/explore') ?>" class="nav-link<?= isset($activeNav) && $activeNav === 'explore' ? ' active' : '' ?>">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <span>Explore</span>
                </a>
            </li>
            <li>
                <a href="<?= url('/bookmarks') ?>" class="nav-link<?= isset($activeNav) && $activeNav === 'bookmarks' ? ' active' : '' ?>">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" fill="none" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <span>Bookmarks</span>
                </a>
            </li>
            <li>
                <a href="<?= url('/settings') ?>" class="nav-link<?= isset($activeNav) && $activeNav === 'settings' ? ' active' : '' ?>">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" fill="none" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <?php if (isset($trendingTags) && !empty($trendingTags)): ?>
        <section class="trending-section" aria-labelledby="trending-heading">
            <h2 id="trending-heading" class="section-heading">Trending</h2>
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
    
    <?php if (isset($instance)): ?>
        <section class="instance-info" aria-labelledby="instance-heading">
            <h2 id="instance-heading" class="visually-hidden">Instance Information</h2>
            <div class="instance-stats">
                <dl class="stats-list">
                    <div class="stat-item">
                        <dt>Users</dt>
                        <dd><?= e(number_format($instance['user_count'] ?? 0)) ?></dd>
                    </div>
                    <div class="stat-item">
                        <dt>Statuses</dt>
                        <dd><?= e(number_format($instance['status_count'] ?? 0)) ?></dd>
                    </div>
                    <div class="stat-item">
                        <dt>Peers</dt>
                        <dd><?= e(number_format($instance['peer_count'] ?? 0)) ?></dd>
                    </div>
                </dl>
            </div>
        </section>
    <?php endif; ?>
    
    <div class="sidebar-footer">
        <a href="<?= url('/compose') ?>" class="btn btn-primary btn-block compose-btn">
            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                <line x1="10" y1="4" x2="10" y2="16" stroke="currentColor" stroke-width="2"/>
                <line x1="4" y1="10" x2="16" y2="10" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span>Compose</span>
        </a>
    </div>
</aside>