<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'NanoPub') ?></title>
    <link rel="stylesheet" href="<?= url('/assets/css/embed.css') ?>">
    <?php if (isset($head)): ?>
        <?= $head ?>
    <?php endif; ?>
</head>
<body class="bare">
    <?= $content ?? '' ?>
    
    <?php if (isset($scripts)): ?>
        <?= $scripts ?>
    <?php endif; ?>
</body>
</html>