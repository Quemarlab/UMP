<?php
declare(strict_types=1);

namespace UMP\Core;

/**
 * Key/value settings in the shared `site_settings` table.
 * UMP's own settings are stored with a "ump_" prefix; the sec_* security settings are shared with the
 * website admin (edited there) and are read here unprefixed.
 */
final class Settings
{
    /** key => [label, default, type, help]. Types: text, number, bool, date, textarea. */
    public const FIELDS = [
        'company_name'    => ['Company name', 'Young Masters LTD', 'text', ''],
        'system_name'     => ['System name', 'Unprocessed Materials Production', 'text', ''],
        'unit'            => ['Unit of measure', 'kg', 'text', 'Shown next to every quantity (kg, t …).'],
        'decimals'        => ['Decimal places shown', '2', 'number', '0 to 3. Figures are always stored to 2 decimals.'],
        'opening_stock'   => ['Starting stock', '0', 'number', 'Opening stock for the very first day. Later days start from the previous closing.'],
        'monthly_target'  => ['Monthly receipt target', '0', 'number', 'Used for the progress bar and the month-end forecast. 0 hides them.'],
        'low_stock'       => ['Low-stock alert level', '0', 'number', 'Warn on the dashboard and by email when closing stock falls to or below this. 0 turns it off.'],
        'edit_window'     => ['Operator edit window (days)', '2', 'number', 'Operators may change a record up to this many days after its date. Administrators are not limited by this.'],
        'lock_until'      => ['Records locked up to (date)', '', 'date', 'Month-end close: nobody can create or change records dated on or before this day. Clear it to unlock.'],
        'maintenance'     => ['Read-only mode', '0', 'bool', 'Blocks all recording and editing (for audits or migrations). Everyone can still view and export.'],
        'record_deadline' => ['Daily recording reminder after (hour, 0–23)', '17', 'number', 'After this hour the dashboard — and the email reminder — flags a day with no record yet.'],
        'anomaly_sigma'   => ['Unusual-day sensitivity', '2.5', 'number', 'A day is flagged when received stock is this many standard deviations from the 30-day average. Lower = more flags.'],
        'digest_enabled'  => ['Email the daily summary', '0', 'bool', 'Sent by the scheduled task (see README) to everyone with UMP access who has notifications switched on.'],
        'digest_hour'     => ['Daily summary hour (0–23)', '18', 'number', ''],
        'alert_emails'    => ['Extra recipients', '', 'text', 'Comma-separated email addresses that also receive the summary and alerts (people without accounts).'],

        // Shared with the website admin — edited there, read-only here.
        'sec_session_minutes'  => ['Sign out after inactivity (minutes)', '120', 'number', ''],
        'sec_lockout_attempts' => ['Failed sign-ins before an account is locked', '5', 'number', ''],
        'sec_lockout_minutes'  => ['Lock duration (minutes)', '15', 'number', ''],
        'sec_password_min'     => ['Minimum password length', '10', 'number', ''],
        'sec_require_2fa'      => ['Require two-factor authentication for administrators', '0', 'bool', ''],
    ];

    private static ?array $cache = null;

    private static function storageKey(string $key): string
    {
        return str_starts_with($key, 'sec_') ? $key : 'ump_' . $key;
    }

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (DB::all('SELECT skey, value FROM site_settings') as $row) {
                    self::$cache[$row['skey']] = $row['value'];
                }
            } catch (\Throwable) {
                // Table missing (fresh checkout): fall back to defaults.
            }
        }
        return self::$cache;
    }

    /** Stored value for a field, '' when never saved (no default applied). */
    public static function stored(string $key): string
    {
        return self::all()[self::storageKey($key)] ?? '';
    }

    public static function get(string $key, string $default = ''): string
    {
        $v = self::stored($key);
        if ($v !== '') {
            return $v;
        }
        return self::FIELDS[$key][1] ?? $default;
    }

    public static function float(string $key): float
    {
        return (float) self::get($key, '0');
    }

    public static function bool(string $key): bool
    {
        return self::get($key, '0') === '1';
    }

    public static function set(string $key, string $value): void
    {
        DB::run('INSERT INTO site_settings (skey, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [self::storageKey($key), $value]);
        self::$cache = null;
    }
}
