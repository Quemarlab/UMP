<?php
declare(strict_types=1);

namespace UMP\Core;

/**
 * Session authentication shared by the website admin and UMP (same accounts, passwords and 2FA).
 * Roles are per application (table user_access); APP_ID says which application is asking.
 * Brute-force limits, idle timeout and password policy come from the shared security settings.
 */
final class Auth
{
    private static ?array $user = null;

    private static function idleSeconds(): int
    {
        return max(5, (int) Settings::get('sec_session_minutes', '120')) * 60;
    }

    /** @return array{ok:bool,message:string,twofactor?:bool} */
    public static function attempt(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $generic = 'Invalid email or password.';

        if (self::isLocked($email)) {
            return ['ok' => false, 'message' => 'Too many failed attempts. Try again in ' . self::lockMinutes() . ' minutes.'];
        }

        $user = DB::one('SELECT * FROM users WHERE email = ?', [$email]);
        // Always run a hash comparison so response time doesn't reveal whether the account exists.
        $hash = $user['password_hash'] ?? '$2y$10$PaTj27AxQESxiK6eO/R/nuAdhQhxr832y9./IPiBU1.8vQ1pFOoZe';
        $valid = password_verify($password, $hash) && $user !== null;

        if (!$valid) {
            DB::insert('login_attempts', ['email' => $email, 'ip' => client_ip()]);
            Audit::log('login.failed', 'user', null, $email);
            return ['ok' => false, 'message' => $generic];
        }
        if ($user['status'] !== 'active') {
            return ['ok' => false, 'message' => 'This account is disabled. Contact an administrator.'];
        }
        if (self::roleFor((int) $user['id'], APP_ID) === null) {
            Audit::log('login.denied', 'user', (int) $user['id'], 'no access to ' . APP_ID);
            return ['ok' => false, 'message' => 'Your account does not have access to this system. Ask an administrator to grant it.'];
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            DB::update('users', (int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        if (!empty($user['totp_enabled_at'])) {
            $_SESSION['2fa'] = ['uid' => (int) $user['id'], 'exp' => time() + 300];
            return ['ok' => true, 'message' => 'Enter your verification code.', 'twofactor' => true];
        }

        self::completeLogin((int) $user['id']);
        return ['ok' => true, 'message' => 'OK'];
    }

    private static function completeLogin(int $uid): void
    {
        session_regenerate_id(true);
        unset($_SESSION['2fa']);
        $_SESSION['uid'] = $uid;
        $_SESSION['last_seen'] = time();
        DB::update('users', $uid, ['last_login_at' => date('Y-m-d H:i:s')]);
        $email = (string) DB::val('SELECT email FROM users WHERE id = ?', [$uid]);
        DB::run('DELETE FROM login_attempts WHERE email = ?', [$email]);
        Audit::log('login.success', 'user', $uid);
    }

    // ------------------------------------------------------------------ two-factor
    public static function twoFactorPending(): bool
    {
        $p = $_SESSION['2fa'] ?? null;
        return is_array($p) && $p['exp'] > time();
    }

    public static function cancelTwoFactor(): void
    {
        unset($_SESSION['2fa']);
    }

    /** Accepts an authenticator code or an unused recovery code. */
    public static function verifyTwoFactor(string $code): bool
    {
        if (!self::twoFactorPending()) {
            return false;
        }
        $uid = (int) $_SESSION['2fa']['uid'];
        if (!RateLimiter::hit('2fa:' . $uid, 6, 600)) {
            return false;
        }
        $user = DB::one('SELECT id, totp_secret, totp_last_step, recovery_codes FROM users WHERE id = ?', [$uid]);
        if (!$user || !$user['totp_secret']) {
            return false;
        }
        $code = trim($code);
        if (preg_match('/^\d{3}\s?\d{3}$/', $code)) {
            $step = Totp::verify($user['totp_secret'], $code, (int) $user['totp_last_step']);
            if ($step !== null) {
                DB::update('users', $uid, ['totp_last_step' => $step]);
                self::completeLogin($uid);
                return true;
            }
        } else {
            $hashes = json_decode((string) $user['recovery_codes'], true) ?: [];
            $h = Totp::hashCode($code);
            $i = array_search($h, $hashes, true);
            if ($i !== false) {
                unset($hashes[$i]);
                DB::update('users', $uid, ['recovery_codes' => json_encode(array_values($hashes))]);
                Audit::log('2fa.recovery_used', 'user', $uid, count($hashes) . ' left');
                self::completeLogin($uid);
                return true;
            }
        }
        DB::insert('login_attempts', ['email' => (string) DB::val('SELECT email FROM users WHERE id = ?', [$uid]), 'ip' => client_ip()]);
        Audit::log('2fa.failed', 'user', $uid);
        return false;
    }

    // ------------------------------------------------------------------ throttling
    private static function maxFailures(): int
    {
        return max(3, (int) Settings::get('sec_lockout_attempts', '5'));
    }

    private static function lockMinutes(): int
    {
        return max(1, (int) Settings::get('sec_lockout_minutes', '15'));
    }

    private static function isLocked(string $email): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::lockMinutes() * 60);
        $byEmail = (int) DB::val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > ?', [$email, $since]);
        $byIp = (int) DB::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [client_ip(), $since]);
        return $byEmail >= self::maxFailures() || $byIp >= self::maxFailures() * 3;
    }

    // ------------------------------------------------------------------ current user
    /** Role of $userId in $app ('site' | 'ump'), or null when the account has no access. */
    public static function roleFor(int $userId, string $app): ?string
    {
        $r = DB::val('SELECT role FROM user_access WHERE user_id = ? AND app = ?', [$userId, $app]);
        return $r === null || $r === '' ? null : (string) $r;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        if (empty($_SESSION['uid'])) {
            return null;
        }
        if (time() - ($_SESSION['last_seen'] ?? 0) > self::idleSeconds()) {
            self::logout();
            return null;
        }
        $_SESSION['last_seen'] = time();
        $user = DB::one(
            'SELECT u.id, u.name, u.email, u.status, u.totp_enabled_at, a.role, a.notify
             FROM users u JOIN user_access a ON a.user_id = u.id AND a.app = ? WHERE u.id = ?',
            [APP_ID, $_SESSION['uid']]
        );
        if (!$user || $user['status'] !== 'active') {
            self::logout();
            return null;
        }
        return self::$user = $user;
    }

    /** Is there a live session? Unlike user(), does not extend the idle timeout (used by background polling). */
    public static function peek(): bool
    {
        return !empty($_SESSION['uid']) && time() - ($_SESSION['last_seen'] ?? 0) <= self::idleSeconds();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function is(string ...$roles): bool
    {
        $u = self::user();
        return $u !== null && in_array($u['role'], $roles, true);
    }

    /** Gate for pages; optionally restricted to roles. */
    public static function require(string ...$roles): array
    {
        $user = self::user();
        if ($user === null) {
            // Remember where they were going: shown again after sign-in (also carried in the URL so it survives a lost session or another system's cookie).
            $to = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? ($_SERVER['REQUEST_URI'] ?? null) : null;
            $_SESSION['intended'] = $to;
            redirect(admin_url('login' . ($to && safe_next($to) ? '?next=' . rawurlencode($to) : '')));
        }
        if ($roles && !in_array($user['role'], $roles, true)) {
            http_response_code(403);
            exit('You do not have permission to view this page.');
        }
        // Optional policy: administrators must use two-factor authentication.
        if ($user['role'] === 'admin' && empty($user['totp_enabled_at']) && Settings::get('sec_require_2fa', '0') === '1'
            && !in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['profile.php', 'logout.php'], true)) {
            flash('warning', 'Administrators must turn on two-factor authentication. Set it up below to continue.');
            redirect(admin_url('profile#2fa'));
        }
        return $user;
    }

    public static function logout(): void
    {
        $id = $_SESSION['uid'] ?? null;
        if ($id) {
            Audit::log('logout', 'user', (int) $id);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$user = null;
    }

    // ------------------------------------------------------------------ password reset / invitations
    /** Creates a reset token (returned raw, stored hashed). Null when the email is unknown or disabled. */
    public static function createResetToken(string $email, int $ttlSeconds = 3600): ?array
    {
        $user = DB::one("SELECT id, name, email FROM users WHERE email = ? AND status = 'active'", [strtolower(trim($email))]);
        if (!$user) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        DB::insert('password_resets', [
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ]);
        return ['user' => $user, 'token' => $token];
    }

    public static function resetUserFromToken(string $token): ?array
    {
        return DB::one(
            'SELECT r.id AS reset_id, u.id, u.email FROM password_resets r JOIN users u ON u.id = r.user_id
             WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW()',
            [hash('sha256', $token)]
        );
    }

    public static function resetPassword(string $token, string $newPassword): bool
    {
        $row = self::resetUserFromToken($token);
        if (!$row) {
            return false;
        }
        DB::update('users', (int) $row['id'], ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'password_changed_at' => date('Y-m-d H:i:s')]);
        DB::run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ?', [$row['id']]);
        DB::run('DELETE FROM login_attempts WHERE email = ?', [$row['email']]);
        Audit::log('password.reset', 'user', (int) $row['id']);
        return true;
    }

    public static function passwordMin(): int
    {
        return max(8, (int) Settings::get('sec_password_min', '10'));
    }

    /** Minimum policy shared by every password form. Returns an error or null. */
    public static function passwordError(string $password): ?string
    {
        $min = self::passwordMin();
        if (mb_strlen($password) < $min) {
            return "Password must be at least $min characters.";
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Password must contain letters and numbers.';
        }
        return null;
    }
}
