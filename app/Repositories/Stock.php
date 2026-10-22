<?php
declare(strict_types=1);

namespace UMP\Repositories;

use UMP\Core\Audit;
use UMP\Core\DB;
use UMP\Core\Settings;

/** The daily stock ledger: one row per day, opening + received - issued = closing. */
final class Stock
{
    public const FIELDS = ['received_day', 'received_night', 'issued'];
    public const TEXT = ['grv_day', 'grv_night', 'giv', 'notes'];

    public static function find(string $date): ?array
    {
        return DB::one('SELECT * FROM stock_records WHERE record_date = ?', [$date]);
    }

    public static function findId(int $id): ?array
    {
        return DB::one('SELECT * FROM stock_records WHERE id = ?', [$id]);
    }

    public static function latest(): ?array
    {
        return DB::one('SELECT * FROM stock_records ORDER BY record_date DESC LIMIT 1');
    }

    public static function previous(string $date): ?array
    {
        return DB::one('SELECT * FROM stock_records WHERE record_date < ? ORDER BY record_date DESC LIMIT 1', [$date]);
    }

    /** Opening stock a record on $date should start with. */
    public static function openingFor(string $date): float
    {
        $prev = self::previous($date);
        return $prev ? (float) $prev['closing'] : Settings::float('opening_stock');
    }

    /** Parse a user-entered quantity ("1 234,5" / "1,234.5"); null when invalid or negative. */
    public static function num(mixed $v): ?float
    {
        $s = str_replace([' ', "\u{00A0}"], '', trim((string) $v));
        if ($s === '') {
            return 0.0;
        }
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) {
            $s = str_replace(',', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) && (float) $s >= 0 && (float) $s < 1e11 ? round((float) $s, 2) : null;
    }

    /**
     * Create or update the record for $input['record_date'].
     * @return array{ok:bool,errors:array<string,string>,record:?array,warnings:string[]}
     */
    public static function save(array $input, int $userId, bool $isAdmin): array
    {
        $errors = [];
        $warnings = [];
        $date = trim((string) ($input['record_date'] ?? ''));
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            return ['ok' => false, 'errors' => ['record_date' => 'Choose a valid date.'], 'record' => null, 'warnings' => []];
        }
        if ($date > date('Y-m-d')) {
            $errors['record_date'] = 'You cannot record a future date.';
        }
        if (self::readOnly()) {
            $errors['record_date'] = 'The system is in read-only mode. An administrator must switch it off in Settings.';
        } elseif (self::isLocked($date)) {
            $errors['record_date'] = 'This period is locked up to ' . fmt_date(self::lockedUntil()) . '. An administrator can unlock it in Settings.';
        }

        $existing = self::find($date);
        $nums = [];
        foreach (self::FIELDS as $f) {
            $n = self::num($input[$f] ?? '');
            if ($n === null) {
                $errors[$f] = 'Enter a number of zero or more.';
            }
            $nums[$f] = $n ?? 0.0;
        }
        $text = [];
        foreach (self::TEXT as $f) {
            $text[$f] = mb_substr(trim((string) ($input[$f] ?? '')), 0, $f === 'notes' ? 500 : 60);
        }

        $reason = mb_substr(trim((string) ($input['reason'] ?? '')), 0, 255);
        if ($existing) {
            if (!$isAdmin) {
                $window = (int) Settings::get('edit_window', '2');
                if ($date < date('Y-m-d', strtotime("-{$window} days"))) {
                    $errors['record_date'] = "Only administrators can change records older than {$window} day(s).";
                }
            }
            if ($reason === '') {
                $errors['reason'] = 'Say why you are changing this record.';
            }
        }

        $opening = $existing ? (float) $existing['opening'] : self::openingFor($date);
        $received = round($nums['received_day'] + $nums['received_night'], 2);
        $closing = round($opening + $received - $nums['issued'], 2);
        if ($closing < 0) {
            $errors['issued'] = 'Issued (' . qty($nums['issued']) . ') is more than the stock available (' . qty($opening + $received) . ').';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'record' => $existing, 'warnings' => []];
        }

        $row = ['opening' => $opening, 'received_day' => $nums['received_day'], 'received_night' => $nums['received_night'],
            'received_total' => $received, 'issued' => $nums['issued'], 'closing' => $closing] + $text;

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            if ($existing) {
                DB::run('INSERT INTO stock_revisions (record_id, user_id, action, before_json, reason) VALUES (?,?,?,?,?)',
                    [$existing['id'], $userId, 'update', json_encode(array_intersect_key($existing, $row + ['opening' => 1]), JSON_UNESCAPED_UNICODE), $reason]);
                DB::update('stock_records', (int) $existing['id'], $row + ['updated_by' => $userId]);
                $id = (int) $existing['id'];
                Audit::log('update', 'stock', $id, $date . ' — ' . $reason);
            } else {
                $id = DB::insert('stock_records', $row + ['record_date' => $date, 'created_by' => $userId, 'updated_by' => $userId]);
                DB::run('INSERT INTO stock_revisions (record_id, user_id, action, before_json, reason) VALUES (?,?,?,?,?)', [$id, $userId, 'create', null, '']);
                Audit::log('create', 'stock', $id, $date);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $next = DB::one('SELECT record_date, opening FROM stock_records WHERE record_date > ? ORDER BY record_date LIMIT 1', [$date]);
        if ($next && abs((float) $next['opening'] - $closing) > 0.004) {
            $warnings[] = 'The next recorded day (' . fmt_date($next['record_date']) . ') still opens with ' . qty($next['opening'])
                . '. An administrator can realign later days from the Reports page.';
        }
        return ['ok' => true, 'errors' => [], 'record' => self::findId($id), 'warnings' => $warnings];
    }

    // ------------------------------------------------------------------ listing
    /** @param array{from?:string,to?:string,q?:string} $f */
    private static function where(array $f): array
    {
        $w = ['1=1'];
        $p = [];
        if (!empty($f['from'])) { $w[] = 'record_date >= ?'; $p[] = $f['from']; }
        if (!empty($f['to']))   { $w[] = 'record_date <= ?'; $p[] = $f['to']; }
        if (!empty($f['q'])) {
            $w[] = '(grv_day LIKE ? OR grv_night LIKE ? OR giv LIKE ? OR notes LIKE ?)';
            array_push($p, ...array_fill(0, 4, '%' . addcslashes($f['q'], '%_\\') . '%'));
        }
        return [implode(' AND ', $w), $p];
    }

    public static function paginate(array $f, int $page, int $per = 20): array
    {
        [$w, $p] = self::where($f);
        $total = (int) DB::val("SELECT COUNT(*) FROM stock_records WHERE $w", $p);
        $pages = max(1, (int) ceil($total / $per));
        $page = max(1, min($page, $pages));
        $items = DB::all("SELECT * FROM stock_records WHERE $w ORDER BY record_date DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        return compact('items', 'total', 'page', 'pages', 'per');
    }

    public static function all(array $f): array
    {
        [$w, $p] = self::where($f);
        return DB::all("SELECT * FROM stock_records WHERE $w ORDER BY record_date DESC", $p);
    }

    public static function totals(array $f): array
    {
        [$w, $p] = self::where($f);
        return DB::one("SELECT COUNT(*) AS days, COALESCE(SUM(received_day),0) AS received_day, COALESCE(SUM(received_night),0) AS received_night,
            COALESCE(SUM(received_total),0) AS received_total, COALESCE(SUM(issued),0) AS issued FROM stock_records WHERE $w", $p);
    }

    public static function revisions(int $recordId): array
    {
        return DB::all('SELECT r.*, u.name AS user_name FROM stock_revisions r LEFT JOIN users u ON u.id = r.user_id WHERE r.record_id = ? ORDER BY r.id DESC', [$recordId]);
    }

    // ------------------------------------------------------------------ analytics
    /** Last $days calendar days (zero-filled); closing carries forward over unrecorded days. */
    public static function series(int $days = 30): array
    {
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $rows = array_column(DB::all('SELECT * FROM stock_records WHERE record_date >= ?', [$from]), null, 'record_date');
        $prev = self::previous($from);
        $carry = $prev ? (float) $prev['closing'] : Settings::float('opening_stock');
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $r = $rows[$d] ?? null;
            if ($r) {
                $carry = (float) $r['closing'];
            }
            $out[] = ['date' => $d, 'recorded' => (bool) $r, 'received' => $r ? (float) $r['received_total'] : 0.0,
                'issued' => $r ? (float) $r['issued'] : 0.0, 'closing' => $carry];
        }
        return $out;
    }

    public static function sum(string $from, string $to): array
    {
        return self::totals(['from' => $from, 'to' => $to]);
    }

    /** Month-to-date receipts (the old system's "MTD" was a never-resetting running total). */
    public static function mtd(?string $upTo = null): float
    {
        $upTo ??= date('Y-m-d');
        return (float) self::sum(date('Y-m-01', strtotime($upTo)), $upTo)['received_total'];
    }

    // ------------------------------------------------------------------ controls
    public static function readOnly(): bool
    {
        return Settings::bool('maintenance');
    }

    /** Last day (inclusive) closed for changes by an administrator, or null. */
    public static function lockedUntil(): ?string
    {
        $d = Settings::get('lock_until');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
    }

    public static function isLocked(string $date): bool
    {
        $l = self::lockedUntil();
        return $l !== null && $date <= $l;
    }

    // ------------------------------------------------------------------ insight
    /**
     * Month-end outlook from the run rate so far this month.
     * @return array{mtd:float,days_elapsed:int,days_in_month:int,projected:float,target:float,status:string,needed_per_day:?float}
     */
    public static function forecast(?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $first = date('Y-m-01', strtotime($today));
        $dim = (int) date('t', strtotime($today));
        $mtd = (float) self::sum($first, $today)['received_total'];
        // A day only counts as elapsed once it has a record or has finished.
        $elapsed = (int) date('j', strtotime($today)) - (self::find($today) ? 0 : 1);
        $elapsed = max(1, $elapsed);
        $projected = $mtd / $elapsed * $dim;
        $target = Settings::float('monthly_target');
        $remaining = $dim - (int) date('j', strtotime($today)) + (self::find($today) ? 0 : 1);
        $status = 'none';
        $needed = null;
        if ($target > 0) {
            $status = $projected >= $target ? 'on-track' : ($projected >= $target * 0.9 ? 'at-risk' : 'behind');
            $needed = $remaining > 0 ? max(0.0, ($target - $mtd) / $remaining) : null;
        }
        return ['mtd' => $mtd, 'days_elapsed' => $elapsed, 'days_in_month' => $dim, 'projected' => $projected, 'target' => $target, 'status' => $status, 'needed_per_day' => $needed];
    }

    /**
     * Recent days whose received total is far from the usual (mean ± sigma × standard deviation of the last 60 recorded days).
     * @return array<int,array{date:string,value:float,mean:float,z:float}>
     */
    public static function anomalies(int $withinDays = 30): array
    {
        $sigma = max(1.0, (float) Settings::get('anomaly_sigma', '2.5'));
        $rows = DB::all('SELECT record_date, received_total FROM stock_records ORDER BY record_date DESC LIMIT 60');
        if (count($rows) < 10) {
            return [];
        }
        $vals = array_map(fn($r) => (float) $r['received_total'], $rows);
        $mean = array_sum($vals) / count($vals);
        $var = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $vals)) / count($vals);
        $sd = sqrt($var);
        if ($sd < 0.0001) {
            return [];
        }
        $since = date('Y-m-d', strtotime("-$withinDays days"));
        $out = [];
        foreach ($rows as $r) {
            $z = ((float) $r['received_total'] - $mean) / $sd;
            if ($r['record_date'] >= $since && abs($z) >= $sigma) {
                $out[] = ['date' => $r['record_date'], 'value' => (float) $r['received_total'], 'mean' => $mean, 'z' => $z];
            }
        }
        return $out;
    }

    /** Map date => record for one calendar month (for the dashboard calendar). */
    public static function monthMap(string $ym): array
    {
        return array_column(DB::all('SELECT record_date, received_total, issued, closing FROM stock_records WHERE record_date BETWEEN ? AND ?',
            [$ym . '-01', date('Y-m-t', strtotime($ym . '-01'))]), null, 'record_date');
    }

    public static function monthly(int $year): array
    {
        $out = [];
        foreach (DB::all('SELECT * FROM stock_records WHERE YEAR(record_date) = ? ORDER BY record_date', [$year]) as $r) {
            $m = substr($r['record_date'], 0, 7);
            $o = &$out[$m];
            $o ??= ['ym' => $m, 'days' => 0, 'received_day' => 0.0, 'received_night' => 0.0, 'received_total' => 0.0, 'issued' => 0.0, 'closing' => 0.0];
            $o['days']++;
            foreach (['received_day', 'received_night', 'received_total', 'issued'] as $k) {
                $o[$k] += (float) $r[$k];
            }
            $o['closing'] = (float) $r['closing'];
            unset($o);
        }
        return array_values($out);
    }

    public static function years(): array
    {
        $y = array_map('intval', array_column(DB::all('SELECT DISTINCT YEAR(record_date) AS y FROM stock_records ORDER BY y DESC'), 'y'));
        return $y ?: [(int) date('Y')];
    }

    /** Past dates in the last $days days (not before the first record) that have no record, newest first. */
    public static function missingDates(int $days = 60): array
    {
        $first = DB::val('SELECT MIN(record_date) FROM stock_records');
        if (!$first) {
            return [];
        }
        $start = max($first, date('Y-m-d', strtotime("-$days days")));
        $have = array_flip(array_column(DB::all('SELECT record_date FROM stock_records WHERE record_date >= ?', [$start]), 'record_date'));
        $out = [];
        for ($t = strtotime($start); $t <= time(); $t = strtotime('+1 day', $t)) {
            $d = date('Y-m-d', $t);
            if (!isset($have[$d])) {
                $out[] = $d;
            }
        }
        return array_reverse($out);
    }

    /** Days whose opening stock differs from the previous day's closing. */
    public static function chainBreaks(): array
    {
        $out = [];
        $prev = null;
        foreach (DB::all('SELECT id, record_date, opening, closing, received_total, issued FROM stock_records ORDER BY record_date') as $r) {
            if ($prev !== null && abs((float) $r['opening'] - (float) $prev['closing']) > 0.004) {
                $out[] = ['date' => $r['record_date'], 'expected' => (float) $prev['closing'], 'found' => (float) $r['opening']];
            }
            // Closing must also equal opening + received - issued.
            if (abs((float) $r['opening'] + (float) $r['received_total'] - (float) $r['issued'] - (float) $r['closing']) > 0.004) {
                $out[] = ['date' => $r['record_date'], 'expected' => round((float) $r['opening'] + (float) $r['received_total'] - (float) $r['issued'], 2), 'found' => (float) $r['closing'], 'closing' => true];
            }
            $prev = $r;
        }
        return $out;
    }

    /** Make every day from $fromDate onward open with the previous day's closing. Returns days changed. */
    public static function rechain(string $fromDate, int $userId): int
    {
        if (self::readOnly() || (self::lockedUntil() !== null && self::lockedUntil() >= $fromDate)) {
            throw new \RuntimeException('Records in this period are locked or the system is read-only.');
        }
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        $changed = 0;
        try {
            $prev = self::previous($fromDate);
            $carry = $prev ? (float) $prev['closing'] : Settings::float('opening_stock');
            foreach (DB::all('SELECT * FROM stock_records WHERE record_date >= ? ORDER BY record_date', [$fromDate]) as $r) {
                $closing = round($carry + (float) $r['received_total'] - (float) $r['issued'], 2);
                if (abs((float) $r['opening'] - $carry) > 0.004 || abs((float) $r['closing'] - $closing) > 0.004) {
                    DB::run('INSERT INTO stock_revisions (record_id, user_id, action, before_json, reason) VALUES (?,?,?,?,?)',
                        [$r['id'], $userId, 'rechain', json_encode(['opening' => $r['opening'], 'closing' => $r['closing']]), 'Stock chain realigned']);
                    DB::update('stock_records', (int) $r['id'], ['opening' => $carry, 'closing' => $closing, 'updated_by' => $userId]);
                    $changed++;
                }
                $carry = $closing;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log('rechain', 'stock', null, "from $fromDate, $changed day(s)");
        return $changed;
    }
}
