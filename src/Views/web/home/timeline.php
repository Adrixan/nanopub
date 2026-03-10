<?php
/**
 * @var array $account Current account
 * @var iterable $statuses Timeline statuses
 */
if (!isset($account) || empty($account) || !is_array($account)) { // @phpstan-ignore isset.variable
    header('Location: /login');
    exit;
}
?>

<div class="timeline-layout">
    <?php 
    $activeNav = 'home';
    $notificationCount = $notificationCount ?? 0;
    $trendingTags = $trendingTags ?? [];
    include __DIR__ . '/../../partials/sidebar.php'; 
    ?>
    
    <main class="timeline-main" role="main">
        <header class="timeline-header">
            <h1 class="timeline-title">Home</h1>
            <nav class="timeline-tabs" aria-label="Timeline filters">
                <a href="<?= url('/') ?>" class="tab active" aria-current="page">
                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="none" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Following
                </a>
                <a href="<?= url('/public') ?>" class="tab">
                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                        <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                        <line x1="2" y1="10" x2="18" y2="10" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Federated
                </a>
            </nav>
        </header>
        
        <section class="compose-section" aria-label="Compose new status">
            <form action="<?= url('/statuses') ?>" 
                  method="post" 
                  class="compose-form"
                  enctype="multipart/form-data">
                
                <div class="compose-author">
                    <img 
                        src="<?= e($account['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
                        alt="" 
                        width="48" 
                        height="48"
                        class="compose-avatar"
                    >
                    <span class="compose-name"><?= e($account['display_name'] ?? $account['username']) ?></span>
                </div>
                
                <div class="compose-content">
                    <textarea 
                        name="status" 
                        id="compose-textarea"
                        class="compose-textarea"
                        placeholder="What's on your mind?"
                        aria-label="Status content"
                        rows="4"
                        maxlength="<?= e($maxChars ?? 500) ?>"
                        required
                    ></textarea>
                    
                    <div class="compose-counter">
                        <span id="char-counter">0</span> / <?= e($maxChars ?? 500) ?>
                    </div>
                    
                    <div id="compose-preview" class="compose-preview" aria-live="polite"></div>
                    
                    <div class="compose-options">
                        <div class="compose-media">
                            <label for="compose-media" class="compose-media-label" title="Add media">
                                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                    <rect x="2" y="2" width="16" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="7" cy="7" r="1.5" fill="currentColor"/>
                                    <path d="M18 14l-5-5-8 8" fill="none" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                <span class="visually-hidden">Add media</span>
                            </label>
                            <input 
                                type="file" 
                                id="compose-media" 
                                name="media[]" 
                                multiple 
                                accept="image/*,video/*,audio/*"
                                class="visually-hidden"
                            >
                        </div>
                        
                        <div class="compose-cw">
                            <button type="button" class="compose-cw-toggle" aria-expanded="false" title="Add content warning">
                                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                    <path d="M10 12v-2M10 8h.01M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0z" fill="none" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                <span class="visually-hidden">Add content warning</span>
                            </button>
                        </div>
                        
                        <div class="compose-visibility">
                            <label for="compose-visibility" class="visually-hidden">Post visibility</label>
                            <select name="visibility" id="compose-visibility" class="compose-select">
                                <option value="public">Public</option>
                                <option value="unlisted">Unlisted</option>
                                <option value="private" selected>Followers only</option>
                                <option value="direct">Direct</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="cw-field" class="compose-cw-field hidden">
                        <label for="compose-spoiler-text" class="visually-hidden">Content warning</label>
                        <input 
                            type="text" 
                            id="compose-spoiler-text" 
                            name="spoiler_text" 
                            class="compose-cw-input"
                            placeholder="Content warning"
                            maxlength="100"
                        >
                    </div>
                    
                    <div id="media-preview" class="compose-media-preview" aria-live="polite"></div>
                </div>
                
                <div class="compose-submit">
                    <button type="submit" class="btn btn-primary">
                        Post
                    </button>
                </div>
            </form>
        </section>
        
        <section class="timeline-feed" aria-label="Timeline">
            <?php if (empty($statuses) || count($statuses) === 0): ?>
                <div class="timeline-empty">
                    <h2 class="empty-title">Your timeline is empty</h2>
                    <p class="empty-description">
                        Follow some people to see their posts here!
                    </p>
                    <a href="<?= url('/explore') ?>" class="btn btn-primary">
                        Explore Users
                    </a>
                </div>
            <?php else: ?>
                <div class="status-list" role="feed" aria-label="Statuses">
                    <?php foreach ($statuses as $status): ?>
                        <?php 
                        $currentAccount = $account ?? null; // @phpstan-ignore nullCoalesce.variable
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
        <?php if (isset($suggestions) && !empty($suggestions)): ?>
            <section class="suggestions-section" aria-labelledby="suggestions-heading">
                <h2 id="suggestions-heading" class="section-heading">Who to follow</h2>
                <ul class="suggestions-list">
                    <?php foreach (array_slice($suggestions, 0, 3) as $suggestedAccount): ?>
                        <li>
                            <?php 
                            $account = $suggestedAccount;
                            $relationship = $suggestedAccount['relationship'] ?? null;
                            include __DIR__ . '/../../partials/account-card.php'; 
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?= url('/explore') ?>" class="suggestions-more">Show more</a>
            </section>
        <?php endif; ?>
        
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