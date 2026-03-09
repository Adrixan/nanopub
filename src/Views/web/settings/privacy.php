<?php
/**
 * @var array $account Current account
 */
?>

<section class="settings-section">
    <h1 class="page-title">Privacy Settings</h1>
    
    <nav class="settings-nav" aria-label="Settings navigation">
        <a href="<?= url('/settings') ?>" class="nav-link">Profile</a>
        <a href="<?= url('/settings/account') ?>" class="nav-link">Account</a>
        <a href="<?= url('/settings/privacy') ?>" class="nav-link active" aria-current="page">Privacy</a>
        <a href="<?= url('/settings/notifications') ?>" class="nav-link">Notifications</a>
        <a href="<?= url('/settings/export') ?>" class="nav-link">Export</a>
    </nav>
    
    <div class="settings-panels">
        <section class="settings-panel active">
            <form action="<?= url('/settings/privacy') ?>" 
                  method="post" 
                  class="settings-form privacy-form"
                  data-csrf="<?= e($csrf ?? '') ?>">
                
                <div class="form-section">
                    <h3 class="form-section-title">Follow Requests</h3>
                    
                    <div class="form-group">
                        <label for="locked" class="form-label">
                            <input type="checkbox" 
                                   id="locked" 
                                   name="locked" 
                                   class="form-checkbox"
                                   <?= ($account['locked'] ?? $account['is_locked'] ?? false) ? 'checked' : '' ?>>
                            Require follow requests
                        </label>
                        <span class="form-hint">When enabled, you must manually approve new followers</span>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-section-title">Discovery</h3>
                    
                    <div class="form-group">
                        <label for="discoverable" class="form-label">
                            <input type="checkbox" 
                                   id="discoverable" 
                                   name="discoverable" 
                                   class="form-checkbox"
                                   <?= ($account['discoverable'] ?? true) ? 'checked' : '' ?>>
                            Allow account discovery
                        </label>
                        <span class="form-hint">Allow your account to appear in search results and suggestions</span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Privacy Settings</button>
                </div>
            </form>
        </section>
    </div>
</section>
