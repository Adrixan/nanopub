<?php
/**
 * @var array $errors Validation errors
 * @var array $input Previous input values
 */
?>

<section class="auth-section">
    <div class="auth-container">
        <header class="auth-header">
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Join <?= e($instance['title'] ?? 'NanoPub') ?> today</p>
        </header>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error" role="alert">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                    <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                    <line x1="10" y1="6" x2="10" y2="10" stroke="currentColor" stroke-width="2"/>
                    <line x1="10" y1="14" x2="10.01" y2="14" stroke="currentColor" stroke-width="2"/>
                </svg>
                <span><?= e($errors['general']) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($instance['approval_required'] ?? false): ?>
            <div class="alert alert-info" role="alert">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 20 20">
                    <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                    <line x1="10" y1="6" x2="10" y2="10" stroke="currentColor" stroke-width="2"/>
                    <line x1="10" y1="14" x2="10.01" y2="14" stroke="currentColor" stroke-width="2"/>
                </svg>
                <span>Registration requires approval. Your account will be reviewed by an administrator.</span>
            </div>
        <?php endif; ?>
        
        <form action="<?= url('/register') ?>" method="post" class="auth-form">
            <input type="hidden" name="csrf" value="<?= e($csrf ?? '') ?>">
            
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-prefix">@</span>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input<?= isset($errors['username']) ? ' is-invalid' : '' ?>"
                        value="<?= e($input['username'] ?? '') ?>"
                        placeholder="username"
                        required
                        pattern="^[a-zA-Z0-9_]+$"
                        minlength="3"
                        maxlength="30"
                        autocomplete="username"
                        aria-describedby="username-help username-error"
                    >
                </div>
                <small id="username-help" class="form-help">
                    Only letters, numbers, and underscores. 3-30 characters.
                </small>
                <?php if (isset($errors['username'])): ?>
                    <p id="username-error" class="form-error"><?= e($errors['username']) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="display_name" class="form-label">Display Name (optional)</label>
                <input 
                    type="text" 
                    id="display_name" 
                    name="display_name" 
                    class="form-input"
                    value="<?= e($input['display_name'] ?? '') ?>"
                    placeholder="Your Name"
                    maxlength="50"
                    autocomplete="name"
                >
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                    value="<?= e($input['email'] ?? '') ?>"
                    placeholder="you@example.com"
                    required
                    autocomplete="email"
                    aria-describedby="email-error"
                >
                <?php if (isset($errors['email'])): ?>
                    <p id="email-error" class="form-error"><?= e($errors['email']) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                    placeholder="Create a strong password"
                    required
                    minlength="8"
                    autocomplete="new-password"
                    aria-describedby="password-help password-error"
                >
                <small id="password-help" class="form-help">
                    At least 8 characters. Include uppercase, lowercase, numbers, and symbols.
                </small>
                <?php if (isset($errors['password'])): ?>
                    <p id="password-error" class="form-error"><?= e($errors['password']) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="password_confirm" class="form-label">Confirm Password</label>
                <input 
                    type="password" 
                    id="password_confirm" 
                    name="password_confirm" 
                    class="form-input<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                    placeholder="Confirm your password"
                    required
                    autocomplete="new-password"
                    aria-describedby="password_confirm-error"
                >
                <?php if (isset($errors['password_confirm'])): ?>
                    <p id="password_confirm-error" class="form-error"><?= e($errors['password_confirm']) ?></p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($instance['rules'])): ?>
                <div class="form-group">
                    <fieldset>
                        <legend class="form-label">Instance Rules</legend>
                        <p class="form-help">By creating an account, you agree to follow these rules:</p>
                        <ol class="rules-list">
                            <?php foreach ($instance['rules'] as $rule): ?>
                                <li><?= e($rule['text'] ?? $rule) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </fieldset>
                </div>
            <?php endif; ?>
            
            <div class="form-group form-checkbox">
                <input 
                    type="checkbox" 
                    id="agreement" 
                    name="agreement" 
                    value="1"
                    required
                >
                <label for="agreement">
                    I agree to the <a href="<?= url('/terms') ?>">Terms of Service</a> 
                    and <a href="<?= url('/privacy') ?>">Privacy Policy</a>
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">
                    <?= ($instance['approval_required'] ?? false) ? 'Submit Request' : 'Create Account' ?>
                </button>
            </div>
        </form>
        
        <footer class="auth-footer">
            <p>
                Already have an account? 
                <a href="<?= url('/login') ?>">Sign in</a>
            </p>
        </footer>
    </div>
</section>