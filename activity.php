<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Auth;
use UMP\Core\DB;

Auth::require('admin');

$per = 25;
$page = max(1, (int) input('page', '1'));
$total = (int) DB::val("SELECT COUNT(*) FROM audit_logs WHERE app = 'ump'");
$pages = max(1, (int) ceil($total / $per));
$page = min($page, $pages);
$rows = DB::all(
    'SELECT a.*, u.name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.app = \'ump\' ORDER BY a.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per)
);
$pg = ['page' => $page, 'pages' => $pages];

$icons = ['create' => 'bi-plus-circle text-success', 'update' => 'bi-pencil text-primary', 'delete' => 'bi-trash text-danger',
    'login.success' => 'bi-box-arrow-in-right text-success', 'login.failed' => 'bi-shield-exclamation text-danger', 'logout' => 'bi-box-arrow-right', 'login.denied' => 'bi-slash-circle text-danger', '2fa.enabled' => 'bi-shield-check text-success', '2fa.disabled' => 'bi-shield-x text-danger', 'export' => 'bi-download', 'backup' => 'bi-database-down', 'rechain' => 'bi-link-45deg', 'lock' => 'bi-lock text-warning', 'attach' => 'bi-paperclip'];

admin_header('Activity log', 'activity');
?>
<div class="card">
    <?php if (!$rows): ?>
        <div class="empty-admin"><i class="bi bi-clock-history"></i>Nothing recorded yet.</div>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $a): ?>
                <tr>
                    <td class="small text-nowrap" title="<?= e($a['created_at']) ?>"><?= e(time_ago($a['created_at'])) ?></td>
                    <td><?= e($a['name'] ?? '—') ?></td>
                    <td class="text-nowrap"><i class="bi <?= $icons[$a['action']] ?? 'bi-dot' ?> me-1"></i><?= e($a['action']) ?><?= $a['entity'] ? ' · ' . e($a['entity']) : '' ?></td>
                    <td class="small text-body-secondary"><?= e($a['detail']) ?></td>
                    <td class="small text-body-secondary"><?= e($a['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="card-body py-2"><?php $pgClass = 'mb-0'; include __DIR__ . '/inc/pagination.php'; ?></div>
    <?php endif; ?>
</div>
<?php admin_footer();
