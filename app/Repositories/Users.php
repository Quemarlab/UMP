<?php
declare(strict_types=1);

namespace UMP\Repositories;

use UMP\Core\DB;

/** Accounts are shared by every application; what a person may do in each one lives in user_access. */
final class Users
{
    /** app => role => label. 'site' is the website admin panel. */
    public const ROLES = [
        'site' => ['admin' => 'Administrator', 'editor' => 'Editor'],
        'ump'  => ['admin' => 'Administrator', 'operator' => 'Operator', 'viewer' => 'Viewer'],
    ];

    public const APPS = ['site' => 'Website admin', 'ump' => 'UMP production system'];

    private const COLUMNS = 'u.id, u.name, u.email, u.status, u.last_login_at, u.created_at, u.totp_enabled_at, u.password_changed_at,
        s.role AS site_role, m.role AS ump_role, m.notify AS ump_notify';
    private const JOINS = "LEFT JOIN user_access s ON s.user_id = u.id AND s.app = 'site' LEFT JOIN user_access m ON m.user_id = u.id AND m.app = 'ump'";

    public static function all(): array
    {
        return DB::all('SELECT ' . self::COLUMNS . ' FROM users u ' . self::JOINS . ' ORDER BY u.id');
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT ' . self::COLUMNS . ' FROM users u ' . self::JOINS . ' WHERE u.id = ?', [$id]);
    }

    public static function emailTaken(string $email, int $ignoreId = 0): bool
    {
        return (bool) DB::val('SELECT id FROM users WHERE email = ? AND id <> ?', [strtolower($email), $ignoreId]);
    }

    /** @param array<string,string> $access app => role, e.g. ['site' => 'admin', 'ump' => 'viewer'] */
    public static function create(string $name, string $email, string $password, array $access): int
    {
        $id = DB::insert('users', [
            'name' => $name,
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'password_changed_at' => date('Y-m-d H:i:s'),
        ]);
        foreach ($access as $app => $role) {
            self::setAccess($id, $app, $role);
        }
        return $id;
    }

    /** Grant, change or (with null/'') remove a user's role in one application. */
    public static function setAccess(int $userId, string $app, ?string $role, ?bool $notify = null): void
    {
        if (!isset(self::ROLES[$app])) {
            throw new \InvalidArgumentException("Unknown application: $app");
        }
        if ($role === null || $role === '') {
            DB::run('DELETE FROM user_access WHERE user_id = ? AND app = ?', [$userId, $app]);
            return;
        }
        if (!isset(self::ROLES[$app][$role])) {
            throw new \InvalidArgumentException("Unknown role '$role' for $app");
        }
        DB::run(
            'INSERT INTO user_access (user_id, app, role, notify) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role)' . ($notify === null ? '' : ', notify = VALUES(notify)'),
            [$userId, $app, $role, (int) ($notify ?? false)]
        );
    }

    /** Active administrators of $app other than $excludeId: stops the last one being demoted, disabled or deleted. */
    public static function activeAdmins(int $excludeId = 0, string $app = 'site'): int
    {
        return (int) DB::val(
            "SELECT COUNT(*) FROM users u JOIN user_access a ON a.user_id = u.id AND a.app = ? AND a.role = 'admin'
             WHERE u.status = 'active' AND u.id <> ?",
            [$app, $excludeId]
        );
    }

    /** Active users with access to $app, for directories and notification lists. */
    public static function forApp(string $app): array
    {
        return DB::all(
            "SELECT u.id, u.name, u.email, u.status, u.last_login_at, u.totp_enabled_at, a.role, a.notify
             FROM users u JOIN user_access a ON a.user_id = u.id AND a.app = ? ORDER BY u.name",
            [$app]
        );
    }

    public static function delete(int $id): void
    {
        DB::run('DELETE FROM users WHERE id = ?', [$id]);
    }
}
