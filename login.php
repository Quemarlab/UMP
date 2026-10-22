<?php
declare(strict_types=1);

require __DIR__ . '/inc/auth-layout.php';

use UMP\Core\Auth;

$prefix = null;
// Where to go after signing in: ?next= (set by the page that asked for sign-in), else what this session remembered.
$next = safe_next(input('next'), $prefix) ?? safe_next($_SESSION['intended'] ?? null, $prefix);

if (Auth::check()) {
    redirect($next ?? admin_url('dashboard'));
}

if (is_post()) {
    admin_post();
    $result = Auth::attempt(input('email'), $_POST['password'] ?? '');
    if ($result['ok'] && !empty($result['twofactor'])) {
        $_SESSION['intended'] = $next;
        redirect(admin_url('login-2fa'));
    }
    if ($result['ok']) {
        unset($_SESSION['intended']);
        redirect($next ?? admin_url('dashboard'));
    }
    flash('error', $result['message']);
    $_SESSION['_old'] = ['email' => input('email')];
    redirect(admin_url('login' . ($next ? '?next=' . rawurlencode($next) : '')));
}

$oldEmail = old('email');
unset($_SESSION['_old']);

auth_page('Sign in', function () use ($oldEmail, $next) { ?>
    <h1 class="h3 mb-1">Welcome back</h1>
    <p class="text-body-secondary mb-4"><?= $next ? 'Sign in and we will take you straight to the page you asked for.' : 'Sign in to record and review production.' ?></p>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <?php if ($next): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" value="<?= e($oldEmail) ?>" required autofocus autocomplete="username">
        </div>
        <div class="mb-3">
            <div class="d-flex justify-content-between"><label class="form-label" for="password">Password</label>
                <a class="small" href="<?= admin_url('forgot-password') ?>">Forgot password?</a></div>
            <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-gold w-100 py-2 mt-2">Sign in</button>
    </form>
<?php });