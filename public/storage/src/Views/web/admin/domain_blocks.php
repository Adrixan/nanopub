<?php
/**
 * @var iterable $blocks Domain blocks list
 */
?>

<section class="admin-section">
    <header class="admin-header">
        <h1 class="page-title">Domain Blocks</h1>
        <nav class="admin-nav" aria-label="Admin navigation">
            <a href="<?= url('/admin') ?>" class="nav-link">Dashboard</a>
            <a href="<?= url('/admin/reports') ?>" class="nav-link">Reports</a>
            <a href="<?= url('/admin/domain_blocks') ?>" class="nav-link active" aria-current="page">Domain Blocks</a>
            <a href="<?= url('/admin/settings') ?>" class="nav-link">Settings</a>
        </nav>
    </header>
    
    <section class="add-domain-block" aria-label="Add new domain block">
        <h2 class="section-title">Add Domain Block</h2>
        
        <form action="<?= url('/api/v1/admin/domain_blocks') ?>" 
              method="post" 
              class="domain-block-form"
              data-csrf="<?= e($csrf ?? '') ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="domain" class="form-label">Domain</label>
                    <input type="text" 
                           id="domain" 
                           name="domain" 
                           class="form-input"
                           placeholder="example.com"
                           pattern="^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]*(\.[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]*)+$"
                           required>
                    <span class="form-hint">Enter the domain to block (e.g., spam.example.com)</span>
                </div>
                
                <div class="form-group">
                    <label for="severity" class="form-label">Severity</label>
                    <select id="severity" name="severity" class="form-select">
                        <option value="silence">Silence</option>
                        <option value="suspend" selected>Suspend</option>
                        <option value="noop">Noop</option>
                    </select>
                    <span class="form-hint">
                        Silence: Hide from timeline. Suspend: Block all activities. Noop: No effect.
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="public_comment" class="form-label">Public Comment</label>
                <input type="text" 
                       id="public_comment" 
                       name="public_comment" 
                       class="form-input"
                       placeholder="Reason for block (visible to users)"
                       maxlength="200">
            </div>
            
            <div class="form-group">
                <label for="private_comment" class="form-label">Private Comment</label>
                <textarea id="private_comment" 
                          name="private_comment" 
                          class="form-textarea"
                          rows="2"
                          placeholder="Internal notes (only visible to admins)"
                          maxlength="500"></textarea>
            </div>
            
            <div class="form-options">
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="reject_media" class="form-checkbox">
                        Reject media files
                    </label>
                    <span class="form-hint">Remove and reject any media files from this domain</span>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="reject_reports" class="form-checkbox">
                        Reject reports
                    </label>
                    <span class="form-hint">Ignore reports coming from this domain</span>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="obfuscate" class="form-checkbox">
                        Obfuscate domain
                    </label>
                    <span class="form-hint">Display domain partially obfuscated in public list</span>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Block</button>
            </div>
        </form>
    </section>
    
    <section class="domain-blocks-list" aria-label="Blocked domains">
        <h2 class="section-title">Blocked Domains</h2>
        
        <?php if (empty($blocks) || count($blocks) === 0): ?>
            <div class="empty-state">
                <svg aria-hidden="true" width="48" height="48" viewBox="0 0 48 48">
                    <path d="M24 4C12.95 4 4 12.95 4 24s8.95 20 20 20 20-8.95 20-20S35.05 4 24 4zm-2 30h4v4h-4v-4zm0-24h4v20h-4V10z" fill="currentColor"/>
                </svg>
                <p>No domain blocks configured</p>
            </div>
        <?php else: ?>
            <table class="blocks-table" role="grid">
                <thead>
                    <tr>
                        <th scope="col">Domain</th>
                        <th scope="col">Severity</th>
                        <th scope="col">Options</th>
                        <th scope="col">Comment</th>
                        <th scope="col">Created</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blocks as $block): ?>
                        <tr data-block-id="<?= e($block['id'] ?? '') ?>">
                            <td class="domain-cell">
                                <span class="domain-name"><?= e($block['domain'] ?? '') ?></span>
                                <?php if ($block['obfuscate'] ?? false): ?>
                                    <span class="obfuscated-badge" title="Obfuscated">O</span>
                                <?php endif; ?>
                            </td>
                            <td class="severity-cell">
                                <span class="severity-badge <?= e($block['severity'] ?? 'suspend') ?>">
                                    <?= e(ucfirst($block['severity'] ?? 'suspend')) ?>
                                </span>
                            </td>
                            <td class="options-cell">
                                <ul class="block-options">
                                    <?php if ($block['reject_media'] ?? false): ?>
                                        <li class="option-tag">No Media</li>
                                    <?php endif; ?>
                                    <?php if ($block['reject_reports'] ?? false): ?>
                                        <li class="option-tag">No Reports</li>
                                    <?php endif; ?>
                                </ul>
                            </td>
                            <td class="comment-cell">
                                <?php if (!empty($block['public_comment'])): ?>
                                    <span class="public-comment" title="Public comment">
                                        <?= e($block['public_comment']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($block['private_comment'])): ?>
                                    <span class="private-comment" title="Private comment (admin only)">
                                        <svg aria-hidden="true" width="14" height="14" viewBox="0 0 14 14">
                                            <rect x="2" y="6" width="10" height="6" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                            <path d="M4 6V4a3 3 0 0 1 6 0v2" fill="none" stroke="currentColor" stroke-width="1.5"/>
                                        </svg>
                                        <?= e($block['private_comment']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="created-cell">
                                <time datetime="<?= e($block['created_at'] ?? '') ?>">
                                    <?= time_ago($block['created_at'] ?? '') ?>
                                </time>
                            </td>
                            <td class="actions-cell">
                                <div class="action-buttons">
                                    <button type="button" 
                                            class="btn btn-secondary btn-small"
                                            data-action="edit-block"
                                            data-block-id="<?= e($block['id'] ?? '') ?>"
                                            title="Edit block">
                                        Edit
                                    </button>
                                    <button type="button" 
                                            class="btn btn-danger btn-small"
                                            data-action="delete-block"
                                            data-block-id="<?= e($block['id'] ?? '') ?>"
                                            data-csrf="<?= e($csrf ?? '') ?>"
                                            title="Remove block">
                                        Remove
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (!empty($pagination)): ?>
                <nav class="pagination" aria-label="Domain blocks pagination">
                    <?php if (!empty($pagination['prev'])): ?>
                        <a href="<?= e($pagination['prev']) ?>" class="pagination-link prev">Previous</a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Page <?= e($pagination['current'] ?? 1) ?> of <?= e($pagination['total_pages'] ?? 1) ?>
                    </span>
                    
                    <?php if (!empty($pagination['next'])): ?>
                        <a href="<?= e($pagination['next']) ?>" class="pagination-link next">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    
    <section class="import-export-section" aria-label="Import/Export domain blocks">
        <h2 class="section-title">Import / Export</h2>
        
        <div class="import-export-grid">
            <div class="import-block">
                <h3 class="subsection-title">Import Domain Blocks</h3>
                <p class="subsection-description">Import a CSV file with domain blocks from another instance.</p>
                
                <form action="<?= url('/api/v1/admin/domain_blocks/import') ?>" 
                      method="post" 
                      enctype="multipart/form-data"
                      class="import-form"
                      data-csrf="<?= e($csrf ?? '') ?>">
                    
                    <div class="form-group">
                        <label for="import-file" class="form-label">CSV File</label>
                        <input type="file" 
                               id="import-file" 
                               name="file" 
                               class="form-file"
                               accept=".csv,text/csv"
                               required>
                        <span class="form-hint">Format: domain,severity,reject_media,reject_reports,public_comment,private_comment</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="overwrite" class="form-checkbox">
                            Overwrite existing blocks
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary">Import</button>
                </form>
            </div>
            
            <div class="export-block">
                <h3 class="subsection-title">Export Domain Blocks</h3>
                <p class="subsection-description">Download current domain blocks as a CSV file.</p>
                
                <a href="<?= url('/api/v1/admin/domain_blocks/export') ?>" 
                   class="btn btn-secondary"
                   download="domain_blocks.csv">
                    Export CSV
                </a>
            </div>
        </div>
    </section>
</section>