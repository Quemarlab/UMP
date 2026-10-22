<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Auth;
use UMP\Core\Env;
use UMP\Repositories\Users;

$me = Auth::require('admin');
$people = Users::forApp('ump');
$ymUrl = rtrim((string) Env::get('YM_URL', ''), '/');
$canManage = $ymUrl !== '' && Auth::roleFor((int) $me['id'], 'site') === 'admin';

admin_header('People', 'users');
?>
<div class="alert alert-info d-flex flex-wrap align-items-center gap-2" role="alert">
    <i class="bi bi-info-circle"></i>
    <span class="flex-grow-1">Accounts for UMP and the website are created and managed in one place: the website admin. This list shows everyone who can sign in here.</span>
    <?php if ($canManage): ?><a class="btn btn-sm btn-gold" href="<?= e($ymUrl) ?>/users">Manage users</a><?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Name</th><th>UMP role</th><th>2FA</th><th>Daily summary</th><th>Status</th><th>Last sign-in</th></tr></thead>
    <tbody>
    <?php foreach ($people as $p): ?>
        <tr>
            <td><div class="d-flex align-items-center gap-2"><span class="avatar" style="width:36px;height:36px"><?= e(strtoupper(mb_substr($p['name'], 0, 1))) ?></span>
                <div><strong><?= e($p['name']) ?></strong><?= $p['id'] === $me['id'] ? ' <span class="badge text-bg-light">you</span>' : '' ?><div class="small text-body-secondary"><?= e($p['email']) ?></div></div></div></td>
            <td><span class="badge text-bg-<?= $p['role'] === 'admin' ? 'dark' : 'secondary' ?>"><?= e(Users::ROLES['ump'][$p['role']] ?? $p['role']) ?></span></td>
            <td><?= $p['totp_enabled_at'] ? '<i class="bi bi-shield-check text-success" title="Two-factor is on"></i>' : '<i class="bi bi-shield text-body-secondary" title="Off"></i>' ?></td>
            <td><?= $p['notify'] ? '<i class="bi bi-envelope-check text-success"></i>' : '<span class="text-body-secondary small">—</span>' ?></td>
            <td><?= status_badge($p['status']) ?></td>
            <td class="small text-body-secondary"><?= $p['last_login_at'] ? e(time_ago($p['last_login_at'])) : 'Never' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<div class="row g-3 mt-1">
    <?php foreach ([['Administrator', 'bi-shield-lock', 'Everything: settings, record locking, backups, all records.'],
        ['Operator', 'bi-pencil-square', 'Records daily figures, attaches documents, sees reports. Cannot change settings.'],
        ['Viewer', 'bi-eye', 'Read-only: dashboard, records and reports.']] as [$t, $ic, $d]): ?>
        <div class="col-md-4"><div class="card card-body h-100"><div class="fw-semibold"><i class="bi <?= $ic ?> me-1 text-warning"></i><?= $t ?></div><div class="small text-body-secondary"><?= $d ?></div></div></div>
    <?php endforeach; ?>
</div>
<?php admin_footer();