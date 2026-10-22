<?php
declare(strict_types=1);

require __DIR__ . '/inc/auth-layout.php';

use UMP\Core\Auth;

$token = input('token');
$valid = preg_match('/^[a-f0-9]{64}$/', $token) && Auth::resetUserFromToken($token);

if ($valid && is_post()) {
    admin_post();
    $pw = $_POST['password'] ?? '';
    $error = Auth::passwordError($pw) ?? ($pw !== ($_POST['password_confirm'] ?? '') ? 'The passwords do not match.' : null);
    if ($error) {
        flash('error', $error);
        redirect(admin_url('reset-password?token=' . $token));
    }
    Auth::resetPassword($token, $pw);
    flash('success', 'Password updated. You can sign in now.');
    redirect(admin_url('login'));
}

auth_page('Choose a new password', function () use ($valid, $token) {
    if (!$valid): ?>
        <h1 class="h3 mb-2">Link expired</h1>
        <p class="text-body-secondary">This reset link is invalid or has expired.</p>
        <a class="btn btn-gold" href="<?= admin_url('forgot-password') ?>">Request a new link</a>
    <?php else: ?>
        <h1 class="h3 mb-1">Choose a new password</h1>
        <p class="text-body-secondary mb-4">At least 10 characters, with letters and numbers.</p>
        <form method="post" novalidate>
            <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3"><label class="form-label" for="password">New password</label>
                <input class="form-control" type="password" id="password" name="password" required minlength="10" autocomplete="new-password" autofocus></div>
            <div class="mb-3"><label class="form-label" for="pc">Confirm password</label>
                <input class="form-control" type="password" id="pc" name="password_confirm" required autocomplete="new-password"></div>
            <button class="btn btn-gold w-100 py-2">Update password</button>
        </form>
    <?php endif;
});
