<?php
/**
 * @var array $notification Notification data
 */
$type = $notification['type'] ?? 'unknown';
$account = $notification['account'] ?? [];
$status = $notification['status'] ?? null;
$createdAt = $notification['created_at'] ?? '';
?>

<article class="notification notification-<?= e($type) ?>" 
         data-notification-id="<?= e($notification['id'] ?? '') ?>"
         role="article"
         aria-label="Notification">
    
    <div class="notification-icon">
        <?php switch ($type):
            case 'follow': ?>
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2"/>
                    <circle cx="8.5" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/>
                    <line x1="20" y1="8" x2="20" y2="14" stroke="currentColor" stroke-width="2"/>
                    <line x1="23" y1="11" x2="17" y2="11" stroke="currentColor" stroke-width="2"/>
                </svg>
                <?php break;
            case 'favourite': ?>
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41 1.01 4.5 2.09C13.09 4.01 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="currentColor"/>
                </svg>
                <?php break;
            case 'reblog': ?>
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M7 7l10 10M17 7v10H7" fill="none" stroke="currentColor" stroke-width="2"/>
                </svg>
                <?php break;
            case 'mention': ?>
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" fill="none" stroke="currentColor" stroke-width="2"/>
                </svg>
                <?php break;
            case 'poll': ?>
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
                    <polyline points="12 6 12 12 16 14" fill="none" stroke="currentColor" stroke-width="2"/>
                </svg>
                <?php break;
            case 'status': ?>
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
                    <line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2"/>
                    <line x1="12" y1="16" x2="12.01" y2="16" stroke="currentColor" stroke-width="2"/>
                </svg>
                <?php break;
            case 'update': ?>
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" fill="none" stroke="currentColor" stroke-width="2"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" fill="none" stroke="currentColor" stroke-width="2"/>
                </svg>
                <?php break;
            default: ?>
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
                </svg>
        <?php endswitch; ?>
    </div>
    
    <div class="notification-content">
        <header class="notification-header">
            <a href="<?= url('/@' . e($account['username'] ?? 'unknown')) ?>" class="notification-account">
                <img 
                    src="<?= e($account['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
                    alt="" 
                    width="32" 
                    height="32"
                    loading="lazy"
                >
                <span class="account-name">
                    <strong><?= e($account['display_name'] ?? $account['username'] ?? 'Unknown') ?></strong>
                </span>
            </a>
            
            <time class="notification-time" datetime="<?= e($createdAt) ?>">
                <?= time_ago($createdAt) ?>
            </time>
        </header>
        
        <div class="notification-message">
            <?php switch ($type):
                case 'follow': ?>
                    <p>followed you</p>
                    <?php break;
                case 'favourite': ?>
                    <p>favourited your status</p>
                    <?php break;
                case 'reblog': ?>
                    <p>boosted your status</p>
                    <?php break;
                case 'mention': ?>
                    <p>mentioned you</p>
                    <?php break;
                case 'poll': ?>
                    <p>A poll you voted in has ended</p>
                    <?php break;
                case 'status': ?>
                    <p>posted a status</p>
                    <?php break;
                case 'update': ?>
                    <p>edited a status</p>
                    <?php break;
                default: ?>
                    <p>sent a notification</p>
            <?php endswitch; ?>
        </div>
        
        <?php if ($status): ?>
            <div class="notification-status">
                <?php 
                $status = $status;
                $currentAccount = $currentAccount ?? null;
                include __DIR__ . '/status.php'; 
                ?>
            </div>
        <?php endif; ?>
        
        <?php if ($type === 'follow' && isset($currentAccount) && $currentAccount): ?>
            <div class="notification-actions">
                <?php if ($relationship['following'] ?? false): ?>
                    <button type="button" 
                            class="btn btn-secondary btn-small"
                            data-action="unfollow"
                            data-account-id="<?= e($account['id'] ?? '') ?>"
                            data-csrf="<?= e($csrf ?? '') ?>">
                        Following
                    </button>
                <?php elseif ($relationship['requested'] ?? false): ?>
                    <button type="button" 
                            class="btn btn-secondary btn-small"
                            data-action="cancel-follow-request"
                            data-account-id="<?= e($account['id'] ?? '') ?>"
                            data-csrf="<?= e($csrf ?? '') ?>">
                        Requested
                    </button>
                <?php else: ?>
                    <button type="button" 
                            class="btn btn-primary btn-small"
                            data-action="follow"
                            data-account-id="<?= e($account['id'] ?? '') ?>"
                            data-csrf="<?= e($csrf ?? '') ?>">
                        Follow back
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</article>