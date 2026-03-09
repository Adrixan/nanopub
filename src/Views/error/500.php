<?php
/**
 * @var string|null $message Error message
 */
$message = $message ?? 'An unexpected error occurred. Please try again later.';
?>

<article class="error-page error-500">
    <div class="error-content">
        <div class="error-icon" aria-hidden="true">
            <svg width="120" height="120" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="50" fill="none" stroke="currentColor" stroke-width="4"/>
                <text x="60" y="70" text-anchor="middle" font-size="36" font-weight="bold" fill="currentColor">500</text>
            </svg>
        </div>
        
        <h1 class="error-title">Internal Server Error</h1>
        
        <p class="error-message"><?= e($message) ?></p>
        
        <div class="error-actions">
            <a href="<?= url('/') ?>" class="btn btn-primary">
                Go Home
            </a>
            <button type="button" class="btn btn-secondary" onclick="location.reload()">
                Try Again
            </button>
        </div>
        
        <div class="error-info">
            <p>If this problem persists, please contact the administrator.</p>
            <?php if (isset($requestId)): ?>
                <p class="request-id">Request ID: <code><?= e($requestId) ?></code></p>
            <?php endif; ?>
        </div>
    </div>
</article>