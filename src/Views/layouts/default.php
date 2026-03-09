<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'NanoPub') ?></title>
    <link rel="stylesheet" href="<?= url('/assets/css/main.css') ?>">
    <link rel="icon" href="<?= url('/assets/images/favicon.png') ?>">
    <?php if (isset($head)): ?>
        <?= $head ?>
    <?php endif; ?>
</head>
<body>
    <?php include __DIR__ . '/../partials/header.php'; ?>
    
    <div class="app-layout">
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>
        
        <main id="main-content" class="container" role="main">
            <?php if (isset($flash) && $flash): ?>
                <div class="flash-message flash-<?= e($flash['type'] ?? 'info') ?>" role="alert" aria-live="polite">
                    <p><?= e($flash['message'] ?? '') ?></p>
                    <button type="button" class="flash-close" aria-label="Close message">&times;</button>
                </div>
            <?php endif; ?>
            
            <?= $content ?? '' ?>
        </main>
    </div>
    
    <?php include __DIR__ . '/../partials/footer.php'; ?>
    
    <script src="<?= url('/assets/js/main.js') ?>" defer></script>
    <?php if (isset($scripts)): ?>
        <?= $scripts ?>
    <?php endif; ?>
</body>
</html>