<?php
require_once __DIR__ . '/app/bootstrap.php';

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Page not found</title>
    <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
    <link href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center text-center" style="min-height:100vh;background:#f4f4f0;font-family:system-ui,sans-serif">
    <div>
        <div style="font-size:6rem;font-weight:800;color:#d4a017;line-height:1">404</div>
        <h1 class="h3 mt-3">That page does not exist</h1>
        <p class="text-secondary">The link may be broken or the page may have moved.</p>
        <a class="btn btn-dark" href="<?= url('dashboard') ?>">Go to the dashboard</a>
    </div>
</body>
</html>
