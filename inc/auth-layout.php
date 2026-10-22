<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

/** Split-screen layout for login / password screens. $body renders the card contents. */
function auth_page(string $title, callable $body): void
{
    ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> · <?= e(setting('company_name')) ?></title>
    <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('admin/admin.css') ?>" rel="stylesheet">
</head>
<body class="admin">
<div class="auth-wrap">
    <div class="auth-art">
        <h2>Every kilogram <em>accounted for</em>.</h2>
        <p class="text-white-50 mb-0">Daily receipts, issues and stock balances for unprocessed materials — recorded once, reported anywhere.</p>
    </div>
    <div class="auth-form">
        <div class="auth-card">
            <img class="logo" src="<?= asset('img/logo.png') ?>" alt="<?= e(setting('company_name')) ?>">
            <?php foreach (flash() as $f): ?>
                <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : e($f['type']) ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
            <?php $body(); ?>
        </div>
    </div>
</div>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
    <?php
}
