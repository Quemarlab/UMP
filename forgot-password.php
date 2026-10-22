<?php
declare(strict_types=1);

require __DIR__ . '/inc/auth-layout.php';

use UMP\Core\Auth;
use UMP\Core\Mailer;
use UMP\Core\RateLimiter;

if (is_post()) {
    admin_post();
    $email = input('email');
    if (RateLimiter::hit('reset:' . client_ip(), 5, 3600) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reset = Auth::createResetToken($email);
        if ($reset) {
            $link = abs_url('reset-password?token=' . $reset['token']);
            Mailer::send(
                $reset['user']['email'],
                'Reset your password',
                '<p>Hello ' . e($reset['user']['name']) . ',</p><p>We received a request to reset your admin password. This link is valid for one hour:</p>'
                . '<p><a href="' . e($link) . '" style="background:#d4a017;color:#1d2124;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:600">Choose a new password</a></p>'
                . '<p style="color:#888;font-size:13px">If you didn\'t request this, you can safely ignore this email.</p>'
            );
        }
    }
    // Same answer whether or not the account exists (prevents account enumeration).
    flash('success', 'If that email belongs to an account, a reset link is on its way.');
    redirect(admin_url('forgot-password'));
}

auth_page('Forgot password', function () { ?>
    <h1 class="h3 mb-1">Reset your password</h1>
    <p class="text-body-secondary mb-4">Enter your account email and we'll send you a reset link.</p>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" required autofocus></div>
        <button class="btn btn-gold w-100 py-2">Send reset link</button>
    </form>
    <p class="text-center mt-4 mb-0"><a class="small" href="<?= admin_url('login') ?>">&larr; Back to sign in</a></p>
<?php });
