<?php
/**
 * @var string|null $error Error message
 * @var string $login Pre-filled login (username or email)
 */
?>

<section class="auth-section">
    <div class="auth-container">
        <header class="auth-header">
            <h1 class="auth-title">Sign In</h1>
            <p class="auth-subtitle">Welcome back to <?= e($instance['title'] ?? 'NanoPub') ?></p>
        </header>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                    <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                    <line x1="10" y1="6" x2="10" y2="10" stroke="currentColor" stroke-width="2"/>
                    <line x1="10" y1="14" x2="10.01" y2="14" stroke="currentColor" stroke-width="2"/>
                </svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>
        
        <form action="<?= url('/login') ?>" method="post" class="auth-form">
            <input type="hidden" name="csrf" value="<?= e($csrf ?? '') ?>">
            
            <div class="form-group">
                <label for="login" class="form-label">Username or email address</label>
                <input 
                    type="text" 
                    id="login" 
                    name="login" 
                    class="form-input"
                    value="<?= e($login ?? '') ?>" <?php // @phpstan-ignore nullCoalesce.variable ?>
                    placeholder="username or email@example.com"
                    required
                    autocomplete="username"
                    autofocus
                >
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">
                    Password
                    <a href="<?= url('/forgot-password') ?>" class="form-link">Forgot password?</a>
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >
            </div>
            
            <div class="form-group form-checkbox">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember">Remember me</label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">
                    Sign In
                </button>
            </div>
        </form>
        
        <footer class="auth-footer">
            <?php if ($instance['registrations'] ?? true): ?>
                <p>
                    Don't have an account? 
                    <a href="<?= url('/register') ?>">Create one</a>
                </p>
            <?php endif; ?>
        </footer>
    </div>
</section>