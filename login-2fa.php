<?php
declare(strict_types=1);

require __DIR__ . '/inc/auth-layout.php';

use UMP\Core\Auth;

if (Auth::check()) {
    redirect(admin_url('dashboard'));
}
if (!Auth::twoFactorPending()) {
    redirect(admin_url('login'));
}

if (is_post()) {
    admin_post();
    if (input('cancel') !== '') {
        Auth::cancelTwoFactor();
        redirect(admin_url('login'));
    }
    if (Auth::verifyTwoFactor((string) ($_POST['code'] ?? ''))) {
        $to = safe_next($_SESSION['intended'] ?? null, null);
        unset($_SESSION['intended']);
        redirect($to ?? admin_url('dashboard'));
    }
    flash('error', Auth::twoFactorPending() ? 'That code is not valid. Check the time on your phone and try again.' : 'The verification expired. Sign in again.');
    redirect(admin_url(Auth::twoFactorPending() ? 'login-2fa' : 'login'));
}

auth_page('Verification code', function () { ?>
    <h1 class="h3 mb-1">Two-step verification</h1>
    <p class="text-body-secondary mb-4">Enter the 6-digit code from your authenticator app. Lost your phone? Use one of your recovery codes instead.</p>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="code">Verification or recovery code</label>
            <input class="form-control form-control-lg text-center" id="code" name="code" inputmode="text" autocomplete="one-time-code" required autofocus maxlength="12" placeholder="123456" style="letter-spacing:.25em">
        </div>
        <button class="btn btn-gold w-100 py-2">Verify and sign in</button>
        <button class="btn btn-link w-100 mt-2 text-body-secondary" name="cancel" value="1" formnovalidate>&larr; Use a different account</button>
    </form>
<?php });