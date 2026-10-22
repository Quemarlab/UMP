<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Audit;
use UMP\Core\Auth;
use UMP\Core\DB;
use UMP\Core\Totp;
use UMP\Core\Validator;
use UMP\Repositories\Users;

$me = Auth::require();
$row = DB::one('SELECT password_hash, totp_secret, totp_enabled_at, recovery_codes FROM users WHERE id = ?', [$me['id']]);
$twoFa = !empty($row['totp_enabled_at']);

if (is_post()) {
    admin_post();
    $op = input('op');
    $passwordOk = fn() => password_verify($_POST['current_password'] ?? '', $row['password_hash']);

    if ($op === 'profile') {
        $v = new Validator($_POST, ['name' => 'required|max:120', 'email' => 'required|email|max:190']);
        if ($v->fails()) {
            flash('error', $v->first());
        } elseif (Users::emailTaken($v->data()['email'], $me['id'])) {
            flash('error', 'That email is already in use.');
        } else {
            DB::update('users', $me['id'], ['name' => $v->data()['name'], 'email' => strtolower($v->data()['email'])]);
            Audit::log('update', 'user', $me['id'], 'profile');
            flash('success', 'Profile updated.');
        }
    } elseif ($op === 'password') {
        $new = $_POST['new_password'] ?? '';
        if (!$passwordOk()) {
            flash('error', 'Your current password is incorrect.');
        } elseif ($e = Auth::passwordError($new)) {
            flash('error', $e);
        } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
            flash('error', 'The new passwords do not match.');
        } else {
            DB::update('users', $me['id'], ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'password_changed_at' => date('Y-m-d H:i:s')]);
            session_regenerate_id(true);
            Audit::log('password.change', 'user', $me['id']);
            flash('success', 'Password changed.');
        }
    } elseif ($op === '2fa_start' && !$twoFa) {
        $_SESSION['2fa_setup'] = Totp::generateSecret();
        redirect(admin_url('profile#2fa'));
    } elseif ($op === '2fa_cancel') {
        unset($_SESSION['2fa_setup']);
    } elseif ($op === '2fa_enable' && !$twoFa && !empty($_SESSION['2fa_setup'])) {
        $step = Totp::verify($_SESSION['2fa_setup'], (string) ($_POST['code'] ?? ''));
        if ($step === null) {
            flash('error', 'That code did not match. Make sure the time on your phone is correct and try again.');
            redirect(admin_url('profile#2fa'));
        }
        $codes = Totp::recoveryCodes();
        DB::update('users', $me['id'], ['totp_secret' => $_SESSION['2fa_setup'], 'totp_enabled_at' => date('Y-m-d H:i:s'), 'totp_last_step' => $step,
            'recovery_codes' => json_encode(array_map([Totp::class, 'hashCode'], $codes))]);
        unset($_SESSION['2fa_setup']);
        $_SESSION['new_recovery'] = $codes;
        Audit::log('2fa.enabled', 'user', $me['id']);
        flash('success', 'Two-factor authentication is on.');
    } elseif ($op === '2fa_codes' && $twoFa) {
        if (!$passwordOk()) {
            flash('error', 'Your current password is incorrect.');
        } else {
            $codes = Totp::recoveryCodes();
            DB::update('users', $me['id'], ['recovery_codes' => json_encode(array_map([Totp::class, 'hashCode'], $codes))]);
            $_SESSION['new_recovery'] = $codes;
            Audit::log('2fa.codes_regenerated', 'user', $me['id']);
        }
    } elseif ($op === '2fa_disable' && $twoFa) {
        if (!$passwordOk()) {
            flash('error', 'Your current password is incorrect.');
        } else {
            DB::update('users', $me['id'], ['totp_secret' => null, 'totp_enabled_at' => null, 'totp_last_step' => 0, 'recovery_codes' => null]);
            Audit::log('2fa.disabled', 'user', $me['id']);
            flash('success', 'Two-factor authentication is off.');
        }
    }
    redirect(admin_url('profile' . (str_starts_with($op, '2fa') ? '#2fa' : '')));
}

$setup = $_SESSION['2fa_setup'] ?? null;
$recovery = $_SESSION['new_recovery'] ?? null;
unset($_SESSION['new_recovery']);
$left = $twoFa ? count(json_decode((string) $row['recovery_codes'], true) ?: []) : 0;
$full = Users::find($me['id']);

admin_header('My profile', '');
?>
<div class="row g-4" style="max-width:1000px">
    <div class="col-md-6">
        <form class="card" method="post" action="<?= admin_url('profile') ?>">
            <div class="card-header">Profile</div>
            <div class="card-body p-4">
                <?= csrf_field() ?><input type="hidden" name="op" value="profile">
                <div class="mb-3"><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" value="<?= e($me['name']) ?>" required></div>
                <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" value="<?= e($me['email']) ?>" required></div>
                <div class="mb-4"><span class="form-label d-block">Your access</span>
                    <?php foreach (Users::APPS as $app => $label): $r = $full[$app === 'site' ? 'site_role' : 'ump_role']; ?>
                        <div class="small"><?= e($label) ?>: <?= $r ? '<span class="badge text-bg-light">' . e(Users::ROLES[$app][$r]) . '</span>' : '<span class="text-body-secondary">no access</span>' ?></div>
                    <?php endforeach; ?></div>
                <button class="btn btn-gold">Save profile</button>
            </div>
        </form>
    </div>
    <div class="col-md-6">
        <form class="card" method="post" action="<?= admin_url('profile') ?>">
            <div class="card-header">Change password</div>
            <div class="card-body p-4">
                <?= csrf_field() ?><input type="hidden" name="op" value="password">
                <div class="mb-3"><label class="form-label" for="cp">Current password</label><input class="form-control" type="password" id="cp" name="current_password" required autocomplete="current-password"></div>
                <div class="mb-3"><label class="form-label" for="np">New password</label><input class="form-control" type="password" id="np" name="new_password" required minlength="<?= Auth::passwordMin() ?>" autocomplete="new-password">
                    <div class="form-text">At least <?= Auth::passwordMin() ?> characters with letters and numbers. One password works in every system you have access to.</div></div>
                <div class="mb-4"><label class="form-label" for="cf">Confirm new password</label><input class="form-control" type="password" id="cf" name="confirm_password" required autocomplete="new-password"></div>
                <button class="btn btn-dark">Update password</button>
            </div>
        </form>
    </div>

    <div class="col-12" id="2fa">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">Two-factor authentication
                <?= $twoFa ? '<span class="badge text-bg-success">On</span>' : '<span class="badge text-bg-secondary">Off</span>' ?></div>
            <div class="card-body p-4">
            <?php if ($recovery): ?>
                <div class="alert alert-warning"><strong>Save these recovery codes now.</strong> Each works once if you lose your phone. They will not be shown again.</div>
                <div class="row g-2 mb-3 font-monospace" style="max-width:420px"><?php foreach ($recovery as $c): ?><div class="col-6"><span class="badge text-bg-light w-100 py-2 fs-6"><?= e($c) ?></span></div><?php endforeach; ?></div>
            <?php endif; ?>

            <?php if ($twoFa): ?>
                <p class="mb-1">Signing in asks for a code from your authenticator app, in the website admin and in UMP.</p>
                <p class="text-body-secondary small">Recovery codes left: <strong><?= $left ?></strong></p>
                <div class="row g-3">
                    <div class="col-md-6"><form method="post" action="<?= admin_url('profile') ?>"><?= csrf_field() ?><input type="hidden" name="op" value="2fa_codes">
                        <label class="form-label small" for="rc">Password</label>
                        <div class="input-group"><input class="form-control" type="password" id="rc" name="current_password" required autocomplete="current-password"><button class="btn btn-light">New recovery codes</button></div></form></div>
                    <div class="col-md-6"><form method="post" action="<?= admin_url('profile') ?>" data-confirm="Turn off two-factor authentication?" data-confirm-text="Your account will be protected by the password only."><?= csrf_field() ?><input type="hidden" name="op" value="2fa_disable">
                        <label class="form-label small" for="dc">Password</label>
                        <div class="input-group"><input class="form-control" type="password" id="dc" name="current_password" required autocomplete="current-password"><button class="btn btn-outline-danger">Turn off</button></div></form></div>
                </div>
            <?php elseif ($setup): ?>
                <div class="row g-4 align-items-start">
                    <div class="col-md-4 text-center"><div id="qr" class="d-inline-block bg-white p-2 rounded" data-uri="<?= e(Totp::uri($setup, $me['email'], setting('company_name'))) ?>" style="width:200px;height:200px"></div></div>
                    <div class="col-md-8">
                        <ol class="ps-3">
                            <li>Open an authenticator app (Google Authenticator, Microsoft Authenticator, Authy …) and scan the code.</li>
                            <li>Or enter this key by hand: <code class="user-select-all"><?= e(implode(' ', str_split($setup, 4))) ?></code></li>
                            <li>Type the 6-digit code the app shows to confirm.</li>
                        </ol>
                        <form method="post" action="<?= admin_url('profile') ?>" class="d-flex flex-wrap gap-2"><?= csrf_field() ?><input type="hidden" name="op" value="2fa_enable">
                            <input class="form-control" style="max-width:180px;letter-spacing:.2em" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" required>
                            <button class="btn btn-gold">Turn on</button><button class="btn btn-link text-body-secondary" name="op" value="2fa_cancel" formnovalidate>Cancel</button></form>
                    </div>
                </div>
            <?php else: ?>
                <p class="mb-3">Add a second step to signing in. Even if someone learns your password, they cannot get in without your phone. Works in the website admin and in UMP.</p>
                <form method="post" action="<?= admin_url('profile') ?>"><?= csrf_field() ?><input type="hidden" name="op" value="2fa_start"><button class="btn btn-gold"><i class="bi bi-shield-lock me-1"></i>Set up two-factor</button></form>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php admin_footer($setup ? [asset('vendor/qrcode/qrcode.js'), admin_asset('twofa.js')] : []);