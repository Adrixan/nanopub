<?php
/**
 * @var array $account Current account
 * @var string|null $error Error message (optional)
 */
?>

<section class="settings-section">
    <h1 class="page-title">Account Settings</h1>
    
    <nav class="settings-nav" aria-label="Settings navigation">
        <a href="<?= url('/settings') ?>" class="nav-link">Profile</a>
        <a href="<?= url('/settings/account') ?>" class="nav-link active" aria-current="page">Account</a>
        <a href="<?= url('/settings/privacy') ?>" class="nav-link">Privacy</a>
        <a href="<?= url('/settings/notifications') ?>" class="nav-link">Notifications</a>
        <a href="<?= url('/settings/export') ?>" class="nav-link">Export</a>
    </nav>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-error" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>
    
    <div class="settings-panels">
        <section class="settings-panel active">
            <form action="<?= url('/settings/account') ?>" 
                  method="post" 
                  class="settings-form account-form"
                  data-csrf="<?= e($csrf ?? '') ?>">
                
                <div class="form-section">
                    <h3 class="form-section-title">Email Address</h3>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input"
                               value="<?= e($account['email'] ?? '') ?>"
                               autocomplete="email">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-section-title">Change Password</h3>
                    
                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" 
                               id="current_password" 
                               name="current_password" 
                               class="form-input"
                               autocomplete="current-password">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-input"
                               autocomplete="new-password"
                               minlength="8">
                        <span class="form-hint">Minimum 8 characters</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-input"
                               autocomplete="new-password"
                               minlength="8">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </section>
    </div>
</section>
