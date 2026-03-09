<?php
/**
 * @var string|null $message Error message
 */
$message = $message ?? 'The page you are looking for does not exist.';
?>

<article class="error-page error-404">
    <div class="error-content">
        <div class="error-icon" aria-hidden="true">
            <svg width="120" height="120" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="50" fill="none" stroke="currentColor" stroke-width="4"/>
                <text x="60" y="70" text-anchor="middle" font-size="36" font-weight="bold" fill="currentColor">404</text>
            </svg>
        </div>
        
        <h1 class="error-title">Page Not Found</h1>
        
        <p class="error-message"><?= e($message) ?></p>
        
        <div class="error-actions">
            <a href="<?= url('/') ?>" class="btn btn-primary">
                Go Home
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                Go Back
            </a>
        </div>
        
        <div class="error-suggestions">
            <h2>Looking for something?</h2>
            <ul>
                <li>
                    <a href="<?= url('/') ?>">Home Timeline</a>
                </li>
                <li>
                    <a href="<?= url('/explore') ?>">Explore</a>
                </li>
                <li>
                    <a href="<?= url('/search') ?>">Search</a>
                </li>
            </ul>
        </div>
    </div>
</article>