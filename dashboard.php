<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Auth;
use UMP\Core\Settings;
use UMP\Repositories\Stock;

Auth::require();

// Chart range switcher (JSON, no page reload)
if (input('series') !== '') {
    json_response(Stock::series(max(7, min(180, (int) input('series')))));
}

$unit = setting('unit');
$today = date('Y-m-d');
$latest = Stock::latest();
$todayRec = Stock::find($today);
$series = Stock::series(30);

$weekStart = date('Y-m-d', strtotime('monday this week'));
$thisWeek = Stock::sum($weekStart, $today);
$lastWeek = Stock::sum(date('Y-m-d', strtotime('monday last week')), date('Y-m-d', strtotime('sunday last week')));

$mtd = Stock::mtd();
$target = Settings::float('monthly_target');
$pct = $target > 0 ? min(100, round($mtd / $target * 100)) : 0;

$last7 = array_slice($series, -7);
$avg7 = array_sum(array_column($last7, 'received')) / 7;

$lowLevel = Settings::float('low_stock');
$alerts = [];
if (Stock::readOnly()) {
    $alerts[] = ['warning', 'bi-tools', 'Read-only mode is on: recording and editing are paused.', Auth::is('admin') ? ['Settings', 'settings'] : null];
}
if ($l = Stock::lockedUntil()) {
    $alerts[] = ['secondary', 'bi-lock', 'Records are locked up to ' . fmt_date($l) . '.', null];
}
if (!$todayRec && (int) date('G') >= (int) Settings::get('record_deadline', '17')) {
    $alerts[] = ['warning', 'bi-exclamation-triangle-fill', 'Today (' . fmt_date($today) . ') has no record yet.', can_record() ? ['Record now', 'record'] : null];
}
if ($latest && $lowLevel > 0 && (float) $latest['closing'] <= $lowLevel) {
    $alerts[] = ['danger', 'bi-box-seam', 'Stock is low: ' . qty($latest['closing']) . " $unit (alert level " . qty($lowLevel) . ').', null];
}
$missing = Stock::missingDates(30);
$missing = array_values(array_filter($missing, fn($d) => $d < $today));
if ($missing) {
    $alerts[] = ['warning', 'bi-calendar-x', count($missing) . ' day(s) without a record in the last 30 days.', ['Review', 'reports#checks']];
}
if ($breaks = Stock::chainBreaks()) {
    $alerts[] = ['warning', 'bi-link-45deg', count($breaks) . ' stock figure(s) do not follow on from the previous day.', ['Review', 'reports#checks']];
}

$delta = fn(float $a, float $b) => $b > 0 ? round(($a - $b) / $b * 100) : null;
$recent = Stock::paginate([], 1, 7)['items'];
$fc = Stock::forecast();
$anoms = Stock::anomalies(30);
$cal = preg_match('/^\d{4}-\d{2}$/', input('cal')) ? input('cal') : date('Y-m');
$calMap = Stock::monthMap($cal);
$calFirst = strtotime($cal . '-01');
$calDays = (int) date('t', $calFirst);
$calLead = (int) date('N', $calFirst) - 1; // Monday = 0
$prevCal = date('Y-m', strtotime('-1 month', $calFirst));
$nextCal = date('Y-m', strtotime('+1 month', $calFirst));
$fcBadge = ['on-track' => ['success', 'On track'], 'at-risk' => ['warning', 'At risk'], 'behind' => ['danger', 'Behind target']][$fc['status']] ?? null;
if (input('partial') === 'cal') {
    include __DIR__ . '/inc/calendar.php';
    exit;
}

admin_header('Dashboard', 'dashboard');
?>
<?php foreach ($alerts as [$type, $icon, $text, $action]): ?>
    <div class="alert alert-<?= $type ?> d-flex align-items-center gap-2 py-2" role="alert">
        <i class="bi <?= $icon ?>"></i><span class="flex-grow-1"><?= e($text) ?></span>
        <?php if ($action): ?><a class="btn btn-sm btn-light" href="<?= admin_url($action[1]) ?>"><?= e($action[0]) ?></a><?php endif; ?>
    </div>
<?php endforeach; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card stat-card">
        <span class="ic" style="--c:#d4a017"><i class="bi bi-box-seam"></i></span>
        <div><div class="num"><?= $latest ? qty($latest['closing']) : '—' ?></div>
            <div class="lbl">Closing stock (<?= e($unit) ?>)<?= $latest ? ' · ' . e(fmt_date($latest['record_date'], 'j M')) : '' ?></div></div>
    </div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card">
        <span class="ic" style="--c:#2f7d32"><i class="bi bi-box-arrow-in-down"></i></span>
        <div><div class="num"><?= $todayRec ? qty($todayRec['received_total']) : '—' ?></div>
            <div class="lbl">Received today<?= $todayRec ? ' · issued ' . qty($todayRec['issued']) : ' · not recorded' ?></div></div>
    </div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card flex-wrap">
        <span class="ic" style="--c:#2b6cb0"><i class="bi bi-calendar-month"></i></span>
        <div><div class="num"><?= qty($mtd) ?></div><div class="lbl">Received this month (<?= e($unit) ?>)</div></div>
        <?php if ($target > 0): ?>
            <div class="w-100 mt-1"><div class="progress" style="height:6px" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div></div>
                <div class="small text-body-secondary mt-1"><?= $pct ?>% of <?= qty($target) ?> target</div></div>
        <?php endif; ?>
    </div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card">
        <span class="ic" style="--c:#7b61c4"><i class="bi bi-graph-up-arrow"></i></span>
        <div><div class="num"><?= qty($avg7, 0) ?></div><div class="lbl">Daily average received, last 7 days</div></div>
    </div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card h-100"><div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Received vs issued · <span class="range-label">last 30 days</span></span>
            <span class="d-flex align-items-center gap-3"><span class="legend small"><i class="sw sw-recv"></i>Received <i class="sw sw-iss ms-2"></i>Issued</span>
                <span class="btn-group btn-group-sm" role="group" aria-label="Chart range"><?php foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $n => $l): ?><button type="button" class="btn btn-light<?= $n === 30 ? ' active' : '' ?>" data-range="<?= $n ?>"><?= $l ?></button><?php endforeach; ?></span></span></div>
            <div class="card-body"><div class="chart" id="chartFlow" data-chart="bars" data-unit="<?= e($unit) ?>"></div></div></div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100"><div class="card-header">Closing stock · <span class="range-label">last 30 days</span></div>
            <div class="card-body"><div class="chart" id="chartStock" data-chart="line" data-unit="<?= e($unit) ?>"></div></div></div>
    </div>
</div>
<script type="application/json" id="chartData"><?= json_encode($series, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<div class="row g-3 mb-4">
    <div class="col-lg-4"><div class="card h-100"><div class="card-header d-flex justify-content-between align-items-center">Month-end forecast <?php if ($fcBadge): ?><span class="badge text-bg-<?= $fcBadge[0] ?>"><?= $fcBadge[1] ?></span><?php endif; ?></div>
        <div class="card-body">
            <div class="display-6 fw-bold" style="font-family:'Plus Jakarta Sans',sans-serif"><?= qty($fc['projected'], 0) ?> <small class="fs-6 text-body-secondary"><?= e($unit) ?></small></div>
            <div class="text-body-secondary small mb-3">projected for <?= e(date('F')) ?> at the current pace (<?= qty($fc['mtd'], 0) ?> so far in <?= $fc['days_elapsed'] ?> day<?= $fc['days_elapsed'] === 1 ? '' : 's' ?>)</div>
            <?php if ($fc['target'] > 0): $w = min(100, $fc['mtd'] / $fc['target'] * 100); $m = min(100, $fc['projected'] / $fc['target'] * 100); ?>
                <div class="position-relative" style="height:10px;background:var(--line);border-radius:6px">
                    <div style="position:absolute;inset:0 auto 0 0;width:<?= $m ?>%;background:color-mix(in srgb, var(--c-recv) 30%, transparent);border-radius:6px"></div>
                    <div style="position:absolute;inset:0 auto 0 0;width:<?= $w ?>%;background:var(--c-recv);border-radius:6px"></div></div>
                <div class="d-flex justify-content-between small text-body-secondary mt-1"><span>Actual</span><span>Target <?= qty($fc['target'], 0) ?></span></div>
                <?php if ($fc['needed_per_day'] !== null): ?><p class="small mt-3 mb-0"><i class="bi bi-bullseye me-1 text-warning"></i>To reach the target you need <strong><?= qty($fc['needed_per_day'], 0) ?> <?= e($unit) ?></strong> a day for the rest of the month.</p><?php endif; ?>
            <?php else: ?><p class="small text-body-secondary mb-0">Set a monthly target in Settings to see pace and progress.</p><?php endif; ?>
        </div></div></div>

    <div class="col-lg-4" id="calCard"><?php include __DIR__ . '/inc/calendar.php'; ?></div>

    <div class="col-lg-4"><div class="card h-100"><div class="card-header">Unusual days <span class="text-body-secondary fw-normal small">· last 30 days</span></div>
        <div class="card-body">
            <?php if (!$anoms): ?><div class="text-center text-body-secondary py-3"><i class="bi bi-check2-circle fs-2 text-success d-block"></i>Nothing unusual. Received quantities are within the normal range.</div>
            <?php else: ?><ul class="list-unstyled mb-0">
                <?php foreach ($anoms as $a): ?><li class="d-flex justify-content-between align-items-center py-2 border-bottom small">
                    <span><a href="<?= admin_url('record?date=' . $a['date']) ?>"><?= e(fmt_date($a['date'], 'D j M')) ?></a><br><span class="text-body-secondary">usual ≈ <?= qty($a['mean'], 0) ?> <?= e($unit) ?></span></span>
                    <span class="text-end"><strong><?= qty($a['value'], 0) ?></strong><br><span class="<?= $a['z'] > 0 ? 'text-success' : 'text-danger' ?>"><i class="bi bi-arrow-<?= $a['z'] > 0 ? 'up' : 'down' ?>-short"></i><?= number_format(abs($a['z']), 1) ?>σ</span></span></li><?php endforeach; ?>
            </ul><?php endif; ?>
        </div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card h-100"><div class="card-header">This week vs last week</div>
            <div class="table-responsive"><table class="table">
                <thead><tr><th></th><th class="text-end">This week</th><th class="text-end">Last week</th><th class="text-end">Change</th></tr></thead>
                <tbody>
                <?php foreach (['received_day' => 'Received · day shift', 'received_night' => 'Received · night shift', 'received_total' => 'Received · total', 'issued' => 'Issued'] as $k => $label):
                    $d = $delta((float) $thisWeek[$k], (float) $lastWeek[$k]); ?>
                    <tr><td><?= e($label) ?></td><td class="text-end"><?= qty($thisWeek[$k]) ?></td><td class="text-end text-body-secondary"><?= qty($lastWeek[$k]) ?></td>
                        <td class="text-end"><?php if ($d === null): ?><span class="text-body-secondary">—</span><?php else: ?>
                            <span class="<?= $d >= 0 ? 'text-success' : 'text-danger' ?>"><i class="bi bi-arrow-<?= $d >= 0 ? 'up' : 'down' ?>-short"></i><?= abs($d) ?>%</span><?php endif; ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <div class="card-body pt-0 small text-body-secondary"><?= (int) $thisWeek['days'] ?> day(s) recorded this week, <?= (int) $lastWeek['days'] ?> last week.</div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card h-100"><div class="card-header d-flex justify-content-between"><span>Recent records</span><a class="small" href="<?= admin_url('records') ?>">View all</a></div>
            <?php if (!$recent): ?>
                <div class="empty-admin"><i class="bi bi-journal-plus"></i>No records yet.<?php if (can_record()): ?> <a href="<?= admin_url('record') ?>">Add the first one</a>.<?php endif; ?></div>
            <?php else: ?>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Date</th><th class="text-end">Opening</th><th class="text-end">Received</th><th class="text-end">Issued</th><th class="text-end">Closing</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr><td><a class="row-title" href="<?= admin_url('record?date=' . $r['record_date']) ?>"><?= e(fmt_date($r['record_date'], 'D j M Y')) ?></a></td>
                        <td class="text-end text-body-secondary"><?= qty($r['opening']) ?></td><td class="text-end"><?= qty($r['received_total']) ?></td>
                        <td class="text-end"><?= qty($r['issued']) ?></td><td class="text-end fw-semibold"><?= qty($r['closing']) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php admin_footer([admin_asset('charts.js'), admin_asset('dashboard.js')]);
