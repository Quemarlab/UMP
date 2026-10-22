<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Audit;
use UMP\Core\Auth;
use UMP\Repositories\Stock;

$me = Auth::require();
$unit = setting('unit');

if (is_post()) {
    admin_post();
    Auth::require('admin');
    if (input('op') === 'rechain') {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', input('from')) ? input('from') : '1970-01-01';
        try {
            $n = Stock::rechain($from, (int) $me['id']);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect(admin_url('reports#checks'));
        }
        flash('success', $n ? "Realigned $n day(s) so every opening stock follows the previous closing." : 'Nothing needed realigning.');
    }
    redirect(admin_url('reports#checks'));
}

$years = Stock::years();
$year = in_array((int) input('year'), $years, true) ? (int) input('year') : $years[0];
$months = Stock::monthly($year);

if (input('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ump-monthly-' . $year . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Month', 'Days recorded', 'Received day', 'Received night', 'Received total', 'Issued', 'Net change', 'Average received per day', 'Closing stock']);
    foreach ($months as $m) {
        fputcsv($out, [$m['ym'], $m['days'], $m['received_day'], $m['received_night'], $m['received_total'], $m['issued'],
            round($m['received_total'] - $m['issued'], 2), round($m['received_total'] / max(1, $m['days']), 2), $m['closing']]);
    }
    Audit::log('export', 'stock', null, "monthly $year");
    exit;
}

$sum = array_fill_keys(['days', 'received_day', 'received_night', 'received_total', 'issued'], 0);
foreach ($months as $m) {
    foreach ($sum as $k => $_) {
        $sum[$k] += $m[$k];
    }
}
$dayShare = $sum['received_total'] > 0 ? round($sum['received_day'] / $sum['received_total'] * 100) : 0;
$best = null;
foreach ($months as $m) {
    if (!$best || $m['received_total'] > $best['received_total']) {
        $best = $m;
    }
}

$missing = Stock::missingDates(60);
$missing = array_values(array_filter($missing, fn($d) => $d < date('Y-m-d')));
$breaks = Stock::chainBreaks();

admin_header('Reports', 'reports');
?>
<div class="print-title"><h2><?= e(setting('system_name')) ?> — <?= $year ?></h2><p><?= e(setting('company_name')) ?></p></div>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3 no-print">
    <form method="get" class="d-flex gap-2 align-items-center"><label class="small text-body-secondary" for="year">Year</label>
        <select class="form-select" id="year" name="year" data-autosubmit style="width:auto"><?php foreach ($years as $y): ?><option<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></form>
    <div class="d-flex gap-2">
        <a class="btn btn-light" href="<?= admin_url('reports?export=csv&year=' . $year) ?>"><i class="bi bi-download me-1"></i>CSV</a>
        <button class="btn btn-light" type="button" data-print><i class="bi bi-printer me-1"></i>Print</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card card-body"><div class="small text-body-secondary">Received in <?= $year ?></div><div class="h3 mb-0"><?= qty($sum['received_total']) ?> <small class="text-body-secondary fs-6"><?= e($unit) ?></small></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="small text-body-secondary">Issued in <?= $year ?></div><div class="h3 mb-0"><?= qty($sum['issued']) ?> <small class="text-body-secondary fs-6"><?= e($unit) ?></small></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="small text-body-secondary">Best month</div><div class="h3 mb-0"><?= $best ? e(date('F', strtotime($best['ym'] . '-01'))) : '—' ?> <small class="text-body-secondary fs-6"><?= $best ? qty($best['received_total']) . ' ' . e($unit) : '' ?></small></div></div></div>
</div>

<div class="card mb-4"><div class="card-header">Monthly summary · <?= $year ?></div>
    <?php if (!$months): ?><div class="empty-admin"><i class="bi bi-bar-chart"></i>No records in <?= $year ?>.</div><?php else: ?>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Month</th><th class="num-cell">Days</th><th class="num-cell">Received (D)</th><th class="num-cell">Received (N)</th><th class="num-cell">Total received</th>
            <th class="num-cell">Issued</th><th class="num-cell">Net</th><th class="num-cell">Avg / day</th><th class="num-cell">Closing</th></tr></thead>
        <tbody>
        <?php foreach ($months as $m): $net = $m['received_total'] - $m['issued']; ?>
            <tr><td><a class="row-title" href="<?= admin_url('records?from=' . $m['ym'] . '-01&to=' . date('Y-m-t', strtotime($m['ym'] . '-01'))) ?>"><?= e(date('F Y', strtotime($m['ym'] . '-01'))) ?></a></td>
                <td class="num-cell"><?= $m['days'] ?></td><td class="num-cell"><?= qty($m['received_day']) ?></td><td class="num-cell"><?= qty($m['received_night']) ?></td>
                <td class="num-cell fw-semibold"><?= qty($m['received_total']) ?></td><td class="num-cell"><?= qty($m['issued']) ?></td>
                <td class="num-cell <?= $net >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($net >= 0 ? '+' : '') . qty($net) ?></td>
                <td class="num-cell"><?= qty($m['received_total'] / max(1, $m['days']), 0) ?></td><td class="num-cell fw-semibold"><?= qty($m['closing']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td>Year</td><td class="num-cell"><?= $sum['days'] ?></td><td class="num-cell"><?= qty($sum['received_day']) ?></td><td class="num-cell"><?= qty($sum['received_night']) ?></td>
            <td class="num-cell"><?= qty($sum['received_total']) ?></td><td class="num-cell"><?= qty($sum['issued']) ?></td>
            <td class="num-cell"><?= qty($sum['received_total'] - $sum['issued']) ?></td><td class="num-cell"><?= qty($sum['received_total'] / max(1, $sum['days']), 0) ?></td><td></td></tr></tfoot>
    </table></div>
    <?php endif; ?>
</div>

<?php if ($sum['received_total'] > 0): ?>
<div class="card mb-4"><div class="card-header">Shift contribution · <?= $year ?></div><div class="card-body">
    <div class="progress" style="height:14px" role="img" aria-label="Day shift <?= $dayShare ?> percent, night shift <?= 100 - $dayShare ?> percent">
        <div class="progress-bar" style="width:<?= $dayShare ?>%;background:var(--c-recv)"></div>
        <div class="progress-bar" style="width:<?= 100 - $dayShare ?>%;background:var(--c-stock)"></div>
    </div>
    <div class="d-flex justify-content-between small mt-2"><span><i class="sw" style="background:var(--c-recv)"></i>Day shift · <?= $dayShare ?>% (<?= qty($sum['received_day']) ?> <?= e($unit) ?>)</span>
        <span><i class="sw" style="background:var(--c-stock)"></i>Night shift · <?= 100 - $dayShare ?>% (<?= qty($sum['received_night']) ?> <?= e($unit) ?>)</span></div>
</div></div>
<?php endif; ?>

<div class="card" id="checks"><div class="card-header">Data checks</div><div class="card-body">
    <div class="row g-4">
        <div class="col-lg-6">
            <h2 class="h6"><?= $missing ? '<i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>' : '<i class="bi bi-check-circle-fill text-success me-1"></i>' ?>Days without a record</h2>
            <?php if (!$missing): ?><p class="small text-body-secondary mb-0">Every day in the last 60 days has an entry.</p><?php else: ?>
                <p class="small text-body-secondary">Gaps in the last 60 days (<?= count($missing) ?>):</p>
                <div class="d-flex flex-wrap gap-1"><?php foreach ($missing as $d): ?>
                    <?php if (can_record()): ?><a class="badge text-bg-light text-decoration-none" href="<?= admin_url('record?date=' . $d) ?>"><?= e(fmt_date($d, 'D j M Y')) ?></a>
                    <?php else: ?><span class="badge text-bg-light"><?= e(fmt_date($d, 'D j M Y')) ?></span><?php endif; ?>
                <?php endforeach; ?></div>
            <?php endif; ?>
        </div>
        <div class="col-lg-6">
            <h2 class="h6"><?= $breaks ? '<i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>' : '<i class="bi bi-check-circle-fill text-success me-1"></i>' ?>Stock continuity</h2>
            <?php if (!$breaks): ?><p class="small text-body-secondary mb-0">Every day opens with the previous day's closing, and every closing adds up.</p><?php else: ?>
                <ul class="small ps-3">
                    <?php foreach (array_slice($breaks, 0, 8) as $b): ?>
                        <li><a href="<?= admin_url('record?date=' . $b['date']) ?>"><?= e(fmt_date($b['date'])) ?></a> —
                            <?= empty($b['closing']) ? 'opens with' : 'closing is' ?> <strong><?= qty($b['found']) ?></strong>, expected <strong><?= qty($b['expected']) ?></strong></li>
                    <?php endforeach; ?>
                    <?php if (count($breaks) > 8): ?><li>…and <?= count($breaks) - 8 ?> more</li><?php endif; ?>
                </ul>
                <?php if (Auth::is('admin')): ?>
                    <form method="post" class="no-print" data-confirm="Realign all stock figures?" data-confirm-text="Opening and closing stock will be recalculated from the first mismatch onward. The old values are kept in each record's history.">
                        <?= csrf_field() ?><input type="hidden" name="op" value="rechain"><input type="hidden" name="from" value="<?= e($breaks[0]['date']) ?>">
                        <button class="btn btn-sm btn-gold"><i class="bi bi-link-45deg me-1"></i>Realign from <?= e(fmt_date($breaks[0]['date'])) ?></button></form>
                <?php else: ?><p class="small text-body-secondary mb-0">Ask an administrator to realign these figures.</p><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div></div>
<?php admin_footer();
