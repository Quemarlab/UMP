<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Audit;
use UMP\Core\Auth;
use UMP\Repositories\Stock;

Auth::require();
$unit = setting('unit');

// Filters: default to the current month; ?range=all clears them.
$range = input('range');
$from = input('from', $range === 'all' ? '' : date('Y-m-01'));
$to = input('to', $range === 'all' ? '' : date('Y-m-d'));
$valid = fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
$filters = ['from' => $valid($from) ? $from : '', 'to' => $valid($to) ? $to : '', 'q' => mb_substr(input('q'), 0, 60)];

if (input('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ump-records-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date', "Opening ($unit)", 'Received day', 'Received night', 'Received total', 'GRV day', 'GRV night', "Issued ($unit)", 'GIV', "Closing ($unit)", 'Notes']);
    $safe = fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v;
    foreach (array_reverse(Stock::all($filters)) as $r) {
        fputcsv($out, [$r['record_date'], $r['opening'], $r['received_day'], $r['received_night'], $r['received_total'], $safe($r['grv_day']), $safe($r['grv_night']),
            $r['issued'], $safe($r['giv']), $r['closing'], $safe($r['notes'])]);
    }
    Audit::log('export', 'stock', null, 'CSV ' . ($filters['from'] ?: 'start') . ' → ' . ($filters['to'] ?: 'now'));
    exit;
}

$pg = Stock::paginate($filters, (int) input('page', '1'), 25);
$tot = Stock::totals($filters);
$qs = fn(array $extra = []) => http_build_query(array_filter($extra + ['from' => $filters['from'], 'to' => $filters['to'], 'q' => $filters['q'], 'range' => $range]));

admin_header('Records', 'records');
?>
<div class="print-title"><h2><?= e(setting('system_name')) ?></h2>
    <p><?= e(setting('company_name')) ?> · <?= $filters['from'] ? e(fmt_date($filters['from'])) : 'start' ?> to <?= $filters['to'] ? e(fmt_date($filters['to'])) : 'today' ?></p></div>

<form method="get" class="card card-body mb-3 no-print">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="q">GRV / GIV / notes</label><input class="form-control" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Search…"></div>
        <div class="col-md-5 d-flex flex-wrap gap-2">
            <button class="btn btn-gold"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a class="btn btn-light" href="<?= admin_url('records') ?>">This month</a>
            <a class="btn btn-light" href="<?= admin_url('records?range=all') ?>">All time</a>
            <span class="ms-md-auto d-flex gap-2">
                <a class="btn btn-light" href="<?= admin_url('records?' . $qs(['export' => 'csv'])) ?>"><i class="bi bi-download me-1"></i>CSV</a>
                <button class="btn btn-light" type="button" data-print><i class="bi bi-printer me-1"></i>Print</button>
            </span>
        </div>
    </div>
</form>

<div class="card">
    <?php if (!$pg['items']): ?>
        <div class="empty-admin"><i class="bi bi-journal-x"></i>No records match these filters.</div>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>Date</th><th class="num-cell">Opening</th><th class="num-cell">Received (D)</th><th class="num-cell">Received (N)</th><th class="num-cell">Total</th>
                <th>GRV (D / N)</th><th class="num-cell">Issued</th><th>GIV</th><th class="num-cell">Closing</th><th class="no-print"></th></tr></thead>
            <tbody>
            <?php foreach ($pg['items'] as $r): ?>
                <tr>
                    <td><a class="row-title" href="<?= admin_url('record?date=' . $r['record_date']) ?>"><?= e(fmt_date($r['record_date'], 'D j M Y')) ?></a></td>
                    <td class="num-cell text-body-secondary"><?= qty($r['opening']) ?></td>
                    <td class="num-cell"><?= qty($r['received_day']) ?></td><td class="num-cell"><?= qty($r['received_night']) ?></td>
                    <td class="num-cell fw-semibold"><?= qty($r['received_total']) ?></td>
                    <td class="small"><?= e($r['grv_day'] ?: '—') ?> / <?= e($r['grv_night'] ?: '—') ?></td>
                    <td class="num-cell"><?= qty($r['issued']) ?></td><td class="small"><?= e($r['giv'] ?: '—') ?></td>
                    <td class="num-cell fw-semibold"><?= qty($r['closing']) ?></td>
                    <td class="text-end no-print"><a class="btn btn-sm btn-light" href="<?= admin_url('record?date=' . $r['record_date']) ?>" title="<?= can_record() ? 'Open / edit' : 'Open' ?>"><i class="bi bi-<?= can_record() ? 'pencil' : 'eye' ?>"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td>Totals · <?= (int) $tot['days'] ?> day(s)</td><td></td><td class="num-cell"><?= qty($tot['received_day']) ?></td><td class="num-cell"><?= qty($tot['received_night']) ?></td>
                <td class="num-cell"><?= qty($tot['received_total']) ?></td><td></td><td class="num-cell"><?= qty($tot['issued']) ?></td><td></td><td></td><td class="no-print"></td></tr></tfoot>
        </table></div>
        <div class="card-body py-2 no-print"><?php $pgClass = 'mb-0'; $pgExtra = $qs(); include __DIR__ . '/inc/pagination.php'; ?></div>
    <?php endif; ?>
</div>
<?php admin_footer();
