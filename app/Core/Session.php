<?php
declare(strict_types=1);

namespace UMP\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE || PHP_SAPI === 'cli') {
            return;
        }
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_name('umpsid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }
}
