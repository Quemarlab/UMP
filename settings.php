<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Audit;
use UMP\Core\Auth;
use UMP\Core\Env;
use UMP\Core\Mailer;
use UMP\Core\Notifier;
use UMP\Core\Settings;

$me = Auth::require('admin');

/** field => [min, max] for numeric settings. */
const RANGES = ['decimals' => [0, 3], 'opening_stock' => [0, 1e11], 'monthly_target' => [0, 1e11], 'low_stock' => [0, 1e11], 'edit_window' => [0, 365],
    'record_deadline' => [0, 23], 'digest_hour' => [0, 23], 'anomaly_sigma' => [1, 6]];

$errors = [];
if (is_post()) {
    admin_post();

    if (input('op') === 'test_digest') {
        $ok = Mailer::configured() && Notifier::sendDigest([$me['email'] => $me['name']], true);
        flash($ok ? 'success' : 'error', $ok ? 'A test summary was emailed to ' . $me['email'] . '.' : 'The email could not be sent. Check the mail settings in .env.');
        redirect(admin_url('settings#notifications'));
    }

    $new = [];
    foreach (Settings::FIELDS as $key => [$label, , $type]) {
        if (str_starts_with($key, 'sec_')) {
            continue; // edited in the website admin
        }
        $val = mb_substr(trim((string) ($_POST[$key] ?? '')), 0, 190);
        if ($type === 'bool') {
            $new[$key] = isset($_POST[$key]) ? '1' : '0';
            continue;
        }
        if ($type === 'number') {
            [$min, $max] = RANGES[$key] ?? [0, 1e11];
            if ($val === '') {
                $val = Settings::FIELDS[$key][1];
            }
            if (!is_numeric($val) || (float) $val < $min || (float) $val > $max) {
                $errors[$key] = "$label must be a number between " . qty($min) . ' and ' . qty($max) . '.';
            }
        } elseif ($type === 'date' && $val !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', $val);
            if (!$d || $d->format('Y-m-d') !== $val) {
                $errors[$key] = "$label must be a valid date.";
            } elseif ($val > date('Y-m-d')) {
                $errors[$key] = "$label cannot be in the future.";
            }
        } elseif ($key === 'unit' && !preg_match('/^[\p{L}\p{N} .\/²³]{1,10}$/u', $val)) {
            $errors[$key] = 'Unit must be short, e.g. kg or t.';
        } elseif ($key === 'alert_emails' && $val !== '') {
            foreach (array_filter(array_map('trim', explode(',', $val))) as $addr) {
                if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                    $errors[$key] = "“{$addr}” is not a valid email address.";
                }
            }
        } elseif (in_array($key, ['company_name', 'system_name'], true) && $val === '') {
            $errors[$key] = "$label cannot be empty.";
        }
        $new[$key] = $val;
    }
    if (!$errors) {
        foreach (['lock_until' => 'lock', 'maintenance' => 'maintenance'] as $k => $action) {
            if (($new[$k] ?? '') !== Settings::stored($k) && (($new[$k] ?? '') !== '' || Settings::stored($k) !== '')) {
                Audit::log($action, 'settings', null, $k . ': ' . (Settings::stored($k) ?: 'off') . ' → ' . ($new[$k] ?: 'off'));
            }
        }
        foreach ($new as $k => $val) {
            Settings::set($k, $val);
        }
        Audit::log('update', 'settings', null, 'UMP settings');
        flash('success', 'Settings saved.');
        redirect(admin_url('settings'));
    }
}

$groups = [
    ['Organisation', 'bi-building', ['company_name', 'system_name', 'unit', 'decimals']],
    ['Stock & targets', 'bi-box-seam', ['opening_stock', 'monthly_target', 'low_stock', 'anomaly_sigma']],
    ['Rules & controls', 'bi-lock', ['edit_window', 'lock_until', 'maintenance', 'record_deadline']],
    ['Notifications', 'bi-envelope-paper', ['digest_enabled', 'digest_hour', 'alert_emails']],
];
$ymUrl = rtrim((string) Env::get('YM_URL', ''), '/');

admin_header('Settings', 'settings');
?>
<form method="post" novalidate>
    <?= csrf_field() ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if (Settings::bool('maintenance')): ?><div class="alert alert-warning"><i class="bi bi-tools me-1"></i><strong>Read-only mode is on.</strong> Nobody can record or edit until it is switched off below.</div><?php endif; ?>
    <div class="row g-4">
        <?php foreach ($groups as [$title, $icon, $keys]): ?>
            <div class="col-lg-6" id="<?= $title === 'Notifications' ? 'notifications' : '' ?>"><div class="card h-100"><div class="card-header"><i class="bi <?= $icon ?> me-2"></i><?= e($title) ?></div><div class="card-body">
                <?php foreach ($keys as $key): [$label, $default, $type, $help] = Settings::FIELDS[$key];
                    $cur = (string) ($_POST[$key] ?? Settings::get($key)); ?>
                    <div class="mb-3">
                        <?php if ($type === 'bool'): ?>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="<?= $key ?>" name="<?= $key ?>" value="1"<?= $cur === '1' ? ' checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="<?= $key ?>"><?= e($label) ?></label></div>
                        <?php else: ?>
                            <label class="form-label" for="<?= $key ?>"><?= e($label) ?></label>
                            <input class="form-control<?= isset($errors[$key]) ? ' is-invalid' : '' ?>" id="<?= $key ?>" name="<?= $key ?>" type="<?= $type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text') ?>"
                                <?= $type === 'number' ? 'step="any" min="0"' : '' ?><?= $type === 'date' ? ' max="' . date('Y-m-d') . '"' : '' ?> value="<?= e($cur) ?>">
                        <?php endif; ?>
                        <?php if ($help): ?><div class="form-text"><?= e($help) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($title === 'Notifications'): ?>
                    <button class="btn btn-light btn-sm" name="op" value="test_digest" formnovalidate<?= Mailer::configured() ? '' : ' disabled' ?>><i class="bi bi-send me-1"></i>Email me a test summary now</button>
                    <?php if (!Mailer::configured()): ?><span class="small text-danger ms-2">Mail is not configured in .env.</span><?php endif; ?>
                <?php endif; ?>
            </div></div></div>
        <?php endforeach; ?>

        <div class="col-12"><div class="card"><div class="card-header"><i class="bi bi-shield-lock me-2"></i>Security <span class="text-body-secondary fw-normal small">— shared with the website admin</span></div>
            <div class="card-body">
                <div class="row g-3 small">
                    <?php foreach (['sec_session_minutes' => 'Sign out after inactivity', 'sec_lockout_attempts' => 'Failed sign-ins before lock', 'sec_lockout_minutes' => 'Lock duration', 'sec_password_min' => 'Minimum password length'] as $k => $l): ?>
                        <div class="col-6 col-md-3"><div class="text-body-secondary"><?= $l ?></div><div class="fw-semibold"><?= e(Settings::get($k)) ?><?= str_contains($k, 'minutes') ? ' min' : '' ?><?= $k === 'sec_password_min' ? ' characters' : '' ?><?= $k === 'sec_lockout_attempts' ? ' attempts' : '' ?></div></div>
                    <?php endforeach; ?>
                    <div class="col-12"><div class="text-body-secondary">Two-factor required for administrators</div><div class="fw-semibold"><?= Settings::bool('sec_require_2fa') ? 'Yes' : 'No' ?></div></div>
                </div>
                <?php if ($ymUrl): ?><a class="btn btn-light btn-sm mt-3" href="<?= e($ymUrl) ?>/settings">Change in the website admin</a><?php endif; ?>
            </div></div></div>
    </div>
    <div class="mt-4"><button class="btn btn-gold px-4"><i class="bi bi-check2-circle me-1"></i>Save settings</button></div>
</form>
<?php admin_footer();
