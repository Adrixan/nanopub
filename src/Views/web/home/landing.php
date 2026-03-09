<?php
/**
 * @var array $instance Instance data
 */
?>

<section class="landing-hero">
    <div class="hero-content">
        <h1 class="hero-title"><?= e($instance['title'] ?? 'NanoPub') ?></h1>
        
        <?php if (!empty($instance['short_description'])): ?>
            <p class="hero-description"><?= e($instance['short_description']) ?></p>
        <?php elseif (!empty($instance['description'])): ?>
            <p class="hero-description"><?= e($instance['description']) ?></p>
        <?php else: ?>
            <p class="hero-description">A federated social network powered by NanoPub</p>
        <?php endif; ?>
        
        <div class="hero-actions">
            <?php if ($instance['registrations'] ?? true): ?>
                <?php if ($instance['approval_required'] ?? false): ?>
                    <a href="<?= url('/register') ?>" class="btn btn-primary btn-large">
                        Request Access
                    </a>
                <?php else: ?>
                    <a href="<?= url('/register') ?>" class="btn btn-primary btn-large">
                        Create Account
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            
            <a href="<?= url('/login') ?>" class="btn btn-secondary btn-large">
                Sign In
            </a>
        </div>
    </div>
    
    <div class="hero-stats">
        <div class="stat-card">
            <span class="stat-value"><?= e(number_format($instance['user_count'] ?? 0)) ?></span>
            <span class="stat-label">Users</span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?= e(number_format($instance['status_count'] ?? 0)) ?></span>
            <span class="stat-label">Posts</span>
        </div>
        <div class="stat-card">
            <span class="stat-value"><?= e(number_format($instance['peer_count'] ?? 0)) ?></span>
            <span class="stat-label">Connected Instances</span>
        </div>
    </div>
</section>

<section class="landing-features">
    <h2 class="section-title">Why NanoPub?</h2>
    
    <div class="features-grid">
        <article class="feature-card">
            <div class="feature-icon">
                <svg aria-hidden="true" width="48" height="48" viewBox="0 0 48 48">
                    <circle cx="24" cy="24" r="20" fill="none" stroke="currentColor" stroke-width="2"/>
                    <path d="M24 14v10l6 6" fill="none" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <h3 class="feature-title">Real-time Updates</h3>
            <p class="feature-description">
                Stay connected with instant notifications and live timeline updates.
            </p>
        </article>
        
        <article class="feature-card">
            <div class="feature-icon">
                <svg aria-hidden="true" width="48" height="48" viewBox="0 0 48 48">
                    <circle cx="16" cy="16" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                    <circle cx="32" cy="32" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                    <line x1="22" y1="22" x2="26" y2="26" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <h3 class="feature-title">Federated Network</h3>
            <p class="feature-description">
                Connect with users across different instances through ActivityPub.
            </p>
        </article>
        
        <article class="feature-card">
            <div class="feature-icon">
                <svg aria-hidden="true" width="48" height="48" viewBox="0 0 48 48">
                    <rect x="8" y="8" width="32" height="32" rx="4" fill="none" stroke="currentColor" stroke-width="2"/>
                    <path d="M16 24h16M24 16v16" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <h3 class="feature-title">Rich Media</h3>
            <p class="feature-description">
                Share images, videos, polls, and more with your followers.
            </p>
        </article>
        
        <article class="feature-card">
            <div class="feature-icon">
                <svg aria-hidden="true" width="48" height="48" viewBox="0 0 48 48">
                    <path d="M24 4L4 14v20l20 10 20-10V14L24 4z" fill="none" stroke="currentColor" stroke-width="2"/>
                    <path d="M24 24v20M4 14l20 10 20-10" fill="none" stroke="currentColor" stroke-width="2"/>
                </svg>
            </div>
            <h3 class="feature-title">Privacy Focused</h3>
            <p class="feature-description">
                Your data stays on your instance. No tracking, no ads.
            </p>
        </article>
    </div>
</section>

<?php if (!empty($instance['rules']) && is_array($instance['rules'])): ?>
<section class="landing-rules">
    <h2 class="section-title">Instance Rules</h2>
    <ol class="rules-list">
        <?php foreach ($instance['rules'] as $rule): ?>
            <li class="rule-item">
                <span class="rule-text"><?= e($rule['text'] ?? $rule) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>

<section class="landing-cta">
    <div class="cta-content">
        <h2 class="cta-title">Ready to join?</h2>
        <p class="cta-description">
            Create your account and become part of the fediverse today.
        </p>
        <div class="cta-actions">
            <?php if ($instance['registrations'] ?? true): ?>
                <a href="<?= url('/register') ?>" class="btn btn-primary btn-large">
                    Get Started
                </a>
            <?php endif; ?>
            <a href="<?= url('/public') ?>" class="btn btn-secondary btn-large">
                Browse Public Timeline
            </a>
        </div>
    </div>
</section>