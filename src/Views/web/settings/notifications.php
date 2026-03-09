<?php
/**
 * @var array $account Current account
 */
?>

<section class="settings-section">
    <h1 class="page-title">Notification Settings</h1>
    
    <nav class="settings-nav" aria-label="Settings navigation">
        <a href="<?= url('/settings') ?>" class="nav-link">Profile</a>
        <a href="<?= url('/settings/account') ?>" class="nav-link">Account</a>
        <a href="<?= url('/settings/privacy') ?>" class="nav-link">Privacy</a>
        <a href="<?= url('/settings/notifications') ?>" class="nav-link active" aria-current="page">Notifications</a>
        <a href="<?= url('/settings/export') ?>" class="nav-link">Export</a>
    </nav>
    
    <div class="settings-panels">
        <section class="settings-panel active">
            <form action="<?= url('/settings/notifications') ?>" 
                  method="post" 
                  class="settings-form notifications-form"
                  data-csrf="<?= e($csrf ?? '') ?>">
                
                <div class="form-section">
                    <h3 class="form-section-title">Email Notifications</h3>
                    
                    <div class="form-group">
                        <label for="notify_mentions" class="form-label">
                            <input type="checkbox" 
                                   id="notify_mentions" 
                                   name="notify_mentions" 
                                   class="form-checkbox"
                                   <?= ($account['notify_mentions'] ?? true) ? 'checked' : '' ?>>
                            Someone mentions you
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="notify_follows" class="form-label">
                            <input type="checkbox" 
                                   id="notify_follows" 
                                   name="notify_follows" 
                                   class="form-checkbox"
                                   <?= ($account['notify_follows'] ?? true) ? 'checked' : '' ?>>
                            Someone follows you
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="notify_favourites" class="form-label">
                            <input type="checkbox" 
                                   id="notify_favourites" 
                                   name="notify_favourites" 
                                   class="form-checkbox"
                                   <?= ($account['notify_favourites'] ?? true) ? 'checked' : '' ?>>
                            Someone favourites your post
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="notify_reblogs" class="form-label">
                            <input type="checkbox" 
                                   id="notify_reblogs" 
                                   name="notify_reblogs" 
                                   class="form-checkbox"
                                   <?= ($account['notify_reblogs'] ?? true) ? 'checked' : '' ?>>
                            Someone boosts your post
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Notification Settings</button>
                </div>
            </form>
        </section>
    </div>
</section>
