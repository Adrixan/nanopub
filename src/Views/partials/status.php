<?php
/**
 * @var array $status Status data
 * @var array|null $currentAccount Current logged in account
 */
$author = $status['account'] ?? [];
$isOwnStatus = isset($currentAccount['id']) && $currentAccount['id'] === ($author['id'] ?? null);
$hasMedia = !empty($status['media_attachments']);
$isBoost = isset($status['reblog']) && $status['reblog'];
$boostAuthor = $isBoost ? $author : null;
$displayStatus = $isBoost ? $status['reblog'] : $status;
$displayAuthor = $displayStatus['account'] ?? [];
?>

<article class="status<?= $isBoost ? ' status-boosted' : '' ?>" 
         data-status-id="<?= e($displayStatus['id'] ?? '') ?>"
         role="article"
         aria-labelledby="status-title-<?= e($displayStatus['id'] ?? '') ?>">
    
    <?php if ($isBoost): ?>
        <div class="status-boost-header">
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16">
                <path d="M4 4l8 8M12 4v8h-8" fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
            <a href="<?= url('/@' . e($boostAuthor['username'] ?? '')) ?>">
                <?= e($boostAuthor['display_name'] ?? $boostAuthor['username'] ?? 'Unknown') ?>
            </a> boosted
        </div>
    <?php endif; ?>
    
    <div class="status-content-wrapper">
        <header class="status-header">
            <a href="<?= url('/@' . e($displayAuthor['username'] ?? '')) ?>" class="status-author-avatar">
                <img 
                    src="<?= e($displayAuthor['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
                    alt="" 
                    width="48" 
                    height="48"
                    loading="lazy"
                >
            </a>
            
            <div class="status-author-info">
                <a href="<?= url('/@' . e($displayAuthor['username'] ?? '')) ?>" class="status-author-name">
                    <span class="display-name"><?= e($displayAuthor['display_name'] ?? $displayAuthor['username'] ?? 'Unknown') ?></span>
                    <span class="username">@<?= e($displayAuthor['username'] ?? 'unknown') ?></span>
                </a>
                <a href="<?= url('/@' . e($displayAuthor['username'] ?? '') . '/' . e($displayStatus['id'] ?? '')) ?>" 
                   class="status-time"
                   id="status-title-<?= e($displayStatus['id'] ?? '') ?>">
                    <time datetime="<?= e($displayStatus['created_at'] ?? '') ?>">
                        <?= time_ago($displayStatus['created_at'] ?? '') ?>
                    </time>
                </a>
            </div>
            
            <?php if ($isOwnStatus): ?>
                <div class="status-actions-menu">
                    <button type="button" class="status-menu-toggle" aria-label="Status options" aria-haspopup="true">
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                            <circle cx="10" cy="4" r="1.5" fill="currentColor"/>
                            <circle cx="10" cy="10" r="1.5" fill="currentColor"/>
                            <circle cx="10" cy="16" r="1.5" fill="currentColor"/>
                        </svg>
                    </button>
                    <ul class="status-dropdown" role="menu" aria-hidden="true">
                        <li role="none">
                            <a href="<?= url('/status/' . e($displayStatus['id'] ?? '') . '/edit') ?>" role="menuitem">Edit</a>
                        </li>
                        <li role="none">
                            <button type="button" 
                                    role="menuitem" 
                                    data-action="delete-status" 
                                    data-status-id="<?= e($displayStatus['id'] ?? '') ?>"
                                    data-csrf="<?= e($csrf ?? '') ?>">
                                Delete
                            </button>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </header>
        
        <div class="status-content">
            <?php if (!empty($displayStatus['content_warning'])): ?>
                <div class="content-warning">
                    <p><?= e($displayStatus['content_warning']) ?></p>
                    <button type="button" class="cw-toggle" aria-expanded="false">
                        Show more
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="status-text<?= !empty($displayStatus['content_warning']) ? ' hidden' : '' ?>">
                <?= $displayStatus['content'] ?? '' ?>
            </div>
            
            <?php if ($hasMedia): ?>
                <div class="status-media media-count-<?= count($displayStatus['media_attachments']) ?>">
                    <?php foreach ($displayStatus['media_attachments'] as $media): ?>
                        <figure class="media-item">
                            <?php if (($media['type'] ?? 'image') === 'image'): ?>
                                <a href="<?= e($media['url'] ?? '') ?>" target="_blank" rel="noopener">
                                    <img 
                                        src="<?= e($media['preview_url'] ?? $media['url'] ?? '') ?>" 
                                        alt="<?= e($media['description'] ?? '') ?>"
                                        loading="lazy"
                                    >
                                </a>
                            <?php elseif (($media['type'] ?? '') === 'video'): ?>
                                <video 
                                    src="<?= e($media['url'] ?? '') ?>" 
                                    poster="<?= e($media['preview_url'] ?? '') ?>"
                                    controls
                                    preload="metadata"
                                >
                                    <track kind="captions" src="<?= e($media['caption_url'] ?? '') ?>">
                                </video>
                            <?php elseif (($media['type'] ?? '') === 'gifv'): ?>
                                <video 
                                    src="<?= e($media['url'] ?? '') ?>" 
                                    poster="<?= e($media['preview_url'] ?? '') ?>"
                                    autoplay
                                    loop
                                    muted
                                    playsinline
                                ></video>
                            <?php elseif (($media['type'] ?? '') === 'audio'): ?>
                                <audio src="<?= e($media['url'] ?? '') ?>" controls></audio>
                            <?php endif; ?>
                            
                            <?php if (!empty($media['description'])): ?>
                                <figcaption class="visually-hidden"><?= e($media['description']) ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($displayStatus['poll'])): ?>
                <?php $poll = $displayStatus['poll']; ?>
                <div class="status-poll" data-poll-id="<?= e($poll['id'] ?? '') ?>">
                    <?php foreach ($poll['options'] ?? [] as $option): ?>
                        <div class="poll-option">
                            <label class="poll-label">
                                <?php if (($poll['voted'] ?? false) || ($poll['expired'] ?? false)): ?>
                                    <span class="poll-result">
                                        <span class="poll-result-bar" style="width: <?= e($option['percentage'] ?? 0) ?>%"></span>
                                        <span class="poll-option-text"><?= e($option['title'] ?? '') ?></span>
                                        <span class="poll-percentage"><?= e($option['percentage'] ?? 0) ?>%</span>
                                    </span>
                                <?php else: ?>
                                    <input type="radio" name="poll-<?= e($poll['id'] ?? '') ?>" value="<?= e($option['id'] ?? '') ?>">
                                    <span class="poll-option-text"><?= e($option['title'] ?? '') ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="poll-meta">
                        <span><?= e(number_format($poll['votes_count'] ?? 0)) ?> votes</span>
                        <?php if ($poll['expired'] ?? false): ?>
                            <span>Ended</span>
                        <?php else: ?>
                            <span>Ends in <?= time_ago($poll['expires_at'] ?? '') ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!($poll['voted'] ?? false) && !($poll['expired'] ?? false)): ?>
                        <button type="button" class="btn btn-small poll-vote" data-poll-id="<?= e($poll['id'] ?? '') ?>">
                            Vote
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <footer class="status-footer">
            <nav class="status-interactions" aria-label="Status interactions">
                <?php if (isset($currentAccount) && $currentAccount): ?>
                    <a href="<?= url('/reply/' . e($displayStatus['id'] ?? '')) ?>" 
                       class="interaction-btn reply-btn"
                       aria-label="Reply"
                       title="Reply">
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                            <path d="M18 10c0 4.4-3.6 8-8 8H4l2-2H10c3.3 0 6-2.7 6-6s-2.7-6-6-6H4l2-2H10c4.4 0 8 3.6 8 8z" fill="none" stroke="currentColor" stroke-width="2"/>
                            <path d="M2 10l6-4v8L2 10z" fill="currentColor"/>
                        </svg>
                        <?php if (($displayStatus['replies_count'] ?? 0) > 0): ?>
                            <span class="count"><?= e($displayStatus['replies_count']) ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <button type="button" 
                            class="interaction-btn boost-btn<?= ($displayStatus['boosted'] ?? false) ? ' active' : '' ?>"
                            aria-label="Boost"
                            aria-pressed="<?= ($displayStatus['boosted'] ?? false) ? 'true' : 'false' ?>"
                            title="Boost"
                            data-action="boost"
                            data-status-id="<?= e($displayStatus['id'] ?? '') ?>"
                            data-csrf="<?= e($csrf ?? '') ?>">
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                            <path d="M4 4l8 8M12 4v8h-8" fill="none" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <?php if (($displayStatus['boosts_count'] ?? 0) > 0): ?>
                            <span class="count"><?= e($displayStatus['boosts_count']) ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <button type="button" 
                            class="interaction-btn favourite-btn<?= ($displayStatus['favourited'] ?? false) ? ' active' : '' ?>"
                            aria-label="Favourite"
                            aria-pressed="<?= ($displayStatus['favourited'] ?? false) ? 'true' : 'false' ?>"
                            title="Favourite"
                            data-action="favourite"
                            data-status-id="<?= e($displayStatus['id'] ?? '') ?>"
                            data-csrf="<?= e($csrf ?? '') ?>">
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                            <path d="M10 18l-1.45-1.32C4.4 12.36 2 10.28 2 7.5 2 5.42 3.42 4 5.5 4c1.74 0 3.41 1.01 4.5 2.09C11.09 5.01 12.76 4 14.5 4 16.58 4 18 5.42 18 7.5c0 2.78-2.4 4.86-6.55 8.18L10 18z" fill="none" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <?php if (($displayStatus['favourites_count'] ?? 0) > 0): ?>
                            <span class="count"><?= e($displayStatus['favourites_count']) ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <button type="button" 
                            class="interaction-btn bookmark-btn<?= ($displayStatus['bookmarked'] ?? false) ? ' active' : '' ?>"
                            aria-label="Bookmark"
                            aria-pressed="<?= ($displayStatus['bookmarked'] ?? false) ? 'true' : 'false' ?>"
                            title="Bookmark"
                            data-action="bookmark"
                            data-status-id="<?= e($displayStatus['id'] ?? '') ?>"
                            data-csrf="<?= e($csrf ?? '') ?>">
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                            <path d="M16 18l-6-5-6 5V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v14z" fill="none" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </button>
                <?php else: ?>
                    <span class="interaction-btn reply-btn" aria-label="Replies">
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                            <path d="M18 10c0 4.4-3.6 8-8 8H4l2-2H10c3.3 0 6-2.7 6-6s-2.7-6-6-6H4l2-2H10c4.4 0 8 3.6 8 8z" fill="none" stroke="currentColor" stroke-width="2"/>
                            <path d="M2 10l6-4v8L2 10z" fill="currentColor"/>
                        </svg>
                        <?php if (($displayStatus['replies_count'] ?? 0) > 0): ?>
                            <span class="count"><?= e($displayStatus['replies_count']) ?></span>
                        <?php endif; ?>
                    </span>
                    
                    <span class="interaction-btn boost-btn">
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                            <path d="M4 4l8 8M12 4v8h-8" fill="none" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <?php if (($displayStatus['boosts_count'] ?? 0) > 0): ?>
                            <span class="count"><?= e($displayStatus['boosts_count']) ?></span>
                        <?php endif; ?>
                    </span>
                    
                    <span class="interaction-btn favourite-btn">
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                            <path d="M10 18l-1.45-1.32C4.4 12.36 2 10.28 2 7.5 2 5.42 3.42 4 5.5 4c1.74 0 3.41 1.01 4.5 2.09C11.09 5.01 12.76 4 14.5 4 16.58 4 18 5.42 18 7.5c0 2.78-2.4 4.86-6.55 8.18L10 18z" fill="none" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <?php if (($displayStatus['favourites_count'] ?? 0) > 0): ?>
                            <span class="count"><?= e($displayStatus['favourites_count']) ?></span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
                
                <button type="button" 
                        class="interaction-btn share-btn"
                        aria-label="Share"
                        title="Share"
                        data-action="share"
                        data-status-id="<?= e($displayStatus['id'] ?? '') ?>">
                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                        <circle cx="16" cy="6" r="3" fill="none" stroke="currentColor" stroke-width="2"/>
                        <circle cx="4" cy="10" r="3" fill="none" stroke="currentColor" stroke-width="2"/>
                        <circle cx="16" cy="14" r="3" fill="none" stroke="currentColor" stroke-width="2"/>
                        <line x1="7" y1="8.5" x2="13" y2="6.5" stroke="currentColor" stroke-width="2"/>
                        <line x1="7" y1="11.5" x2="13" y2="13.5" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>
            </nav>
        </footer>
    </div>
</article>