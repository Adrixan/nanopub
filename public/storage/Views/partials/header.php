<header class="site-header" role="banner">
    <div class="header-container">
        <a href="<?= url('/') ?>" class="logo" aria-label="NanoPub home">
            <svg class="logo-icon" aria-hidden="true" width="32" height="32" viewBox="0 0 32 32">
                <circle cx="16" cy="16" r="14" fill="none" stroke="currentColor" stroke-width="2"/>
                <circle cx="16" cy="16" r="6" fill="currentColor"/>
            </svg>
            <span class="logo-text">NanoPub</span>
        </a>
        
        <form action="<?= url('/search') ?>" method="get" class="search-form" role="search">
            <label for="header-search" class="visually-hidden">Search</label>
            <input 
                type="search" 
                id="header-search" 
                name="q" 
                class="search-input" 
                placeholder="Search..."
                autocomplete="off"
                aria-label="Search"
            >
            <button type="submit" class="search-button" aria-label="Submit search">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                    <circle cx="9" cy="9" r="6" fill="none" stroke="currentColor" stroke-width="2"/>
                    <line x1="13" y1="13" x2="18" y2="18" stroke="currentColor" stroke-width="2"/>
                </svg>
            </button>
        </form>
        
        <nav class="header-nav" aria-label="Primary navigation">
            <?php if (isset($currentAccount) && $currentAccount): ?>
                <a href="<?= url('/notifications') ?>" class="nav-link nav-notifications" aria-label="Notifications">
                    <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" fill="currentColor"/>
                    </svg>
                    <?php if (isset($notificationCount) && $notificationCount > 0): ?>
                        <span class="notification-badge" aria-label="<?= $notificationCount ?> unread notifications">
                            <?= $notificationCount > 99 ? '99+' : $notificationCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <div class="profile-dropdown">
                    <button 
                        type="button" 
                        class="profile-toggle" 
                        aria-expanded="false" 
                        aria-haspopup="true"
                        aria-label="Profile menu"
                    >
                        <img 
                            src="<?= e($currentAccount['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
                            alt="" 
                            class="avatar-small"
                            width="32"
                            height="32"
                        >
                    </button>
                    
                    <ul class="dropdown-menu" role="menu" aria-hidden="true">
                        <li role="none">
                            <a href="<?= url('/@' . e($currentAccount['username'])) ?>" role="menuitem">
                                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                    <circle cx="10" cy="6" r="4" fill="none" stroke="currentColor" stroke-width="2"/>
                                    <path d="M2 18c0-4 4-6 8-6s8 2 8 6" fill="none" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Profile
                            </a>
                        </li>
                        <li role="none">
                            <a href="<?= url('/settings') ?>" role="menuitem">
                                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                    <circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="10" cy="10" r="2" fill="currentColor"/>
                                </svg>
                                Settings
                            </a>
                        </li>
                        <?php if (($currentAccount['is_admin'] ?? false) || ($currentAccount['is_moderator'] ?? false)): ?>
                            <li role="none" class="dropdown-divider"></li>
                            <li role="none">
                                <a href="<?= url('/admin') ?>" role="menuitem">
                                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                        <rect x="2" y="2" width="16" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                                        <line x1="6" y1="6" x2="14" y2="6" stroke="currentColor" stroke-width="2"/>
                                        <line x1="6" y1="10" x2="14" y2="10" stroke="currentColor" stroke-width="2"/>
                                        <line x1="6" y1="14" x2="10" y2="14" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    Administration
                                </a>
                            </li>
                        <?php endif; ?>
                        <li role="none" class="dropdown-divider"></li>
                        <li role="none">
                            <a href="<?= url('/logout') ?>" role="menuitem" data-method="post" data-csrf="<?= e($csrf ?? '') ?>">
                                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                    <path d="M8 2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4" fill="none" stroke="currentColor" stroke-width="2"/>
                                    <path d="M14 6l4 4-4 4M18 10H6" fill="none" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Log out
                            </a>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="auth-links">
                    <a href="<?= url('/login') ?>" class="btn btn-text">Log in</a>
                    <a href="<?= url('/register') ?>" class="btn btn-primary">Register</a>
                </div>
            <?php endif; ?>
        </nav>
        
        <button 
            type="button" 
            class="mobile-menu-toggle" 
            aria-label="Toggle menu" 
            aria-expanded="false"
            aria-controls="mobile-nav"
        >
            <span class="hamburger"></span>
        </button>
    </div>
</header>