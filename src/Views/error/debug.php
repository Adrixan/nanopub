<?php
/**
 * Debug error view - only shown when app.debug is true.
 *
 * @var string $message Exception message
 * @var string $file    File where exception occurred
 * @var int    $line    Line number
 * @var string $trace   Stack trace string
 */
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Debug Error - NanoPub</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; color: #e0e0e0; padding: 2rem; }
        .debug-container { max-width: 960px; margin: 0 auto; }
        h1 { color: #ff6b6b; margin-bottom: 1rem; font-size: 1.5rem; }
        .error-message { background: #16213e; border-left: 4px solid #ff6b6b; padding: 1rem 1.5rem; margin-bottom: 1.5rem; border-radius: 0 4px 4px 0; font-size: 1.1rem; word-break: break-word; }
        .meta { display: flex; gap: 2rem; margin-bottom: 1.5rem; font-size: 0.9rem; color: #a0a0a0; }
        .meta strong { color: #e0e0e0; }
        .trace { background: #0f3460; padding: 1.5rem; border-radius: 4px; overflow-x: auto; font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.85rem; line-height: 1.6; white-space: pre-wrap; word-break: break-all; }
        .back-link { display: inline-block; margin-top: 1.5rem; color: #4ecdc4; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .warning { background: #e94560; color: #fff; padding: 0.5rem 1rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="debug-container">
        <div class="warning">⚠ Debug mode is enabled. Disable in production by setting app.debug to false.</div>
        <h1>Unhandled Exception</h1>
        <div class="error-message"><?= e($message ?? 'Unknown error') ?></div> <?php // @phpstan-ignore nullCoalesce.variable ?>
        <div class="meta">
            <div><strong>File:</strong> <?= e($file ?? 'unknown') ?></div> <?php // @phpstan-ignore nullCoalesce.variable ?>
            <div><strong>Line:</strong> <?= e((string) ($line ?? 0)) ?></div> <?php // @phpstan-ignore nullCoalesce.variable ?>
        </div>
        <h2 style="font-size: 1.1rem; margin-bottom: 0.75rem; color: #a0a0a0;">Stack Trace</h2>
        <div class="trace"><?= e($trace ?? 'No trace available') ?></div> <?php // @phpstan-ignore nullCoalesce.variable ?>
        <a href="/" class="back-link">← Back to Home</a>
    </div>
</body>
</html>
