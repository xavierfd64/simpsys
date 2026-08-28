<?php

/**
 * Rendered by preflight.php when the server doesn't meet a hard
 * requirement. Deliberately plain PHP + inline CSS — Laravel, Vite, and
 * Blade are not safe to assume available at this point.
 *
 * @var array<int, array{label: string, passed: bool, detail: string, fix: string}> $checks
 * @var array<int, array{label: string, passed: bool, detail: string, fix: string}> $failed
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Server Not Ready — BizManager</title>
<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 40px 20px;
        background: #f8f7f5;
        color: #1c1a17;
        font: 15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    .wrap { max-width: 720px; margin: 0 auto; }
    .brand { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 18px; margin-bottom: 28px; }
    .dot { width: 10px; height: 10px; border-radius: 999px; background: #d97706; }
    h1 { font-size: 22px; margin: 0 0 8px; }
    .lede { color: #6b6155; margin: 0 0 28px; }
    .card { background: #fff; border: 1px solid #ece7e0; border-radius: 12px; padding: 4px 0; margin-bottom: 16px; overflow: hidden; }
    .row { display: flex; align-items: flex-start; gap: 12px; padding: 14px 20px; border-bottom: 1px solid #f1ede7; }
    .row:last-child { border-bottom: none; }
    .row.fail { background: #fdf6f2; }
    .badge { flex-shrink: 0; width: 20px; height: 20px; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; margin-top: 1px; }
    .badge.ok { background: #e3f3e8; color: #1e7d3b; }
    .badge.no { background: #fbe4dd; color: #b5451f; }
    .row-label { font-weight: 600; }
    .row-detail { color: #6b6155; margin-top: 2px; }
    .row-fix { color: #8a5a2f; margin-top: 6px; font-size: 13.5px; }
    .ok-list { color: #6b6155; font-size: 13px; padding: 12px 20px; }
    footer { color: #a39a8c; font-size: 13px; margin-top: 32px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="brand"><span class="dot"></span> BizManager</div>

    <h1>This server isn't ready to run BizManager yet</h1>
    <p class="lede">
        Nothing has installed or run any code — this check happens before anything else. Fix the item(s) below,
        then reload this page.
    </p>

    <div class="card">
        <?php foreach ($failed as $check): ?>
        <div class="row fail">
            <span class="badge no">&times;</span>
            <div>
                <div class="row-label"><?= htmlspecialchars($check['label']) ?></div>
                <div class="row-detail"><?= htmlspecialchars($check['detail']) ?></div>
                <div class="row-fix"><?= htmlspecialchars($check['fix']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php $passedCount = count($checks) - count($failed); ?>
    <?php if ($passedCount > 0): ?>
    <div class="card">
        <div class="ok-list">
            <?php foreach ($checks as $check): if (! $check['passed']) continue; ?>
                <div style="display:flex;align-items:center;gap:8px;padding:3px 0;">
                    <span class="badge ok" style="width:16px;height:16px;font-size:10px;">&check;</span>
                    <?= htmlspecialchars($check['label']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <footer>HTTP 503 &middot; Service Unavailable until the requirements above are met.</footer>
</div>
</body>
</html>
