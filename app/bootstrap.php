<?php
declare(strict_types=1);

/**
 * Application bootstrap: autoloading, environment, error handling,
 * secure session and global view helpers. Required by every entry point.
 */

define('UMP_ROOT', dirname(__DIR__));
/** Which application this codebase is: 'site' (website + admin) or 'ump'. Roles are looked up per application. */
define('APP_ID', 'ump');

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'UMP\\', 4) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use UMP\Core\Env;
use UMP\Core\Session;

Env::load(UMP_ROOT . '/.env');
date_default_timezone_set('Africa/Kigali');
mb_internal_encoding('UTF-8');

$debug = Env::bool('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', UMP_ROOT . '/storage/logs/app.log');

set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($debug) {
        echo '<pre style="padding:1rem;white-space:pre-wrap">' . htmlspecialchars((string) $e) . '</pre>';
    } else {
        $page = UMP_ROOT . '/500.html';
        echo is_file($page) ? file_get_contents($page) : 'Something went wrong.';
    }
});

Session::start();

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store');
    header_remove('X-Powered-By');
}

require __DIR__ . '/helpers.php';
