<?php
declare(strict_types=1);

/**
 * Shared layout. Pages call:
 *   $user = Auth::require();            // gate (optionally with roles)
 *   admin_header('Title', 'nav-key');   // …page markup…
 *   admin_footer();
 */
require_once __DIR__ . '/../app/bootstrap.php';

use UMP\Core\Auth;

function admin_asset(string $path): string
{
    return asset('admin/' . $path);
}

/** Roles allowed to create/edit stock records. */
function can_record(): bool
{
    return Auth::is('admin', 'operator');
}

function admin_nav(): array
{
    $items = [
        ['Overview', [['dashboard', 'Dashboard', 'bi-speedometer2', null]]],
        ['Production', array_merge(
            can_record() ? [['record', 'Daily record', 'bi-pencil-square', null]] : [],
            [['records', 'Records', 'bi-table', null], ['reports', 'Reports', 'bi-bar-chart-line', null]]
        )],
    ];
    if (Auth::is('admin')) {
        $items[] = ['Administration', [
            ['users', 'People', 'bi-people', null],
            ['settings', 'Settings', 'bi-sliders', null],
            ['activity', 'Activity log', 'bi-clock-history', null],
            ['backup', 'Backup', 'bi-database-down', null],
        ]];
    }
    return $items;
}

function admin_header(string $title, string $active = '', string $head = ''): void
{
    $user = Auth::user();
    $initials = strtoupper(mb_substr($user['name'], 0, 1));
    ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> · <?= e(setting('system_name')) ?></title>
    <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= admin_asset('admin.css') ?>" rel="stylesheet">
    <?= $head ?>
    <script src="<?= admin_asset('theme.js') ?>"></script>
</head>
<body class="admin">
<div class="admin-shell">
    <aside class="admin-sidebar" id="sidebar">
        <a class="brand" href="<?= admin_url('dashboard') ?>">
            <span class="brand-chip"><img src="<?= asset('img/logo-mark.png') ?>" alt="" width="40" height="40"></span>
            <span><strong>UMP</strong><small><?= e(setting('company_name')) ?></small></span>
        </a>
        <nav>
            <?php foreach (admin_nav() as [$group, $links]): ?>
                <div class="nav-group"><?= e($group) ?></div>
                <?php foreach ($links as [$key, $label, $icon, $href]): ?>
                    <a class="nav-item<?= $active === $key ? ' active' : '' ?>" href="<?= admin_url($href ?? $key) ?>">
                        <i class="bi <?= $icon ?>"></i><span><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <?php $ymUrl = rtrim((string) \UMP\Core\Env::get('YM_URL', ''), '/'); if ($ymUrl && Auth::roleFor((int) $user['id'], 'site') !== null): ?>
            <a class="nav-item mt-auto" href="<?= e($ymUrl) ?>/dashboard"><i class="bi bi-globe2"></i><span>Website admin</span></a>
        <?php endif; ?>
        <div class="side-foot<?= $ymUrl ? ' mt-2' : '' ?>"><i class="bi bi-circle-fill text-success me-2"></i><span id="clock" class="font-monospace"></span></div>
    </aside>
    <div class="admin-backdrop" id="backdrop"></div>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="btn btn-icon d-lg-none" id="sidebarToggle" type="button" aria-label="Menu"><i class="bi bi-list fs-4"></i></button>
            <h1><?= e($title) ?></h1>
            <div class="ms-auto d-flex align-items-center gap-2">
                <button class="pal-btn d-none d-md-inline-flex" type="button" data-palette-open aria-label="Search and jump (Ctrl K)"><i class="bi bi-search"></i>Search <kbd>Ctrl K</kbd></button>
                <button class="btn btn-icon d-md-none" type="button" data-palette-open aria-label="Search"><i class="bi bi-search"></i></button>
                <button class="btn btn-icon" id="themeToggle" type="button" aria-label="Toggle dark mode"><i class="bi bi-moon-stars"></i></button>
                <div class="dropdown">
                    <button class="user-chip dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar"><?= e($initials) ?></span>
                        <span class="d-none d-md-block text-start"><strong><?= e($user['name']) ?></strong><small><?= e(ucfirst($user['role'])) ?></small></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="<?= admin_url('profile') ?>"><i class="bi bi-person me-2"></i>My profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= admin_url('logout') ?>"><?= csrf_field() ?>
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="admin-content">
            <?php foreach (flash() as $f): ?>
                <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : e($f['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
    <?php
}

function admin_footer(array $scripts = []): void
{
    ?>
        </main>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-body p-4 text-center">
            <i class="bi bi-exclamation-triangle text-danger display-5"></i>
            <h2 class="h5 mt-3" id="confirmTitle">Are you sure?</h2>
            <p class="text-body-secondary mb-4" id="confirmText">This cannot be undone.</p>
            <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmOk">Yes, continue</button>
        </div>
    </div></div>
</div>

<?php
$paletteItems = [];
foreach (admin_nav() as [$grp, $links]) {
    foreach ($links as [$key, $label, $icon, $href]) {
        $paletteItems[] = ['label' => $label, 'url' => admin_url($href ?? $key), 'icon' => $icon, 'group' => 'Go to'];
    }
}
if (can_record()) {
    $paletteItems[] = ['label' => "Record today's figures", 'url' => admin_url('record?date=' . date('Y-m-d')), 'icon' => 'bi-plus-circle', 'group' => 'Quick actions'];
}
$paletteItems[] = ['label' => "Print today's report", 'url' => admin_url('report-day?date=' . date('Y-m-d')), 'icon' => 'bi-printer', 'group' => 'Quick actions'];
$paletteItems[] = ['label' => 'Switch light / dark mode', 'action' => 'theme', 'icon' => 'bi-moon-stars', 'group' => 'Quick actions'];
?>
<script type="application/json" id="paletteData"><?= json_encode($paletteItems, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<div id="palette" data-search="<?= e(admin_url('search')) ?>" hidden role="dialog" aria-modal="true" aria-label="Search and jump">
    <div class="pal-box">
        <input type="text" placeholder="Jump to a page, a date (2025-02-27, today) or a GRV / GIV number…" autocomplete="off" aria-label="Search">
        <div class="pal-list"></div>
        <div class="pal-foot"><span><kbd>↑</kbd> <kbd>↓</kbd> move</span><span><kbd>Enter</kbd> open</span><span><kbd>Esc</kbd> close</span></div>
    </div>
</div>

<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<?php foreach ($scripts as $s): ?><script src="<?= e($s) ?>"></script><?php endforeach; ?>
<script src="<?= admin_asset('admin.js') ?>"></script>
<script src="<?= admin_asset('palette.js') ?>"></script>
</body>
</html>
    <?php
}

/** Reads the current POST's CSRF token or aborts. Use at the top of every state-changing handler. */
function admin_post(): void
{
    if (!is_post()) {
        http_response_code(405);
        exit('Method not allowed');
    }
    \UMP\Core\Csrf::check();
}

function status_badge(string $status): string
{
    $map = ['active' => 'success', 'disabled' => 'danger', 'ok' => 'success', 'warn' => 'warning'];
    return '<span class="badge text-bg-' . ($map[$status] ?? 'secondary') . '">' . e(ucfirst($status)) . '</span>';
}
