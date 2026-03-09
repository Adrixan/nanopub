<footer class="site-footer" role="contentinfo">
    <div class="footer-container">
        <nav class="footer-nav" aria-label="Footer navigation">
            <ul class="footer-links">
                <li><a href="<?= url('/about') ?>">About</a></li>
                <li><a href="<?= url('/terms') ?>">Terms of Service</a></li>
                <li><a href="<?= url('/privacy') ?>">Privacy Policy</a></li>
                <li><a href="<?= url('/docs') ?>">API Documentation</a></li>
            </ul>
        </nav>
        
        <div class="footer-info">
            <p class="instance-name">
                <a href="<?= url('/') ?>"><?= e($instance['title'] ?? 'NanoPub') ?></a>
            </p>
            <p class="version-info">
                Powered by <a href="https://nanopub.org" rel="noopener">NanoPub</a>
                <?php if (isset($version)): ?>
                    <span class="version">v<?= e($version) ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>
</footer>