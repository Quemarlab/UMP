<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Auth;
use UMP\Core\DB;
use UMP\Repositories\Stock;

Auth::require();

$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', input('date')) ? input('date') : date('Y-m-d');
$r = Stock::find($date);
if (!$r) {
    flash('error', 'There is no record for ' . fmt_date($date) . '.');
    redirect(admin_url('records'));
}
$unit = setting('unit');
$by = $r['created_by'] ? (string) DB::val('SELECT name FROM users WHERE id = ?', [$r['created_by']]) : '';
$mtd = Stock::mtd($date);
$prepared = $r['updated_by'] && $r['updated_by'] !== $r['created_by'] ? (string) DB::val('SELECT name FROM users WHERE id = ?', [$r['updated_by']]) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Daily report · <?= e(fmt_date($date)) ?></title>
    <link href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= admin_asset('report.css') ?>" rel="stylesheet">
</head>
<body>
<div class="sheet">
    <div class="no-print text-end mb-3"><button class="btn btn-dark" data-print>Print</button>
        <a class="btn btn-light" href="<?= admin_url('record?date=' . $date) ?>">Back</a></div>
    <header class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
        <div class="d-flex align-items-center gap-3"><img src="<?= asset('img/logo.png') ?>" alt="" height="64">
            <div><div class="h5 mb-0"><?= e(setting('company_name')) ?></div><div class="text-secondary"><?= e(setting('system_name')) ?></div></div></div>
        <div class="text-end"><div class="h4 mb-0">Daily production report</div><div class="fw-semibold"><?= e(fmt_date($date, 'l, j F Y')) ?></div></div>
    </header>

    <table class="table table-bordered align-middle">
        <tbody>
            <tr><th style="width:55%">Opening stock</th><td class="text-end"><?= qty($r['opening']) ?> <?= e($unit) ?></td></tr>
            <tr><th>Received — day shift <span class="text-secondary fw-normal">(GRV <?= e($r['grv_day'] ?: '—') ?>)</span></th><td class="text-end"><?= qty($r['received_day']) ?> <?= e($unit) ?></td></tr>
            <tr><th>Received — night shift <span class="text-secondary fw-normal">(GRV <?= e($r['grv_night'] ?: '—') ?>)</span></th><td class="text-end"><?= qty($r['received_night']) ?> <?= e($unit) ?></td></tr>
            <tr class="table-light"><th>Total received</th><td class="text-end fw-bold"><?= qty($r['received_total']) ?> <?= e($unit) ?></td></tr>
            <tr><th>Issued <span class="text-secondary fw-normal">(GIV <?= e($r['giv'] ?: '—') ?>)</span></th><td class="text-end"><?= qty($r['issued']) ?> <?= e($unit) ?></td></tr>
            <tr class="table-warning"><th>Closing stock</th><td class="text-end fw-bold fs-5"><?= qty($r['closing']) ?> <?= e($unit) ?></td></tr>
            <tr><th>Received month-to-date (<?= e(date('F', strtotime($date))) ?>)</th><td class="text-end"><?= qty($mtd) ?> <?= e($unit) ?></td></tr>
        </tbody>
    </table>
    <?php if ($r['notes']): ?><p><strong>Notes:</strong> <?= e($r['notes']) ?></p><?php endif; ?>

    <div class="row mt-5 pt-4 text-center small">
        <?php foreach (['Prepared by' => $by, 'Verified by' => $prepared, 'Approved by' => ''] as $label => $name): ?>
            <div class="col-4"><div class="sign-line"><?= e($name) ?></div><?= $label ?></div>
        <?php endforeach; ?>
    </div>
    <footer class="text-secondary small text-center mt-5">Generated <?= e(date('j M Y H:i')) ?> · <?= e(setting('system_name')) ?> · Record <?= e(fmt_date($r['updated_at'], 'j M Y H:i')) ?></footer>
</div>
<script src="<?= admin_asset('admin.js') ?>"></script>
</body>
</html>
