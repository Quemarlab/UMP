<?php
declare(strict_types=1);

namespace UMP\Core;

use UMP\Repositories\Stock;
use UMP\Repositories\Users;

/** Emails: the daily summary, low-stock alerts and "no record yet today" reminders. Driven by bin/cron.php. */
final class Notifier
{
    /** @return array<string,string> email => name: users with UMP access who switched notifications on, plus the extra addresses from Settings. */
    public static function recipients(bool $recorders = false): array
    {
        $out = [];
        foreach (Users::forApp('ump') as $u) {
            if ($u['status'] === 'active' && $u['notify'] && (!$recorders || in_array($u['role'], ['admin', 'operator'], true))) {
                $out[$u['email']] = $u['name'];
            }
        }
        foreach (array_filter(array_map('trim', explode(',', Settings::get('alert_emails')))) as $addr) {
            if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $out[strtolower($addr)] = $out[strtolower($addr)] ?? '';
            }
        }
        return $out;
    }

    /** @param array<string,string> $to */
    private static function mail(array $to, string $subject, string $html): bool
    {
        $ok = false;
        foreach ($to as $email => $name) {
            $ok = Mailer::send($email, $subject, $html, null, $name ?: null) || $ok;
        }
        return $ok;
    }

    private static function button(string $label, string $path): string
    {
        return '<p><a href="' . e(abs_url($path)) . '" style="background:#d4a017;color:#1d2124;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:600">' . e($label) . '</a></p>';
    }

    private static function row(string $label, string $value, bool $strong = false): string
    {
        return '<tr><td style="padding:6px 0;color:#777">' . e($label) . '</td><td style="padding:6px 0;text-align:right;' . ($strong ? 'font-weight:700' : '') . '">' . $value . '</td></tr>';
    }

    /** @param array<string,string> $to */
    public static function sendDigest(array $to, bool $test = false): bool
    {
        $unit = Settings::get('unit');
        $latest = Stock::latest();
        $today = date('Y-m-d');
        $rec = Stock::find($today);
        $f = Stock::forecast();
        $html = '<p>Here is the ' . e(Settings::get('system_name')) . ' summary for <strong>' . e(fmt_date($today, 'l, j F Y')) . '</strong>.</p>'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border-top:1px solid #eee">'
            . self::row('Closing stock' . ($latest ? ' (' . fmt_date($latest['record_date'], 'j M') . ')' : ''), $latest ? qty($latest['closing']) . ' ' . e($unit) : '—', true)
            . self::row('Received today', $rec ? qty($rec['received_total']) . ' ' . e($unit) : 'not recorded yet')
            . self::row('Issued today', $rec ? qty($rec['issued']) . ' ' . e($unit) : '—')
            . self::row('Received this month', qty($f['mtd']) . ' ' . e($unit))
            . ($f['target'] > 0 ? self::row('Month-end forecast', qty($f['projected']) . ' of ' . qty($f['target']) . ' ' . e($unit) . ' target (' . str_replace('-', ' ', $f['status']) . ')') : '')
            . '</table>';

        $notes = [];
        $low = Settings::float('low_stock');
        if ($latest && $low > 0 && (float) $latest['closing'] <= $low) {
            $notes[] = 'Stock is at or below the alert level (' . qty($low) . ' ' . e($unit) . ').';
        }
        foreach (array_slice(Stock::anomalies(14), 0, 3) as $a) {
            $notes[] = fmt_date($a['date']) . ' looked unusual: ' . qty($a['value']) . ' ' . e($unit) . ' received (usual ≈ ' . qty($a['mean'], 0) . ').';
        }
        if ($missing = array_filter(Stock::missingDates(7), fn($d) => $d < $today)) {
            $notes[] = count($missing) . ' day(s) in the last week have no record.';
        }
        if ($notes) {
            $html .= '<p style="margin-top:18px"><strong>Worth a look</strong></p><ul style="padding-left:18px;color:#444"><li>' . implode('</li><li>', $notes) . '</li></ul>';
        }
        $html .= self::button('Open the dashboard', 'dashboard');
        return self::mail($to, ($test ? '[Test] ' : '') . 'UMP daily summary — ' . fmt_date($today, 'j M Y'), $html);
    }

    /** Run by cron every hour. Each message is sent at most once per day. @return string[] what happened */
    public static function run(): array
    {
        $log = [];
        $today = date('Y-m-d');
        $hour = (int) date('G');
        $latest = Stock::latest();

        if (Settings::bool('digest_enabled') && $hour >= (int) Settings::get('digest_hour', '18') && Settings::get('last_digest') !== $today) {
            if ($to = self::recipients()) {
                $sent = self::sendDigest($to);
                $log[] = 'daily summary ' . ($sent ? 'sent to ' . count($to) : 'FAILED');
                if ($sent) {
                    Settings::set('last_digest', $today);
                }
            }
        }

        $low = Settings::float('low_stock');
        if ($latest && $low > 0 && (float) $latest['closing'] <= $low && Settings::get('last_low_alert') !== $today && ($to = self::recipients())) {
            $sent = self::mail($to, 'UMP alert: stock is low (' . qty($latest['closing']) . ' ' . Settings::get('unit') . ')',
                '<p>Closing stock on ' . e(fmt_date($latest['record_date'])) . ' is <strong>' . qty($latest['closing']) . ' ' . e(Settings::get('unit')) . '</strong>, at or below the alert level of ' . qty($low) . '.</p>' . self::button('Open the dashboard', 'dashboard'));
            $log[] = 'low-stock alert ' . ($sent ? 'sent' : 'FAILED');
            if ($sent) {
                Settings::set('last_low_alert', $today);
            }
        }

        if ($hour >= (int) Settings::get('record_deadline', '17') && !Stock::find($today) && !Stock::readOnly() && Settings::get('last_reminder') !== $today && ($to = self::recipients(true))) {
            $sent = self::mail($to, 'UMP reminder: today has no record yet',
                '<p>No stock record has been entered for <strong>' . e(fmt_date($today, 'l, j F')) . '</strong> yet.</p>' . self::button('Record today', 'record'));
            $log[] = 'recording reminder ' . ($sent ? 'sent' : 'FAILED');
            if ($sent) {
                Settings::set('last_reminder', $today);
            }
        }
        return $log ?: ['nothing to send'];
    }
}
