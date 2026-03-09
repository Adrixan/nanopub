<?php
/**
 * @var array $account Current account
 */
?>

<section class="settings-section">
    <h1 class="page-title">Settings</h1>
    
    <div class="settings-tabs" role="tablist" aria-label="Settings sections">
        <button type="button" 
                role="tab" 
                class="tab active" 
                id="profile-tab" 
                aria-selected="true" 
                aria-controls="profile-panel">
            Profile
        </button>
        <button type="button" 
                role="tab" 
                class="tab" 
                id="password-tab" 
                aria-selected="false" 
                aria-controls="password-panel">
            Password
        </button>
        <button type="button" 
                role="tab" 
                class="tab" 
                id="preferences-tab" 
                aria-selected="false" 
                aria-controls="preferences-panel">
            Preferences
        </button>
        <button type="button" 
                role="tab" 
                class="tab" 
                id="export-tab" 
                aria-selected="false" 
                aria-controls="export-panel">
            Export
        </button>
    </div>
    
    <div class="settings-panels">
        <!-- Profile Panel -->
        <section role="tabpanel" 
                 id="profile-panel" 
                 class="settings-panel active"
                 aria-labelledby="profile-tab">
            <h2 class="visually-hidden">Profile Settings</h2>
            
            <form action="<?= url('/api/v1/accounts/update_credentials') ?>" 
                  method="post" 
                  class="settings-form profile-form"
                  enctype="multipart/form-data"
                  data-csrf="<?= e($csrf ?? '') ?>">
                
                <div class="form-section">
                    <h3 class="form-section-title">Display Name & Bio</h3>
                    
                    <div class="form-group">
                        <label for="display_name" class="form-label">Display Name</label>
                        <input type="text" 
                               id="display_name" 
                               name="display_name" 
                               class="form-input"
                               value="<?= e($account['display_name'] ?? '') ?>"
                               maxlength="30"
                               autocomplete="name">
                        <span class="form-hint">30 characters max</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="note" class="form-label">Bio</label>
                        <textarea id="note" 
                                  name="note" 
                                  class="form-textarea"
                                  rows="4"
                                  maxlength="500"
                                  placeholder="Tell us about yourself..."><?= e($account['note'] ?? '') ?></textarea>
                        <span class="form-hint">500 characters max</span>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-section-title">Profile Images</h3>
                    
                    <div class="form-group avatar-upload">
                        <label class="form-label">Avatar</label>
                        <div class="avatar-preview">
                            <img 
                                src="<?= e($account['avatar_url'] ?? url('/assets/images/default-avatar.png')) ?>" 
                                alt="Current avatar"
                                width="80"
                                height="80"
                                id="avatar-preview-image"
                            >
                        </div>
                        <input type="file" 
                               id="avatar" 
                               name="avatar" 
                               class="form-file"
                               accept="image/jpeg,image/png,image/gif,image/webp"
                               data-preview="avatar-preview-image">
                        <span class="form-hint">JPG, PNG, GIF, or WebP. Max 2MB. Recommended: 400x400px</span>
                    </div>
                    
                    <div class="form-group header-upload">
                        <label class="form-label">Header</label>
                        <div class="header-preview">
                            <img 
                                src="<?= e($account['header_url'] ?? url('/assets/images/default-header.png')) ?>" 
                                alt="Current header"
                                id="header-preview-image"
                            >
                        </div>
                        <input type="file" 
                               id="header" 
                               name="header" 
                               class="form-file"
                               accept="image/jpeg,image/png,image/gif,image/webp"
                               data-preview="header-preview-image">
                        <span class="form-hint">JPG, PNG, GIF, or WebP. Max 4MB. Recommended: 1500x500px</span>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-section-title">Profile Fields</h3>
                    <p class="form-section-description">Add up to 4 custom fields to your profile.</p>
                    
                    <div class="profile-fields-list">
                        <?php 
                        $fields = $account['fields'] ?? [];
                        for ($i = 0; $i < 4; $i++): 
                            $field = $fields[$i] ?? ['name' => '', 'value' => ''];
                        ?>
                            <div class="field-row">
                                <div class="form-group">
                                    <label for="field_name_<?= $i ?>" class="form-label visually-hidden">Field <?= $i + 1 ?> Name</label>
                                    <input type="text" 
                                           id="field_name_<?= $i ?>" 
                                           name="fields[<?= $i ?>][name]" 
                                           class="form-input"
                                           value="<?= e($field['name'] ?? '') ?>"
                                           placeholder="Label"
                                           maxlength="255">
                                </div>
                                <div class="form-group">
                                    <label for="field_value_<?= $i ?>" class="form-label visually-hidden">Field <?= $i + 1 ?> Value</label>
                                    <input type="text" 
                                           id="field_value_<?= $i ?>" 
                                           name="fields[<?= $i ?>][value]" 
                                           class="form-input"
                                           value="<?= e($field['value'] ?? '') ?>"
                                           placeholder="Content"
                                           maxlength="255">
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </section>
        
        <!-- Password Panel -->
        <section role="tabpanel" 
                 id="password-panel" 
                 class="settings-panel"
                 aria-labelledby="password-tab"
                 hidden>
            <h2 class="visually-hidden">Password Settings</h2>
            
            <form action="<?= url('/settings/password') ?>" 
                  method="post" 
                  class="settings-form password-form"
                  data-csrf="<?= e($csrf ?? '') ?>">
                
                <div class="form-section">
                    <h3 class="form-section-title">Change Password</h3>
                    
                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" 
                               id="current_password" 
                               name="current_password" 
                               class="form-input"
                               autocomplete="current-password"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-input"
                               autocomplete="new-password"
                               minlength="8"
                               required>
                        <span class="form-hint">Minimum 8 characters</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-input"
                               autocomplete="new-password"
                               minlength="8"
                               required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </section>
        
        <!-- Preferences Panel -->
        <section role="tabpanel" 
                 id="preferences-panel" 
                 class="settings-panel"
                 aria-labelledby="preferences-tab"
                 hidden>
            <h2 class="visually-hidden">Preferences</h2>
            
            <form action="<?= url('/settings/preferences') ?>" 
                  method="post" 
                  class="settings-form preferences-form"
                  data-csrf="<?= e($csrf ?? '') ?>">
                
                <div class="form-section">
                    <h3 class="form-section-title">Posting Defaults</h3>
                    
                    <div class="form-group">
                        <label for="default_visibility" class="form-label">Default Post Visibility</label>
                        <select id="default_visibility" 
                                name="default_visibility" 
                                class="form-select">
                            <option value="public" <?= ($account['default_visibility'] ?? 'public') === 'public' ? 'selected' : '' ?>>
                                Public
                            </option>
                            <option value="unlisted" <?= ($account['default_visibility'] ?? '') === 'unlisted' ? 'selected' : '' ?>>
                                Unlisted
                            </option>
                            <option value="private" <?= ($account['default_visibility'] ?? '') === 'private' ? 'selected' : '' ?>>
                                Followers only
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="default_sensitive" class="form-label">
                            <input type="checkbox" 
                                   id="default_sensitive" 
                                   name="default_sensitive" 
                                   class="form-checkbox"
                                   <?= ($account['default_sensitive'] ?? false) ? 'checked' : '' ?>>
                            Mark media as sensitive by default
                        </label>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-section-title">Privacy Settings</h3>
                    
                    <div class="form-group">
                        <label for="locked" class="form-label">
                            <input type="checkbox" 
                                   id="locked" 
                                   name="locked" 
                                   class="form-checkbox"
                                   <?= ($account['locked'] ?? false) ? 'checked' : '' ?>>
                            Require follow requests
                        </label>
                        <span class="form-hint">When enabled, you must approve new followers</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="discoverable" class="form-label">
                            <input type="checkbox" 
                                   id="discoverable" 
                                   name="discoverable" 
                                   class="form-checkbox"
                                   <?= ($account['discoverable'] ?? true) ? 'checked' : '' ?>>
                            Allow account discovery
                        </label>
                        <span class="form-hint">Allow your account to be featured in suggestions</span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                </div>
            </form>
        </section>
        
        <!-- Export Panel -->
        <section role="tabpanel" 
                 id="export-panel" 
                 class="settings-panel"
                 aria-labelledby="export-tab"
                 hidden>
            <h2 class="visually-hidden">Export Data</h2>
            
            <div class="form-section">
                <h3 class="form-section-title">Export Your Data</h3>
                <p class="form-section-description">Download a copy of your data in standard formats.</p>
                
                <ul class="export-options">
                    <li class="export-option">
                        <span class="export-label">Follows</span>
                        <a href="<?= url('/settings/export/follows') ?>" 
                           class="btn btn-secondary btn-small"
                           download>
                            Download CSV
                        </a>
                    </li>
                    <li class="export-option">
                        <span class="export-label">Followers</span>
                        <a href="<?= url('/settings/export/followers') ?>" 
                           class="btn btn-secondary btn-small"
                           download>
                            Download CSV
                        </a>
                    </li>
                    <li class="export-option">
                        <span class="export-label">Bookmarks</span>
                        <a href="<?= url('/settings/export/bookmarks') ?>" 
                           class="btn btn-secondary btn-small"
                           download>
                            Download CSV
                        </a>
                    </li>
                    <li class="export-option">
                        <span class="export-label">Mutes</span>
                        <a href="<?= url('/settings/export/mutes') ?>" 
                           class="btn btn-secondary btn-small"
                           download>
                            Download CSV
                        </a>
                    </li>
                    <li class="export-option">
                        <span class="export-label">Blocks</span>
                        <a href="<?= url('/settings/export/blocks') ?>" 
                           class="btn btn-secondary btn-small"
                           download>
                            Download CSV
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="form-section danger-zone">
                <h3 class="form-section-title">Account Actions</h3>
                
                <div class="danger-action">
                    <div class="danger-action-info">
                        <strong>Deactivate Account</strong>
                        <p>Temporarily disable your account. You can reactivate by logging in.</p>
                    </div>
                    <button type="button" 
                            class="btn btn-secondary"
                            data-action="deactivate"
                            data-csrf="<?= e($csrf ?? '') ?>">
                        Deactivate
                    </button>
                </div>
                
                <div class="danger-action">
                    <div class="danger-action-info">
                        <strong>Delete Account</strong>
                        <p>Permanently delete your account and all data. This cannot be undone.</p>
                    </div>
                    <button type="button" 
                            class="btn btn-danger"
                            data-action="delete-account"
                            data-csrf="<?= e($csrf ?? '') ?>">
                        Delete Account
                    </button>
                </div>
            </div>
        </section>
    </div>
</section>