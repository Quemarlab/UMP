<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Auth;
use UMP\Repositories\Stock;

// JSON lookups for the Ctrl+K command palette: jump to a date, or find records by GRV / GIV / notes.
if (!Auth::peek()) {
    json_response(['ok' => false], 401);
}
Auth::require();

$q = mb_substr(trim(input('q')), 0, 60);
$out = ['ok' => true, 'date' => null, 'records' => []];

$date = null;
$lq = strtolower($q);
if ($lq === 'today') {
    $date = date('Y-m-d');
} elseif ($lq === 'yesterday') {
    $date = date('Y-m-d', strtotime('-1 day'));
} elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $q, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
    $date = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
} elseif (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $q, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
    $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
}
if ($date !== null && $date <= date('Y-m-d')) {
    $rec = Stock::find($date);
    $out['date'] = [
        'label' => ($rec ? 'Open the record for ' : (can_record() ? 'Record ' : 'No record for ')) . fmt_date($date, 'l, j F Y'),
        'url' => admin_url(($rec || can_record() ? 'record?date=' : 'records?from=') . $date . (($rec || can_record()) ? '' : '&to=' . $date)),
        'exists' => (bool) $rec,
    ];
}

if (mb_strlen($q) >= 2) {
    foreach (Stock::paginate(['q' => $q], 1, 6)['items'] as $r) {
        $out['records'][] = [
            'label' => fmt_date($r['record_date'], 'D j M Y'),
            'sub' => 'received ' . qty($r['received_total']) . ' · issued ' . qty($r['issued']) . ' · closing ' . qty($r['closing'])
                . ($r['grv_day'] || $r['grv_night'] || $r['giv'] ? ' · ' . trim(implode(' ', array_filter([$r['grv_day'], $r['grv_night'], $r['giv']]))) : ''),
            'url' => admin_url('record?date=' . $r['record_date']),
        ];
    }
}
json_response($out);
