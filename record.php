<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Audit;
use UMP\Core\Auth;
use UMP\Core\Settings;
use UMP\Repositories\Attachments;
use UMP\Repositories\Stock;

$me = Auth::require('admin', 'operator');
$isAdmin = Auth::is('admin');
$unit = setting('unit');

$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', input('date')) ? input('date') : date('Y-m-d');
if ($date > date('Y-m-d')) {
    $date = date('Y-m-d');
}

$errors = [];
$values = null;
if (is_post() && !in_array(input('op'), ['attach', 'detach'], true)) {
    admin_post();
    $date = input('record_date');
    $result = Stock::save($_POST, (int) $me['id'], $isAdmin);
    if ($result['ok']) {
        flash('success', 'Record for ' . fmt_date($date) . ' saved.');
        foreach ($result['warnings'] as $w) {
            flash('warning', $w);
        }
        redirect(admin_url('record?date=' . $date));
    }
    $errors = $result['errors'];
    $values = $_POST;
}

$existing = Stock::find($date);
$opening = $existing ? (float) $existing['opening'] : Stock::openingFor($date);
$prev = Stock::previous($date);
$v = fn(string $k) => $values[$k] ?? ($existing[$k] ?? '');
$numv = fn(string $k) => ($x = $v($k)) === '' ? '0' : rtrim(rtrim(number_format((float) str_replace(',', '.', (string) $x), 2, '.', ''), '0'), '.');

$window = (int) Settings::get('edit_window', '2');
$locked = $existing && !$isAdmin && $date < date('Y-m-d', strtotime("-{$window} days"));
$readOnly = Stock::readOnly();
$periodLocked = Stock::isLocked($date);
$frozen = $readOnly || $periodLocked;
$locked = $locked || $frozen;

// Documents (scanned GRV / GIV / delivery notes) attach to an existing record.
if (is_post() && in_array(input('op'), ['attach', 'detach'], true)) {
    admin_post();
    if (!$existing || $frozen) {
        flash('error', $frozen ? 'Records are locked, so documents cannot be changed.' : 'Save the record first, then attach documents.');
    } elseif (input('op') === 'attach') {
        $added = 0;
        try {
            foreach (Attachments::normalise($_FILES['documents'] ?? []) as $f) {
                if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                Attachments::add((int) $existing['id'], $f, (int) $me['id']);
                Audit::log('attach', 'stock', (int) $existing['id'], $date . ' · ' . $f['name']);
                $added++;
            }
            flash($added ? 'success' : 'error', $added ? $added . ' document(s) attached.' : 'Choose a file first.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
    } else {
        $att = Attachments::find((int) input('attachment_id'));
        if ($att && (int) $att['record_id'] === (int) $existing['id'] && ($isAdmin || (int) $att['uploaded_by'] === (int) $me['id'])) {
            Attachments::remove((int) $att['id']);
            Audit::log('detach', 'stock', (int) $existing['id'], $date . ' · ' . $att['original_name']);
            flash('success', 'Document removed.');
        } else {
            flash('error', 'Only the person who uploaded a document, or an administrator, can remove it.');
        }
    }
    redirect(admin_url('record?date=' . $date . '#documents'));
}
$documents = $existing ? Attachments::forRecord((int) $existing['id']) : [];
$revisions = $existing ? Stock::revisions((int) $existing['id']) : [];

admin_header($existing ? 'Edit record' : 'Daily record', 'record');
?>
<div class="row g-4">
    <div class="col-xl-8">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-1"><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <div class="card"><div class="card-body p-4">
            <form method="post" id="recordForm" data-opening="<?= e($opening) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3 mb-2">
                    <div class="col-md-5">
                        <label class="form-label" for="record_date">Date</label>
                        <input class="form-control<?= isset($errors['record_date']) ? ' is-invalid' : '' ?>" type="date" id="record_date" name="record_date" max="<?= date('Y-m-d') ?>" value="<?= e($date) ?>" required>
                        <div class="form-text"><?= $existing ? 'A record exists for this day — you are editing it.' : 'No record for this day yet.' ?></div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="opening">Opening stock (<?= e($unit) ?>)</label>
                        <input class="form-control" id="opening" value="<?= e(qty($opening)) ?>" readonly>
                        <div class="form-text"><?= $existing ? 'Fixed when the record was created.' : ($prev ? 'Closing stock of ' . e(fmt_date($prev['record_date'])) . '.' : 'Starting stock from Settings.') ?></div>
                    </div>
                </div>

                <div class="form-section-title">Received</div>
                <div class="row g-3">
                    <?php foreach (['received_day' => 'Day shift', 'received_night' => 'Night shift'] as $k => $label): ?>
                        <div class="col-md-6"><label class="form-label" for="<?= $k ?>"><?= $label ?> (<?= e($unit) ?>)</label>
                            <input class="form-control calc<?= isset($errors[$k]) ? ' is-invalid' : '' ?>" type="number" step="0.01" min="0" inputmode="decimal" id="<?= $k ?>" name="<?= $k ?>" value="<?= e($numv($k)) ?>" required<?= $locked ? ' disabled' : '' ?>></div>
                    <?php endforeach; ?>
                    <div class="col-md-6"><label class="form-label" for="grv_day">GRV no. (day)</label>
                        <input class="form-control" id="grv_day" name="grv_day" maxlength="60" value="<?= e($v('grv_day')) ?>"<?= $locked ? ' disabled' : '' ?>></div>
                    <div class="col-md-6"><label class="form-label" for="grv_night">GRV no. (night)</label>
                        <input class="form-control" id="grv_night" name="grv_night" maxlength="60" value="<?= e($v('grv_night')) ?>"<?= $locked ? ' disabled' : '' ?>></div>
                </div>

                <div class="form-section-title">Issued</div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="issued">Issued (<?= e($unit) ?>)</label>
                        <input class="form-control calc<?= isset($errors['issued']) ? ' is-invalid' : '' ?>" type="number" step="0.01" min="0" inputmode="decimal" id="issued" name="issued" value="<?= e($numv('issued')) ?>" required<?= $locked ? ' disabled' : '' ?>></div>
                    <div class="col-md-6"><label class="form-label" for="giv">GIV no.</label>
                        <input class="form-control" id="giv" name="giv" maxlength="60" value="<?= e($v('giv')) ?>"<?= $locked ? ' disabled' : '' ?>></div>
                    <div class="col-12"><label class="form-label" for="notes">Notes <span class="text-body-secondary fw-normal">(optional)</span></label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="500"<?= $locked ? ' disabled' : '' ?>><?= e($v('notes')) ?></textarea></div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6"><div class="calc-box"><div class="small text-body-secondary">Total received</div><div class="num" id="totalReceived">0</div></div></div>
                    <div class="col-md-6"><div class="calc-box" id="closingBox"><div class="small text-body-secondary">Closing stock</div><div class="num" id="closingStock">0</div>
                        <div class="small text-danger d-none" id="negWarn">Issued is more than the stock available.</div></div></div>
                </div>

                <?php if ($existing && !$locked): ?>
                    <div class="mt-4"><label class="form-label" for="reason">Reason for the change <span class="text-danger">*</span></label>
                        <input class="form-control<?= isset($errors['reason']) ? ' is-invalid' : '' ?>" id="reason" name="reason" maxlength="255" placeholder="e.g. corrected night-shift weight after re-weighing" value="<?= e($values['reason'] ?? '') ?>" required>
                        <div class="form-text">Kept in the record's history together with the figures it replaces.</div></div>
                <?php endif; ?>

                <?php if ($locked): ?>
                    <div class="alert alert-secondary mt-4 mb-0"><i class="bi bi-lock me-1"></i><?= $readOnly ? 'The system is in read-only mode. An administrator can switch it off in Settings.' : ($periodLocked ? 'This period is locked up to ' . e(fmt_date(Stock::lockedUntil())) . '. An administrator can unlock it in Settings.' : 'This record is older than ' . $window . ' day(s). Ask an administrator to change it.') ?></div>
                <?php else: ?>
                    <div class="mt-4 d-flex gap-2">
                        <button class="btn btn-gold px-4" id="saveBtn"><i class="bi bi-check2-circle me-1"></i><?= $existing ? 'Update record' : 'Save record' ?></button>
                        <a class="btn btn-light" href="<?= admin_url('records') ?>">Cancel</a>
                    </div>
                <?php endif; ?>
            </form>
        </div></div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3"><div class="card-header">Context</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item bg-transparent d-flex justify-content-between"><span>Previous day</span><strong><?= $prev ? e(fmt_date($prev['record_date'], 'j M')) . ' · closed ' . qty($prev['closing']) : '—' ?></strong></li>
                <li class="list-group-item bg-transparent d-flex justify-content-between"><span>Received this month</span><strong><?= qty(Stock::mtd($date)) ?> <?= e($unit) ?></strong></li>
                <li class="list-group-item bg-transparent d-flex justify-content-between"><span>Daily average (7 days)</span><strong><?= qty(array_sum(array_column(array_slice(Stock::series(7), 0), 'received')) / 7, 0) ?> <?= e($unit) ?></strong></li>
            </ul></div>
        <?php if ($existing): ?>
            <div class="card mb-3" id="documents"><div class="card-header d-flex justify-content-between align-items-center">Documents <a class="small" href="<?= admin_url('report-day?date=' . $date) ?>" target="_blank"><i class="bi bi-printer me-1"></i>Print report</a></div>
                <div class="card-body py-2">
                    <?php if (!$documents): ?><p class="small text-body-secondary my-2">No GRV / GIV scans attached yet.</p><?php endif; ?>
                    <ul class="list-unstyled mb-2">
                        <?php foreach ($documents as $d): $img = str_starts_with($d['mime'], 'image/'); ?>
                            <li class="d-flex align-items-center gap-2 py-1 small">
                                <i class="bi <?= $img ? 'bi-file-earmark-image' : 'bi-file-earmark-pdf' ?> fs-5 text-body-secondary"></i>
                                <div class="flex-grow-1 text-truncate"><a href="<?= admin_url('attachment?id=' . $d['id']) ?>" target="_blank"><?= e($d['original_name']) ?></a>
                                    <div class="text-body-secondary"><?= e(number_format($d['size'] / 1024, 0)) ?> KB · <?= e($d['uploader'] ?? '—') ?></div></div>
                                <?php if (!$frozen && ($isAdmin || (int) $d['uploaded_by'] === (int) $me['id'])): ?>
                                    <form method="post" action="<?= admin_url('record?date=' . $date) ?>" data-confirm="Remove this document?"><?= csrf_field() ?><input type="hidden" name="op" value="detach"><input type="hidden" name="attachment_id" value="<?= (int) $d['id'] ?>">
                                        <button class="btn btn-sm btn-light text-danger" aria-label="Remove"><i class="bi bi-x-lg"></i></button></form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!$frozen): ?>
                        <form method="post" action="<?= admin_url('record?date=' . $date) ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="op" value="attach">
                            <input class="form-control form-control-sm mb-2" type="file" name="documents[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp" aria-label="Choose documents">
                            <button class="btn btn-sm btn-light"><i class="bi bi-paperclip me-1"></i>Attach</button>
                            <span class="small text-body-secondary ms-1">PDF or image, up to 8 MB each</span></form>
                    <?php endif; ?>
                </div></div>
        <?php endif; ?>
        <?php if ($existing): ?>
            <div class="card"><div class="card-header">History</div><div class="card-body py-2">
                <ul class="list-unstyled hist mb-0">
                    <?php foreach ($revisions as $r):
                        $before = $r['before_json'] ? json_decode($r['before_json'], true) : null; ?>
                        <li><strong><?= e(ucfirst($r['action'] === 'create' ? 'created' : ($r['action'] === 'rechain' ? 'realigned' : 'updated'))) ?></strong>
                            by <?= e($r['user_name'] ?? 'deleted user') ?> <span class="text-body-secondary">· <?= e(fmt_date($r['created_at'], 'j M Y H:i')) ?></span>
                            <?php if ($r['reason']): ?><div class="text-body-secondary">“<?= e($r['reason']) ?>”</div><?php endif; ?>
                            <?php if ($before): ?><div class="text-body-secondary">was: received <?= qty(($before['received_total'] ?? 0)) ?>, issued <?= qty($before['issued'] ?? 0) ?>, closing <?= qty($before['closing'] ?? 0) ?></div><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul></div></div>
        <?php endif; ?>
    </div>
</div>
<?php admin_footer([admin_asset('record.js')]);
