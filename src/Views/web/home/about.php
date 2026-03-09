<?php
/**
 * @var array $instance Instance data
 */
?>

<section class="about-section">
    <div class="about-content">
        <h1 class="page-title">About <?= e($instance['title'] ?? 'NanoPub') ?></h1>
        
        <?php if (!empty($instance['description'])): ?>
            <p class="about-description"><?= e($instance['description']) ?></p>
        <?php else: ?>
            <p class="about-description">A federated social network powered by NanoPub</p>
        <?php endif; ?>
    </div>
    
    <section class="about-features" aria-label="Features">
        <h2 class="section-title">What is NanoPub?</h2>
        
        <div class="features-grid">
            <article class="feature-card">
                <h3 class="feature-title">Decentralised</h3>
                <p class="feature-description">
                    NanoPub is part of the fediverse, a network of interconnected servers using the ActivityPub protocol.
                    Your account lives on this instance, but you can interact with users on any compatible server.
                </p>
            </article>
            
            <article class="feature-card">
                <h3 class="feature-title">Open Source</h3>
                <p class="feature-description">
                    NanoPub is free and open-source software. Anyone can inspect the code, contribute improvements,
                    or run their own instance.
                </p>
            </article>
            
            <article class="feature-card">
                <h3 class="feature-title">Privacy Focused</h3>
                <p class="feature-description">
                    No ads, no tracking, no algorithms deciding what you see.
                    Your data stays on your instance.
                </p>
            </article>
        </div>
    </section>
    
    <?php if (!empty($instance['rules']) && is_array($instance['rules'])): ?>
    <section class="about-rules" aria-label="Instance Rules">
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
    
    <?php if (!empty($instance['contact_email'])): ?>
    <section class="about-contact" aria-label="Contact">
        <h2 class="section-title">Contact</h2>
        <p>
            If you have questions or concerns, reach out to the instance administrator at
            <a href="mailto:<?= e($instance['contact_email']) ?>"><?= e($instance['contact_email']) ?></a>.
        </p>
    </section>
    <?php endif; ?>
</section>
