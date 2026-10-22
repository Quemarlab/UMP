<?php
declare(strict_types=1);

/**
 * CLI installer / migrator.
 *
 *   php database/install.php                         create/upgrade the tables (safe to repeat)
 *   php database/install.php --admin=you@mail.com    also create (or reset) an administrator; prints a generated password
 *   php database/install.php --merge-from=ump        import users, records, settings and history from the separate `ump`
 *                                                    database this system used before it shared one database with the website
 *   php database/install.php --migrate-legacy        copy the very first version's tables (`data`, `settings`) into the ledger
 *
 * Nothing is dropped. Remove old tables later with  php database/cleanup-legacy.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use UMP\Core\DB;
use UMP\Core\Env;
use UMP\Core\Migrator;
use UMP\Core\Settings;
use UMP\Repositories\Stock;
use UMP\Repositories\Users;

$opts = getopt('', ['admin::', 'name::', 'migrate-legacy', 'merge-from:']);
$ok = "\u{2714}";

// 1. Schema + migrations -------------------------------------------------------
$sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(__DIR__ . '/schema.sql'));
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    DB::pdo()->exec($statement);
}
foreach (Migrator::run(__DIR__ . '/migrations') as $id) {
    echo "$ok Migration applied: $id\n";
}
echo "$ok Schema ready (database `" . Env::get('DB_NAME', 'ump') . "`)\n";

// 2. Administrator --------------------------------------------------------------
if (!empty($opts['admin'])) {
    $email = strtolower((string) $opts['admin']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "Invalid email for --admin\n");
        exit(1);
    }
    $password = bin2hex(random_bytes(6)) . 'A7';
    $existing = DB::one('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing) {
        DB::update('users', (int) $existing['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'status' => 'active']);
        Users::setAccess((int) $existing['id'], 'ump', 'admin');
        echo "$ok Existing account reset and given UMP administrator access: $email\n";
    } else {
        Users::create($opts['name'] ?? 'Administrator', $email, $password, ['ump' => 'admin']);
        echo "$ok Administrator created: $email\n";
    }
    echo "  Temporary password: $password\n  (change it after signing in; the same account works in the website admin if it has access there)\n";
}

// 3. Merge from the separate `ump` database ---------------------------------------
if (!empty($opts['merge-from'])) {
    $srcName = (string) $opts['merge-from'];
    if ($srcName === Env::get('DB_NAME', 'ump')) {
        fwrite(STDERR, "The source database is the one already in use; nothing to merge.\n");
        exit(1);
    }
    $src = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', Env::get('DB_HOST', 'localhost'), $srcName), Env::get('DB_USER', 'root'), Env::get('DB_PASS', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ((int) DB::val('SELECT COUNT(*) FROM stock_records') > 0) {
        echo "• stock_records is not empty — skipping the merge to avoid duplicates.\n";
        exit(0);
    }

    // Accounts: match by email so a person keeps ONE account; copy any that do not exist yet.
    $userMap = [];
    foreach ($src->query('SELECT * FROM users')->fetchAll() as $u) {
        $target = DB::one('SELECT id FROM users WHERE email = ?', [$u['email']]);
        if ($target) {
            $tid = (int) $target['id'];
        } else {
            $tid = DB::insert('users', ['name' => $u['name'], 'email' => $u['email'], 'password_hash' => $u['password_hash'], 'status' => $u['status'],
                'last_login_at' => $u['last_login_at'], 'created_at' => $u['created_at']]);
        }
        $userMap[(int) $u['id']] = $tid;
        Users::setAccess($tid, 'ump', $u['role']);
    }
    echo "$ok Accounts matched/created: " . count($userMap) . "\n";

    $recMap = [];
    foreach ($src->query('SELECT * FROM stock_records ORDER BY record_date')->fetchAll() as $r) {
        $old = (int) $r['id'];
        unset($r['id']);
        $r['created_by'] = $userMap[(int) $r['created_by']] ?? null;
        $r['updated_by'] = $userMap[(int) $r['updated_by']] ?? null;
        $recMap[$old] = DB::insert('stock_records', $r);
    }
    $rev = 0;
    foreach ($src->query('SELECT * FROM stock_revisions ORDER BY id')->fetchAll() as $r) {
        if (!isset($recMap[(int) $r['record_id']])) {
            continue;
        }
        unset($r['id']);
        $r['record_id'] = $recMap[(int) $r['record_id']];
        $r['user_id'] = $userMap[(int) $r['user_id']] ?? null;
        DB::insert('stock_revisions', $r);
        $rev++;
    }
    echo "$ok Records imported: " . count($recMap) . " (+ $rev history entries)\n";

    $set = 0;
    foreach ($src->query('SELECT skey, value FROM site_settings')->fetchAll() as $s) {
        $key = str_starts_with($s['skey'], 'sec_') ? $s['skey'] : 'ump_' . $s['skey'];
        if (!DB::val('SELECT COUNT(*) FROM site_settings WHERE skey = ?', [$key])) {
            DB::insert('site_settings', ['skey' => $key, 'value' => $s['value']]);
            $set++;
        }
    }
    echo "$ok Settings copied: $set\n";

    $log = 0;
    foreach ($src->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll() as $a) {
        unset($a['id']);
        $a['app'] = 'ump';
        $a['user_id'] = $userMap[(int) $a['user_id']] ?? null;
        DB::insert('audit_logs', $a);
        $log++;
    }
    echo "$ok Activity entries copied: $log\n";
    echo "  The old `$srcName` database was not changed or dropped.\n";
}

// 4. Very first version's tables ---------------------------------------------------
if (isset($opts['migrate-legacy'])) {
    $has = fn(string $t) => Migrator::hasTable($t);
    if (!$has('data')) {
        echo "• No legacy `data` table found — nothing to migrate.\n";
        exit(0);
    }
    if ((int) DB::val('SELECT COUNT(*) FROM stock_records') > 0) {
        echo "• stock_records is not empty — skipping migration to avoid duplicates.\n";
        exit(0);
    }
    if ($has('settings') && ($s = DB::one('SELECT opening_stock FROM settings ORDER BY id LIMIT 1'))) {
        Settings::set('opening_stock', (string) (Stock::num($s['opening_stock']) ?? 0));
    }
    $rows = [];
    foreach (DB::all('SELECT * FROM data ORDER BY id') as $r) {
        $d = trim((string) $r['date']);
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        if ($dt && $dt->format('Y-m-d') === $d) {
            $rows[$d] = $r; // the newest entry wins where a date was saved twice
        }
    }
    ksort($rows);
    $n = fn($v) => Stock::num($v) ?? 0.0;
    foreach ($rows as $d => $r) {
        $id = DB::insert('stock_records', ['record_date' => $d, 'opening' => $n($r['opening_stock']), 'received_day' => $n($r['recieved_day']),
            'received_night' => $n($r['recieved_night']), 'received_total' => $n($r['recieved_total']), 'issued' => $n($r['issued']), 'closing' => $n($r['closed_stock']),
            'grv_day' => mb_substr((string) $r['grv_day'], 0, 60), 'grv_night' => mb_substr((string) $r['grv_night'], 0, 60), 'giv' => mb_substr((string) $r['giv'], 0, 60),
            'legacy_code' => mb_substr((string) $r['code'], 0, 40), 'created_at' => $r['recorded_on'] ?: date('Y-m-d H:i:s')]);
        DB::run("INSERT INTO stock_revisions (record_id, action, reason) VALUES (?, 'create', 'Imported from the first version')", [$id]);
    }
    echo "$ok Imported " . count($rows) . " daily record(s) from the first version\n";
}
echo "Done.\n";
