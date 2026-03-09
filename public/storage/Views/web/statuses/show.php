<?php
/**
 * @var array $status Status data
 * @var array $account Author account
 * @var array $context Context with ancestors and descendants
 * @var array|null $currentAccount Current logged in account
 */
?>

<article class="status-thread" role="article" aria-label="Status thread">
    <?php if (!empty($context['ancestors'])): ?>
        <section class="thread-ancestors" aria-label="Previous posts in thread">
            <?php foreach ($context['ancestors'] as $ancestor): ?>
                <?php 
                $status = $ancestor;
                include __DIR__ . '/../../partials/status.php'; 
                ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    
    <section class="thread-main" aria-label="Main status">
        <article class="status status-detail" data-status-id="<?= e($status['id'] ?? '') ?>">
            <header class="status-header">
                <a href="<?= url('/@' . e($account['username'] ?? '')) ?>" class="status-author-avatar">
                    <img 
                        src="<?= e($account['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
                        alt="" 
                        width="64" 
                        height="64"
                        loading="lazy"
                    >
                </a>
                
                <div class="status-author-info">
                    <a href="<?= url('/@' . e($account['username'] ?? '')) ?>" class="status-author-name">
                        <span class="display-name"><?= e($account['display_name'] ?? $account['username'] ?? 'Unknown') ?></span>
                        <span class="username">@<?= e($account['username'] ?? 'unknown') ?></span>
                    </a>
                    <time class="status-time" datetime="<?= e($status['created_at'] ?? '') ?>">
                        <?= date('F j, Y \a\t g:i A', strtotime($status['created_at'] ?? 'now')) ?>
                    </time>
                </div>
            </header>
            
            <div class="status-content">
                <?php if (!empty($status['content_warning'])): ?>
                    <div class="content-warning">
                        <p><?= e($status['content_warning']) ?></p>
                        <button type="button" class="cw-toggle" aria-expanded="false">
                            Show more
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="status-text<?= !empty($status['content_warning']) ? ' hidden' : '' ?>">
                    <?= $status['content'] ?? '' ?>
                </div>
                
                <?php if (!empty($status['media_attachments'])): ?>
                    <div class="status-media media-count-<?= count($status['media_attachments']) ?>">
                        <?php foreach ($status['media_attachments'] as $media): ?>
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
                                    <figcaption class="media-description">
                                        <?= e($media['description']) ?>
                                    </figcaption>
                                <?php endif; ?>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($status['poll'])): ?>
                    <?php $poll = $status['poll']; ?>
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
                    </div>
                <?php endif; ?>
            </div>
            
            <footer class="status-footer">
                <div class="status-stats">
                    <span class="stat">
                        <strong><?= e(number_format($status['replies_count'] ?? 0)) ?></strong> replies
                    </span>
                    <span class="stat">
                        <strong><?= e(number_format($status['boosts_count'] ?? 0)) ?></strong> boosts
                    </span>
                    <span class="stat">
                        <strong><?= e(number_format($status['favourites_count'] ?? 0)) ?></strong> favourites
                    </span>
                </div>
                
                <nav class="status-interactions" aria-label="Status interactions">
                    <?php if (isset($currentAccount) && $currentAccount): ?>
                        <a href="<?= url('/reply/' . e($status['id'] ?? '')) ?>" 
                           class="interaction-btn reply-btn"
                           aria-label="Reply"
                           title="Reply">
                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                <path d="M18 10c0 4.4-3.6 8-8 8H4l2-2H10c3.3 0 6-2.7 6-6s-2.7-6-6-6H4l2-2H10c4.4 0 8 3.6 8 8z" fill="none" stroke="currentColor" stroke-width="2"/>
                                <path d="M2 10l6-4v8L2 10z" fill="currentColor"/>
                            </svg>
                        </a>
                        
                        <button type="button" 
                                class="interaction-btn boost-btn<?= ($status['boosted'] ?? false) ? ' active' : '' ?>"
                                aria-label="Boost"
                                aria-pressed="<?= ($status['boosted'] ?? false) ? 'true' : 'false' ?>"
                                title="Boost"
                                data-action="boost"
                                data-status-id="<?= e($status['id'] ?? '') ?>"
                                data-csrf="<?= e($csrf ?? '') ?>">
                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                <path d="M4 4l8 8M12 4v8h-8" fill="none" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </button>
                        
                        <button type="button" 
                                class="interaction-btn favourite-btn<?= ($status['favourited'] ?? false) ? ' active' : '' ?>"
                                aria-label="Favourite"
                                aria-pressed="<?= ($status['favourited'] ?? false) ? 'true' : 'false' ?>"
                                title="Favourite"
                                data-action="favourite"
                                data-status-id="<?= e($status['id'] ?? '') ?>"
                                data-csrf="<?= e($csrf ?? '') ?>">
                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                <path d="M10 18l-1.45-1.32C4.4 12.36 2 10.28 2 7.5 2 5.42 3.42 4 5.5 4c1.74 0 3.41 1.01 4.5 2.09C11.09 5.01 12.76 4 14.5 4 16.58 4 18 5.42 18 7.5c0 2.78-2.4 4.86-6.55 8.18L10 18z" fill="none" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </button>
                        
                        <button type="button" 
                                class="interaction-btn bookmark-btn<?= ($status['bookmarked'] ?? false) ? ' active' : '' ?>"
                                aria-label="Bookmark"
                                aria-pressed="<?= ($status['bookmarked'] ?? false) ? 'true' : 'false' ?>"
                                title="Bookmark"
                                data-action="bookmark"
                                data-status-id="<?= e($status['id'] ?? '') ?>"
                                data-csrf="<?= e($csrf ?? '') ?>">
                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                                <path d="M16 18l-6-5-6 5V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v14z" fill="none" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </button>
                    <?php endif; ?>
                    
                    <button type="button" 
                            class="interaction-btn share-btn"
                            aria-label="Share"
                            title="Share"
                            data-action="share"
                            data-status-id="<?= e($status['id'] ?? '') ?>">
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
        </article>
        
        <?php if (isset($currentAccount) && $currentAccount): ?>
            <section class="reply-form-section" aria-label="Reply to status">
                <form action="<?= url('/api/v1/statuses') ?>" 
                      method="post" 
                      class="reply-form"
                      enctype="multipart/form-data"
                      data-csrf="<?= e($csrf ?? '') ?>">
                    <input type="hidden" name="in_reply_to_id" value="<?= e($status['id'] ?? '') ?>">
                    
                    <div class="reply-author">
                        <img 
                            src="<?= e($currentAccount['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
                            alt="" 
                            width="40" 
                            height="40"
                            class="reply-avatar"
                        >
                    </div>
                    
                    <div class="reply-content">
                        <textarea 
                            name="status" 
                            class="reply-textarea"
                            placeholder="Write a reply..."
                            aria-label="Reply content"
                            rows="3"
                            maxlength="<?= e($maxChars ?? 500) ?>"
                            required
                        ></textarea>
                        
                        <div class="reply-actions">
                            <div class="reply-options">
                                <label for="reply-visibility" class="visually-hidden">Reply visibility</label>
                                <select name="visibility" id="reply-visibility" class="reply-select">
                                    <option value="public">Public</option>
                                    <option value="unlisted">Unlisted</option>
                                    <option value="private" selected>Followers only</option>
                                    <option value="direct">Direct</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-small">
                                Reply
                            </button>
                        </div>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </section>
    
    <?php if (!empty($context['descendants'])): ?>
        <section class="thread-descendants" aria-label="Replies">
            <?php foreach ($context['descendants'] as $descendant): ?>
                <?php 
                $status = $descendant;
                include __DIR__ . '/../../partials/status.php'; 
                ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</article>