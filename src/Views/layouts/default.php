<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrf ?? '') ?>">
    <title><?= e($title ?? 'NanoPub') ?></title>
    <link rel="stylesheet" href="<?= url('/assets/css/main.css') ?>">
    <link rel="icon" href="<?= url('/assets/images/favicon.png') ?>">
    <?php if (isset($head)): ?>
        <?= $head ?>
    <?php endif; ?>
</head>
<body>
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <?php if (isset($flash) && $flash): ?>
        <div class="flash-message flash-<?= e($flash['type'] ?? 'info') ?>" role="alert" aria-live="polite">
            <p><?= e($flash['message'] ?? '') ?></p>
            <button type="button" class="flash-close" aria-label="Close message">&times;</button>
        </div>
    <?php endif; ?>

    <?= $content ?? '' ?>

    <?php include __DIR__ . '/../partials/footer.php'; ?>

    <script src="<?= url('/assets/js/main.js') ?>" defer></script>
    <?php if (isset($scripts)): ?>
        <?= $scripts ?>
    <?php endif; ?>
</body>
</html>