<?php
/**
 * @var array $instance Instance configuration
 */
?>

<section class="admin-section">
    <header class="admin-header">
        <h1 class="page-title">Instance Settings</h1>
        <nav class="admin-nav" aria-label="Admin navigation">
            <a href="<?= url('/admin') ?>" class="nav-link">Dashboard</a>
            <a href="<?= url('/admin/reports') ?>" class="nav-link">Reports</a>
            <a href="<?= url('/admin/domain_blocks') ?>" class="nav-link">Domain Blocks</a>
            <a href="<?= url('/admin/settings') ?>" class="nav-link active" aria-current="page">Settings</a>
        </nav>
    </header>
    
    <form action="<?= url('/api/v1/admin/settings') ?>" 
          method="post" 
          class="settings-form"
          enctype="multipart/form-data"
          data-csrf="<?= e($csrf ?? '') ?>">
        
        <section class="settings-group" aria-labelledby="basic-settings">
            <h2 id="basic-settings" class="settings-group-title">Basic Information</h2>
            
            <div class="form-group">
                <label for="instance_name" class="form-label">Instance Name</label>
                <input type="text" 
                       id="instance_name" 
                       name="instance_name" 
                       class="form-input"
                       value="<?= e($instance['name'] ?? '') ?>"
                       maxlength="100"
                       required>
            </div>
            
            <div class="form-group">
                <label for="instance_description" class="form-label">Short Description</label>
                <input type="text" 
                       id="instance_description" 
                       name="instance_description" 
                       class="form-input"
                       value="<?= e($instance['description'] ?? '') ?>"
                       maxlength="200"
                       placeholder="A brief description shown in instance listings">
            </div>
            
            <div class="form-group">
                <label for="instance_extended_description" class="form-label">Extended Description</label>
                <textarea id="instance_extended_description" 
                          name="instance_extended_description" 
                          class="form-textarea"
                          rows="6"
                          placeholder="Detailed description shown on the about page"><?= e($instance['extended_description'] ?? '') ?></textarea>
                <span class="form-hint">Supports HTML. Shown on the about page.</span>
            </div>
            
            <div class="form-group">
                <label for="instance_thumbnail" class="form-label">Instance Thumbnail</label>
                <div class="thumbnail-preview">
                    <img 
                        src="<?= e($instance['thumbnail_url'] ?? url('/assets/images/default-thumbnail.png')) ?>" 
                        alt="Instance thumbnail"
                        id="thumbnail-preview-image"
                    >
                </div>
                <input type="file" 
                       id="instance_thumbnail" 
                       name="instance_thumbnail" 
                       class="form-file"
                       accept="image/jpeg,image/png,image/gif,image/webp"
                       data-preview="thumbnail-preview-image">
                <span class="form-hint">JPG, PNG, GIF, or WebP. Max 2MB. Recommended: 1200x630px</span>
            </div>
        </section>
        
        <section class="settings-group" aria-labelledby="contact-settings">
            <h2 id="contact-settings" class="settings-group-title">Contact Information</h2>
            
            <div class="form-group">
                <label for="contact_email" class="form-label">Contact Email</label>
                <input type="email" 
                       id="contact_email" 
                       name="contact_email" 
                       class="form-input"
                       value="<?= e($instance['contact_email'] ?? '') ?>"
                       placeholder="admin@example.com">
            </div>
            
            <div class="form-group">
                <label for="contact_account" class="form-label">Contact Account</label>
                <input type="text" 
                       id="contact_account" 
                       name="contact_account" 
                       class="form-input"
                       value="<?= e($instance['contact_account'] ?? '') ?>"
                       placeholder="@admin">
                <span class="form-hint">Local account username for contact (e.g., @admin)</span>
            </div>
        </section>
        
        <section class="settings-group" aria-labelledby="registration-settings">
            <h2 id="registration-settings" class="settings-group-title">Registration Settings</h2>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" 
                           name="registrations_open" 
                           class="form-checkbox"
                           <?= ($instance['registrations_open'] ?? true) ? 'checked' : '' ?>>
                    Open for new registrations
                </label>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" 
                           name="registrations_require_approval" 
                           class="form-checkbox"
                           <?= ($instance['registrations_require_approval'] ?? false) ? 'checked' : '' ?>>
                    Require approval for new registrations
                </label>
                <span class="form-hint">Admins must approve new accounts before they can post</span>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" 
                           name="registrations_require_invite" 
                           class="form-checkbox"
                           <?= ($instance['registrations_require_invite'] ?? false) ? 'checked' : '' ?>>
                    Require invite link for registration
                </label>
            </div>
            
            <div class="form-group">
                <label for="registrations_reason_required" class="form-label">Reason Required</label>
                <select id="registrations_reason_required" 
                        name="registrations_reason_required" 
                        class="form-select">
                    <option value="none" <?= ($instance['registrations_reason_required'] ?? 'none') === 'none' ? 'selected' : '' ?>>
                        Not required
                    </option>
                    <option value="optional" <?= ($instance['registrations_reason_required'] ?? '') === 'optional' ? 'selected' : '' ?>>
                        Optional
                    </option>
                    <option value="required" <?= ($instance['registrations_reason_required'] ?? '') === 'required' ? 'selected' : '' ?>>
                        Required
                    </option>
                </select>
                <span class="form-hint">Ask users why they want to join</span>
            </div>
        </section>
        
        <section class="settings-group" aria-labelledby="content-settings">
            <h2 id="content-settings" class="settings-group-title">Content Settings</h2>
            
            <div class="form-group">
                <label for="max_toot_chars" class="form-label">Maximum Post Length</label>
                <input type="number" 
                       id="max_toot_chars" 
                       name="max_toot_chars" 
                       class="form-input"
                       value="<?= e($instance['max_toot_chars'] ?? 500) ?>"
                       min="100"
                       max="10000">
                <span class="form-hint">Maximum characters per post (default: 500)</span>
            </div>
            
            <div class="form-group">
                <label for="max_media_attachments" class="form-label">Maximum Media Attachments</label>
                <input type="number" 
                       id="max_media_attachments" 
                       name="max_media_attachments" 
                       class="form-input"
                       value="<?= e($instance['max_media_attachments'] ?? 4) ?>"
                       min="1"
                       max="8">
            </div>
            
            <div class="form-group">
                <label for="max_image_size" class="form-label">Maximum Image Size (MB)</label>
                <input type="number" 
                       id="max_image_size" 
                       name="max_image_size" 
                       class="form-input"
                       value="<?= e($instance['max_image_size'] ?? 8) ?>"
                       min="1"
                       max="50">
            </div>
            
            <div class="form-group">
                <label for="max_video_size" class="form-label">Maximum Video Size (MB)</label>
                <input type="number" 
                       id="max_video_size" 
                       name="max_video_size" 
                       class="form-input"
                       value="<?= e($instance['max_video_size'] ?? 40) ?>"
                       min="1"
                       max="200">
            </div>
        </section>
        
        <section class="settings-group" aria-labelledby="federation-settings">
            <h2 id="federation-settings" class="settings-group-title">Federation Settings</h2>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" 
                           name="federation_enabled" 
                           class="form-checkbox"
                           <?= ($instance['federation_enabled'] ?? true) ? 'checked' : '' ?>>
                    Enable federation with other instances
                </label>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" 
                           name="federation_allow_private" 
                           class="form-checkbox"
                           <?= ($instance['federation_allow_private'] ?? true) ? 'checked' : '' ?>>
                    Allow federation of private posts
                </label>
            </div>
            
            <div class="form-group">
                <label for="federation_mode" class="form-label">Federation Mode</label>
                <select id="federation_mode" 
                        name="federation_mode" 
                        class="form-select">
                    <option value="open" <?= ($instance['federation_mode'] ?? 'open') === 'open' ? 'selected' : '' ?>>
                        Open - Federate with all instances
                    </option>
                    <option value="allowlist" <?= ($instance['federation_mode'] ?? '') === 'allowlist' ? 'selected' : '' ?>>
                        Allowlist - Only federate with allowed instances
                    </option>
                    <option value="blocklist" <?= ($instance['federation_mode'] ?? '') === 'blocklist' ? 'selected' : '' ?>>
                        Blocklist - Federate with all except blocked instances
                    </option>
                </select>
            </div>
        </section>
        
        <section class="settings-group" aria-labelledby="rules-settings">
            <h2 id="rules-settings" class="settings-group-title">Instance Rules</h2>
            <p class="settings-group-description">Define rules that users must agree to when registering.</p>
            
            <div class="rules-list" id="rules-list">
                <?php 
                $rules = $instance['rules'] ?? [];
                foreach ($rules as $index => $rule): 
                ?>
                    <div class="rule-item" data-rule-index="<?= e($index) ?>">
                        <span class="rule-number"><?= e((string) ($index + 1)) ?></span>
                        <input type="text" 
                               name="rules[<?= e($index) ?>]" 
                               class="form-input rule-input"
                               value="<?= e($rule['text'] ?? $rule) ?>"
                               placeholder="Enter rule text">
                        <button type="button" 
                                class="btn btn-secondary btn-small remove-rule-btn"
                                data-action="remove-rule"
                                aria-label="Remove rule">
                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16">
                                <path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" 
                    class="btn btn-secondary add-rule-btn"
                    data-action="add-rule">
                Add Rule
            </button>
            
            <template id="rule-template">
                <div class="rule-item" data-rule-index="__INDEX__">
                    <span class="rule-number">__NUMBER__</span>
                    <input type="text" 
                           name="rules[__INDEX__]" 
                           class="form-input rule-input"
                           placeholder="Enter rule text">
                    <button type="button" 
                            class="btn btn-secondary btn-small remove-rule-btn"
                            data-action="remove-rule"
                            aria-label="Remove rule">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16">
                            <path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </button>
                </div>
            </template>
        </section>
        
        <section class="settings-group" aria-labelledby="feature-settings">
            <h2 id="feature-settings" class="settings-group-title">Feature Flags</h2>
            
            <div class="feature-flags-grid">
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="feature_timeline_preview" 
                               class="form-checkbox"
                               <?= ($instance['feature_timeline_preview'] ?? true) ? 'checked' : '' ?>>
                        Timeline Preview
                    </label>
                    <span class="form-hint">Show public timeline to anonymous visitors</span>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="feature_profile_directory" 
                               class="form-checkbox"
                               <?= ($instance['feature_profile_directory'] ?? true) ? 'checked' : '' ?>>
                        Profile Directory
                    </label>
                    <span class="form-hint">Allow browsing user profiles</span>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="feature_trends" 
                               class="form-checkbox"
                               <?= ($instance['feature_trends'] ?? true) ? 'checked' : '' ?>>
                        Trending Hashtags
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="feature_suggestions" 
                               class="form-checkbox"
                               <?= ($instance['feature_suggestions'] ?? true) ? 'checked' : '' ?>>
                        User Suggestions
                    </label>
                    <span class="form-hint">Show suggested accounts to follow</span>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="feature_bookmarks" 
                               class="form-checkbox"
                               <?= ($instance['feature_bookmarks'] ?? true) ? 'checked' : '' ?>>
                        Bookmarks
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="feature_polls" 
                               class="form-checkbox"
                               <?= ($instance['feature_polls'] ?? true) ? 'checked' : '' ?>>
                        Polls
                    </label>
                </div>
            </div>
        </section>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Settings</button>
            <button type="button" class="btn btn-secondary" data-action="reset-form">Reset</button>
        </div>
    </form>
</section>