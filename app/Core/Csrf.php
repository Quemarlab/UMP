<?php
declare(strict_types=1);

namespace UMP\Core;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function valid(?string $token = null): bool
    {
        $token ??= $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return is_string($token) && $token !== '' && hash_equals(self::token(), $token);
    }

    /** Abort the request when the token is missing or wrong. */
    public static function check(bool $json = false): void
    {
        if (self::valid()) {
            return;
        }
        if ($json) {
            json_response(['ok' => false, 'message' => 'Your session expired. Please refresh the page and try again.'], 403);
        }
        http_response_code(403);
        exit('Session expired. Go back, refresh the page and try again.');
    }
}
